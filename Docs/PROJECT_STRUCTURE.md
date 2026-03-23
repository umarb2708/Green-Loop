# Green Loop – Project Structure

Complete directory structure and file descriptions for the Green Loop Smart Bin Project.

---

## 📁 Root Directory Structure

```
Green-Loop/
├── README.md                      # Main project documentation
├── Firmware/                      # ESP32 firmware code
├── Website/                       # PHP web application
├── Docs/                          # Documentation files
└── Libraries/                     # Custom libraries (if any)
```

---

## 🔧 Firmware Directory

**Path:** `Firmware/smart_bin_esp32_c3/`

### Main Firmware File

**`smart_bin_v3.ino`** - Main Arduino sketch for ESP32-C3

**Key Functions:**

#### Setup & Initialization
- `setup()` - Main setup function, runs once on boot
  - Initializes serial communication at 115200 baud
  - Calls init_display(), init_hw(), and Wifi_Setup()
  
- `init_display()` - Initialize OLED display
  - Initializes SSD1306 OLED on I2C address 0x3C
  - Displays "Green Loop" startup message
  - Error handling if display fails

- `init_hw()` - Initialize hardware components
  - Initializes PCA9685 servo driver (I2C 0x40)
  - Initializes HX711 load cell amplifier
  - Sets up IR sensor GPIO pins
  - Closes all chamber lids to starting position
  - Displays error messages if initialization fails

- `Wifi_Setup()` - WiFi connection management
  - Loads credentials from flash memory (Preferences library)
  - Attempts WiFi connection with stored credentials
  - If connection fails or no credentials, enters Config_Mode()
  - Displays connection status on OLED

- `Config_Mode()` - WiFi configuration via web interface
  - Sets ESP32 as Access Point (SSID: "GreenLoop")
  - Starts web server on 192.168.4.1
  - Serves HTML configuration form
  - Saves credentials to flash memory  
  - Restarts ESP32 after configuration saved

#### Main Loop Functions
- `loop()` - Main program loop
  - Calls read_bin_status() every 5 seconds
  - Calls read_button_click() continuously
  - Handles config mode server if active

- `read_bin_status()` - Monitor and upload bin status
  - Gets current weight from load cell
  - Checks IR sensors for each chamber
  - Updates bin_full[] array (full if IR triggered + lid closed)
  - Creates JSON data with bin status
  - Uploads to server via update_bin_status.php API

- `read_button_click()` - Handle button presses with debouncing
  - Reads button state (GPIO 10, active LOW)
  - Implements 50ms debounce delay
  - On press: Toggles disposal_started flag
  - If starting: calls disposal()
  - If stopping: calls rewards_earned()

#### Disposal Process
- `disposal()` - Main disposal session handler
  - Resets tot_points to 0
  - Polls server for disposal requests every 500ms
  - For each disposal request:
    - Checks if chamber is full (rejects if full)
    - Opens appropriate chamber lid using openLid()
    - Waits for IR sensor detection (10s timeout)
    - Closes lid using closeLid()
    - Checks for weight change
    - Adds points based on plastic type
    - Confirms disposal to server
  - Continues until button pressed again

- `rewards_earned()` - Generate and display reward code
  - Generates 6-character alphanumeric code
  - Reads final bin status
  - Uploads code + points to server
  - Displays code on OLED for 30 seconds
  - Resets tot_points to 0

#### Hardware Control
- `setServoAngle(servoNum, angle)` - Control servo position
  - Converts angle (0-180°) to PWM pulse width
  - Sends PWM signal via PCA9685

- `openLid(chamber)` - Open chamber lid
  - Sets servo to SERVO_OPEN position (90°)
  - Updates lid_status[chamber] = true
  - Logs action to serial monitor

- `closeLid(chamber)` - Close chamber lid
  - Sets servo to SERVO_CLOSE position (0°)
  - Updates lid_status[chamber] = false
  - Logs action to serial monitor

#### Sensors
- `checkIRSensor(chamber)` - Read IR sensor state
  - Returns true if clear (HIGH)
  - Returns false if object detected (LOW)

- `getWeight()` - Read load cell weight
  - Gets average of 5 readings from HX711
  - Returns weight in kg (or 0 if negative)

- `checkWeightChange()` - Detect weight increase
  - Compares current_weight with previous_weight
  - Returns true if difference > 10g

