# Green Loop – Hardware Wiring Diagram
## ESP32-C3 Super Mini Version

Firmware: `smart_bin_v3.ino`

This document provides complete wiring instructions for the Green Loop smart bin using the **ESP32-C3 Super Mini** microcontroller.

---

## 🔌 Component List

| # | Component | Model / Notes | Quantity |
|---|-----------|---------------|----------|
| 1 | Microcontroller | **ESP32-C3 Super Mini** | 1 |
| 2 | OLED Display | SSD1306 128×64 – I²C (0x3C) | 1 |
| 3 | Servo Driver | PCA9685 16-channel PWM – I²C (0x40) | 1 |
| 4 | Servo Motors | SG90 (5V, 180°) | 4 |
| 5 | Load Cell | 20KG strain-gauge load cell | 1 |
| 6 | HX711 ADC | Load cell amplifier module | 1 |
| 7 | IR Obstacle Sensors | Active-LOW digital output | 4 |
| 8 | Push Button | Momentary tactile button | 1 |
| 9 | Power Supply | 5V / 3-5A DC adapter | 1 |
| 10 | Breadboard/PCB | For connections | 1 |

---

## 📐 ESP32-C3 Super Mini Pinout Reference

```
ESP32-C3 Super Mini
┌─────────────────────────┐
│                         │
│  USB-C Port             │
│                         │
├─────────────────────────┤
│ 3V3  GND  GPIO0  GPIO1  │  ← Top Row
│ GPIO2 GPIO3 GPIO4 GPIO5 │
│ GPIO6 GPIO7 GPIO8 GPIO9 │
│ GPIO10 5V   GND   3V3   │  ← Bottom Row
└─────────────────────────┘

Key Pins:
- GPIO8 & GPIO9: I2C (SDA & SCL)
- GPIO2-5: IR Sensors
- GPIO6: HX711 DOUT
- GPIO7: HX711 SCK
- GPIO10: Button
- 5V & GND: Power distribution
```

---

## 🔧 Detailed Wiring Instructions

### 1. Power Distribution

**Important:** The ESP32-C3 runs on 3.3V internally but can accept 5V input through its voltage regulator.

| ESP32-C3 Pin | Connect To | Notes |
|--------------|------------|-------|
| 5V | Power supply (+5V) AND PCA9685 VCC | Powers ESP32 and servo driver |
| GND | Power supply (GND) | Common ground for all components |
| 3V3 | OLED VCC | 3.3V output for OLED |

**Power Rail Setup:**
```
Power Supply (5V 3A)
  ├─► ESP32-C3 (5V pin)
  ├─► PCA9685 (VCC)
  └─► PCA9685 (V+ terminal) for servos

All GND pins connected together
```

---

### 2. I2C Bus (SDA & SCL)

The ESP32-C3 I2C pins connect multiple devices on the same bus.

| ESP32-C3 Pin | I2C Function | Connect To |
|--------------|--------------|------------|
| GPIO 8 | SDA (Data) | OLED SDA + PCA9685 SDA |
| GPIO 9 | SCL (Clock) | OLED SCL + PCA9685 SCL |

**I2C Wiring Diagram:**
```
ESP32-C3
  GPIO 8 (SDA) ──┬──► OLED SDA
                 └──► PCA9685 SDA
                 
  GPIO 9 (SCL) ──┬──► OLED SCL
                 └──► PCA9685 SCL
```

**I2C Addresses:**
- OLED SSD1306: `0x3C` (default)
- PCA9685: `0x40` (default)

---

### 3. OLED Display (SSD1306 128x64)

| OLED Pin | ESP32-C3 Pin | Wire Color (Typical) |
|----------|--------------|---------------------|
| VCC | 3V3 | Red |
| GND | GND | Black |
| SDA | GPIO 8 | Blue/Green |
| SCL | GPIO 9 | Yellow/White |

**Connection:**
```
OLED Display
  VCC ──────► ESP32-C3 3V3
  GND ──────► ESP32-C3 GND
  SDA ──────► ESP32-C3 GPIO 8
  SCL ──────► ESP32-C3 GPIO 9
```

