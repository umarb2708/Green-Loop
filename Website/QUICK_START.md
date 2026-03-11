# 🚀 Green Loop Website - XAMPP Quick Start Guide

Get the Green Loop website up and running on your local machine in under 10 minutes using XAMPP.

## 📋 Prerequisites

- **XAMPP** (PHP 7.4 or higher, MySQL 5.7 or higher)
  - Download from: https://www.apachefriends.org/
- **Web Browser** (Chrome, Firefox, Edge, etc.)

---

## 🔧 Step 1: Install XAMPP

1. Download XAMPP from the official website
2. Run the installer and install with at least:
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin
3. Install to default location (e.g., `C:\xampp`)
4. Launch XAMPP Control Panel

---

## 📁 Step 2: Copy Website Files

1. Locate your XAMPP installation folder (default: `C:\xampp`)
2. Navigate to `C:\xampp\htdocs\`
3. Create a new folder called `greenloop`
4. Copy all files from the `Website` folder into `C:\xampp\htdocs\greenloop\`

Your folder structure should look like:
```
C:\xampp\htdocs\greenloop\
├── admin_dashboard.php
├── api.php
├── config.example.php
├── config.php
├── database.sql
├── get_bin_status.php
├── index.php
├── logout.php
├── style.css
└── user_dashboard.php
```

---

## 🗄️ Step 3: Setup Database

### Method A: Using phpMyAdmin (Recommended for Beginners)

1. Start **Apache** and **MySQL** services in XAMPP Control Panel
2. Open your browser and go to: `http://localhost/phpmyadmin`
3. Click **"New"** in the left sidebar
4. Create a database named: `greenloop_db`
5. Click on the `greenloop_db` database
6. Click the **"Import"** tab
7. Click **"Choose File"** and select `database.sql` from your `greenloop` folder
8. Click **"Go"** at the bottom to import
9. ✅ You should see tables created successfully!

### Method B: Using MySQL Command Line

1. Open XAMPP Control Panel
2. Click **"Shell"** button
3. Run these commands:
```bash
mysql -u root -p
# Press Enter (no password by default)

# Then run:
source C:/xampp/htdocs/greenloop/database.sql
exit
```

---

## ⚙️ Step 4: Configure Database Connection

1. Navigate to `C:\xampp\htdocs\greenloop\`
2. Find the file `config.example.php`
3. Create a copy or rename it to: `config.php`
4. Open `config.php` in a text editor
5. Update the database credentials:

```php
// Database Configuration
define('DB_HOST', 'localhost');        // Keep as 'localhost'
define('DB_USER', 'root');             // Default XAMPP MySQL user
define('DB_PASS', '');                 // Empty by default (no password)
define('DB_NAME', 'greenloop_db');     // Database name we created

// Site Configuration
define('SITE_URL', 'http://localhost/greenloop');

// Debug Mode
define('DEBUG_MODE', true);  // Set to false in production
```

6. **Save** the file

> ⚠️ **Important**: If you set a MySQL root password during XAMPP setup, update `DB_PASS` accordingly.

---

## 🎉 Step 5: Launch the Website

1. Make sure **Apache** and **MySQL** are running in XAMPP Control Panel (green indicators)
2. Open your web browser
3. Navigate to: `http://localhost/greenloop`
4. You should see the Green Loop login page!

### Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Test User Account:**
- Username: `user1`
- Password: `user123`

---

## 🎯 Step 6: Test the System

1. **Login as Admin:**
   - Go to `http://localhost/greenloop`
   - Login with admin credentials
   - You should see the Admin Dashboard with:
     - Bin management
     - Product management
     - User management
     - Analytics

2. **Test User Portal:**
   - Logout and login as `user1`
   - You should see the User Dashboard with:
     - Rewards balance
     - Recycling history
     - Impact statistics

3. **Test API Endpoint:**
   - Open: `http://localhost/greenloop/api.php?action=getBinStatus&bin_id=1`
   - You should see JSON response (even if no data yet)

---

## 🔍 Troubleshooting

### ❌ "Access forbidden" or 403 Error

**Solution 1: Check .htaccess**
- If there's a `.htaccess` file, temporarily rename it to `.htaccess.bak`
- Try accessing the site again

**Solution 2: Check httpd.conf**
1. Open XAMPP Control Panel
2. Click **Config** next to Apache → **httpd.conf**
3. Find the line: `AllowOverride None`
4. Change to: `AllowOverride All`
5. Save and restart Apache

### ❌ "Database connection error"

**Check these:**
1. MySQL is running in XAMPP Control Panel (green light)
2. Database `greenloop_db` exists in phpMyAdmin
3. `config.php` credentials match your MySQL settings
4. If you set a MySQL password, update `DB_PASS` in config.php

### ❌ Port 80 or 443 already in use

**Solution:**
1. Close Skype, IIS, or other applications using port 80
2. Or change Apache port:
   - XAMPP Control Panel → Apache Config → httpd.conf
   - Find `Listen 80` and change to `Listen 8080`
   - Access site at: `http://localhost:8080/greenloop`

### ❌ PHP errors visible on page

**Solution:**
- This is normal in development with `DEBUG_MODE = true`
- Check the specific error message and fix the issue
- Common issues:
  - Missing `config.php` file
  - Wrong database credentials
  - Database not imported

### ❌ Session errors

**Solution:**
1. Make sure Apache has write permissions to PHP session directory
2. In XAMPP, this is usually automatic
3. Restart Apache service

---

## 📱 Step 7: Connect ESP32 Hardware (Optional)

Once your website is running, you can connect the ESP32-CAM hardware:

1. Get your computer's local IP address:
   ```cmd
   ipconfig
   ```
   Look for "IPv4 Address" (e.g., `192.168.1.100`)

2. In the ESP32 firmware WiFi configuration:
   - Server URL: `http://192.168.1.100/greenloop/api.php`
   - Get Bin ID from the admin dashboard

3. Test the connection by scanning a QR code on the ESP32

---

## 🔐 Security Notes (For Production Deployment)

If deploying to a production server, **DO NOT** skip these:

1. ✅ Change default admin password immediately
2. ✅ Set `DEBUG_MODE = false` in config.php
3. ✅ Set a strong MySQL root password
4. ✅ Use HTTPS (SSL certificate)
5. ✅ Keep XAMPP and PHP updated
6. ✅ Configure proper file permissions
7. ✅ Never commit `config.php` to version control

---

## 📚 Next Steps

- ✅ Read [README.md](../README.md) for complete project documentation
- ✅ Check [CONFIGURATION.md](../CONFIGURATION.md) for advanced settings
- ✅ See [PROJECT_STRUCTURE.md](../PROJECT_STRUCTURE.md) for code organization
- ✅ Upload firmware to ESP32-CAM (see main QUICKSTART.md)

---

## ❓ Need Help?

- Check the main project README for detailed documentation
- Review PHP error logs in `C:\xampp\apache\logs\error.log`
- Check MySQL logs in `C:\xampp\mysql\data\mysql_error.log`

---

## ✅ Success Checklist

- [ ] XAMPP installed with Apache and MySQL
- [ ] Website files in `C:\xampp\htdocs\greenloop\`
- [ ] Database `greenloop_db` created and imported
- [ ] `config.php` configured with correct credentials
- [ ] Apache and MySQL services running (green in XAMPP)
- [ ] Can access `http://localhost/greenloop`
- [ ] Can login with admin/user credentials
- [ ] Admin dashboard loads successfully
- [ ] API endpoint responds to requests

---

**🌱 You're all set! Happy recycling with Green Loop!**
