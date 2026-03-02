# Required Arduino Libraries for Green Loop

## Installation Instructions

Open Arduino IDE and go to: **Sketch > Include Library > Manage Libraries**

Search and install each of the following:

## Core Libraries (Built-in with ESP32)
- WiFi (Built-in)
- WebServer (Built-in)
- Preferences (Built-in)
- HTTPClient (Built-in)
- Wire (Built-in)

## External Libraries to Install

### 1. Adafruit PWM Servo Driver Library
**Name**: Adafruit PWM Servo Driver Library
**Author**: Adafruit
**Purpose**: Control PCA9685 servo controller via I2C
```
Search: "Adafruit PWM Servo"
Install: Adafruit PWM Servo Driver Library
```

### 2. HX711 Arduino Library
**Name**: HX711 Arduino Library
**Author**: Bogdan Necula
**Purpose**: Interface with HX711 load cell amplifier
```
Search: "HX711"
Install: HX711 Arduino Library by Bogdan Necula
```

### 3. Adafruit GFX Library
**Name**: Adafruit GFX Library
**Author**: Adafruit
**Purpose**: Graphics library for OLED display
```
Search: "Adafruit GFX"
Install: Adafruit GFX Library
```

### 4. Adafruit SSD1306
**Name**: Adafruit SSD1306
**Author**: Adafruit
**Purpose**: Driver for SSD1306 OLED displays
```
Search: "Adafruit SSD1306"
Install: Adafruit SSD1306
```

### 5. ESP32 Camera Driver (Built-in)
**Note**: This comes with ESP32 board support package
```
Already included with ESP32 Arduino Core
```

### 6. QUIRC QR Code Library
**Name**: ESP32 QR Code Reader
**Alternative**: You may need to install manually
**Purpose**: QR code detection and decoding
```
Option 1: Search "quirc" in Library Manager
Option 2: Download from: https://github.com/dlbeer/quirc
```

## ESP32 Board Support Installation

### Add ESP32 Board Manager URL
1. Open Arduino IDE
2. Go to **File > Preferences**
3. In "Additional Board Manager URLs" add:
```
https://dl.espressif.com/dl/package_esp32_index.json
```

### Install ESP32 Board Package
1. Go to **Tools > Board > Board Manager**
2. Search for "ESP32"
3. Install "esp32 by Espressif Systems"
4. Wait for installation to complete

## Board Selection

After installation, select your board:
- **Tools > Board > ESP32 Arduino > AI Thinker ESP32-CAM**

## Port Selection

- **Tools > Port > [Select your COM port]**
  - Windows: COM3, COM4, etc.
  - Mac/Linux: /dev/ttyUSB0, /dev/cu.usbserial, etc.

## Upload Settings (Important for ESP32-CAM!)

```
Board: "AI Thinker ESP32-CAM"
Upload Speed: "115200"
Flash Frequency: "40MHz"
Flash Mode: "DIO"
Partition Scheme: "Huge APP (3MB No OTA/1MB SPIFFS)"
Core Debug Level: "None"
Port: [Your COM Port]
```

## Upload Process for ESP32-CAM

⚠️ **Important**: ESP32-CAM requires special procedure to upload:

1. Connect USB-to-Serial adapter:
   - RX → TX (ESP32-CAM)
   - TX → RX (ESP32-CAM)
   - GND → GND
   - 5V → 5V

2. **Upload Mode**:
   - Connect GPIO0 to GND
   - Press Reset button
   - Click Upload in Arduino IDE
   - When you see "Connecting...", you can release reset

3. **Run Mode**:
   - Disconnect GPIO0 from GND
   - Press Reset button
   - Program will run

## Verification

After installing all libraries, compile the sketch:
1. Open `Firmware/smart_bin.ino`
2. Click **Verify** (checkmark icon)
3. Should compile without errors

## Troubleshooting Library Issues

### "Library not found" error
- Check library names exactly match
- Restart Arduino IDE after installation
- Check Library Manager installation path

### Compilation errors
- Ensure all dependencies installed
- Update ESP32 board package to latest
- Check board selection is correct

### Camera errors
- Camera libraries are part of ESP32 core
- Update ESP32 Arduino Core if issues persist
- Check camera model matches code configuration

## Alternative Installation (Manual)

If Library Manager doesn't work:

1. Download library ZIP files from GitHub
2. Go to **Sketch > Include Library > Add .ZIP Library**
3. Select downloaded ZIP file
4. Restart Arduino IDE

## Library Versions (Tested)

- Adafruit PWMServoDriver: v2.4.0+
- HX711: v0.7.5+
- Adafruit GFX: v1.11.0+
- Adafruit SSD1306: v2.5.0+
- ESP32 Arduino Core: v2.0.0+

---

## Quick Install Commands (via CLI)

If using arduino-cli:

```bash
arduino-cli lib install "Adafruit PWM Servo Driver Library"
arduino-cli lib install "HX711 Arduino Library"
arduino-cli lib install "Adafruit GFX Library"
arduino-cli lib install "Adafruit SSD1306"
arduino-cli core install esp32:esp32
```

---

**All set?** Head back to README.md for complete setup instructions!