---

### 4. PCA9685 Servo Driver

The PCA9685 controls all 4 servos via I2C.

| PCA9685 Pin | ESP32-C3 Pin | Notes |
|-------------|--------------|-------|
| VCC | 5V | Logic power |
| GND | GND | Common ground |
| SDA | GPIO 8 | I2C data |
| SCL | GPIO 9 | I2C clock |
| V+ | 5V Power Supply | Servo power (high current) |
| GND (V+ terminal) | Power Supply GND | Servo ground |

**Servo Connections on PCA9685:**

| Servo Channel | Controls | Chamber |
|---------------|----------|---------|
| Channel 0 | Servo 1 | PET |
| Channel 1 | Servo 2 | HDPE |
| Channel 2 | Servo 3 | PP |
| Channel 3 | Servo 4 | Others |

**Each Servo (SG90) has 3 wires:**
- Brown/Black: GND
- Red: VCC (5V from PCA9685)
- Orange/Yellow: Signal (from PCA9685 channel)

```
PCA9685
  VCC ──────────► ESP32-C3 5V
  GND ──────────► ESP32-C3 GND
  SDA ──────────► ESP32-C3 GPIO 8
  SCL ──────────► ESP32-C3 GPIO 9
  V+ ───────────► 5V Power Supply (+)
  GND (V+) ─────► Power Supply (-)
  
  PWM 0-3 ──────► Servos 1-4 (signal wires)
```

---

### 5. HX711 Load Cell Amplifier

The HX711 amplifies and digitizes the load cell signal.

| HX711 Pin | ESP32-C3 Pin | Notes |
|-----------|--------------|-------|
| VCC | 3V3 | Power (3.3V or 5V) |
| GND | GND | Ground |
| DT (DOUT) | GPIO 6 | Data output |
| SCK | GPIO 7 | Clock input |
| E+ | Load Cell Red | Excitation + |
| E- | Load Cell Black | Excitation - |
| A+ | Load Cell White | Signal + |
| A- | Load Cell Green | Signal - |

**Load Cell Color Code (Standard):**
- Red: E+ (Excitation+)
- Black: E- (Excitation-)
- White: A+ (Signal+)
- Green: A- (Signal-)

```
HX711
  VCC ──────────► ESP32-C3 3V3 or 5V
  GND ──────────► ESP32-C3 GND
  DT  ──────────► ESP32-C3 GPIO 6
  SCK ──────────► ESP32-C3 GPIO 7
  
  E+ ───────────► Load Cell Red
  E- ───────────► Load Cell Black
  A+ ───────────► Load Cell White
  A- ───────────► Load Cell Green
```

---

### 6. IR Obstacle Sensors (×4)

Each IR sensor detects plastic entry into its chamber.

**Sensor Pinout:**
- VCC: 3.3V to 5V
- GND: Ground
- OUT: Digital output (LOW when object detected)

| Sensor | ESP32-C3 GPIO | Chamber | Wire Color |
|--------|---------------|---------|------------|
| IR 1 | GPIO 2 | PET | Yellow |
| IR 2 | GPIO 3 | HDPE | Green |
| IR 3 | GPIO 4 | PP | Blue |
| IR 4 | GPIO 5 | Others | White |

**Each IR Sensor:**
```
IR Sensor 1 (PET)
  VCC ──────────► ESP32-C3 3V3
  GND ──────────► ESP32-C3 GND
  OUT ──────────► ESP32-C3 GPIO 2

IR Sensor 2 (HDPE)
  VCC ──────────► ESP32-C3 3V3
  GND ──────────► ESP32-C3 GND
  OUT ──────────► ESP32-C3 GPIO 3

IR Sensor 3 (PP)
  VCC ──────────► ESP32-C3 3V3
  GND ──────────► ESP32-C3 GND
  OUT ──────────► ESP32-C3 GPIO 4

IR Sensor 4 (Others)
  VCC ──────────► ESP32-C3 3V3
  GND ──────────► ESP32-C3 GND
  OUT ──────────► ESP32-C3 GPIO 5
```

