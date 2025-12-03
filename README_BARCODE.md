# Micron Barcode-Based Dynamic Bin Tracking System

## 🎯 System Overview

Real-time production tracking system for PTO manufacturing with **1500 dynamic bins** using permanent engraved barcodes. Materials flow through bins automatically with operator-only input for rejects and reworks.

## 🔑 Key Features

### ✅ Dynamic Bin Tracking
- **1500 permanent bins** with unique barcodes (BIN-001 to BIN-1500)
- Materials move bin-to-bin throughout production
- **Scan once** → Auto-updates material location
- No material stays in same bin through entire process

### ✅ Purchase Order Based Tracking
- Track by **PO Number** instead of just part numbers
- Multiple parts per PO
- Real-time PO progress monitoring
- Automatic quantity calculations

### ✅ Auto-Calculations
- **Input quantity at stage** = Output from previous stage - rejects - reworks
- Only manual inputs: **Rejects** and **Reworks** at each stage
- System calculates everything else automatically

### ✅ Complete Audit Trail
- Every bin transfer recorded
- Stage-wise operation tracking
- Material movement history from incoming to finished goods

## 📊 Database Structure

### Core Tables

1. **bins** - 1500 bins with permanent barcodes
   - Organized by zones (A-F)
   - Zone A: Incoming (1-100)
   - Zone B: CNC/Machining (101-500)
   - Zone C: Drilling (501-800)
   - Zone D: Heat Treatment/QC (801-1000)
   - Zone E: Finishing (1001-1400)
   - Zone F: Finished Goods (1401-1500)

2. **purchase_orders** - Customer orders
3. **po_items** - Parts within each PO
4. **bin_inventory** - Current contents of each bin
5. **bin_transfers** - Complete movement history
6. **stage_operations** - Processing at each stage
7. **parts** - Part master data
8. **stages** - Manufacturing stages per part

## 🚀 Installation

### 1. Database Setup
```bash
# Import the new barcode-based schema
mysql -u your_user -p < database_barcode.sql
```

This creates:
- 1500 bins with barcodes
- All necessary tables
- Sample PO and parts data

### 2. Import All 62 Parts
```bash
# Add all your parts from the CSV
mysql -u your_user -p micron_tracking < complete_parts_insertion.sql
```

### 3. Configure API
Edit `api/config.php` with your database credentials.

### 4. Deploy Files
Upload to your shared hosting:
- `api/` folder → API endpoints
- `public/scanner.html` → Barcode scanner interface
- `public/po_dashboard.html` → PO tracking dashboard

## 💻 User Interfaces

### 1. Barcode Scanner (`/scanner.html`)
**For operators on the production floor:**

- **Scan bin barcode** → See current contents
- **Transfer material**:
  - Scan FROM bin (source)
  - Scan TO bin (destination)
  - Enter PO Item ID
  - Enter **rejects** (manual input)
  - Enter **reworks** (manual input)
  - Click "Execute Transfer"

**System automatically:**
- Calculates transfer quantity (current - rejects - reworks)
- Moves material to next stage
- Updates bin inventory
- Records transfer in audit trail
- Updates PO progress

### 2. PO Dashboard (`/po_dashboard.html`)
**For managers and supervisors:**

- View all purchase orders
- Real-time progress tracking
- Stage-wise breakdown per PO
- Current bin locations
- Reject/rework statistics
- Material flow visualization

## 🔄 Typical Workflow

### Example: PTO Shaft RW-236A Production

**PO-2025-001: 500 units ordered**

1. **Incoming (BIN-001)**
   - Operator scans BIN-001
   - 500 units received

2. **Transfer to CNC-1 (BIN-105)**
   - Scan BIN-001 (from)
   - Scan BIN-105 (to)
   - Enter: 5 rejects, 10 rework
   - Transfer: 485 units → BIN-105 (auto-calculated)

3. **Transfer to Drilling (BIN-523)**
   - Scan BIN-105 (from)
   - Scan BIN-523 (to)
   - Enter: 3 rejects, 5 rework
   - Transfer: 477 units → BIN-523 (auto-calculated)

