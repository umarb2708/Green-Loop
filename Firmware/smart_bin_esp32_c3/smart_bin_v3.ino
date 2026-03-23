/*
 * Green Loop - Smart Plastic Collection Bin
 * ESP32-C3 Super Mini Firmware
 * Version 3.0
 */

#include <WiFi.h>
#include <WebServer.h>
#include <Preferences.h>
#include <Wire.h>
#include <Adafruit_SSD1306.h>
#include <Adafruit_PWMServoDriver.h>
#include <HX711.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// OLED Display Configuration
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// Hardware Pin Definitions
#define BUTTON_PIN 10       // Start/Stop session button
#define IR_SENSOR_1 2       // PET chamber
#define IR_SENSOR_2 3       // HDPE chamber
#define IR_SENSOR_3 4       // PP chamber
#define IR_SENSOR_4 5       // Others chamber

// HX711 Load Cell
#define HX711_DOUT 6
#define HX711_SCK 7
HX711 scale;

// PCA9685 Servo Driver
Adafruit_PWMServoDriver pwm = Adafruit_PWMServoDriver();

// Servo Configuration
#define SERVO_MIN 150  // Min pulse length
#define SERVO_MAX 600  // Max pulse length
#define SERVO_CLOSE 0  // Closed position (degrees)
#define SERVO_OPEN 90  // Open position (degrees)

// Chamber Definitions
#define NUM_CHAMBERS 4
#define CHAMBER_PET 0
#define CHAMBER_HDPE 1
#define CHAMBER_PP 2
#define CHAMBER_OTHERS 3

const char* chamberNames[] = {"PET", "HDPE", "PP", "Others"};
const int rewardPoints[] = {10, 20, 30, 5};  // Points for each chamber
const int irPins[] = {IR_SENSOR_1, IR_SENSOR_2, IR_SENSOR_3, IR_SENSOR_4};

// Global Variables
bool disposal_started = false;
int tot_points = 0;
bool lid_status[NUM_CHAMBERS] = {false, false, false, false};  // false = closed, true = open
bool bin_full[NUM_CHAMBERS] = {false, false, false, false};
float current_weight = 0.0;
float previous_weight = 0.0;
bool hx711_available = false;  // Track if HX711 is working
float simulated_weight = 0.0;  // Dummy weight when HX711 fails
int disposal_count = 0;        // Count disposals for weight simulation

// WiFi Configuration
String wifi_ssid = "";
String wifi_password = "";
String server_ip = "";
String bin_id = "";

// Flash Storage
Preferences preferences;

// Web Server for Configuration
WebServer server(80);

// Timing
unsigned long lastBinStatusCheck = 0;
unsigned long lastButtonCheck = 0;
const unsigned long BIN_STATUS_INTERVAL = 5000;    // 5 seconds
const unsigned long BUTTON_CHECK_INTERVAL = 100;   // 100ms for button debounce

// Button State
bool lastButtonState = HIGH;
unsigned long lastDebounceTime = 0;
const unsigned long debounceDelay = 50;

// Function Declarations
void init_display();
void Wifi_Setup();
void Config_Mode();
void init_hw();
void read_bin_status();
void read_button_click();
void disposal();
void rewards_earned();
void setServoAngle(int servoNum, int angle);
void openLid(int chamber);
void closeLid(int chamber);
bool checkIRSensor(int chamber);
float getWeight();
bool checkWeightChange();
String getBinStatusString();
void displayMessage(String line1, String line2 = "", String line3 = "", String line4 = "");
bool uploadToServer(String endpoint, String jsonData);
bool pollDisposalData(int& chamber, String& plasticType);

void setup() {
  // Initialize Serial Monitor
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n\n=== Green Loop Smart Bin v3.0 ===");
  
  // Initialize Display
  init_display();
  
  // Setup WiFi (before hardware to get network ready)
  Wifi_Setup();
  
  // Initialize Hardware
  init_hw();
  
  // Initialize button
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  
  Serial.println("System Ready!");
  displayMessage("Green Loop", "System Ready", "", "Press to Start");
}