---

### 7. Push Button

Single button for Start/Stop disposal sessions.

| Button Terminal | ESP32-C3 Pin | Notes |
|-----------------|--------------|-------|
| Terminal 1 | GPIO 10 | Input with internal pull-up |
| Terminal 2 | GND | Ground |

```
Push Button
  Terminal 1 ────► ESP32-C3 GPIO 10
  Terminal 2 ────► ESP32-C3 GND
  
Note: Internal pull-up enabled in code
Button press pulls GPIO 10 to GND (LOW)
```

---

## 📊 Complete Pin Assignment Table

| ESP32-C3 Pin | Function | Connected To |
|--------------|----------|--------------|
| GPIO 2 | IR Sensor 1 | PET chamber sensor |
| GPIO 3 | IR Sensor 2 | HDPE chamber sensor |
| GPIO 4 | IR Sensor 3 | PP chamber sensor |
| GPIO 5 | IR Sensor 4 | Others chamber sensor |
| GPIO 6 | HX711 DOUT | Load cell data |
| GPIO 7 | HX711 SCK | Load cell clock |
| GPIO 8 | I2C SDA | OLED + PCA9685 data |
| GPIO 9 | I2C SCL | OLED + PCA9685 clock |
| GPIO 10 | Button Input | Start/Stop button |
| 3V3 | Power Out | OLED + HX711 + IR Sensors |
| 5V | Power In | From power supply |
| GND | Ground | Common ground all components |

---

## 🔋 Power Requirements

**Total Current Calculation:**

| Component | Current Draw | Quantity | Total |
|-----------|--------------|----------|-------|
| ESP32-C3 | ~100mA | 1 | 100mA |
| OLED | ~20mA | 1 | 20mA |
| PCA9685 | ~10mA | 1 | 10mA |
| IR Sensors | ~20mA each | 4 | 80mA |
| HX711 | ~1.5mA | 1 | 1.5mA |
| SG90 Servos | ~100-500mA each | 4 | 400-2000mA |

**Total: ~600mA to 2.2A** (depending on servo load)

**Recommended Power Supply:** 5V / 3A minimum

---

## 🛠️ Assembly Tips

### 1. **Test Components Individually**
   - Connect one component at a time
   - Upload test sketches to verify each works
   - Check I2C addresses with scanner sketch

### 2. **Use Proper Wire Gauge**
   - Signal wires: 22-26 AWG
   - Power wires for servos: 18-20 AWG
   - Keep wires as short as possible

### 3. **Secure Connections**
   - Solder connections for permanent installation
   - Use heat shrink tubing for insulation
   - Label all wires clearly

### 4. **I2C Pull-up Resistors**
   - Most modules have built-in pull-ups
   - If issues occur, add external 4.7kΩ resistors
   - Connect between SDA/SCL and 3.3V

### 5. **Servo Power**
   - Connect servos to PCA9685 V+ terminal, NOT ESP32
   - Use separate 5V power supply for servos
   - Ensure common ground between all power sources

---

## 🧪 Testing Procedure

### 1. **Power Test**
```arduino
void setup() {
  Serial.begin(115200);
  Serial.println("ESP32-C3 Powered On!");
}
```

### 2. **I2C Scanner**
```arduino
#include <Wire.h>

void setup() {
  Wire.begin(8, 9); // SDA=8, SCL=9
  Serial.begin(115200);
  
  Serial.println("Scanning I2C...");
  for(byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      Serial.print("Found at 0x");
      Serial.println(addr, HEX);
    }
  }
}
```

Expected output:
```
Found at 0x3C  (OLED)
Found at 0x40  (PCA9685)
```

### 3. **IR Sensor Test**
```arduino
pinMode(2, INPUT);  // IR1
pinMode(3, INPUT);  // IR2
pinMode(4, INPUT);  // IR3
pinMode(5, INPUT);  // IR4

void loop() {
  Serial.print("IR1:");
  Serial.print(digitalRead(2));
  Serial.print(" IR2:");
  Serial.print(digitalRead(3));
  // ... etc
  delay(500);
}
```

