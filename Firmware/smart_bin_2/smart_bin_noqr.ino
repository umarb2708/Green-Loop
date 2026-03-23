/*
 * Green Loop - Smart Plastic Collection Bin (No-QR Version)
 * ESP32 based system - QR scanning handled by website/mobile app
 *
 * Flow:
 *  1. Press START → generate 6-char SYNC CODE → register with server → display on OLED
 *  2. Poll server every 2 s for a pending disposal (confirmed = 0) with this SYNC CODE
 *  3. On found: open correct bin servo, wait for IR sensor + weight confirmation
 *  4. Confirm disposal (confirmed = 1) via API
 *  5. Repeat until STOP button pressed
 *  6. On STOP: generate reward code, upload session totals, display reward code
 *  7. Both START+STOP together → reset config
 */

#include <WiFi.h>
#include <WebServer.h>
#include <Preferences.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_PWMServoDriver.h>
#include <HX711.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ==================== Forward Declarations ====================
// OLED error code legend:
// W00: No WiFi credentials in NVS
// W01: WiFi STA connection failed (fallback to AP config mode)
// W02: WiFi disconnected during API operation
// C01: Config form save request missing fields
// HW1/HW2: Missing hardware (PCA9685 / HX711)
// A01-A04: API failures (register, poll, confirm, upload)
void displayDebug(String line1, String line2 = "", String line3 = "", unsigned long hold_ms = 0);
void displayErrorCode(String code, String meaning, String detail = "", unsigned long hold_ms = 2500);
void displayMessage(String line1, String line2 = "");
void Wifi_Setup();
void Config_Mode();
void read_bin_status();
void read_button_click();
void setServo(int servo_num, bool open);
void disposal();
int  getPlasticBinNumber(String plastic_type);
int  getPlasticPoints(String plastic_type);
String generateSyncCode();
String generateUniqueCode();
bool registerSync(String sync_code);
String pollDisposal(String sync_code, int &disposal_id, String &plastic_type);
bool confirmDisposal(int disposal_id);
bool uploadData(int points, String unique_code, float weight, String bin_status);
String get_bin_status_string();
void handleRoot();
void handleSave();

// ==================== Pin Definitions ====================
#define IR_PIN_1     2   // PET chamber
#define IR_PIN_2     4   // HDPE chamber
#define IR_PIN_3    14   // PP chamber
#define IR_PIN_4    15   // Others chamber

#define BUTTON_START  0  // Start button (BOOT button on ESP32-CAM)
#define BUTTON_STOP  16  // Stop / Done button

#define HX711_DOUT   3
#define HX711_SCK    1

#define I2C_SDA     12   // Safe with camera (not used here, but kept same)
#define I2C_SCL     13

// ==================== Constants ====================
#define NUM_BINS     4
#define SERVO_MIN  150   // Servo closed pulse
#define SERVO_MAX  450   // Servo open pulse

#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT  64
#define OLED_RESET     -1

#define POINTS_PET     10
#define POINTS_HDPE    20
#define POINTS_PP      30
#define POINTS_OTHERS   5

#define POLL_INTERVAL  2000   // ms between API polls
#define INSERT_TIMEOUT 30000  // ms to wait for physical insertion

// ==================== Global Objects ====================
Preferences preferences;
WebServer server(80);
Adafruit_PWMServoDriver pwm = Adafruit_PWMServoDriver(0x40);
HX711 scale;
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// ==================== Hardware Availability Flags ====================
bool oled_available      = false;
bool servo_available     = false;
bool load_cell_available = false;

// ==================== Global Variables ====================
String wifi_ssid     = "";
String wifi_password = "";
String server_ip     = "";
String bin_id        = "";

bool lid_status[NUM_BINS] = {false, false, false, false};
bool bin_full[NUM_BINS]   = {false, false, false, false};
int  ir_pins[NUM_BINS]    = {IR_PIN_1, IR_PIN_2, IR_PIN_3, IR_PIN_4};

float current_weight  = 0.0;
float previous_weight = 0.0;

unsigned long last_status_check = 0;
const unsigned long STATUS_CHECK_INTERVAL = 5000;
unsigned long last_poll_error_notice = 0;

