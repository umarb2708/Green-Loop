# Green Loop - Configuration Checklist

## ⚙️ Pre-Deployment Configuration Guide

Use this checklist to ensure everything is properly configured before deployment.

---

## 📱 Hardware Configuration

### ESP32-CAM Settings

#### WiFi Credentials (via Config Portal)
```
SSID: GreenLoop
Password: Green@123#
Config URL: http://192.168.4.1

To Enter Config Mode:
- Press both buttons simultaneously on startup
- Or wait 15 seconds without WiFi connection
```

#### Required Configuration Values
- [ ] **WiFi SSID**: Your network name
- [ ] **WiFi Password**: Your network password
- [ ] **Server IP**: `http://192.168.1.100` (your web server)
- [ ] **Bin ID**: From admin dashboard (e.g., "1", "2", "3")

#### Hardware Calibration
- [ ] **Load Cell Calibration**
  - Current value in code: `420.0983`
  - Adjust based on your load cell
  - Method: Place known weight, calculate factor
  
- [ ] **Servo Positions**
  - Closed: `150` (SERVO_MIN)
  - Open: `450` (SERVO_MAX)
  - Adjust based on your servo and lid design

- [ ] **IR Sensor Sensitivity**
  - Adjust potentiometer on each sensor
  - HIGH when no plastic, LOW when plastic present
  - Test with sample plastic piece

#### Points Configuration
Current point values (edit in firmware):
- [ ] PET: `10` points
- [ ] HDPE: `20` points
- [ ] PP: `30` points
- [ ] Others: `5` points

---

## 🌐 Web Server Configuration

### Database Setup

#### MySQL Configuration
```bash
# File: Website/config.php
```

- [ ] **DB_HOST**: `localhost` or your MySQL server IP
- [ ] **DB_USER**: MySQL username (default: `root`)
- [ ] **DB_PASS**: MySQL password (default: empty)
- [ ] **DB_NAME**: `greenloop`

#### Create Database
```bash
mysql -u root -p < Website/database.sql
```

Verify:
```sql
USE greenloop;
SHOW TABLES;
-- Should show: users, product_data, bin_data, rewards_data
```

### Default Admin Account

**IMPORTANT: Change after first login!**
```
Username: admin
Password: admin123
```

Steps to change:
1. Login to admin dashboard
2. (Future enhancement: Add password change feature)
3. Or manually update in database:
```sql
UPDATE users SET password = '$2y$10$your_new_hash' WHERE username = 'admin';
```

Generate new hash with PHP:
```php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
```

### File Permissions

#### Linux/Mac
```bash
cd Website/
chmod 755 .
chmod 644 *.php *.css *.sql
chmod 600 config.php  # Extra protection for config
```

#### Windows
- Ensure IIS_IUSRS or IUSR has read permission
- config.php should not be publicly accessible

### Apache Configuration

#### .htaccess Settings
- [ ] Verify `mod_rewrite` is enabled
- [ ] Check `mod_headers` is enabled
- [ ] Test security headers are working

#### Virtual Host (Optional)
```apache
<VirtualHost *:80>
    ServerName greenloop.local
    DocumentRoot /var/www/html/greenloop
    
    <Directory /var/www/html/greenloop>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### PHP Configuration

Required PHP extensions:
- [ ] mysqli
- [ ] session
- [ ] json
- [ ] gd (for future QR generation)

Check with:
```bash
php -m | grep mysqli
php -m | grep session
```

#### php.ini Settings (Optional)
```ini
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
session.gc_maxlifetime = 86400  # 24 hours
```

---

## 🔐 Security Configuration

### Production Settings

#### Enable HTTPS
Uncomment in `.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### SSL Certificate
```bash
# Using Let's Encrypt (free)
sudo certbot --apache -d yourdomain.com
```

#### Disable Debug Mode
In `config.php`:
```php
define('DEBUG_MODE', false);  # Set to false in production!
```

#### Secure Database User
```sql
# Create dedicated user (not root)
CREATE USER 'greenloop_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON greenloop.* TO 'greenloop_user'@'localhost';
FLUSH PRIVILEGES;
```

Update `config.php`:
```php
define('DB_USER', 'greenloop_user');
define('DB_PASS', 'strong_password_here');
```

### Firewall Rules

#### Allow only necessary ports
```bash
# Ubuntu/Debian (ufw)
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

#### Restrict MySQL access
```bash
# Edit /etc/mysql/mysql.conf.d/mysqld.cnf
bind-address = 127.0.0.1  # Only localhost can connect
```

---

## 🧪 Testing Configuration

### Hardware Tests

#### Individual Component Test
```
1. Power Test
   - [ ] All LEDs lit
   - [ ] No burning smell
   - [ ] Voltage at 5V ±0.25V

2. OLED Display
   - [ ] Shows "Green Loop"
   - [ ] Text is clear and readable
   
3. Servo Motors
   - [ ] All 4 servos move smoothly
   - [ ] Lids open and close fully
   - [ ] No jittering
   
4. IR Sensors
   - [ ] Output HIGH normally
   - [ ] Output LOW when blocked
   - [ ] All 4 sensors working
   
5. Load Cell
   - [ ] Reads 0kg when empty
   - [ ] Changes with added weight
   - [ ] Stable readings
   