---

## 🚨 Troubleshooting

### Problem: OLED not displaying
- **Check:** I2C address (use scanner)
- **Check:** Power (3.3V to VCC)
- **Check:** SDA/SCL connections
- **Try:** Add 4.7kΩ pull-up resistors

### Problem: Servos not moving
- **Check:** PCA9685 power (5V to VCC and V+)
- **Check:** Common ground
- **Check:** I2C communication
- **Try:** Test with single servo first

### Problem: Load cell shows random values
- **Check:** HX711 wiring (especially E+, E-, A+, A-)
- **Run:** scale.tare() to zero
- **Calibrate:** Adjust scale.set_scale() value

### Problem: IR sensors always LOW
- **Check:** VCC and GND connections
- **Adjust:** Sensor sensitivity potentiometer
- **Test:** Move hand in front of sensor

---

## 📸 Physical Installation

### Chamber Layout
```
┌─────────────────────────────────┐
│         OLED Display            │
│       [Green Loop Status]       │
├─────────────────────────────────┤
│                                 │
│  ┌────┐  ┌────┐  ┌────┐  ┌────┐│
│  │PET │  │HDPE│  │ PP │  │Othr││
│  └────┘  └────┘  └────┘  └────┘│
│   (1)     (2)     (3)     (4)  │
│                                 │
│   [IR]    [IR]    [IR]    [IR] │
│                                 │
└────────[Load Cell]──────────────┘
       [Button]  [ESP32-C3]
```

---

## ✅ Pre-Upload Checklist

Before uploading firmware:

- [ ] All power connections secure
- [ ] Common ground established
- [ ] I2C devices respond to scanner
- [ ] IR sensors tested individually
- [ ] Servos powered separately from logic
- [ ] Load cell properly wired
- [ ] Button pull-up configured
- [ ] No short circuits
- [ ] ESP32-C3 USB connected
- [ ] Correct board selected in Arduino IDE

---

**Ready to upload firmware!** See [QUICKSTART.md](QUICKSTART.md) for software setup.

IR Sensor 3 (PP)
  VCC ──────────► ESP32  3.3V
  GND ──────────► ESP32  GND
  OUT ──────────► ESP32  GPIO 14

IR Sensor 4 (Others)
  VCC ──────────► ESP32  3.3V
  GND ──────────► ESP32  GND
  OUT ──────────► ESP32  GPIO 15
```

---

## 2. Push Buttons (× 2)

No external resistor needed – firmware uses INPUT_PULLUP.

```
START Button
  Pin 1 ────────► ESP32  GPIO 0  (BOOT button on ESP32-CAM board)
  Pin 2 ────────► ESP32  GND

STOP Button
  Pin 1 ────────► ESP32  GPIO 16
  Pin 2 ────────► ESP32  GND
```

---

## 3. HX711 Load Cell ADC

| HX711 Pin | Connect To |
|-----------|-----------|
| VCC | ESP32 **3.3V** |
| GND | ESP32 **GND** |
| DOUT | ESP32 **GPIO 3** (RX0) |
| SCK | ESP32 **GPIO 1** (TX0) |

```
HX711
  VCC  ─────────► ESP32  3.3V
  GND  ─────────► ESP32  GND
  DOUT ─────────► ESP32  GPIO 3
  SCK  ─────────► ESP32  GPIO 1
```

**HX711 → Load Cell (4-wire)**

```
HX711
  E+ ───────────► Load Cell  RED   wire  (Excitation +)
  E- ───────────► Load Cell  BLACK wire  (Excitation −)
  A+ ───────────► Load Cell  WHITE wire  (Signal +)
  A- ───────────► Load Cell  GREEN wire  (Signal −)
