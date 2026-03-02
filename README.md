# 🌱 Green Loop - Smart Plastic Collection System

A comprehensive IoT-based smart plastic collection bin system that rewards users for proper plastic disposal and helps track recycling efforts.

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Hardware Requirements](#hardware-requirements)
- [Software Requirements](#software-requirements)
- [Installation Guide](#installation-guide)
- [Usage Guide](#usage-guide)
- [API Documentation](#api-documentation)
- [Troubleshooting](#troubleshooting)

## 🎯 Overview

Green Loop is an intelligent waste management system that:
- Automatically sorts plastic waste into 4 categories (PET, HDPE, PP, Others)
- Scans QR codes to identify products
- Tracks weight and bin capacity
- Rewards users with points for recycling
- Provides real-time monitoring through web dashboard

## ✨ Features

### Hardware Features
- **QR Code Scanning**: ESP32-CAM scans product QR codes
- **4-Chamber Sorting**: Automated lid control for different plastic types
- **Weight Sensing**: HX711 load cell tracks disposal weight
- **IR Detection**: Confirms plastic entry into bins
- **OLED Display**: Real-time user feedback
- **WiFi Config Mode**: Easy setup via web interface

### Software Features
- **User Portal**: Track rewards and recycling impact
- **Admin Dashboard**: Monitor bins and manage products
- **Product Database**: QR code generation for plastic products
- **Reward System**: Points-based incentive system
- **Real-time Monitoring**: Live bin status updates

## 🔧 Hardware Requirements

| Component | Quantity | Notes |
|-----------|----------|-------|
| ESP32-CAM | 1 | Main controller with camera |
| IR Sensors | 4 | Plastic entry detection |
| SG90 Servo Motors | 4 | Lid control |
| HX711 Load Cell (20kg) | 1 | Weight measurement |
| PCA9685 Servo Driver | 1 | I2C servo controller |
| Push Buttons | 2 | Start/Continue and Stop/Done |
| OLED Display (128x64) | 1 | I2C display (SSD1306) |
| Power Supply | 1 | 5V, minimum 3A |

### Pin Connections

```
ESP32-CAM Pin Configuration:
- IR Sensors: GPIO 13, 12, 14, 15
- Buttons: GPIO 4 (Start), GPIO 2 (Stop)
- HX711: GPIO 16 (DOUT), GPIO 17 (SCK)
- I2C: GPIO 21 (SDA), GPIO 22 (SCL)
```

## 💻 Software Requirements

### For Website
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Web browser (Chrome, Firefox, Safari)

### For Firmware
- Arduino IDE 1.8.19 or higher
- ESP32 Board Support
- Required Arduino Libraries:
  - WiFi
  - WebServer
  - Preferences
  - HTTPClient
  - Wire
  - Adafruit_PWMServoDriver
  - HX711
  - Adafruit_GFX
  - Adafruit_SSD1306
  - ESP32 Camera Driver
  - QUIRC (QR Code library)

## 📦 Installation Guide

### 1. Hardware Setup

1. **Connect Components** according to pin configuration table above
2. **Mount Sensors** inside bin chambers
3. **Attach Servos** to chamber lids
4. **Position Load Cell** at bottom of bin
5. **Install OLED Display** on front panel

### 2. Firmware Installation

1. **Install Arduino IDE** and ESP32 board support:
   ```
   File > Preferences > Additional Board Manager URLs
   Add: https://dl.espressif.com/dl/package_esp32_index.json
   ```

2. **Install Required Libraries**:
   - Open Arduino IDE
   - Go to Sketch > Include Library > Manage Libraries
   - Search and install each library listed above

3. **Upload Firmware**:
   - Open `Firmware/smart_bin.ino`
   - Select Board: "AI Thinker ESP32-CAM"
   - Select correct COM Port
   - Click Upload

4. **Calibrate Load Cell**:
   - Place known weight on sensor
   - Adjust `scale.set_scale()` value in code
   - Re-upload firmware

### 3. Website Installation

1. **Setup Database**:
   ```bash
   mysql -u root -p
   source Website/database.sql
   ```

2. **Configure Database Connection**:
   - Edit `Website/config.php`
   - Update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'greenloop');
   ```

3. **Deploy Website**:
   - Copy `Website/` folder to web server directory
   - Ensure PHP has read/write permissions
   - Access via browser: `http://your-server-ip/`

4. **Default Admin Login**:
   - Username: `admin`
   - Password: `admin123`
   - **Change password immediately after first login!**

## 🚀 Usage Guide

### First-Time Setup (Hardware)

1. **Power on ESP32** - System will enter config mode
2. **Connect to WiFi**:
   - SSID: `GreenLoop`
   - Password: `Green@123#`
3. **Open Browser** - Go to IP shown on OLED (usually 192.168.4.1)
4. **Enter Configuration**:
   - WiFi SSID and Password
   - Server IP (your web server address)
   - Bin ID (from admin dashboard)
5. **Save** - Device will restart and connect to WiFi

### Adding Products (Admin)

1. **Login** to admin dashboard
2. **Click "Add Product"**
3. **Enter Details**:
   - Manufacturer name
   - Plastic type (PET, HDPE, PP, Others)
4. **Submit** - QR code will be generated
5. **Print/Save QR Code** for the product

### Adding Bins (Admin)

1. **Click "Add New Bin"**
2. **Enter Location** (e.g., "Main Campus Entrance")
3. **Note the Bin ID** - Use this in hardware config mode

### User Disposal Process

1. **Press Start Button** on bin
2. **Scan QR Code** of plastic product
3. **Wait for Lid** to open automatically
4. **Insert Plastic** into opened chamber
5. **Repeat** for more items or press Stop
6. **Get Reward Code** shown on OLED
7. **Enter Code** in user dashboard to collect points

### Monitoring Bins (Admin)

1. **Select Bin Location** from dropdown
2. **View Status**:
   - Chamber availability (Available/Full)
   - Total weight
   - Last update time

## 📡 API Documentation

### Upload Data (Hardware → Server)

**Endpoint**: `POST /api.php`

**Request Body**:
```json
{
  "action": "upload_data",
  "points": 50,
  "unique_code": "ABC123",
  "bin_weight": 15.5,
  "bin_status": "0010",
  "bin_id": "1"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Data uploaded successfully"
}
```

### Get Product Data (Hardware → Server)

**Endpoint**: `GET /api.php?action=get_product&qr_id=ABC1234567`

**Response**: Returns plastic type as plain text (PET, HDPE, PP, or Others)

## 🔧 Troubleshooting

### Hardware Issues

**Camera Not Working**
- Check camera ribbon cable connection
- Ensure correct camera configuration in code
- Try different frame size settings

**Servos Not Moving**
- Verify PCA9685 I2C address (default 0x40)
- Check power supply (servos need adequate current)
- Test I2C connection with scanner sketch

**Load Cell Inaccurate**
- Recalibrate using known weights
- Check proper mounting (avoid mechanical stress)
- Adjust `scale.set_scale()` value

**WiFi Connection Failed**
- Verify SSID and password
- Check WiFi signal strength
- Ensure 2.4GHz network (ESP32 doesn't support 5GHz)

### Software Issues

**Database Connection Error**
- Verify MySQL is running
- Check credentials in config.php
- Ensure database exists

**QR Code Not Scanning**
- Improve lighting conditions
- Increase QR code size
- Reduce camera exposure time

**Bin Status Not Updating**
- Check hardware API calls
- Verify server IP in ESP32 config
- Check firewall settings

## 📊 Points System

| Plastic Type | Points per Item |
|--------------|-----------------|
| PET | 10 points |
| HDPE | 20 points |
| PP | 30 points |
| Others | 5 points |

## 🔐 Security Notes

1. **Change Default Passwords** immediately after installation
2. **Use HTTPS** in production environments
3. **Secure Database** with strong credentials
4. **Update Regularly** to patch security vulnerabilities
5. **Limit Admin Access** to authorized personnel only

## 📝 License

This project is open-source and available for educational and commercial use.

## 🤝 Support

For issues, questions, or contributions:
- Check troubleshooting guide above
- Review code comments for implementation details
- Test in development environment before production deployment

## 🌟 Future Enhancements

- Mobile application
- Cloud-based analytics
- Multiple reward redemption options
- AI-based plastic type detection
- IoT dashboard with real-time alerts
- Integration with waste management services

---

**Made with 💚 for a cleaner planet**