6. Camera
   - [ ] Can scan QR codes
   - [ ] Focus is clear
   - [ ] Adequate lighting
   
7. Buttons
   - [ ] Start button responsive
   - [ ] Stop button responsive
   - [ ] Enter config mode with both
```

#### System Integration Test
```
1. WiFi Connection
   - [ ] Connects automatically
   - [ ] Shows connected on OLED
   - [ ] Can ping server IP
   
2. API Communication
   - [ ] Can fetch product data
   - [ ] Can upload session data
   - [ ] Server receives requests
   
3. Complete Workflow
   - [ ] Scan QR successfully
   - [ ] Correct bin opens
   - [ ] Weight detected
   - [ ] Points calculated
   - [ ] Data uploaded
   - [ ] Code displayed
```

### Website Tests

#### Frontend Test
```
1. Login Page
   - [ ] Login works (admin)
   - [ ] Login works (user)
   - [ ] Signup creates account
   - [ ] Invalid login shows error
   
2. Admin Dashboard
   - [ ] Add bin works
   - [ ] Bin ID displayed
   - [ ] Add product works
   - [ ] QR code generated
   - [ ] Bin status loads
   - [ ] Chamber status correct
   
3. User Dashboard
   - [ ] Shows current points
   - [ ] Add reward works
   - [ ] Duplicate code rejected
   - [ ] Invalid code rejected
   - [ ] History displays
```

#### Backend/API Test
```bash
# Test product lookup
curl "http://yourserver/api.php?action=get_product&qr_id=ABC1234567"
# Expected: PET

# Test data upload
curl -X POST http://yourserver/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action":"upload_data",
    "points":50,
    "unique_code":"TEST01",
    "bin_weight":10.5,
    "bin_status":"0000",
    "bin_id":"1"
  }'
# Expected: {"success":true,"message":"Data uploaded successfully"}
```

#### Database Test
```sql
# Verify tables exist
USE greenloop;
SHOW TABLES;

# Check default admin
SELECT * FROM users WHERE username = 'admin';

# Check sample products
SELECT * FROM product_data LIMIT 5;

# Verify bins
SELECT * FROM bin_data;

# Check rewards
SELECT * FROM rewards_data;
```

---

## 📊 Performance Monitoring

### Server Monitoring

#### Check Logs
```bash
# Apache access log
tail -f /var/log/apache2/access.log

# Apache error log
tail -f /var/log/apache2/error.log

# MySQL log
tail -f /var/log/mysql/error.log

# PHP error log
tail -f /var/log/php_errors.log
```

#### Monitor Resources
```bash
# CPU and Memory
top
htop

# Disk space
df -h

# MySQL status
mysqladmin status -u root -p
```

### Hardware Monitoring

#### Serial Monitor (Arduino IDE)
- Open Tools > Serial Monitor (115200 baud)
- Check for error messages
- Monitor WiFi connection status
- View QR scan results
- Track API responses

#### ESP32 Stats
```cpp
// Add to loop() for debugging
Serial.print("Free Heap: ");
Serial.println(ESP.getFreeHeap());

Serial.print("WiFi RSSI: ");
Serial.println(WiFi.RSSI());
```

---

## 🔄 Backup Configuration

### Database Backup
```bash
# Daily backup script
mysqldump -u root -p greenloop > backup_$(date +%Y%m%d).sql

# Automated with cron (daily at 2 AM)
0 2 * * * mysqldump -u root -p'password' greenloop > /backups/greenloop_$(date +\%Y\%m\%d).sql
```

### Config File Backup
```bash
cp Website/config.php config.php.backup
cp Firmware/smart_bin.ino smart_bin.ino.backup
```

---

## 📋 Go-Live Checklist

### Final Pre-Launch Checks

- [ ] All hardware components tested individually
- [ ] Complete disposal flow tested end-to-end
- [ ] Database properly configured with strong passwords
- [ ] Admin default password changed
- [ ] Debug mode disabled in production
- [ ] HTTPS enabled (if production)
- [ ] Firewall rules configured
- [ ] Backup system in place
- [ ] Monitoring tools configured
- [ ] Documentation accessible to team
- [ ] User training materials prepared
- [ ] Support contact information available

### Launch Day

1. [ ] Power on hardware
2. [ ] Verify WiFi connection
3. [ ] Test one complete transaction
4. [ ] Monitor logs for errors
5. [ ] Have rollback plan ready
6. [ ] Document any issues immediately

### Post-Launch Monitoring (First Week)

- [ ] Check error logs daily
- [ ] Monitor bin fill levels
- [ ] Verify data integrity
- [ ] Collect user feedback
- [ ] Track system performance
- [ ] Document lessons learned

---

## 📞 Support Contacts

### Emergency Contacts
```
System Administrator: _______________
Database Admin: _______________
Hardware Tech: _______________
Network Support: _______________
```

### Useful Commands Reference
```bash
# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql

# Check service status
sudo systemctl status apache2
sudo systemctl status mysql

# Clear sessions (if needed)
rm /var/lib/php/sessions/sess_*

# Reset ESP32 (hardware)
# Press reset button or power cycle
```

---

**Configuration Complete?** ✅ You're ready to deploy!

For any issues, refer to README.md troubleshooting section.