// ==================== Setup ====================
void setup() {
  Serial.begin(9600);
  delay(1000);
  Serial.println("\n\n=== Green Loop (No-QR) Starting ===");

  displayDebug("Booting...", "Init hardware");

  Wire.begin(I2C_SDA, I2C_SCL);

  // OLED
  if (display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    oled_available = true;
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(WHITE);
    display.setCursor(0, 0);
    display.println("Green Loop");
    display.println("Initializing...");
    display.display();
    Serial.println("OLED: OK");
  } else {
    Serial.println("OLED not found - skipping");
  }

  // Buttons
  pinMode(BUTTON_START, INPUT_PULLUP);
  pinMode(BUTTON_STOP,  INPUT_PULLUP);

  // IR sensors
  for (int i = 0; i < NUM_BINS; i++) {
    pinMode(ir_pins[i], INPUT);
  }

  // Servo driver (PCA9685 at 0x40)
  Wire.beginTransmission(0x40);
  if (Wire.endTransmission() == 0) {
    servo_available = true;
    pwm.begin();
    pwm.setPWMFreq(60);
    for (int i = 0; i < NUM_BINS; i++) setServo(i, false);
    Serial.println("Servo driver: OK");
  } else {
    Serial.println("Servo driver not found - skipping");
    displayErrorCode("HW1", "Servo drv missing", "PCA9685 @0x40");
  }

  // Load cell
  scale.begin(HX711_DOUT, HX711_SCK);
  unsigned long t0 = millis();
  while (millis() - t0 < 2000) {
    if (scale.is_ready()) {
      scale.set_scale(420.0983);
      scale.tare();
      load_cell_available = true;
      Serial.println("Load cell: OK");
      break;
    }
    delay(100);
  }
  if (!load_cell_available) {
    Serial.println("Load cell not connected - skipping");
    displayErrorCode("HW2", "Load cell missing", "HX711 not ready");
  }

  // Preferences
  preferences.begin("greenloop", false);
  wifi_ssid     = preferences.getString("ssid",      "");
  wifi_password = preferences.getString("password",  "");
  server_ip     = preferences.getString("server_ip", "");
  bin_id        = preferences.getString("bin_id",    "");
  Serial.println("Preferences loaded");

  displayDebug("Prefs loaded", wifi_ssid.length() ? ("SSID:" + wifi_ssid) : "SSID: none",
               server_ip.length() ? "Server: set" : "Server: none", 800);

  Wifi_Setup();

  displayMessage("System Ready", "Press START");
  Serial.println("=== Setup Complete ===");
  delay(2000);
}

// ==================== Main Loop ====================
void loop() {
  unsigned long now = millis();

  if (now - last_status_check >= STATUS_CHECK_INTERVAL) {
    read_bin_status();
    last_status_check = now;
  }

  read_button_click();
  server.handleClient();
  delay(100);
}

// ==================== WiFi / Config ====================
void Wifi_Setup() {
  displayDebug("WiFi Setup", "Checking creds...");

  String cfg_reason = "W00";

  if (wifi_ssid.length() > 0) {
    cfg_reason = "W01";
    WiFi.mode(WIFI_STA);
    WiFi.begin(wifi_ssid.c_str(), wifi_password.c_str());
    displayDebug("WiFi STA", "SSID: " + wifi_ssid, "Connecting...");

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
      delay(500);
      Serial.print(".");
      attempts++;

      if (attempts == 1 || (attempts % 5 == 0)) {
        displayDebug("WiFi connecting", "Try " + String(attempts) + "/30", "Please wait");
      }
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\nWiFi connected");
      displayDebug("WiFi Connected", WiFi.localIP().toString(), "Mode: STA", 1200);
      delay(1000);
      return;
    }

    displayErrorCode("W01", "STA connect failed", "Switching to AP", 1600);
  } else {
    displayErrorCode("W00", "No WiFi creds", "Open AP config", 1400);
  }

  // Fall back to AP config mode
  Serial.println("Starting AP config mode");
  WiFi.mode(WIFI_AP);
  WiFi.softAP("GreenLoop", "Green@123#");
  IPAddress IP = WiFi.softAPIP();
  Serial.print("AP IP: "); Serial.println(IP);

  if (oled_available) {
    display.clearDisplay();
    display.setTextSize(1);
    display.setCursor(0, 0);
    display.println("CFG MODE [" + cfg_reason + "]");
    display.println(cfg_reason == "W00" ? "Reason: No creds" : "Reason: STA fail");
    display.println("SSID: GreenLoop");
    display.println("Pass: Green@123#");
    display.print("IP: "); display.println(IP);
    display.println("Open / in browser");
    display.display();
  }

  server.on("/",      HTTP_GET,  handleRoot);
  server.on("/save",  HTTP_POST, handleSave);
  server.begin();

  while (true) {
    server.handleClient();
    delay(10);
  }
}

