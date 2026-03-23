# Green Loop - Quick Start Guide

Complete setup guide for getting Green Loop running with HTTPS support.

---

## 🚀 Quick Setup Overview

1. **Database Setup** (5 minutes)
2. **Website Configuration** (5 minutes)
3. **HTTPS Tunnel Setup** (5 minutes)
4. **Firmware Upload** (10 minutes)
5. **Hardware Configuration** (5 minutes)

**Total Time: ~30 minutes**

---

## Step 1: Database Setup

### Install MySQL/XAMPP

**Windows:**
1. Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
2. Install and start Apache + MySQL from XAMPP Control Panel

**Linux:**
```bash
sudo apt update
sudo apt install mysql-server php apache2
```

### Create Database

1. Open your terminal/command prompt
2. Navigate to the project directory:
```bash
cd Website/smart_bin_qr_v3/database
```

3. Login to MySQL:
```bash
mysql -u root -p
```

4. Import the database:
```sql
source database.sql
```

5. Verify tables were created:
```sql
USE green_loop;
SHOW TABLES;
```

6. Exit MySQL:
```sql
exit
```

### Load Sample Data

1. Open  browser and navigate to:
```
http://localhost/green-loop/Website/smart_bin_qr_v3/database/sample.php
```

2. This will create:
   - Sample users (admin, manufacturers, users)
   - Sample bins
   - Sample products with QR codes
   - Sample rewards data

---

## Step 2: Website Configuration

### Configure Database Connection

1. Navigate to `Website/smart_bin_qr_v3/`
2. Open `config.php`
3. Update database credentials:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'green_loop');
define('DB_USER', 'root');     // Your MySQL username
define('DB_PASS', '');         // Your MySQL password
```

### Deploy Website

**Using XAMPP (Windows):**
1. Copy `Website/smart_bin_qr_v3/` folder to `C:\xampp\htdocs\greenloop\`
2. Access at: `http://localhost/greenloop/`

**Using Apache (Linux):**
```bash
sudo cp -r Website/smart_bin_qr_v3 /var/www/html/greenloop
sudo chown -R www-data:www-data /var/www/html/greenloop
```

### Test Website

1. Open browser: `http://localhost/greenloop/`
2. You should see the login page
3. Login with default admin credentials:
   - Username: `admin`
   - Password: `admin123`

---

## Step 3: HTTPS Tunnel Setup (Cloudflare)

For ESP32 to access your local website over the internet, you need an HTTPS tunnel.

### Option A: Using Cloudflare Tunnel (Recommended)

**Step 1: Install Cloudflared**

Open PowerShell **as Administrator**:
```powershell
cd Website\smart_bin_qr_v3\tools
.\install_cloudflared.ps1
```

**Step 2: Start Tunnel**

In the same PowerShell window:
```powershell
.\start_https_tunnel.ps1
```

You'll see output like:
```
Your quick Tunnel has been created!
https://random-words-1234.trycloudflare.com
```

**Copy this HTTPS URL** - you'll need it for ESP32 configuration.

**Step 3: Auto-Start on Boot (Optional)**

To automatically start the tunnel when Windows boots:
```powershell
.\register_startup_task.ps1
```

### Option B: Using ngrok