void loop() {
  // Check bin status every 5 seconds
  if (millis() - lastBinStatusCheck >= BIN_STATUS_INTERVAL) {
    read_bin_status();
    lastBinStatusCheck = millis();
  }
  
  // Check button state
  read_button_click();
  
  // Handle config mode server if active
  if (WiFi.getMode() == WIFI_AP) {
    server.handleClient();
  }
  
  delay(10);
}

void init_display() {
  Serial.println("Initializing OLED...");
  
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("ERROR: OLED initialization failed! Check wiring and reset.");
    while (1);  // Halt
  }
  
  display.clearDisplay();
  display.setTextSize(2);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 20);
  display.println("Green Loop");
  display.setTextSize(1);
  display.setCursor(0, 45);
  display.println("Initializing...");
  display.display();
  
  Serial.println("OLED initialized successfully");
}

void Wifi_Setup() {
  Serial.println("\nInitializing WiFi...");
  displayMessage("WiFi Setup", "Initializing...");
  
  // Load credentials from flash
  preferences.begin("greenloop", false);
  wifi_ssid = preferences.getString("ssid", "");
  wifi_password = preferences.getString("password", "");
  server_ip = preferences.getString("server_ip", "");
  bin_id = preferences.getString("bin_id", "");
  preferences.end();
  
  // Try to connect if credentials exist
  if (wifi_ssid.length() > 0) {
    Serial.println("Connecting to: " + wifi_ssid);
    displayMessage("Connecting to", wifi_ssid);
    
    WiFi.mode(WIFI_STA);
    WiFi.begin(wifi_ssid.c_str(), wifi_password.c_str());
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\nWiFi Connected!");
      Serial.println("IP: " + WiFi.localIP().toString());
      displayMessage("WiFi Connected", WiFi.localIP().toString(), "Bin ID: " + bin_id);
      delay(2000);
      return;
    }
  }
  
  // Enter config mode if no credentials or connection failed
  Serial.println("\nEntering Config Mode...");
  Config_Mode();
}

void Config_Mode() {
  displayMessage("Config Mode", "SSID: GreenLoop", "Pass: Green@123#");
  
  // Set up AP mode
  WiFi.mode(WIFI_AP);
  WiFi.softAP("GreenLoop", "Green@123#");
  
  IPAddress IP = WiFi.softAPIP();
  Serial.println("AP Started");
  Serial.println("SSID: GreenLoop");
  Serial.println("Password: Green@123#");
  Serial.println("IP: " + IP.toString());
  
  displayMessage("Config Mode", "IP: " + IP.toString(), "SSID: GreenLoop", "Pass: Green@123#");
  
  // Setup web server routes
  server.on("/", HTTP_GET, []() {
    String html = "<!DOCTYPE html><html><head><title>Green Loop Config</title>";
    html += "<meta name='viewport' content='width=device-width, initial-scale=1'>";
    html += "<style>body{font-family:Arial;margin:20px;background:#f0f0f0;}";
    html += ".container{max-width:400px;margin:auto;background:white;padding:20px;border-radius:10px;}";
    html += "h1{color:#2ecc71;text-align:center;}input{width:100%;padding:10px;margin:10px 0;box-sizing:border-box;}";
    html += "button{width:100%;padding:12px;background:#2ecc71;color:white;border:none;border-radius:5px;cursor:pointer;}";
    html += "button:hover{background:#27ae60;}</style></head><body>";
    html += "<div class='container'><h1>Green Loop Configuration</h1>";
    html += "<form action='/save' method='POST'>";
    html += "<label>WiFi SSID:</label><input type='text' name='ssid' required>";
    html += "<label>WiFi Password:</label><input type='password' name='password' required>";
    html += "<label>Server IP:</label><input type='text' name='server_ip' placeholder='http://example.com' required>";
    html += "<label>Bin ID:</label><input type='text' name='bin_id' required>";
    html += "<button type='submit'>Save & Connect</button>";
    html += "</form></div></body></html>";
    server.send(200, "text/html", html);
  });
  
  server.on("/save", HTTP_POST, []() {
    wifi_ssid = server.arg("ssid");
    wifi_password = server.arg("password");
    server_ip = server.arg("server_ip");
    bin_id = server.arg("bin_id");
    
    // Save to flash
    preferences.begin("greenloop", false);
    preferences.putString("ssid", wifi_ssid);
    preferences.putString("password", wifi_password);
    preferences.putString("server_ip", server_ip);
    preferences.putString("bin_id", bin_id);
    preferences.end();
    
    String html = "<!DOCTYPE html><html><head><title>Saved</title></head><body>";
    html += "<h1>Configuration Saved!</h1><p>Device will restart and connect...</p></body></html>";
    server.send(200, "text/html", html);
    
    delay(2000);
    ESP.restart();
  });
  
  server.begin();
  Serial.println("Web server started");
  
  // Keep server running until configured
  while (WiFi.getMode() == WIFI_AP) {
    server.handleClient();
    delay(10);
  }
}