void handleRoot() {
  String html = "<!DOCTYPE html><html><head><title>Green Loop Config</title>";
  html += "<style>body{font-family:Arial;max-width:400px;margin:50px auto;padding:20px;}";
  html += "input{width:100%;padding:10px;margin:10px 0;box-sizing:border-box;}";
  html += "button{width:100%;padding:10px;background:#4CAF50;color:white;border:none;cursor:pointer;}</style></head><body>";
  html += "<h2>Green Loop Configuration</h2>";
  html += "<form action='/save' method='POST'>";
  html += "<label>WiFi SSID:</label><input type='text' name='ssid' required>";
  html += "<label>WiFi Password:</label><input type='password' name='password' required>";
  html += "<label>Server IP (e.g. http://192.168.1.100/smart_bin_qr):</label>";
  html += "<input type='text' name='server_ip' required>";
  html += "<label>Bin ID:</label><input type='text' name='bin_id' required>";
  html += "<button type='submit'>Save Configuration</button>";
  html += "</form></body></html>";
  server.send(200, "text/html", html);
}

void handleSave() {
  if (server.hasArg("ssid") && server.hasArg("password") &&
      server.hasArg("server_ip") && server.hasArg("bin_id")) {

    wifi_ssid     = server.arg("ssid");
    wifi_password = server.arg("password");
    server_ip     = server.arg("server_ip");
    bin_id        = server.arg("bin_id");

    preferences.putString("ssid",      wifi_ssid);
    preferences.putString("password",  wifi_password);
    preferences.putString("server_ip", server_ip);
    preferences.putString("bin_id",    bin_id);

    displayDebug("Config Saved", "Restarting in 2s");
    server.send(200, "text/html", "<h2>Saved! Restarting in 2s...</h2>");
    delay(2000);
    ESP.restart();
  } else {
    displayErrorCode("C01", "Config save failed", "Missing params", 1200);
    server.send(400, "text/plain", "Missing parameters");
  }
}

void Config_Mode() {
  displayDebug("Reset Config", "Clearing SSID/PASS", "Restarting...", 1000);
  preferences.putString("ssid", "");
  preferences.putString("password", "");
  delay(1000);
  ESP.restart();
}

// ==================== Sensor Functions ====================
void read_bin_status() {
  if (load_cell_available && scale.is_ready()) {
    current_weight = scale.get_units(5);
  }
  for (int i = 0; i < NUM_BINS; i++) {
    bin_full[i] = (digitalRead(ir_pins[i]) == LOW && !lid_status[i]);
  }
}

String get_bin_status_string() {
  String s = "";
  for (int i = 0; i < NUM_BINS; i++) s += bin_full[i] ? "1" : "0";
  return s;
}

void read_button_click() {
  static bool b1_last = HIGH;
  static bool b2_last = HIGH;

  bool b1 = digitalRead(BUTTON_START);
  bool b2 = digitalRead(BUTTON_STOP);

  bool b1_click = (b1 == LOW && b1_last == HIGH);
  bool b2_click = (b2 == LOW && b2_last == HIGH);

  b1_last = b1;
  b2_last = b2;

  if (b1_click && b2_click) {
    Config_Mode();
  } else if (b1_click) {
    disposal();
  }
  delay(50); // Debounce
}

// ==================== Servo Control ====================
void setServo(int servo_num, bool open) {
  if (!servo_available) return;
  pwm.setPWM(servo_num, 0, open ? SERVO_MAX : SERVO_MIN);
  lid_status[servo_num] = open;
  delay(500);
}

