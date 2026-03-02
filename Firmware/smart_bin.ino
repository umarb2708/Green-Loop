/*
 * Green Loop - Smart Plastic Collection Bin
 * ESP32 CAM based system with QR scanning, weight sensing, and multi-chamber sorting
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
#include "esp_camera.h"
#include "quirc.h"

// ==================== Pin Definitions ====================
#define IR_PIN_1 13  // PET chamber
#define IR_PIN_2 12  // HDPE chamber
#define IR_PIN_3 14  // PP chamber
#define IR_PIN_4 15  // Others chamber

#define BUTTON_START 4  // Start/Continue button
#define BUTTON_STOP 2   // Stop/Done button

#define HX711_DOUT 16
#define HX711_SCK 17

#define I2C_SDA 21
#define I2C_SCL 22

// ==================== Constants ====================
#define NUM_BINS 4
#define SERVO_MIN 150  // Servo closed position
#define SERVO_MAX 450  // Servo open position

#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1

// Points for each plastic type
#define POINTS_PET 10
#define POINTS_HDPE 20
#define POINTS_PP 30
#define POINTS_OTHERS 5

// ==================== Global Objects ====================
Preferences preferences;
WebServer server(80);
Adafruit_PWMServoDriver pwm = Adafruit_PWMServoDriver(0x40);
HX711 scale;
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// ==================== Global Variables ====================
String wifi_ssid = "";
String wifi_password = "";
String server_ip = "";
String bin_id = "";

bool lid_status[NUM_BINS] = {false, false, false, false}; // false=closed, true=open
bool bin_full[NUM_BINS] = {false, false, false, false};
int ir_pins[NUM_BINS] = {IR_PIN_1, IR_PIN_2, IR_PIN_3, IR_PIN_4};

float current_weight = 0.0;
float previous_weight = 0.0;

unsigned long last_status_check = 0;
unsigned long last_button_check = 0;
const unsigned long STATUS_CHECK_INTERVAL = 5000; // 5 seconds

// ==================== Camera Configuration ====================
camera_config_t camera_config = {
  .pin_pwdn = 32,
  .pin_reset = -1,
  .pin_xclk = 0,
  .pin_sscb_sda = 26,
  .pin_sscb_scl = 27,
  .pin_d7 = 35,
  .pin_d6 = 34,
  .pin_d5 = 39,
  .pin_d4 = 36,
  .pin_d3 = 21,
  .pin_d2 = 19,
  .pin_d1 = 18,
  .pin_vsync = 25,
  .pin_href = 23,
  .pin_pclk = 22,
  .xclk_freq_hz = 20000000,
  .ledc_timer = LEDC_TIMER_0,
  .ledc_channel = LEDC_CHANNEL_0,
  .pixel_format = PIXFORMAT_GRAYSCALE,
  .frame_size = FRAMESIZE_QVGA,
  .jpeg_quality = 12,
  .fb_count = 1
};

// ==================== Setup Functions ====================
void setup() {
  Serial.begin(115200);
  
  // Initialize I2C
  Wire.begin(I2C_SDA, I2C_SCL);
  
  // Initialize OLED
  if(!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println(F("SSD1306 allocation failed"));
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.setCursor(0,0);
  display.println("Green Loop");
  display.println("Initializing...");
  display.display();
  
  // Initialize buttons
  pinMode(BUTTON_START, INPUT_PULLUP);
  pinMode(BUTTON_STOP, INPUT_PULLUP);
  
  // Initialize IR sensors
  for(int i = 0; i < NUM_BINS; i++) {
    pinMode(ir_pins[i], INPUT);
  }
  
  // Initialize servo driver
  pwm.begin();
  pwm.setPWMFreq(60);
  
  // Close all lids initially
  for(int i = 0; i < NUM_BINS; i++) {
    setServo(i, false);
  }
  
  // Initialize load cell
  scale.begin(HX711_DOUT, HX711_SCK);
  scale.set_scale(420.0983); // Calibration factor - adjust as needed
  scale.tare();
  
  // Initialize camera
  esp_err_t err = esp_camera_init(&camera_config);
  if (err != ESP_OK) {
    Serial.printf("Camera init failed with error 0x%x", err);
    displayMessage("Camera Error!");
  }
  
  // Load preferences
  preferences.begin("greenloop", false);
  wifi_ssid = preferences.getString("ssid", "");
  wifi_password = preferences.getString("password", "");
  server_ip = preferences.getString("server_ip", "");
  bin_id = preferences.getString("bin_id", "");
  
  // WiFi setup
  Wifi_Setup();
  
  displayMessage("System Ready");
  delay(2000);
}

// ==================== Main Loop ====================
void loop() {
  unsigned long current_time = millis();
  
  // Check bin status every 5 seconds
  if(current_time - last_status_check >= STATUS_CHECK_INTERVAL) {
    read_bin_status();
    last_status_check = current_time;
  }
  
  // Check button clicks
  read_button_click();
  
  // Handle config mode web server if active
  server.handleClient();
  
  delay(100);
}

// ==================== WiFi Functions ====================
void Wifi_Setup() {
  displayMessage("WiFi Setup...");
  
  if(wifi_ssid.length() > 0) {
    WiFi.mode(WIFI_STA);
    WiFi.begin(wifi_ssid.c_str(), wifi_password.c_str());
    
    int attempts = 0;
    while(WiFi.status() != WL_CONNECTED && attempts < 30) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    
    if(WiFi.status() == WL_CONNECTED) {
      Serial.println("\nConnected to WiFi");
      displayMessage("WiFi Connected!");
      delay(1000);
      return;
    }
  }
  
  // If not connected, start config mode
  Config_Mode();
}

void Config_Mode() {
  displayMessage("Config Mode\nSSID: GreenLoop");
  
  // Start AP mode
  WiFi.mode(WIFI_AP);
  WiFi.softAP("GreenLoop", "Green@123#");
  
  IPAddress IP = WiFi.softAPIP();
  Serial.print("AP IP address: ");
  Serial.println(IP);
  
  display.clearDisplay();
  display.setCursor(0,0);
  display.println("Config Mode");
  display.println("SSID: GreenLoop");
  display.println("Pass: Green@123#");
  display.print("IP: ");
  display.println(IP);
  display.display();
  
  // Setup web server routes
  server.on("/", HTTP_GET, handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.begin();
  
  // Stay in config mode until credentials are saved
  while(true) {
    server.handleClient();
    delay(10);
  }
}

void handleRoot() {
  String html = "<!DOCTYPE html><html><head><title>Green Loop Config</title>";
  html += "<style>body{font-family:Arial;max-width:400px;margin:50px auto;padding:20px;}";
  html += "input{width:100%;padding:10px;margin:10px 0;box-sizing:border-box;}";
  html += "button{width:100%;padding:10px;background:#4CAF50;color:white;border:none;cursor:pointer;}";
  html += "button:hover{background:#45a049;}</style></head><body>";
  html += "<h2>Green Loop Configuration</h2>";
  html += "<form action='/save' method='POST'>";
  html += "<label>WiFi SSID:</label><input type='text' name='ssid' required>";
  html += "<label>WiFi Password:</label><input type='password' name='password' required>";
  html += "<label>Server IP:</label><input type='text' name='server_ip' placeholder='http://192.168.1.100' required>";
  html += "<label>Bin ID:</label><input type='text' name='bin_id' required>";
  html += "<button type='submit'>Save Configuration</button>";
  html += "</form></body></html>";
  
  server.send(200, "text/html", html);
}

void handleSave() {
  if(server.hasArg("ssid") && server.hasArg("password") && 
     server.hasArg("server_ip") && server.hasArg("bin_id")) {
    
    wifi_ssid = server.arg("ssid");
    wifi_password = server.arg("password");
    server_ip = server.arg("server_ip");
    bin_id = server.arg("bin_id");
    
    // Save to preferences
    preferences.putString("ssid", wifi_ssid);
    preferences.putString("password", wifi_password);
    preferences.putString("server_ip", server_ip);
    preferences.putString("bin_id", bin_id);
    
    String html = "<!DOCTYPE html><html><body>";
    html += "<h2>Configuration Saved!</h2>";
    html += "<p>Device will restart...</p>";
    html += "</body></html>";
    
    server.send(200, "text/html", html);
    delay(2000);
    
    ESP.restart();
  } else {
    server.send(400, "text/plain", "Missing parameters");
  }
}

// ==================== Sensor Functions ====================
void read_bin_status() {
  // Read current weight
  if(scale.is_ready()) {
    current_weight = scale.get_units(5);
  }
  
  // Check each bin chamber
  for(int i = 0; i < NUM_BINS; i++) {
    int ir_val = digitalRead(ir_pins[i]);
    
    // If IR detects object (LOW) and lid is closed, bin is full
    if(ir_val == LOW && !lid_status[i]) {
      bin_full[i] = true;
    } else {
      bin_full[i] = false;
    }
  }
}

String get_bin_status_string() {
  String status = "";
  for(int i = 0; i < NUM_BINS; i++) {
    status += bin_full[i] ? "1" : "0";
  }
  return status;
}

void read_button_click() {
  static bool button1_last = HIGH;
  static bool button2_last = HIGH;
  
  bool button1 = digitalRead(BUTTON_START);
  bool button2 = digitalRead(BUTTON_STOP);
  
  // Check for button press (falling edge)
  bool button1_clicked = (button1 == LOW && button1_last == HIGH);
  bool button2_clicked = (button2 == LOW && button2_last == HIGH);
  
  button1_last = button1;
  button2_last = button2;
  
  if(button1_clicked && button2_clicked) {
    // Both buttons pressed - enter config mode
    Config_Mode();
  } else if(button1_clicked && !button2_clicked) {
    // Only start button pressed - start disposal
    disposal();
  }
  
  delay(50); // Debounce
}

// ==================== Servo Control ====================
void setServo(int servo_num, bool open) {
  int position = open ? SERVO_MAX : SERVO_MIN;
  pwm.setPWM(servo_num, 0, position);
  lid_status[servo_num] = open;
  delay(500); // Wait for servo to move
}

// ==================== QR Code Scanning ====================
String scanQRCode() {
  displayMessage("Scan QR Code...");
  
  camera_fb_t *fb = NULL;
  struct quirc *qr;
  qr = quirc_new();
  
  if (!qr) {
    Serial.println("Failed to allocate quirc");
    return "";
  }
  
  quirc_resize(qr, 320, 240);
  
  for(int attempts = 0; attempts < 30; attempts++) {
    fb = esp_camera_fb_get();
    if(!fb) {
      Serial.println("Camera capture failed");
      delay(100);
      continue;
    }
    
    quirc_begin(qr, NULL, NULL);
    uint8_t *image = quirc_begin(qr, NULL, NULL);
    memcpy(image, fb->buf, fb->len);
    quirc_end(qr);
    
    int count = quirc_count(qr);
    if(count > 0) {
      struct quirc_code code;
      struct quirc_data data;
      
      quirc_extract(qr, 0, &code);
      quirc_decode_error_t err = quirc_decode(&code, &data);
      
      if(err == QUIRC_SUCCESS) {
        String qr_data = String((char *)data.payload);
        esp_camera_fb_return(fb);
        quirc_destroy(qr);
        return qr_data;
      }
    }
    
    esp_camera_fb_return(fb);
    delay(100);
  }
  
  quirc_destroy(qr);
  return "";
}

// ==================== Disposal Function ====================
void disposal() {
  int tot_points = 0;
  bool continue_flag = true;
  
  displayMessage("Disposal Mode\nStarted");
  delay(1000);
  
  while(continue_flag) {
    // Step 2: Scan QR code
    String qr_id = scanQRCode();
    
    if(qr_id.length() == 0) {
      displayMessage("QR Scan Failed\nTry Again");
      delay(2000);
      continue;
    }
    
    displayMessage("QR Scanned\nChecking...");
    
    // Step 3: Check plastic type from database
    String plastic_type = getPlasticType(qr_id);
    
    if(plastic_type.length() == 0) {
      displayMessage("Product Not\nFound");
      delay(2000);
      continue;
    }
    
    // Step 4: Determine bin number and check if full
    int bin_num = getPlasticBinNumber(plastic_type);
    read_bin_status();
    
    if(bin_full[bin_num]) {
      displayMessage("Bin Full!\nTry Another");
      delay(2000);
      continue;
    }
    
    // Step 5: Open lid
    display.clearDisplay();
    display.setCursor(0,0);
    display.println("Type: " + plastic_type);
    display.println("Opening lid...");
    display.display();
    
    setServo(bin_num, true);
    previous_weight = current_weight;
    
    // Step 6: Wait for IR confirmation
    displayMessage("Insert Plastic");
    unsigned long start_time = millis();
    bool plastic_inserted = false;
    
    while(millis() - start_time < 30000) { // 30 second timeout
      if(digitalRead(ir_pins[bin_num]) == LOW) {
        plastic_inserted = true;
        break;
      }
      delay(100);
    }
    
    if(!plastic_inserted) {
      setServo(bin_num, false);
      displayMessage("Timeout!\nNo Plastic");
      delay(2000);
      continue;
    }
    
    // Step 7: Wait for weight change confirmation
    delay(2000); // Allow plastic to settle
    read_bin_status();
    
    if(abs(current_weight - previous_weight) > 5) { // 5g minimum weight change
      // Step 8: Update points
      int points = getPlasticPoints(plastic_type);
      tot_points += points;
      
      display.clearDisplay();
      display.setCursor(0,0);
      display.println("Success!");
      display.println("Points: +" + String(points));
      display.println("Total: " + String(tot_points));
      display.display();
      
      delay(2000);
    } else {
      displayMessage("No Weight\nChange!");
      delay(2000);
    }
    
    // Close lid
    setServo(bin_num, false);
    
    // Step 9 & 10: Ask to continue or stop
    display.clearDisplay();
    display.setCursor(0,0);
    display.println("Total: " + String(tot_points));
    display.println("");
    display.println("Button1: Continue");
    display.println("Button2: Stop");
    display.display();
    
    // Wait for button press
    while(true) {
      if(digitalRead(BUTTON_START) == LOW) {
        delay(200); // Debounce
        continue_flag = true;
        break;
      }
      if(digitalRead(BUTTON_STOP) == LOW) {
        delay(200); // Debounce
        continue_flag = false;
        break;
      }
      delay(100);
    }
  }
  
  // Step 11: Generate unique code and upload data
  String unique_code = generateUniqueCode();
  read_bin_status();
  
  displayMessage("Uploading...");
  
  bool upload_success = uploadData(tot_points, unique_code, current_weight, get_bin_status_string());
  
  if(upload_success) {
    display.clearDisplay();
    display.setCursor(0,0);
    display.println("Success!");
    display.println("");
    display.println("Points: " + String(tot_points));
    display.println("Code: " + unique_code);
    display.println("");
    display.println("Press Done");
    display.display();
  } else {
    displayMessage("Upload Failed\nCheck WiFi");
  }
  
  // Step 11.6: Wait for done button
  while(digitalRead(BUTTON_STOP) == HIGH) {
    delay(100);
  }
  
  displayMessage("Thank You!");
  delay(2000);
}

// ==================== Helper Functions ====================
String getPlasticType(String qr_id) {
  if(WiFi.status() != WL_CONNECTED) {
    return "";
  }
  
  HTTPClient http;
  String url = server_ip + "/api.php?action=get_product&qr_id=" + qr_id;
  
  http.begin(url);
  int httpCode = http.GET();
  
  if(httpCode == 200) {
    String payload = http.getString();
    http.end();
    return payload; // Should return plastic type (PET, HDPE, PP, Others)
  }
  
  http.end();
  return "";
}

int getPlasticBinNumber(String plastic_type) {
  if(plastic_type == "PET") return 0;
  if(plastic_type == "HDPE") return 1;
  if(plastic_type == "PP") return 2;
  return 3; // Others
}

int getPlasticPoints(String plastic_type) {
  if(plastic_type == "PET") return POINTS_PET;
  if(plastic_type == "HDPE") return POINTS_HDPE;
  if(plastic_type == "PP") return POINTS_PP;
  return POINTS_OTHERS;
}

String generateUniqueCode() {
  const char chars[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  String code = "";
  
  for(int i = 0; i < 6; i++) {
    code += chars[random(0, 36)];
  }
  
  return code;
}

bool uploadData(int points, String unique_code, float weight, String bin_status) {
  if(WiFi.status() != WL_CONNECTED) {
    return false;
  }
  
  HTTPClient http;
  String url = server_ip + "/api.php";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  String json = "{";
  json += "\"action\":\"upload_data\",";
  json += "\"points\":" + String(points) + ",";
  json += "\"unique_code\":\"" + unique_code + "\",";
  json += "\"bin_weight\":" + String(weight, 2) + ",";
  json += "\"bin_status\":\"" + bin_status + "\",";
  json += "\"bin_id\":\"" + bin_id + "\"";
  json += "}";
  
  int httpCode = http.POST(json);
  http.end();
  
  return (httpCode == 200);
}

void displayMessage(String message) {
  display.clearDisplay();
  display.setCursor(0,0);
  display.println(message);
  display.display();
}