#### Display & Communication
- `displayMessage(line1, line2, line3, line4)` - Update OLED
  - Clears display
  - Prints up to 4 lines of text
  - Updates display buffer

- `uploadToServer(endpoint, jsonData)` - HTTP POST to API
  - Constructs full URL from server_ip + endpoint
  - Sends JSON data via HTTP POST
  - Returns true if successful (HTTP 200)

- `pollDisposalData(&chamber, &plasticType)` - Check for new disposals
  - Sends GET request to get_disposal_data.php
  - Parses JSON response
  - Maps plastic type to chamber number
  - Returns true if disposal pending

#### Utility
- `getBinStatusString()` - Convert bin status to string
  - Returns 4-digit string (e.g., "1010")
  - 1 = full, 0 = available for each chamber

**Global Variables:**
```cpp
bool disposal_started = false;     // Session active flag
int tot_points = 0;                // Total points this session
bool lid_status[4];                // Lid positions (open/closed)
bool bin_full[4];                  // Chamber full status
float current_weight;              // Current total weight
float previous_weight;             // Weight before disposal
String wifi_ssid;                  // WiFi network name
String wifi_password;               // WiFi password
String server_ip;                  // Server URL
String bin_id;                     // Bin identifier
```

---

## 💻 Website Directory

**Path:** `Website/smart_bin_qr_v3/`

### Core PHP Files

#### **`config.php`** - Configuration & Database Connection
**Purpose:** Central configuration file, database connection, utility functions

**Configuration Constants:**
- Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- Session lifetime (SESSION_LIFETIME = 3600s)
- Reward points (POINTS_PET=10, POINTS_HDPE=20, POINTS_PP=30, POINTS_OTHERS=5)
- QR code settings (QR_CODE_LENGTH = 10, REWARD_CODE_LENGTH = 6)

**Key Functions:**
- `isLoggedIn()` - Check if user is authenticated
- `isAdmin()` - Check if user is admin (is_admin = 1)
- `isManufacturer()` - Check if user is manufacturer (is_admin = 2)
- `isUser()` - Check if user is regular user (is_admin = 0)
- `requireLogin()` - Force redirect to login if not authenticated
- `requireAdmin()` - Force admin access only
- `sanitize($data)` - Clean user input (trim, strip tags, htmlspecialchars)
- `generateQRCode()` - Generate unique 10-char QR ID
- `generateRewardCode()` - Generate unique 6-char reward code
- `getPoints($type)` - Get points for plastic type
- `getUserById($userId)` - Fetch user record
- `getBinById($binId)` - Fetch bin record
- `getProductByQR($qrId)` - Fetch product by QR code
- `logActivity($userId, $action, $description)` - Log user actions
- `isChamberAvailable($binStatus, $chamberIndex)` - Check if chamber not full
- `getChamberIndex($type)` - Map plastic type to chamber (0-3)
- `sendJSON($data)` - Send JSON HTTP response
- `sendSuccess($message, $data)` - Send success JSON response
- `sendError($message, $code)` - Send error JSON response
- `checkSessionTimeout()` - Auto-logout after inactivity

---

#### **`index.php`** - Login & Signup Page
**Purpose:** User authentication and registration

**Features:**
- Dual-tab interface (Login / Signup)
- Session validation (redirects authenticated users)
- Password hashing (password_hash with bcrypt)
- Username uniqueness validation
- Automatic role-based routing after login
  - Admin (is_admin=1) → admin_dashboard.php
  - Manufacturer (is_admin=2) → manufacturer_dashboard.php
  - User (is_admin=0) → user_dashboard.php

**POST Handlers:**
- `$_POST['login']` - Authenticate user, create session
- `$_POST['signup']` - Create new user account

---

#### **`admin_dashboard.php`** - Administrator Interface
**Purpose:** Bin and product management for administrators

**Access:** Requires admin role (requireAdmin())

**Features:**
- **Statistics Dashboard:**
  - Total bins, products, users, disposals
  
- **Add New Bin:**
  - POST handler: `$_POST['add_bin']`
  - Inserts new bin with location
  - Returns Bin ID for ESP32 configuration
  
- **Add Product:**
  - POST handler: `$_POST['add_product']`
  - Generates 10-char QR ID
  - Creates product entry
  - Displays QR code for download
  
- **Bin Status Monitor:**
  - Select bin from dropdown
  - Real-time chamber status (Available/Full)
  - Current weight display
  
