# Green Loop - Hardware Wiring Guide

## 📐 Complete Wiring Diagram

### Power Distribution
```
5V Power Supply (3A minimum)
├── ESP32-CAM (5V, GND)
├── PCA9685 (VCC, GND)
├── HX711 (VCC, GND)
├── OLED Display (VCC, GND)
└── 4x Servo Motors (via PCA9685)
```

## 🔌 Detailed Pin Connections

### ESP32-CAM Connections

#### GPIO Pins
| ESP32 GPIO | Connection | Component | Notes |
|------------|------------|-----------|-------|
| GPIO 13 | Input | IR Sensor 1 (PET) | Pull-up enabled |
| GPIO 12 | Input | IR Sensor 2 (HDPE) | Pull-up enabled |
| GPIO 14 | Input | IR Sensor 3 (PP) | Pull-up enabled |
| GPIO 15 | Input | IR Sensor 4 (Others) | Pull-up enabled |
| GPIO 4 | Input | Button 1 (Start) | Pull-up enabled |
| GPIO 2 | Input | Button 2 (Stop) | Pull-up enabled |
| GPIO 16 | Input | HX711 DOUT | Digital data |
| GPIO 17 | Output | HX711 SCK | Clock signal |
| GPIO 21 | I2C | SDA | Shared bus |
| GPIO 22 | I2C | SCL | Shared bus |

#### I2C Bus (Shared)
```
ESP32 GPIO21 (SDA) ─────┬──── PCA9685 SDA
                        └──── OLED SDA

ESP32 GPIO22 (SCL) ─────┬──── PCA9685 SCL
                        └──── OLED SCL
```

### IR Sensors (x4)

Each IR sensor:
```
VCC  ───→ 5V
GND  ───→ GND
OUT  ───→ ESP32 GPIO (13, 12, 14, or 15)
```

**Configuration:**
- IR Sensor 1 (PET chamber) → GPIO 13
- IR Sensor 2 (HDPE chamber) → GPIO 12
- IR Sensor 3 (PP chamber) → GPIO 14
- IR Sensor 4 (Others chamber) → GPIO 15

**Mounting:**
- Position inside chamber near lid
- Adjust sensitivity to detect plastic entry
- Output HIGH when no object, LOW when detected

### Servo Motors (x4) via PCA9685

PCA9685 Driver board:
```
VCC ───→ 5V
GND ───→ GND
SDA ───→ ESP32 GPIO21
SCL ───→ ESP32 GPIO22
V+  ───→ 5V (Servo power - use separate power if needed)
```

Servo connections to PCA9685:
```
Servo 0 (PET lid) ───→ PCA9685 Channel 0
Servo 1 (HDPE lid) ──→ PCA9685 Channel 1
Servo 2 (PP lid) ────→ PCA9685 Channel 2
Servo 3 (Others lid) → PCA9685 Channel 3
```

**Notes:**
- Each servo: Brown(GND), Red(VCC), Orange(Signal)
- If servos draw too much current, use separate 5V power supply
- Connect external power GND to ESP32 GND

### Load Cell + HX711

Load Cell (4-wire):
```
E+ (Excitation+) ─── Red ───→ HX711 E+
E- (Excitation-) ─── Black ─→ HX711 E-
S+ (Signal+) ────── White ──→ HX711 A+
S- (Signal-) ────── Green ──→ HX711 A-
```

HX711 Module:
```
VCC  ───→ 5V
GND  ───→ GND
DT   ───→ ESP32 GPIO16
SCK  ───→ ESP32 GPIO17
```

**Mounting:**
- Load cell under bin base
- Ensure no mechanical stress
- Keep wires away from noise sources

### OLED Display (I2C)

128x64 OLED (SSD1306):
```
VCC ───→ 3.3V or 5V (check your module)
GND ───→ GND
SDA ───→ ESP32 GPIO21 (shared with PCA9685)
SCL ───→ ESP32 GPIO22 (shared with PCA9685)
```

**I2C Address:** 0x3C (default for most SSD1306)

### Push Buttons

#### Button 1 (Start/Continue)
```
One terminal ─→ ESP32 GPIO4
Other terminal ─→ GND
(Internal pull-up resistor enabled in code)
```

#### Button 2 (Stop/Done)
```
One terminal ─→ ESP32 GPIO2
Other terminal ─→ GND
(Internal pull-up resistor enabled in code)
```

**Note:** Buttons connect to GND when pressed (active LOW)

## 🔋 Power Requirements