// ==================== Main Disposal Session ====================
void disposal() {
  // Step 1: Generate SYNC CODE and register with server
  String sync_code = generateSyncCode();
  int tot_points = 0;

  Serial.print("SYNC CODE: "); Serial.println(sync_code);

  // Display sync code prominently for user to read
  if (oled_available) {
    display.clearDisplay();
    display.setTextSize(1);
    display.setCursor(0, 0);
    display.println("== SYNC CODE ==");
    display.setTextSize(2);
    display.setCursor(14, 22);
    display.println(sync_code);
    display.setTextSize(1);
    display.setCursor(0, 48);
    display.println("Enter on website");
    display.setCursor(0, 56);
    display.println("STOP btn to finish");
    display.display();
  }

  // Register sync code with server so website can validate it
  if (!registerSync(sync_code)) {
    Serial.println("Warning: could not register sync code with server");
    displayErrorCode("A01", "Sync register fail", "Check WiFi/IP", 1800);
    delay(3000);
  }

  Serial.println("Polling for disposals... Press STOP to end session.");

  // Step 2-6: Poll loop
  while (true) {
    // Check STOP button (debounced)
    if (digitalRead(BUTTON_STOP) == LOW) {
      delay(60);
      if (digitalRead(BUTTON_STOP) == LOW) {
        Serial.println("STOP pressed - ending session");
        break;
      }
    }

    // Poll API for an unconfirmed disposal entry
    int disposal_id   = -1;
    String plastic_type = "";
    String result = pollDisposal(sync_code, disposal_id, plastic_type);

    if (result == "found") {
      Serial.println("Pending disposal: type=" + plastic_type + " id=" + String(disposal_id));

      int bin_num = getPlasticBinNumber(plastic_type);
      read_bin_status();

      // Check if this bin chamber is full
      if (bin_full[bin_num]) {
        Serial.println("Bin " + String(bin_num) + " full - skipping");
        displayMessage("Bin Full!", "Try another");
        delay(2000);
        // Leave entry unconfirmed - website will detect via bin status
        continue;
      }

      // Open lid
      if (oled_available) {
        display.clearDisplay();
        display.setCursor(0, 0);
        display.setTextSize(1);
        display.println("Type: " + plastic_type);
        display.println("Bin: " + String(bin_num + 1));
        display.println("");
        display.println("Opening lid...");
        display.println("Insert plastic now");
        display.display();
      }
      Serial.println("Opening bin " + String(bin_num) + " for " + plastic_type);
      setServo(bin_num, true);
      previous_weight = current_weight;

      // Wait for IR sensor to detect insertion
      displayMessage("Insert Plastic", "30s to insert...");
      bool inserted = false;
      unsigned long t_start = millis();
      while (millis() - t_start < INSERT_TIMEOUT) {
        if (digitalRead(ir_pins[bin_num]) == LOW) {
          inserted = true;
          break;
        }
        delay(100);
      }

      if (!inserted) {
        setServo(bin_num, false);
        displayMessage("Timeout!", "No plastic detected");
        Serial.println("Insertion timeout");
        delay(2000);
        // Show sync code again
        if (oled_available) {
          display.clearDisplay();
          display.setTextSize(1);
          display.setCursor(0, 0);
          display.println("== SYNC CODE ==");
          display.setTextSize(2);
          display.setCursor(14, 22);
          display.println(sync_code);
          display.setTextSize(1);
          display.setCursor(0, 56);
          display.println("STOP btn to finish");
          display.display();
        }
        continue;
      }

      // Wait for weight to settle then close lid
      delay(2000);
      read_bin_status();
      setServo(bin_num, false);

      if (abs(current_weight - previous_weight) > 5.0) {
        int pts = getPlasticPoints(plastic_type);
        tot_points += pts;

        // Step 7: Confirm disposal in database (confirmed = 1)
        if (confirmDisposal(disposal_id)) {
          Serial.println("Confirmed! +" + String(pts) + " pts. Total: " + String(tot_points));
          if (oled_available) {
            display.clearDisplay();
            display.setCursor(0, 0);
            display.setTextSize(1);
            display.println("Disposed!");
            display.println("+" + String(pts) + " pts");
            display.println("Total: " + String(tot_points));
            display.println("");
            display.println("Scan more on website");
            display.println("STOP btn to finish");
            display.display();
          }
        } else {
          Serial.println("Warning: confirm_disposal API call failed");
          displayErrorCode("A03", "Confirm failed", "DB not updated", 1200);
        }
        delay(3000);
      } else {
        displayMessage("No weight change!", "Disposal skipped");
        Serial.println("No weight change detected");
        delay(2000);
      }

      // Refresh sync code display
      if (oled_available) {
        display.clearDisplay();
        display.setTextSize(1);
        display.setCursor(0, 0);
        display.println("== SYNC CODE ==");
        display.setTextSize(2);
        display.setCursor(14, 22);
        display.println(sync_code);
        display.setTextSize(1);
        display.setCursor(0, 48);
        display.println("Total: " + String(tot_points) + " pts");
        display.setCursor(0, 56);
        display.println("STOP btn to finish");
        display.display();
      }

    } else if (result == "error") {
      Serial.println("pollDisposal error - retrying");

      unsigned long now = millis();
      if (now - last_poll_error_notice >= 8000) {
        displayErrorCode("A02", "Poll API failed", "Will retry", 900);
        last_poll_error_notice = now;
      }
      delay(POLL_INTERVAL);

    } else {
      // No pending entry - keep waiting, show sync code
      if (oled_available) {
        display.clearDisplay();
        display.setTextSize(1);
        display.setCursor(0, 0);
        display.println("== SYNC CODE ==");
        display.setTextSize(2);
        display.setCursor(14, 22);
        display.println(sync_code);
        display.setTextSize(1);
        display.setCursor(0, 48);
        display.println("Pts: " + String(tot_points));
        display.setCursor(0, 56);
        display.println("STOP btn to finish");
        display.display();
      }
      delay(POLL_INTERVAL);
    }
  }

  // Step 9: Session ended - generate reward code and upload
  displayMessage("Uploading...", "Please wait");
  Serial.println("Session ended. Uploading reward data...");

  String unique_code = generateUniqueCode();
  read_bin_status();

  if (uploadData(tot_points, unique_code, current_weight, get_bin_status_string())) {
    Serial.println("Upload OK! Reward code: " + unique_code + " | Pts: " + String(tot_points));
    if (oled_available) {
      display.clearDisplay();
      display.setTextSize(1);
      display.setCursor(0, 0);
      display.println("Session Complete!");
      display.println("Points: " + String(tot_points));
      display.println("");
      display.println("Reward Code:");
      display.setTextSize(2);
      display.setCursor(10, 42);
      display.println(unique_code);
      display.display();
    }
  } else {
    displayErrorCode("A04", "Upload failed", "Check WiFi/Server", 1800);
    Serial.println("Upload failed");
  }

  // Wait for STOP to dismiss
  Serial.println("Press STOP to dismiss");
  while (digitalRead(BUTTON_STOP) == HIGH) {
    delay(100);
  }

  displayMessage("Thank You!", "");
  delay(2000);
}