- **Product Listing:**
  - Recent 20 products
  - View/download QR codes

**JavaScript Functions:**
- `openModal(modalId)` - Show modal dialog
- `closeModal(modalId)` - Hide modal dialog
- `updateBinDisplay()` - Update bin status display
- `showQR(qrId)` - Generate and display QR code
- `downloadQR()` - Download QR code as PNG

---

#### **`user_dashboard.php`** - User Interface
**Purpose:** Disposal session management and reward collection

**Access:** Requires user role (requireUser())

**Features:**
- **Reward Points Display:**
  - Shows total rewards_collected
  
- **Collect Reward:**
  - POST handler: `$_POST['collect_reward']`
  - Validates 6-char reward code
  - Checks if already collected
  - Adds points to user account
  - Updates rewards_data table
  
- **Start Disposal:**
  - Multi-step modal process:
    1. Select bin location
    2. Scan QR code with camera
    3. Submit disposal request
  - Uses html5-qrcode library for scanning
  
- **Recent Rewards Table:**
  - Last 10 collected rewards
  - Code, points, location, date
  
- **Leaderboard:**
  - Top 10 users by points
  - Highlights current user

**JavaScript Functions:**
- `startScanning()` - Initialize QR scanner
- `openScanner()` - Start camera for QR scanning
- `closeScanner()` - Stop camera
- `onScanSuccess(qrCode)` - Handle successful QR scan
  - POSTs to api/add_disposal.php
  - Displays result message

---

#### **`manufacturer_dashboard.php`** - Manufacturer Interface
**Purpose:** Product registration for manufacturers

**Access:** Requires manufacturer role (requireManufacturer())

**Features:**
- **Product Statistics:**
  - Total products, breakdown by plastic type
  
- **Add Product:**
  - POST handler: `$_POST['add_product']`
  - Manufacturer name auto-filled from logged-in user
  - Select plastic type
  - Generates QR code
  
- **My Products Table:**
  - All products created by this manufacturer
  - View/download QR codes

**JavaScript Functions:**
- Same QR code generation/download as admin_dashboard

---

### API Directory

**Path:** `Website/smart_bin_qr_v3/api/`

All API files return JSON responses.

#### **`update_bin_status.php`** - Hardware Status Update
**Purpose:** ESP32 updates bin chamber status and weight

**Method:** POST  
**Input JSON:**
```json
{
  "bin_id": 1,
  "weight": 12.5,
  "bin_status": "0101"
}
```
**Response:**
```json
{
  "success": true,
  "message": "Bin status updated successfully",
  "data": {...}
}
```

**Actions:**
- Validates bin_id exists
- Updates bin_data table
- Logs to bin_history table

---

#### **`get_disposal_data.php`** - Poll Disposal Requests
**Purpose:** ESP32 polls for pending disposal requests

**Method:** GET  
**Params:** `?bin_id=1`

**Response (if disposal pending):**
```json
{
  "success": true,
  "message": "Disposal request found",
  "data": {
    "id": 5,
    "type": "PET",
    "qr_id": "ABC123XYZ0",
    "manufacturer": "Coca Cola"
  }
}
```

**Response (no disposal):**
```json
{
  "success": false,
  "message": "No pending disposals"
}
```

---

#### **`confirm_disposal.php`** - Confirm Disposal Success
**Purpose:** ESP32 confirms disposal was successful

**Method:** POST  
**Input JSON:**
```json
{
  "chamber": 0,
  "confirmed": 1
}
```

**Actions:**
- Maps chamber number to plastic type
- Updates disposal_data.confirmed = 1
- Sets confirmed_at timestamp

---

#### **`upload_rewards.php`** - Upload Reward Code
**Purpose:** ESP32 uploads generated reward code

**Method:** POST  
**Input JSON:**
```json
{
  "unique_code": "ABC123",
  "points": 50,
  "bin_id": 1
}
```

**Actions:**
- Validates unique_code doesn't exist
- Inserts into rewards_data table
- Sets collected = 0 (not yet collected)

---

#### **`add_disposal.php`** - Add Disposal from QR Scan
**Purpose:** User app submits disposal request after QR scan

**Method:** POST  
**Input JSON:**
```json
{
  "qr_id": "ABC123XYZ0",
  "bin_id": 1
}
```