### Current Draw Estimates
| Component | Current | Notes |
|-----------|---------|-------|
| ESP32-CAM | 500mA | Peak with WiFi + Camera |
| PCA9685 | 10mA | Controller only |
| 4x SG90 Servos | 1600mA | 400mA each (peak) |
| HX711 | 10mA | Negligible |
| OLED Display | 20mA | Typical |
| IR Sensors (x4) | 100mA | 25mA each |
| **TOTAL** | **~2.5A** | **Use 3A supply minimum** |

### Power Supply Recommendations
- **5V 3A** power adapter (minimum)
- **5V 5A** recommended for safety margin
- Use quality power supply with stable voltage
- Add 1000µF capacitor near power input for stability

## 🛠️ Assembly Tips

### 1. Start with Power Bus
```
Create a power distribution board:
5V Rail ─┬─ ESP32-CAM
         ├─ PCA9685
         ├─ HX711
         ├─ OLED
         └─ IR Sensors

GND Rail ─┬─ All component grounds
          ├─ Button returns
          └─ Power supply GND
```

### 2. I2C Bus Wiring
- Use twisted pair or shielded cable
- Keep wires short (<50cm if possible)
- Add pull-up resistors (4.7kΩ) if having issues
- Connect: SDA line and SCL line to both OLED and PCA9685

### 3. Sensor Placement
```
Chamber Layout:
┌─────────────────────┐
│  [Servo] ← Lid      │
│  [IR Sensor]        │
│     ↓ Entry         │
│  Chamber Space      │
└─────────────────────┘
        ↓
    Load Cell
```

### 4. Cable Management
- Label all wires
- Use different colors for power, ground, signal
- Keep high-current wires (servos) separate from signal wires
- Use cable ties for organization

## 🧪 Testing Checklist

### Power Up Test
- [ ] 5V stable on all VCC pins
- [ ] No excessive heating
- [ ] LED indicators functioning

### I2C Device Test
```arduino
// I2C Scanner sketch
Wire.begin(21, 22);
Wire.beginTransmission(0x3C); // OLED
Wire.beginTransmission(0x40); // PCA9685
```

### Individual Component Tests
- [ ] OLED displays text
- [ ] Each servo moves when commanded
- [ ] IR sensors change state with obstruction
- [ ] Load cell reads weight changes
- [ ] Buttons register presses
- [ ] Camera captures images

## ⚠️ Safety Notes

1. **Check polarity** before powering on
2. **Never hotplug** I2C devices
3. **Protect against shorts** with proper insulation
4. **Use proper wire gauge** for current levels
5. **Secure all connections** to prevent loosening

## 🔧 Troubleshooting

### Display Not Working
- Check I2C address (use scanner sketch)
- Verify SDA/SCL not swapped
- Check power supply voltage

### Servos Jittering
- Insufficient power supply current
- Add larger capacitor (1000-2200µF)
- Use separate power for servos

### IR Sensors Always Triggered
- Adjust sensitivity pot on sensor
- Check proper mounting position
- Verify correct GPIO pin in code

### Load Cell Readings Unstable
- Check mechanical mounting
- Verify wire connections
- Shield from electrical noise
- Recalibrate in code

---

## 📸 Connection Summary Diagram

```
                    ESP32-CAM
                   ┌─────────┐
        5V ────────┤ 5V      │
       GND ────────┤ GND     │
                   │         │
    Button1 ───────┤ GPIO4   │
    Button2 ───────┤ GPIO2   │
                   │         │
      IR1 ─────────┤ GPIO13  │
      IR2 ─────────┤ GPIO12  │
      IR3 ─────────┤ GPIO14  │
      IR4 ─────────┤ GPIO15  │
                   │         │
 HX711_DT ─────────┤ GPIO16  │
 HX711_SCK ────────┤ GPIO17  │
                   │         │
   I2C_SDA ────────┤ GPIO21  ├──┬── PCA9685 SDA
                   │         │  └── OLED SDA
   I2C_SCL ────────┤ GPIO22  ├──┬── PCA9685 SCL
                   └─────────┘  └── OLED SCL

         PCA9685                    HX711
        ┌────────┐               ┌────────┐
        │ Chan 0 ├── Servo 1     │ E+  ←──┤ Load Cell
        │ Chan 1 ├── Servo 2     │ E-  ←──┤
        │ Chan 2 ├── Servo 3     │ A+  ←──┤
        │ Chan 3 ├── Servo 4     │ A-  ←──┤
        └────────┘               └────────┘
```

---

**Ready to wire?** Follow this guide step-by-step and test each component!