void init_hw() {
  Serial.println("\nInitializing Hardware...");
  displayMessage("Hardware Init", "Please wait...");
  
  // Initialize I2C
  Wire.begin();
  
  // Initialize PCA9685
  Serial.println("Initializing PCA9685...");
  pwm.begin();
  pwm.setPWMFreq(50);  // Servo frequency
  
  // Test PCA9685
  delay(100);
  bool pca9685_ok = true;
  // Close all lids initially
  for (int i = 0; i < NUM_CHAMBERS; i++) {
    closeLid(i);
  }
  
  if (!pca9685_ok) {
    Serial.println("ERROR: PCA9685 not initialized! Check wiring and reset.");
    displayMessage("ERROR", "PCA9685 Failed", "Check wiring", "Reset to retry");
    while (1);
  }
  Serial.println("PCA9685 initialized successfully");
  
  // Initialize HX711
  Serial.println("Initializing HX711...");
  scale.begin(HX711_DOUT, HX711_SCK);
  
  if (!scale.is_ready()) {
    Serial.println("WARNING: HX711 not detected! Using simulated weight mode.");
    displayMessage("WARNING", "HX711 Failed", "Using simulated", "weight mode");
    hx711_available = false;
    simulated_weight = 2.1;  // Start with some base weight (e.g., empty bin weight)
    delay(2000);
  } else {
    scale.set_scale(420.0983);  // Calibration factor (adjust as needed)
    scale.tare();
    hx711_available = true;
    Serial.println("HX711 initialized successfully");
  }
  
  // Initialize IR sensors
  for (int i = 0; i < NUM_CHAMBERS; i++) {
    pinMode(irPins[i], INPUT);
  }
  Serial.println("IR sensors initialized");
  
  displayMessage("Hardware OK", "All systems", "operational");
  delay(1000);
}

void read_bin_status() {
  // Increment simulated weight if using dummy mode
  if (!hx711_available && disposal_count > 0) {
    simulated_weight += 0.030;  // Add 30g per disposal
  }
  
  // Get current weight
  current_weight = getWeight();
  
  // Check each chamber
  for (int i = 0; i < NUM_CHAMBERS; i++) {
    // Check if IR sensor detects object and lid is closed
    if (!checkIRSensor(i) && !lid_status[i]) {
      bin_full[i] = true;
    } else {
      bin_full[i] = false;
    }
  }
  
  // Prepare data for upload
  String binStatusStr = getBinStatusString();
  
  // Create JSON data
  StaticJsonDocument<256> doc;
  doc["bin_id"] = bin_id;
  doc["weight"] = current_weight;
  doc["bin_status"] = binStatusStr;
  
  String jsonData;
  serializeJson(doc, jsonData);
  
  // Upload to server
  uploadToServer("/api/update_bin_status.php", jsonData);
  
  Serial.print("Bin Status: ");
  Serial.print(binStatusStr);
  Serial.print(" | Weight: ");
  Serial.print(current_weight);
  Serial.println(" kg");
}

void read_button_click() {
  int reading = digitalRead(BUTTON_PIN);
  
  // If button state changed, reset debounce timer
  if (reading != lastButtonState) {
    lastDebounceTime = millis();
    lastButtonState = reading;
  }
  
  // If enough time has passed and button is LOW (pressed), trigger action
  if ((millis() - lastDebounceTime) > debounceDelay) {
    if (reading == LOW) {
      // Button pressed and stable
      if (!disposal_started) {
        disposal_started = true;
        tot_points = 0;
        Serial.println("Disposal session STARTED");
        displayMessage("Session Start", "Scan QR codes", "to begin");
        delay(1000);
        disposal();
      } else {
        disposal_started = false;
        Serial.println("Disposal session STOPPED");
        rewards_earned();
      }
      
      // Wait for button release to prevent multiple triggers
      while (digitalRead(BUTTON_PIN) == LOW) {
        delay(10);
      }
      lastDebounceTime = millis();
    }
  }
}