```

> ⚠️ GPIO 1 & 3 are UART0 TX/RX. Serial output works fine at 9600 baud alongside HX711 bit-banging during normal operation.

---

## 4. SSD1306 OLED Display

| OLED Pin | Connect To |
|----------|-----------|
| VCC | ESP32 **3.3V** |
| GND | ESP32 **GND** |
| SDA | ESP32 **GPIO 12** |
| SCL | ESP32 **GPIO 13** |

```
SSD1306 OLED  (I²C addr 0x3C)
  VCC ──────────► ESP32  3.3V
  GND ──────────► ESP32  GND
  SDA ──────────► ESP32  GPIO 12
  SCL ──────────► ESP32  GPIO 13
```

---

## 5. PCA9685 Servo Driver

The PCA9685 has **two separate power inputs**: logic VCC and servo power V+.

| PCA9685 Pin | Connect To |
|-------------|-----------|
| VCC | ESP32 **3.3V** (logic supply) |
| GND | Common **GND** |
| SDA | ESP32 **GPIO 12** |
| SCL | ESP32 **GPIO 13** |
| V+ | **External 5V PSU +** (servo power rail) |
| GND (screw terminal) | **External 5V PSU −** / Common GND |

```
PCA9685  (I²C addr 0x40)
  VCC ──────────► ESP32       3.3V     (logic)
  GND ──────────► Common      GND
  SDA ──────────► ESP32       GPIO 12
  SCL ──────────► ESP32       GPIO 13
  V+  ──────────► Ext 5V PSU  + rail   (servo power)
  GND ──────────► Ext 5V PSU  − rail   (common GND)
```

**PCA9685 → Servo Motors**

Each servo has 3 wires: Signal (orange/yellow), VCC (red), GND (brown/black).
The PCA9685 output connector provides all 3 on every channel.

```
PCA9685  CH 0  ──► Servo 0  Signal / 5V / GND   (PET   lid)
PCA9685  CH 1  ──► Servo 1  Signal / 5V / GND   (HDPE  lid)
PCA9685  CH 2  ──► Servo 2  Signal / 5V / GND   (PP    lid)
PCA9685  CH 3  ──► Servo 3  Signal / 5V / GND   (Others lid)
```

> ⚠️ Servo VCC and GND on CH 0-3 connectors come from the PCA9685 V+ rail (external 5V PSU), NOT from ESP32.

---

## 6. I²C Pull-up Resistors

Both SDA and SCL lines need pull-ups to 3.3V.
Most breakout boards for SSD1306 and PCA9685 include them.
If yours do not:

```
ESP32 3.3V ──┬── 4.7kΩ ──► GPIO 12 (SDA)
             └── 4.7kΩ ──► GPIO 13 (SCL)
```

---

## 7. Power Rails Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                     POWER DISTRIBUTION                          │
│                                                                 │
│  USB / 5V to ESP32 VIN ──► ESP32 DevKit                        │
│                              │                                  │
│                        3.3V PIN ──────────────────► IR Sensor 1 VCC
│                              │                 ├──► IR Sensor 2 VCC
│                              │                 ├──► IR Sensor 3 VCC
│                              │                 ├──► IR Sensor 4 VCC
│                              │                 ├──► HX711       VCC
│                              │                 ├──► OLED        VCC
│                              │                 └──► PCA9685     VCC (logic)
│                              │                                  │
│                        GND PIN ───────────────────► IR Sensor 1 GND
│                              │                 ├──► IR Sensor 2 GND
│                              │                 ├──► IR Sensor 3 GND
│                              │                 ├──► IR Sensor 4 GND
│                              │                 ├──► HX711       GND
│                              │                 ├──► OLED        GND
│                              │                 ├──► PCA9685     GND
│                              │                 ├──► Button 1    pin2
│                              │                 └──► Button 2    pin2
│                                                                 │
│  External 5V / 3A PSU  +  ────────────────────► PCA9685  V+    │
│  External 5V / 3A PSU  −  ────────────────────► Common GND     │
└─────────────────────────────────────────────────────────────────┘
```

---

## 8. Full Connection Diagram

