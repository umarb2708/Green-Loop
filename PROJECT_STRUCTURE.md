# Green Loop - Project Structure

## 📁 Complete Directory Structure

```
Green-Loop/
│
├── Firmware/
│   └── smart_bin.ino              # ESP32 firmware code
│
├── Website/
│   ├── index.php                  # Login/Signup page
│   ├── admin_dashboard.php        # Admin control panel
│   ├── user_dashboard.php         # User rewards portal
│   ├── config.php                 # Database configuration
│   ├── config.example.php         # Config template
│   ├── api.php                    # REST API for hardware
│   ├── get_bin_status.php         # AJAX bin status endpoint
│   ├── logout.php                 # Logout handler
│   ├── style.css                  # Main stylesheet
│   ├── database.sql               # Database schema & seed data
│   └── .htaccess                  # Apache configuration
│
├── README.md                      # Complete documentation
├── QUICKSTART.md                  # 5-minute setup guide
├── LIBRARIES.md                   # Arduino library installation
├── WIRING.md                      # Hardware wiring guide
├── PROJECT_STRUCTURE.md           # This file
└── prompt.txt                     # Original project requirements
```

## 🔍 File Descriptions

### Firmware Files

#### `Firmware/smart_bin.ino`
Main Arduino sketch for ESP32-CAM controller.

**Key Functions:**
- `setup()` - Initialize hardware and connect to WiFi
- `loop()` - Main program loop with status checks
- `Wifi_Setup()` - Connect to WiFi or start config mode
- `Config_Mode()` - Web server for configuration
- `disposal()` - Main disposal workflow
- `scanQRCode()` - Camera-based QR scanning
- `read_bin_status()` - Check sensors and weight
- `uploadData()` - Send data to server API

**Hardware Controls:**
- Camera (QR scanning)
- 4x IR sensors (plastic detection)
- 4x Servos (lid control)
- Load cell (weight measurement)
- OLED display (user feedback)
- 2x Buttons (user input)

---

### Website Files

#### `Website/index.php`
Entry point - Login and new user registration.

**Features:**
- Tab-based UI (Login/Signup)
- Form validation
- Password hashing (bcrypt)
- Session management
- Role-based redirect (admin/user)

**Form Handlers:**
- `$_POST['login']` - Authenticate user
- `$_POST['signup']` - Create new account

---

#### `Website/admin_dashboard.php`
Administrator control panel for system management.

**Sections:**
1. **Actions**
   - Add new bins (generates Bin ID)
   - Add products (generates QR codes)

2. **Bin Monitoring**
   - Location dropdown selector
   - Real-time chamber status display
   - Weight monitoring
   - Fill level indicators

3. **Product Management**
   - Recent products table
   - QR code generation
   - Product details

**Access Control:**
- Requires admin privileges
- `requireAdmin()` check

**Modal Windows:**
- Add Bin Modal
- Add Product Modal
- QR Code Display Modal

---

#### `Website/user_dashboard.php`
User portal for reward management and tracking.

**Features:**
1. **Rewards Display**
   - Total points counter
   - Visual stat cards
   - Environmental impact metrics

2. **Actions**
   - Add reward code (from bin)
   - Claim rewards (placeholder)

3. **Activity History**
   - Recent reward redemptions
   - Date, code, points, location
   - Empty state handling

4. **Impact Calculator**
   - Bottles recycled estimate
   - CO₂ saved calculation
   - Oil saved estimation

**Form Handlers:**
- `$_POST['add_reward']` - Redeem reward code

---

#### `Website/config.php`
Central configuration and database connection.

**Contents:**
- Database credentials (`DB_HOST`, `DB_USER`, etc.)
- `getDBConnection()` - MySQLi connection factory
- Session management
- Authentication helpers:
  - `isLoggedIn()`
  - `isAdmin()`
  - `requireLogin()`
  - `requireAdmin()`

**Security:**
- Not accessible via web (.htaccess protected)
- Contains sensitive credentials
- Should not be in version control

---

#### `Website/api.php`
RESTful API for hardware-server communication.

**Endpoints:**

**POST /api.php**
- Action: `upload_data`
- Params: points, unique_code, bin_weight, bin_status, bin_id
- Response: JSON success/error

**GET /api.php**
- Action: `get_product`
- Params: qr_id
- Response: Plastic type (plain text)