// ==================== API Functions ====================

// Register sync code with server so website can validate it
bool registerSync(String sync_code) {
  if (WiFi.status() != WL_CONNECTED) {
    displayErrorCode("W02", "WiFi disconnected", "register_sync blocked", 1000);
    return false;
  }

  HTTPClient http;
  http.begin(server_ip + "/api.php");
  http.addHeader("Content-Type", "application/json");

  String json = "{\"action\":\"register_sync\",\"sync_code\":\"" + sync_code +
                "\",\"bin_id\":\"" + bin_id + "\"}";
  int code = http.POST(json);
  http.end();

  if (code != 200) {
    displayErrorCode("A01", "register_sync HTTP", String(code), 1200);
  }
  return (code == 200);
}

// Poll for an unconfirmed disposal entry matching sync_code
// Returns "found", "empty", or "error". Fills disposal_id and plastic_type if found.
String pollDisposal(String sync_code, int &disposal_id, String &plastic_type) {
  if (WiFi.status() != WL_CONNECTED) {
    unsigned long now = millis();
    if (now - last_poll_error_notice >= 8000) {
      displayErrorCode("W02", "WiFi disconnected", "poll blocked", 900);
      last_poll_error_notice = now;
    }
    return "error";
  }

  HTTPClient http;
  http.begin(server_ip + "/api.php?action=get_disposal&sync_code=" + sync_code);
  int code = http.GET();

  if (code == 200) {
    String body = http.getString();
    http.end();

    if (body.indexOf("\"found\":true") >= 0) {
      // Parse id
      int id_pos = body.indexOf("\"id\":");
      if (id_pos >= 0) {
        disposal_id = body.substring(id_pos + 5).toInt();
      }
      // Parse type
      int type_pos = body.indexOf("\"type\":\"");
      if (type_pos >= 0) {
        int ts = type_pos + 8;
        int te = body.indexOf("\"", ts);
        plastic_type = body.substring(ts, te);
      }
      return "found";
    }
    return "empty";
  }

  http.end();

  unsigned long now = millis();
  if (now - last_poll_error_notice >= 8000) {
    displayErrorCode("A02", "poll_disposal HTTP", String(code), 900);
    last_poll_error_notice = now;
  }
  return "error";
}