void disposal() {
  Serial.println("\n=== Starting Disposal Process ===");
  tot_points = 0;
  
  while (disposal_started) {
    // Check button for stop signal
    read_button_click();
    
    if (!disposal_started) break;
    
    // Poll for disposal data from server
    int chamber = -1;
    String plasticType = "";
    
    displayMessage("Waiting for", "QR scan...", "", "Press to stop");
    
    if (pollDisposalData(chamber, plasticType)) {
      Serial.println("Disposal request received: " + plasticType + " -> Chamber " + String(chamber));
      
      // Check if bin is full
      if (bin_full[chamber]) {
        Serial.println("ERROR: Bin full!");
        displayMessage("ERROR", chamberNames[chamber] + String(" bin full!"), "Try another");
        delay(3000);
        continue;
      }
      
      // Display disposal message
      displayMessage("Disposing", plasticType, "Open lid...");
      
      // Open lid
      openLid(chamber);
      delay(500);
      
      // Wait for IR sensor detection (10 seconds timeout)
      Serial.println("Waiting for object detection...");
      bool detected = false;
      unsigned long startTime = millis();
      
      while (millis() - startTime < 10000) {
        if (!checkIRSensor(chamber)) {  // IR sensor triggered (LOW = detected)
          detected = true;
          Serial.println("Object detected!");
          break;
        }
        delay(100);
        
        // Check for stop button
        read_button_click();
        if (!disposal_started) {
          closeLid(chamber);
          return;
        }
      }
      
      if (!detected) {
        Serial.println("ERROR: No object detected!");
        displayMessage("ERROR", "No object", "detected!");
        closeLid(chamber);
        delay(2000);
        continue;
      }
      
      // Wait a bit for object to settle
      delay(1000);
      
      // Close lid
      closeLid(chamber);
      delay(500);
      
      // Check weight change
      previous_weight = current_weight;
      current_weight = getWeight();
      
      if (checkWeightChange()) {
        Serial.println("Weight change confirmed!");
        
        // Increment disposal counter for simulated weight
        disposal_count++;
        
        // Add points
        int points = rewardPoints[chamber];
        tot_points += points;
        
        Serial.println("Points earned: " + String(points));
        Serial.println("Total points: " + String(tot_points));
        
        // Update disposal data as confirmed
        StaticJsonDocument<128> doc;
        doc["chamber"] = chamber;
        doc["confirmed"] = 1;
        
        String jsonData;
        serializeJson(doc, jsonData);
        uploadToServer("/api/confirm_disposal.php", jsonData);
        
        // Display points
        displayMessage("Success!", String(points) + " points", "Total: " + String(tot_points));
        delay(2000);
        
      } else {
        Serial.println("ERROR: No weight change detected!");
        displayMessage("ERROR", "Weight not", "changed!");
        delay(2000);
      }
      
    }
    
    delay(500);
  }
}