**Actions:**
- Validates QR code exists in product_data
- Checks bin exists
- Checks chamber availability
- Inserts into disposal_data with confirmed=0
- Logs user activity

**Response:**
```json
{
  "success": true,
  "message": "Disposal request added. Please dispose in PET chamber.",
  "data": {
    "type": "PET",
    "manufacturer": "...",
    "location": "..."
  }
}
```

---

### Database Directory

**Path:** `Website/smart_bin_qr_v3/database/`

#### **`database.sql`** - Database Schema
**Purpose:** Complete MySQL database structure

**Tables Created:**
1. **users** - User accounts
   - Columns: id, name, username, password, is_admin, rewards_collected
   - Indexes: username, is_admin
   
2. **product_data** - Products with QR codes
   - Columns: id, qr_id, manufacturer, type, created_at, created_by
   - Indexes: qr_id (unique), type, manufacturer
   
3. **bin_data** - Smart bin locations
   - Columns: id, location, current_status, weight, last_updated
   - Indexes: location
   
4. **rewards_data** - Generated reward codes
   - Columns: id, unique_code, points, bin_id, collected, collected_by, collected_at
   - Indexes: unique_code (unique), collected, bin_id
   
5. **disposal_data** - Disposal requests
   - Columns: id, bin_id, type, qr_id, user_id, confirmed, created_at, confirmed_at
   - Indexes: bin_id+confirmed, created_at
   
6. **bin_history** - Historical bin status
   - Columns: id, bin_id, status, weight, recorded_at
   
7. **activity_log** - User activity tracking
   - Columns: id, user_id, action, description, ip_address, created_at

**Views Created:**
- `active_disposal_requests` - Pending disposals
- `available_rewards` - Uncollected rewards
- `bin_status_summary` - Bin status overview
- `user_leaderboard` - Top users by points

---

#### **`sample.php`** - Sample Data Generator
**Purpose:** Populate database with test data

**Creates:**
- 7 users (1 admin, 2 manufacturers, 4 regular users)
- 5 bins at different locations
- 15 products from various manufacturers
- 5 sample reward codes (some collected)
- 5 disposal entries

**Usage:** Navigate to in browser to run

---

### Style Directory

**Path:** `Website/smart_bin_qr_v3/style/`

#### **`main.css`** - Global Stylesheet
**Purpose:** Common CSS for all pages

**CSS Variables Defined:**
- Colors: primary, secondary, accent, danger, success, warning
- Typography: font families, sizes
- Spacing: margins, paddings

**Classes Provided:**
- Buttons: .btn, .btn-primary, .btn-secondary, .btn-accent
- Forms: .form-group, input/select styling
- Cards: .card, .card-header, .card-title
- Tables: .table with hover effects
- Alerts: .alert-success, .alert-error, .alert-warning, .alert-info
- Badges: .badge with color variants
- Modals: .modal, .modal-content
- Grid: .grid, .grid-2, .grid-3, .grid-4

---

### Tools Directory

**Path:** `Website/smart_bin_qr_v3/tools/`

#### **`install_cloudflared.ps1`** - Install Cloudflare Tunnel
**Purpose:** Download and install cloudflared on Windows

**Actions:**
- Checks for admin privileges
- Downloads latest cloudflared from GitHub
- Installs to C:\Program Files\cloudflared\
- Adds to system PATH
- Verifies installation

**Usage:** Run as Administrator in PowerShell

---

#### **`start_https_tunnel.ps1`** - Start HTTPS Tunnel
**Purpose:** Start cloudflared tunnel for local server

**Configuration:**
- Default local port: 80
- Creates public HTTPS URL
- Displays tunnel URL in console

**Usage:** Run in PowerShell (leave running)

---

#### **`register_startup_task.ps1`** - Auto-start on Boot
**Purpose:** Create Windows Task Scheduler entry

**Actions:**
- Creates scheduled task "GreenLoopTunnel"
- Triggers at system startup
- Runs start_https_tunnel.ps1 automatically
- Runs as SYSTEM account

**Usage:** Run as Administrator once to register

---

## 📚 Documentation Directory

**Path:** `Docs/`

### Documentation Files

#### **`QUICKSTART.md`** - Quick Setup Guide
Complete step-by-step setup instructions:
- Database setup
- Website configuration
- HTTPS tunnel setup
- Firmware upload
- Hardware configuration
- Testing procedures