// Mark disposal as confirmed (confirmed = 1)
bool confirmDisposal(int disposal_id) {
  if (WiFi.status() != WL_CONNECTED) {
    displayErrorCode("W02", "WiFi disconnected", "confirm blocked", 1000);
    return false;
  }

  HTTPClient http;
  http.begin(server_ip + "/api.php");
  http.addHeader("Content-Type", "application/json");

  String json = "{\"action\":\"confirm_disposal\",\"id\":" + String(disposal_id) + "}";
  int code = http.POST(json);
  http.end();

  if (code != 200) {
    displayErrorCode("A03", "confirm_disposal HTTP", String(code), 1200);
  }
  return (code == 200);
}

// Upload final session reward data
bool uploadData(int points, String unique_code, float weight, String bin_status) {
  if (WiFi.status() != WL_CONNECTED) {
    displayErrorCode("W02", "WiFi disconnected", "upload blocked", 1000);
    return false;
  }

  HTTPClient http;
  http.begin(server_ip + "/api.php");
  http.addHeader("Content-Type", "application/json");

  String json = "{";
  json += "\"action\":\"upload_data\",";
  json += "\"points\":"          + String(points)       + ",";
  json += "\"unique_code\":\""   + unique_code           + "\",";
  json += "\"bin_weight\":"      + String(weight, 2)    + ",";
  json += "\"bin_status\":\""    + bin_status            + "\",";
  json += "\"bin_id\":\""        + bin_id                + "\"";
  json += "}";

  int code = http.POST(json);
  http.end();

  if (code != 200) {
    displayErrorCode("A04", "upload_data HTTP", String(code), 1200);
  }
  return (code == 200);
}

// ==================== Helper Functions ====================
int getPlasticBinNumber(String plastic_type) {
  if (plastic_type == "PET")  return 0;
  if (plastic_type == "HDPE") return 1;
  if (plastic_type == "PP")   return 2;
  return 3; // Others
}

int getPlasticPoints(String plastic_type) {
  if (plastic_type == "PET")  return POINTS_PET;
  if (plastic_type == "HDPE") return POINTS_HDPE;
  if (plastic_type == "PP")   return POINTS_PP;
  return POINTS_OTHERS;
}

String generateSyncCode() {
  const char chars[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  String code = "";
  randomSeed(esp_random()); // Hardware RNG for better entropy
  for (int i = 0; i < 6; i++) {
    code += chars[random(0, 36)];
  }
  return code;
}

String generateUniqueCode() {
  const char chars[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  String code = "";
  randomSeed(esp_random());
  for (int i = 0; i < 6; i++) {
    code += chars[random(0, 36)];
  }
  return code;
}

void displayDebug(String line1, String line2, String line3, unsigned long hold_ms) {
  Serial.println("[DBG] " + line1 +
                 (line2.length() ? (" / " + line2) : "") +
                 (line3.length() ? (" / " + line3) : ""));

  if (!oled_available) return;

  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 0);
  display.println(line1);
  if (line2.length() > 0) display.println(line2);
  if (line3.length() > 0) display.println(line3);
  display.display();

  if (hold_ms > 0) delay(hold_ms);
}

void displayErrorCode(String code, String meaning, String detail, unsigned long hold_ms) {
  Serial.println("[ERR " + code + "] " + meaning + (detail.length() ? (" | " + detail) : ""));
  displayDebug("ERR " + code, meaning, detail, hold_ms);
}

void displayMessage(String line1, String line2) {
  Serial.println("[DISP] " + line1 + (line2.length() ? " / " + line2 : ""));
  if (!oled_available) return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 0);
  display.println(line1);
  if (line2.length() > 0) display.println(line2);
  display.display();
}