```
                    ┌──────────────────────────────────────┐
                    │          ESP32 DevKit                │
                    │                                      │
  BTN_START ────────┤ GPIO 0  ◄── START Button → GND      │
  BTN_STOP  ────────┤ GPIO 16 ◄── STOP  Button → GND      │
                    │                                      │
  IR1 OUT ──────────┤ GPIO 2  ◄── IR Sensor 1 (PET)       │
  IR2 OUT ──────────┤ GPIO 4  ◄── IR Sensor 2 (HDPE)      │
  IR3 OUT ──────────┤ GPIO 14 ◄── IR Sensor 3 (PP)        │
  IR4 OUT ──────────┤ GPIO 15 ◄── IR Sensor 4 (Others)    │
                    │                                      │
  HX711 DOUT ───────┤ GPIO 3  ◄── HX711 DOUT              │
  HX711 SCK  ───────┤ GPIO 1  ◄── HX711 SCK               │
                    │                                      │
  I2C SDA ──────────┤ GPIO 12 ◄──► OLED SDA + PCA9685 SDA │
  I2C SCL ──────────┤ GPIO 13 ◄──► OLED SCL + PCA9685 SCL │
                    │                                      │
  Logic power ──────┤ 3.3V ───────► All VCC lines         │
  Common GND ───────┤ GND  ───────► All GND lines         │
                    └──────────────────────────────────────┘


  ┌──────────┐            ┌──────────────────┐
  │  HX711   │            │     PCA9685      │
  │          │            │  Servo Driver    │
  │ VCC ─────┼─── 3.3V   │                  │
  │ GND ─────┼─── GND    │ VCC ─── 3.3V     │
  │ DOUT ────┼─ GPIO 3   │ GND ─── GND      │
  │ SCK  ────┼─ GPIO 1   │ SDA ─── GPIO 12  │
  │          │            │ SCL ─── GPIO 13  │
  │ E+ ──────┼── RED  ┐  │ V+  ─── 5V EXT   │
  │ E- ──────┼── BLK  │  │ GND ─── GND EXT  │
  │ A+ ──────┼── WHT  ├──► Load Cell         │ CH0 ──► Servo 0 (PET)
  │ A- ──────┼── GRN  ┘  │                  │ CH1 ──► Servo 1 (HDPE)
  └──────────┘            │                  │ CH2 ──► Servo 2 (PP)
                          │                  │ CH3 ──► Servo 3 (Others)
  ┌──────────┐            └──────────────────┘
  │  SSD1306 │
  │   OLED   │
  │          │
  │ VCC ─────┼─── 3.3V
  │ GND ─────┼─── GND
  │ SDA ─────┼─── GPIO 12
  │ SCL ─────┼─── GPIO 13
  └──────────┘


  IR Sensors (all 4 identical)
  ┌───────────┐
  │ IR Module │   × 4
  │ VCC ──────┼─── ESP32 3.3V
  │ GND ──────┼─── ESP32 GND
  │ OUT ──────┼─── GPIO 2 / 4 / 14 / 15
  └───────────┘

  Push Buttons (both identical)
  [START]  GPIO 0  ──── Button ──── GND
  [STOP]   GPIO 16 ──── Button ──── GND
```

---

## Notes

| Topic | Detail |
|-------|--------|
| 3.3V load | All logic devices (4× IR, HX711, OLED, PCA9685 logic) draw ~120 mA total – well within ESP32 3.3V regulator limit of ~600 mA. |
| Servo PSU | 4× SG90 can draw ~800 mA stall each. Use ≥ 3 A @ 5V PSU for V+ rail. |
| Pull-ups | SDA/SCL need 4.7 kΩ to 3.3V. Most SSD1306 and PCA9685 breakouts already have them. |
| GPIO 1 & 3 | These are Serial TX/RX. Disconnected when uploading firmware. HX711 uses them only at runtime. |
| I²C addresses | OLED = 0x3C, PCA9685 = 0x40 (all Ax pads = LOW). |
| Servo PWM | setPWMFreq(60 Hz); MIN pulse 150 ≈ closed, MAX pulse 450 ≈ open. Adjust per your servo. |
| Load cell calibration | `set_scale(420.0983)` – update this value with a calibration sketch using a known weight. |
| Common GND | ALL GND connections must be joined together including external PSU negative terminal. |
