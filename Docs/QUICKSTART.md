# Green Loop - Quick Start Guide

## 🚀 Quick Setup (5 Minutes)

### Step 1: Database Setup
```bash
# Login to MySQL
mysql -u root -p

# Run the setup script
source Website/database.sql

# Exit MySQL
exit
```

### Step 2: Configure Website
1. Edit `Website/config.php`
2. Update these lines:
```php
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
```

### Step 3: Test Website
1. Copy Website folder to your web server (e.g., `/var/www/html/greenloop/`)
2. Open browser: `http://localhost/greenloop/`
3. Login with:
   - Username: `admin`
   - Password: `admin123`

### Step 4: Upload Firmware
1. Open Arduino IDE
2. Install ESP32 board support and required libraries
3. Open `Firmware/smart_bin.ino`
4. Select Board: "AI Thinker ESP32-CAM"
5. Click Upload

### Step 5: Configure Hardware
1. Power on ESP32
2. Connect to WiFi: `GreenLoop` / `Green@123#`
3. Open browser to IP shown on OLED
4. Enter:
   - Your WiFi SSID/Password
   - Server IP: `http://192.168.1.100` (your web server)
   - Bin ID: Get from admin dashboard

## 📋 Checklist

Hardware:
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