**Functions:**
- `uploadData()` - Store disposal session
- `getProductData()` - Lookup product by QR
- `sendSuccess()` - JSON success response
- `sendError()` - JSON error response

---

#### `Website/get_bin_status.php`
AJAX endpoint for real-time bin status updates.

**Usage:** Called by admin dashboard JavaScript

**Request:** `GET ?bin_id=123`

**Response:**
```json
{
  "success": true,
  "status": "0010",
  "weight": 15.5
}
```

---

#### `Website/style.css`
Complete styling for all web pages.

**Styles Include:**
- Responsive layout (mobile-first)
- Gradient backgrounds
- Modal windows
- Form elements
- Button variants
- Dashboard cards
- Status indicators
- Tables
- Animations

**Color Scheme:**
- Primary: Purple gradient (#667eea → #764ba2)
- Success: Green (#4CAF50)
- Error: Red (#f5576c)
- Info: Blue gradient

---

#### `Website/database.sql`
MySQL database schema and seed data.

**Tables:**

1. **users**
   - User accounts (admin and regular users)
   - Password storage (hashed)
   - Reward points tracking

2. **product_data**
   - Product information
   - QR code mapping
   - Plastic type categorization

3. **bin_data**
   - Bin locations
   - Status tracking (4-digit binary)
   - Weight measurements

4. **rewards_data**
   - Disposal sessions
   - Unique codes
   - Collection status

**Seed Data:**
- Default admin account
- Sample products for testing

---

#### `Website/.htaccess`
Apache web server configuration.

**Features:**
- URL rewriting
- Security headers
- File access restrictions
- GZIP compression
- Browser caching
- SQL injection protection

---

#### `Website/logout.php`
Session termination handler.

**Action:**
1. Destroy session
2. Redirect to login page

---

#### `Website/config.example.php`
Template for config.php with extended features.

**Additional Features:**
- Debug mode toggle
- Timezone configuration
- Input sanitization helper
- Enhanced error handling
- Site URL configuration

---

### Documentation Files

#### `README.md`
Complete project documentation.

**Sections:**
- Project overview
- Feature list
- Hardware requirements
- Software requirements
- Installation guide (detailed)
- Usage guide
- API documentation
- Troubleshooting
- Security notes

---

#### `QUICKSTART.md`
5-minute setup guide for rapid deployment.

**Focus:**
- Essential steps only
- Quick commands
- Testing procedures
- Common quick fixes

---

#### `LIBRARIES.md`
Arduino library installation guide.

**Contents:**
- Library list with URLs
- Installation instructions
- ESP32 board setup
- Upload procedure
- Troubleshooting

---

#### `WIRING.md`
Hardware assembly guide.

**Contents:**
- Pin connection tables
- Wiring diagrams (ASCII art)
- Power requirements
- Assembly tips
- Testing procedures
- Safety notes

---

#### `PROJECT_STRUCTURE.md`
This file - complete project structure documentation.

---

## 🔄 Data Flow Diagram

```
┌─────────────┐
│  Hardware   │
│  (ESP32)    │
└──────┬──────┘
       │ QR Scan → Product Lookup
       │
       ├─GET─→ api.php?action=get_product&qr_id=XXX
       │        └─→ Returns: Plastic Type
       │
       │ Disposal Complete → Upload Session Data
       │
       └─POST→ api.php (action: upload_data)
                ├─→ Insert into rewards_data
                └─→ Update bin_data
                
┌──────────────┐
│  Admin Web   │
└──────┬───────┘
       │
       ├─→ View Bins (bin_data table)
       ├─→ Add Products (QR generation)
       ├─→ Monitor Status (get_bin_status.php)
       └─→ Manage System
       
┌──────────────┐
│   User Web   │
└──────┬───────┘
       │
       ├─→ View Rewards (users.rewards_collected)
       ├─→ Add Code (rewards_data lookup)
       └─→ Track History
```

## 🔐 Security Layers

### Hardware
- WiFi configuration via web portal
- Stored credentials in flash memory
- Secure WiFi (WPA2)

### Website
- Password hashing (bcrypt)
- SQL injection prevention (parameterized queries)
- XSS protection headers
- Session management
- Role-based access control
- Config file protection (.htaccess)

### Database
- User credentials (hashed passwords)
- Separate admin/user roles
- Foreign key constraints
- Timestamp tracking

## 📊 Database Schema Relationships

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