1. Download ngrok from [https://ngrok.com](https://ngrok.com)
2. Install and authenticate
3. Run:
```bash
ngrok http 80
```
4. Copy the HTTPS URL provided

### Option C: Port Forwarding (Advanced)

1. Configure your router to forward port 80 to your computer
2. Get your public IP from [https://whatismyip.com](https://whatismyip.com)
3. Use: `http://your-public-ip`

---

## Step 4: Firmware Upload

### Install Arduino IDE

1. Download Arduino IDE 2.0+ from [https://www.arduino.cc](https://www.arduino.cc)
2. Install and open Arduino IDE

### Add ESP32 Board Support

1. Go to `File` → `Preferences`
2. In "Additional Board Manager URLs", add:
```
https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
```
3. Click OK
4. Go to `Tools` → `Board` → `Boards Manager`  
5. Search for "esp32"
6. Install "esp32 by Espressif Systems"

### Install Required Libraries

Go to `Sketch` → `Include Library` → `Manage Libraries`, then install:

- **Adafruit SSD1306** (for OLED)
- **Adafruit GFX Library** (for OLED)
- **Adafruit PWM Servo Driver Library** (for PCA9685)
- **HX711 Arduino Library** (for load cell)
- **ArduinoJson** (for API communication)

### Upload Firmware

1. Open `Firmware/smart_bin_esp32_c3/smart_bin_v3.ino`
2. Select Board:
   - `Tools` → `Board` → `ESP32 Arduino` → `ESP32C3 Dev Module`
3. Select Port:
   - `Tools` → `Port` → Select your ESP32's COM port
4. Click **Upload** button
5. Wait for "Done uploading" message

---

## Step 5: Hardware Configuration

### First Boot

1. Power on the ESP32-C3
2. OLED display will show "Config Mode"
3. ESP32 creates a WiFi hotspot:
   - **SSID:** `GreenLoop`
   - **Password:** `Green@123#`

### Connect and Configure

1. **Connect to ESP32 WiFi:**
   - Use your phone or laptop
   - Connect to WiFi: `GreenLoop` / `Green@123#`

2. **Open Configuration Page:**
   - Open browser
   - Go to IP shown on OLED (usually `192.168.4.1`)

3. **Enter Configuration:**
   - **WiFi SSID:** Your home/office WiFi name
   - **WiFi Password:** Your WiFi password
   - **Server IP:** The HTTPS URL from Step 3 (e.g., `https://random-words-1234.trycloudflare.com`)
   - **Bin ID:** Get this from the admin dashboard

4. **Save Configuration:**
   - Click "Save & Connect"
   - ESP32 will restart and connect to your WiFi
   - OLED will display "WiFi Connected" and show the bin status

### Get Bin ID

1. Login to admin dashboard
2. Click "Add New Bin"
3. Enter location (e.g., "Main Entrance")
4. Click Submit
5. Note the **Bin ID** displayed (e.g., "3")
6. Use this ID in ESP32 configuration

---

## 🎯 Testing the System

### Test 1: Add a Product

1. Login as admin
2. Go to Admin Dashboard
3. Click "Add Product"
4. Enter:
   - Manufacturer: "Test Company"
   - Type: "PET"
5. Submit
6. QR code will be generated - download it

### Test 2: User Disposal Flow

1. Logout and login as user (username: `johndoe`, password: `user123`)
2. Click "Start Disposal"
3. Select bin location
4. Click "Start Scanning"
5. Scan the QR code you generated
6. System should show success message

### Test 3: Hardware Disposal

1. Press the button on ESP32
2. OLED shows "Waiting for QR scan..."
3. Scan QR code from website (as in Test 2)
4. Hardware should:
   - Open the PET chamber lid
   - Wait for IR sensor detection
   - Close lid
   - Check weight change
   - Add points

5. Press button again to stop
6. OLED shows reward code
7. Enter code in user dashboard to collect points

---

## 📋 Post-Setup Checklist

- [ ] Database created and sample data loaded
- [ ] Website accessible at local URL
- [ ] HTTPS tunnel running and accessible
- [ ] ESP32 firmware uploaded successfully
- [ ] ESP32 connected to WiFi
- [ ] ESP32 configured with correct server URL and Bin ID
- [ ] Can login to website
- [ ] Can add products and generate QR codes
- [ ] Can scan QR codes and add disposal requests
- [ ] Hardware responds to disposal requests
- [ ] Reward codes generated and displayable
- [ ] Points collected successfully in user dashboard

---

## 🔧 Troubleshooting

### Website Issues

**"Database connection failed":**
- Check MySQL is running
- Verify credentials in `config.php`
- Ensure database `green_loop` exists

**"Page not found":**
- Check file path
- Ensure web server is running
- Check file permissions

### ESP32 Issues

**"Won't enter config mode":**
- Hold button during power-on
- Check serial monitor for errors
- Reflash firmware

**"Can't connect to WiFi":**
- Verify SSID and password
- Check 2.4GHz network (ESP32 doesn't support 5GHz)
- Move closer to router

**"Can't reach server":**
- Verify HTTPS tunnel is running
- Check server URL in ESP32 config
- Test URL in browser first

### Hardware Issues

**"Servo not moving":**
- Check PCA9685 wiring
- Verify 5V power supply
- Test servo separately

**"Load cell always zero":**
- Check HX711 connections
- Run calibration code
- Verify load cell wiring

---

## 🚀 Next Steps

1. **Add More Products:**
   - Login as manufacturer
   - Add your product catalog
   - Generate QR codes
   - Print and attach to products

2. **Deploy Multiple Bins:**
   - Add more bin locations in admin dashboard
   - Configure additional ESP32 devices
   - Each gets unique Bin ID

3. **Customize Reward Points:**
   - Edit `config.php`
   - Modify `POINTS_PET`, `POINTS_HDPE`, etc.

4. **Secure the System:**
   - Change all default passwords
   - Enable HTTPS on web server
   - Configure firewall rules

---

## 📞 Need Help?

- Check [README.md](../README.md) for detailed documentation
- Review [WIRING_NOQR.md](WIRING_NOQR.md) for hardware connections
- See [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) for code organization

**Happy Recycling! 🌱**
- [ ] All components connected properly
- [ ] Power supply adequate (5V, 3A+)
- [ ] Load cell calibrated
- [ ] Servos tested and moving

Software:
- [ ] Database created and populated
- [ ] PHP files deployed to web server
- [ ] Admin login working
- [ ] Can add products and generate QR codes

Firmware:
- [ ] Code uploaded successfully
- [ ] OLED display showing messages
- [ ] WiFi connected to network
- [ ] Can scan QR codes

## 🧪 Testing

### Test 1: Product Management
1. Login as admin
2. Add a test product
3. Download QR code
4. Print or display on phone

### Test 2: Bin Setup
1. Add a new bin in admin dashboard
2. Note the Bin ID
3. Configure ESP32 with this ID

### Test 3: Complete Disposal Flow
1. Power on hardware
2. Press Start button
3. Scan QR code
4. Insert test plastic
5. Check reward code on OLED
6. Login as user and claim reward

## ⚡ Common Quick Fixes

**ESP32 won't upload?**
- Press and hold BOOT button while uploading
- Release after "Connecting..." appears

**Website shows blank page?**
- Check PHP error log
- Verify file permissions (755 for folders, 644 for files)

**Can't connect to database?**
- Verify MySQL is running: `sudo service mysql status`
- Check credentials in config.php

**QR codes not working?**
- Ensure good lighting
- Print at least 3cm x 3cm size
- Use high contrast (black on white)

## 📞 Next Steps

1. **Customize Points System**: Edit point values in firmware
2. **Brand It**: Update logo and colors in style.css
3. **Add Users**: Register test users and try the flow
4. **Monitor Bins**: Check real-time status in admin dashboard
5. **Deploy**: Move to production server with HTTPS

## 💡 Tips

- Start with one bin for testing
- Use test products before real deployment
- Keep admin credentials secure
- Regular database backups recommended
- Monitor bin weight to plan collection routes

---

Need help? Check README.md for detailed documentation!
