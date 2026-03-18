# Green Loop – Hardware Wiring Diagram (No-QR Version)
Firmware: `smart_bin_noqr.ino`
> All power pins shown explicitly for each component.

---

## Component List

| # | Component | Model / Notes |
|---|-----------|---------------|
| 1 | Microcontroller | ESP32 38-pin DevKit **or** ESP32-CAM (camera unused in No-QR build) |
| 2 | OLED Display | 0.96″ SSD1306 – I²C, 128×64 |
| 3 | Servo Driver | PCA9685 16-channel – I²C address 0x40 |
| 4 | Servo Motors × 4 | SG90 / MG995 (one per chamber lid) |
| 5 | Load Cell | Generic strain-gauge platform load cell |
| 6 | HX711 ADC | Load cell amplifier breakout |
| 7 | IR Obstacle Sensors × 4 | Active-LOW digital output (one per chamber) |
| 8 | Push Buttons × 2 | Momentary tactile – START and STOP |
| 9 | 5 V / 3 A external PSU | For servo power rail on PCA9685 |

---

## 1. IR Obstacle Sensors (× 4)

Each sensor module has 3 pins: **VCC, GND, OUT**

| Sensor Pin | Connect To | Wire colour (typical) |
|------------|------------|----------------------|
| VCC | ESP32 **3.3V** pin | Red |
| GND | ESP32 **GND** pin | Black |
| OUT | ESP32 GPIO (see table below) | Yellow |

| Sensor | OUT → ESP32 GPIO | Chamber |
|--------|-----------------|---------|
| IR Sensor 1 | GPIO **2** | PET |
| IR Sensor 2 | GPIO **4** | HDPE |
| IR Sensor 3 | GPIO **14** | PP |
| IR Sensor 4 | GPIO **15** | Others |

```
IR Sensor 1 (PET)
  VCC ──────────► ESP32  3.3V
  GND ──────────► ESP32  GND
  OUT ──────────► ESP32  GPIO 2

IR Sensor 2 (HDPE)
  VCC ──────────► ESP32  3.3V
  GND ──────────► ESP32  GND
  OUT ──────────► ESP32  GPIO 4

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