4. **Continue through stages...**
   - Broaching → BIN-307
   - Heat Treatment → BIN-845
   - Quality Check → BIN-901
   - Finished Goods → BIN-1450

**System tracks:**
- Every bin transfer
- Running total of rejects/reworks
- Current location of materials
- Progress percentage
- Completion status

## 📡 API Endpoints

### Barcode Scanning
```
POST /api/barcode_scan.php
Body: { "barcode": "BIN-105" }
Returns: Bin info + current contents

GET /api/barcode_scan.php?barcode=BIN-105
Returns: Bin details

GET /api/barcode_scan.php?zone=Zone-B
Returns: All bins in zone
```

### Material Transfer
```
POST /api/transfer.php
Body: {
  "from_barcode": "BIN-105",
  "to_barcode": "BIN-523",
  "po_item_id": 1,
  "rejected_quantity": 5,
  "rework_quantity": 10,
  "scanned_by": "Operator-1"
}
Auto-calculates: transfer_qty = current_qty - rejects - reworks
```

### PO Tracking
```
GET /api/po_tracking.php
Returns: All purchase orders

GET /api/po_tracking.php?po_number=PO-2025-001
Returns: Complete PO details with stage progress

POST /api/po_tracking.php
Body: { "po_number": "PO-2025-002", "items": [...] }
Creates: New purchase order
```

## 🎯 Demonstration Points for Client

### "Why This System?"

1. **Eliminate Manual Tracking Errors**
   - No Excel sheets
   - No manual bin logs
   - Scan once → Auto-updates

2. **Real-Time Visibility**
   - Know exactly where every PO is
   - Current bin location of any material
   - Instant progress reports

3. **Quality Control**
   - Track rejects at every stage
   - Identify bottleneck stages
   - Monitor rework trends

4. **Complete Audit Trail**
   - Every bin transfer recorded
   - Timestamps for all movements
   - Operator accountability

5. **Scalability**
   - 1500 bins ready
   - Add more parts easily
   - Multiple POs simultaneously

### Live Demo Script

**"Let me show you how easy this is..."**

1. Open Scanner Interface
2. Scan a bin barcode (e.g., BIN-105)
3. Show current contents (PO, Part, Qty, Stage)
4. Demonstrate transfer:
   - Scan source bin
   - Scan destination bin
   - Enter only rejects/reworks
   - System calculates rest automatically
5. Open PO Dashboard
6. Show real-time updates
7. Display stage-by-stage progress
8. Show current bin locations

## 🔧 Hardware Requirements

### Barcode Scanners
- **Type**: Handheld USB/Bluetooth barcode scanners
- **Quantity**: 5-10 scanners (based on production lines)
- **Compatibility**: Standard 1D/2D barcode readers

### Bin Labels
- **Material**: Permanent engraved metal/plastic tags
- **Format**: Code 128 or QR codes
- **Size**: 2" x 1" minimum
- **Durability**: Industrial-grade (heat/oil/chemical resistant)

### Tablets/Terminals
- **Option 1**: Rugged tablets with browser
- **Option 2**: Fixed scanning stations with monitors
- **Network**: WiFi/LAN connectivity

## 📈 Benefits vs. Current System

| Current | With Barcode System |
|---------|--------------------|
| Manual bin logs | Scan once, auto-update |
| Excel tracking | Real-time database |
| End-of-day reports | Instant visibility |
| Lost materials | Complete audit trail |
| Manual calculations | Auto-calculated quantities |
| No reject tracking | Stage-wise reject data |
| Guessing bin locations | Exact current location |

## 🎓 Training Requirements

**Operators (30 minutes):**
- How to scan barcodes
- Entering rejects/reworks only
- Understanding transfer confirmations

**Supervisors (1 hour):**
- Using PO dashboard
- Reading progress reports
- Identifying bottlenecks

## 📞 Support

For issues or questions:
- Run `/api/debug.php` for diagnostics
- Check bin barcodes are correct
- Verify PO item IDs before transfer

---

**Built by ZeroAI Technologies**  
**For Micron Enterprises PTO Manufacturing**