#### **`WIRING_NOQR.md`** - Hardware Wiring Diagrams
ESP32-C3 Super Mini wiring guide:
- Component list
- Pin assignments
- Power requirements
- Assembly tips
- Troubleshooting

#### **`PROJECT_STRUCTURE.md`** - This File
Complete project structure and function documentation

#### **`CONFIGURATION.md`** - Configuration Reference
Detailed configuration options and customization

---

## 🎯 Key Workflows

### Disposal Workflow
```
User scans QR → add_disposal.php → disposal_data INSERT
                                          ↓
ESP32 polls → get_disposal_data.php → Returns type & QR
                                          ↓
ESP32 opens lid → IR detects → Weight changes
                                          ↓
ESP32 confirms → confirm_disposal.php → disposal_data UPDATE
                                          ↓
Session ends → generate code → upload_rewards.php
                                          ↓
User enters code → collect_reward → users.rewards_collected UPDATE
```

### Data Flow
```
Hardware (ESP32)
  ↕ (JSON over HTTP)
API Endpoints (PHP)
  ↕ (SQL Queries)
Database (MySQL)
  ↕ (PHP Queries)
Website (PHP/HTML/JS)
  ↕ (User Interaction)
Users (Browser/Mobile)
```

---

## 📝 Coding Conventions

### PHP
- Use `require_once` for includes
- Sanitize all user input
- Prepared statements for SQL
- JSON responses for APIs
- Session security checks on all pages

### Arduino
- CamelCase for functions
- snake_case for variables
- Clear comments for complex logic
- Error handling with serial output
- Modular function design

### JavaScript
- camelCase for variables and functions
- Async/await for API calls
- Event listeners for user interaction
- Clear error messages

---

**This structure provides a complete roadmap for understanding, maintaining, and extending the Green Loop system.**

```
users
├─ id (PK)
└─ rewards_collected (total points)

product_data
├─ id (PK)
├─ qr_id (UNIQUE) ← Scanned by hardware
└─ type (PET/HDPE/PP/Others)

bin_data
├─ id (PK) ← Used as bin_id in hardware
├─ location
├─ current_status (0000-1111)
└─ weight

rewards_data
├─ id (PK)
├─ unique_code (UNIQUE) ← Shown on OLED
├─ points
├─ collected (0/1)
└─ bin_id (FK → bin_data.id)
```

## 🚀 Deployment Checklist

### Development Setup
- [ ] Install Arduino IDE + libraries
- [ ] Setup MySQL database
- [ ] Configure web server (Apache/Nginx)
- [ ] Upload firmware to ESP32
- [ ] Deploy website files
- [ ] Test with sample data

### Production Setup
- [ ] Change default passwords
- [ ] Enable HTTPS (SSL certificate)
- [ ] Set DEBUG_MODE = false
- [ ] Configure firewall rules
- [ ] Setup database backups
- [ ] Monitor system logs
- [ ] Test from external network

### Hardware Deployment
- [ ] Wire all components
- [ ] Test each subsystem
- [ ] Configure WiFi credentials
- [ ] Assign bin ID
- [ ] Calibrate load cell
- [ ] Mount in enclosure
- [ ] Test complete flow

## 🎯 Key Integration Points

1. **QR Scanning → Product Lookup**
   - Hardware scans QR
   - Calls API: `get_product&qr_id=XXX`
   - Receives plastic type
   - Opens correct chamber

2. **Weight Confirmation → Point Calculation**
   - Hardware detects weight change
   - Calculates points based on type
   - Accumulates during session

3. **Session Complete → Data Upload**
   - Generates unique 6-char code
   - Calls API: `upload_data`
   - Updates rewards_data and bin_data
   - Displays code on OLED

4. **User Claims Reward**
   - Logs into web portal
   - Enters unique code
   - System validates code
   - Adds points to user account
   - Marks reward as collected

## 💡 Customization Points

### Easy Customizations
- Points per plastic type (firmware constants)
- Colors and styling (style.css)
- Button labels and text
- OLED messages
- Email/name fields

### Moderate Customizations
- Add more plastic types
- Change chamber count
- Add email notifications
- Integration with payment systems
- Mobile app development

### Advanced Customizations
- AI-based plastic detection
- Cloud-based analytics
- Multiple location support
- IoT platform integration
- Real-time alerts system

---

**Need to modify something?** Use this guide to find the right file!