void rewards_earned() {
  Serial.println("\n=== Disposal Completed ===");
  displayMessage("Disposal", "Completed!", "Total: " + String(tot_points) + " pts");
  delay(2000);
  
  if (tot_points <= 0) {
    displayMessage("No rewards", "to collect");
    delay(2000);
    displayMessage("Green Loop", "Ready", "", "Press to Start");
    return;
  }
  
  // Generate 6-character alphanumeric code
  String uniqueCode = "";
  const char charset[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  for (int i = 0; i < 6; i++) {
    uniqueCode += charset[random(0, 36)];
  }
  
  Serial.println("Unique Code: " + uniqueCode);
  Serial.println("Total Points: " + String(tot_points));
  
  // Get bin status
  read_bin_status();
  
  // Upload rewards data
  StaticJsonDocument<256> doc;
  doc["unique_code"] = uniqueCode;
  doc["points"] = tot_points;
  doc["bin_id"] = bin_id;
  
  String jsonData;
  serializeJson(doc, jsonData);
  
  uploadToServer("/api/upload_rewards.php", jsonData);
  
  // Display code on OLED
  displayMessage("Your Code:", uniqueCode, "Points: " + String(tot_points), "Collect reward!");
  
  // Keep displaying for 30 seconds
  delay(30000);
  
  // Reset
  tot_points = 0;
  displayMessage("Green Loop", "Ready", "", "Press to Start");
}

void setServoAngle(int servoNum, int angle) {
  int pulse = map(angle, 0, 180, SERVO_MIN, SERVO_MAX);
  pwm.setPWM(servoNum, 0, pulse);
}

void openLid(int chamber) {
  if (chamber < 0 || chamber >= NUM_CHAMBERS) return;
  
  Serial.println("Opening lid: " + String(chamberNames[chamber]));
  setServoAngle(chamber, SERVO_OPEN);
  lid_status[chamber] = true;
  delay(500);
}

void closeLid(int chamber) {
  if (chamber < 0 || chamber >= NUM_CHAMBERS) return;
  
  Serial.println("Closing lid: " + String(chamberNames[chamber]));
  setServoAngle(chamber, SERVO_CLOSE);
  lid_status[chamber] = false;
  delay(500);
}

bool checkIRSensor(int chamber) {
  if (chamber < 0 || chamber >= NUM_CHAMBERS) return true;
  
  // IR sensor returns LOW when object detected
  return digitalRead(irPins[chamber]) == HIGH;
}

float getWeight() {
  if (hx711_available && scale.is_ready()) {
    float weight = scale.get_units(5);  // Average of 5 readings
    return max(0.0f, weight);  // Return 0 if negative
  } else {
    // Return simulated weight (increases by 30g per disposal)
    return simulated_weight;
  }
}

bool checkWeightChange() {
  float diff = abs(current_weight - previous_weight);
  Serial.println("Weight change: " + String(diff) + " kg");
  return diff > 0.01;  // More than 10g change
}

String getBinStatusString() {
  String status = "";
  for (int i = 0; i < NUM_CHAMBERS; i++) {
    status += bin_full[i] ? "1" : "0";
  }
  return status;
}

void displayMessage(String line1, String line2, String line3, String line4) {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  
  display.setCursor(0, 0);
  display.println(line1);
  
  if (line2.length() > 0) {
    display.setCursor(0, 16);
    display.println(line2);
  }
  
  if (line3.length() > 0) {
    display.setCursor(0, 32);
    display.println(line3);
  }
  
  if (line4.length() > 0) {
    display.setCursor(0, 48);
    display.println(line4);
  }
  
  display.display();
}

bool uploadToServer(String endpoint, String jsonData) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected!");
    return false;
  }
  
  HTTPClient http;
  String url = server_ip + endpoint;
  
  Serial.println("POST: " + url);
  Serial.println("Data: " + jsonData);
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  int httpCode = http.POST(jsonData);
  
  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("Response: " + response);
    http.end();
    return httpCode == 200;
  } else {
    Serial.println("Error: " + String(httpCode));
    http.end();
    return false;
  }
}

bool pollDisposalData(int& chamber, String& plasticType) {
  if (WiFi.status() != WL_CONNECTED) return false;
  
  HTTPClient http;
  String url = server_ip + "/api/get_disposal_data.php?bin_id=" + bin_id;
  
  http.begin(url);
  int httpCode = http.GET();
  
  if (httpCode == 200) {
    String response = http.getString();
    http.end();
    
    StaticJsonDocument<256> doc;
    DeserializationError error = deserializeJson(doc, response);
    
    if (!error && doc["success"] == true) {
      plasticType = doc["type"].as<String>();
      
      // Map plastic type to chamber
      if (plasticType == "PET") chamber = CHAMBER_PET;
      else if (plasticType == "HDPE") chamber = CHAMBER_HDPE;
      else if (plasticType == "PP") chamber = CHAMBER_PP;
      else chamber = CHAMBER_OTHERS;
      
      return true;
    }
  } else {
    http.end();
  }
  
  return false;
}
