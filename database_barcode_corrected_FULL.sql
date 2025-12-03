-- =====================================================
-- MICRON BARCODE-BASED DYNAMIC BIN TRACKING SYSTEM
-- Real-time PTO Manufacturing with 1500 Dynamic Bins
-- CORRECTED VERSION - Proper Foreign Key Order
-- =====================================================

CREATE DATABASE IF NOT EXISTS micron_tracking 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE micron_tracking;

-- Drop tables in reverse dependency order if rebuilding
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS movements;
DROP TABLE IF EXISTS stage_operations;
DROP TABLE IF EXISTS bin_inventory;
DROP TABLE IF EXISTS po_items;
DROP TABLE IF EXISTS bins;
DROP TABLE IF EXISTS stages;
DROP TABLE IF EXISTS parts;
DROP TABLE IF EXISTS purchase_orders;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 1. PURCHASE ORDERS (No dependencies)
-- =====================================================
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(100),
    order_date DATE,
    delivery_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_number (po_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 2. PARTS MASTER (No dependencies)
-- =====================================================
CREATE TABLE parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(50) NOT NULL UNIQUE,
    part_name VARCHAR(100),
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_part_number (part_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3. MANUFACTURING STAGES (Depends on: parts)
-- =====================================================
CREATE TABLE stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_id INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_order INT NOT NULL,
    stage_type VARCHAR(50),
    machine_code VARCHAR(50),
    requires_qc BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    INDEX idx_part_stage (part_id, stage_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4. BINS (No dependencies)
-- =====================================================
CREATE TABLE bins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bin_barcode VARCHAR(50) NOT NULL UNIQUE,
    bin_name VARCHAR(100),
    location VARCHAR(100),
    zone VARCHAR(50),
    capacity INT DEFAULT 1000,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_barcode (bin_barcode),
    INDEX idx_zone (zone),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 5. PURCHASE ORDER ITEMS (Depends on: purchase_orders, parts, stages)
-- =====================================================
CREATE TABLE po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    part_id INT NOT NULL,
    ordered_quantity INT NOT NULL,
    produced_quantity INT DEFAULT 0,
    rejected_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    current_stage_id INT,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY (current_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
    INDEX idx_po_part (po_id, part_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6. BIN INVENTORY (Depends on: bins, po_items, stages)
-- =====================================================
CREATE TABLE bin_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bin_id INT NOT NULL,
    po_item_id INT NOT NULL,
    stage_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    good_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    rejected_quantity INT DEFAULT 0,
    scanned_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    FOREIGN KEY (po_item_id) REFERENCES po_items(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bin_po_stage (bin_id, po_item_id, stage_id),
    INDEX idx_bin (bin_id),
    INDEX idx_po_item (po_item_id),
    INDEX idx_stage (stage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 7. STAGE OPERATIONS (Depends on: po_items, stages, bins)
-- =====================================================
CREATE TABLE stage_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_item_id INT NOT NULL,
    stage_id INT NOT NULL,
    bin_id INT NOT NULL,
    input_quantity INT NOT NULL,
    output_quantity INT DEFAULT 0,
    good_quantity INT DEFAULT 0,
    rejected_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    operator_name VARCHAR(100),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('in_progress', 'completed', 'paused') DEFAULT 'in_progress',
    notes TEXT,
    FOREIGN KEY (po_item_id) REFERENCES po_items(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    INDEX idx_po_stage (po_item_id, stage_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. BIN TRANSFERS (Depends on: po_items, bins, stages)
-- =====================================================
CREATE TABLE movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_item_id INT NOT NULL,
    from_bin_id INT,
    to_bin_id INT NOT NULL,
    from_stage_id INT,
    to_stage_id INT NOT NULL,
    quantity_moved INT NOT NULL,
    rejected_count INT DEFAULT 0,
    rework_count INT DEFAULT 0,
    transfer_type ENUM('incoming', 'stage_transfer', 'rework', 'quality_check', 'finished') DEFAULT 'stage_transfer',
    scanned_by VARCHAR(100),
    notes TEXT,
    movement_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_item_id) REFERENCES po_items(id) ON DELETE CASCADE,
    FOREIGN KEY (from_bin_id) REFERENCES bins(id) ON DELETE SET NULL,
    FOREIGN KEY (to_bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
    FOREIGN KEY (to_stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    INDEX idx_po_item (po_item_id),
    INDEX idx_timestamp (movement_timestamp),
    INDEX idx_from_bin (from_bin_id),
    INDEX idx_to_bin (to_bin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Sample Parts
INSERT INTO parts (part_number, part_name, category, description) VALUES
('RW-236A', 'PTO Shaft RW 236 A', '200 Series', 'Agriculture PTO component'),
('RW-237', 'PTO Shaft RW 237', '200 Series', 'Agriculture PTO component'),
('RW-238', 'PTO Shaft RW 238', '200 Series', 'Agriculture PTO component');

-- Sample stages for RW-236A
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Incoming', 1, 'receiving', 'RECV-01', FALSE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'CNC-1', 2, 'machining', 'CNC-101', FALSE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'Drilling', 3, 'machining', 'DRILL-201', FALSE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'Broaching', 4, 'machining', 'BROACH-301', FALSE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'Heat Treatment', 5, 'heat_treatment', 'HT-401', TRUE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'Quality Check', 6, 'quality', 'QC-501', TRUE FROM parts WHERE part_number = 'RW-236A'
UNION ALL
SELECT id, 'Finished Goods', 7, 'finished', 'FG-601', FALSE FROM parts WHERE part_number = 'RW-236A';

-- Sample stages for RW-237
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Incoming', 1, 'receiving', 'RECV-01', FALSE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'CNC-1', 2, 'machining', 'CNC-102', FALSE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'Back Champer', 3, 'machining', 'CHAMP-101', FALSE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'Ear Drill', 4, 'machining', 'DRILL-202', FALSE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'Broach', 5, 'machining', 'BROACH-302', FALSE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'Quality Check', 6, 'quality', 'QC-502', TRUE FROM parts WHERE part_number = 'RW-237'
UNION ALL
SELECT id, 'Finished Goods', 7, 'finished', 'FG-602', FALSE FROM parts WHERE part_number = 'RW-237';

-- =====================================================
-- GENERATE 1500 BINS BY ZONE
-- =====================================================

-- Zone: INCOMING (BIN-0001 to BIN-0100) - 100 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Incoming Bin ', n),
    'Receiving Area - Zone A',
    'INCOMING',
    5000
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT @row := 0) t3
) numbers
WHERE n BETWEEN 1 AND 100;

-- Zone: CNC (BIN-0101 to BIN-0400) - 300 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('CNC Bin ', n - 100),
    'Floor 1 - CNC Section',
    'CNC',
    1000
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t3,
         (SELECT @row := 100) t4
) numbers
WHERE n BETWEEN 101 AND 400;

-- Zone: DRILLING (BIN-0401 to BIN-0600) - 200 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Drill Bin ', n - 400),
    'Floor 2 - Drilling Section',
    'DRILLING',
    800
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t3,
         (SELECT @row := 400) t4
) numbers
WHERE n BETWEEN 401 AND 600;

-- Zone: HEAT_TREAT (BIN-0601 to BIN-0800) - 200 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('HT Bin ', n - 600),
    'Floor 3 - Heat Treatment',
    'HEAT_TREAT',
    500
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t3,
         (SELECT @row := 600) t4
) numbers
WHERE n BETWEEN 601 AND 800;

-- Zone: FINISHING (BIN-0801 to BIN-1000) - 200 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Finish Bin ', n - 800),
    'Floor 4 - Finishing Section',
    'FINISHING',
    1000
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t3,
         (SELECT @row := 800) t4
) numbers
WHERE n BETWEEN 801 AND 1000;

-- Zone: QC (BIN-1001 to BIN-1100) - 100 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('QC Bin ', n - 1000),
    'Floor 4 - Quality Control',
    'QC',
    500
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT @row := 1000) t3
) numbers
WHERE n BETWEEN 1001 AND 1100;

-- Zone: REWORK (BIN-1101 to BIN-1300) - 200 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Rework Bin ', n - 1100),
    'Floor 2 - Rework Area',
    'REWORK',
    500
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t3,
         (SELECT @row := 1100) t4
) numbers
WHERE n BETWEEN 1101 AND 1300;

-- Zone: REJECT (BIN-1301 to BIN-1400) - 100 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Reject Bin ', n - 1300),
    'Storage - Rejected Items',
    'REJECT',
    500
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT @row := 1300) t3
) numbers
WHERE n BETWEEN 1301 AND 1400;

-- Zone: FG (BIN-1401 to BIN-1500) - 100 bins
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('FG Bin ', n - 1400),
    'Warehouse - Finished Goods',
    'FG',
    10000
FROM (
    SELECT @row := @row + 1 as n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT @row := 1400) t3
) numbers
WHERE n BETWEEN 1401 AND 1500;

-- =====================================================
-- SAMPLE PURCHASE ORDERS
-- =====================================================
INSERT INTO purchase_orders (po_number, customer_name, order_date, delivery_date, status) VALUES
('PO-2025-001', 'ABC Agriculture Ltd', '2025-12-01', '2025-12-15', 'in_progress'),
('PO-2025-002', 'XYZ Farming Co', '2025-12-02', '2025-12-20', 'in_progress');

-- =====================================================
-- SAMPLE PO ITEMS
-- =====================================================
-- PO-2025-001: 500 units of RW-236A
INSERT INTO po_items (po_id, part_id, ordered_quantity, current_stage_id, status)
SELECT 
    po.id,
    p.id,
    500,
    s.id,
    'in_progress'
FROM purchase_orders po
CROSS JOIN parts p
JOIN stages s ON s.part_id = p.id AND s.stage_order = 1
WHERE po.po_number = 'PO-2025-001' AND p.part_number = 'RW-236A';

-- PO-2025-002: 300 units of RW-237
INSERT INTO po_items (po_id, part_id, ordered_quantity, current_stage_id, status)
SELECT 
    po.id,
    p.id,
    300,
    s.id,
    'in_progress'
FROM purchase_orders po
CROSS JOIN parts p
JOIN stages s ON s.part_id = p.id AND s.stage_order = 1
WHERE po.po_number = 'PO-2025-002' AND p.part_number = 'RW-237';

-- =====================================================
-- SAMPLE INITIAL INVENTORY (Material just arrived)
-- =====================================================
-- PO-2025-001 material in incoming bin
INSERT INTO bin_inventory (bin_id, po_item_id, stage_id, quantity, good_quantity)
SELECT 
    b.id,
    poi.id,
    s.id,
    500,
    500
FROM bins b
CROSS JOIN po_items poi
JOIN purchase_orders po ON poi.po_id = po.id
JOIN stages s ON poi.current_stage_id = s.id
WHERE b.bin_barcode = 'BIN-0001' 
  AND po.po_number = 'PO-2025-001';

-- PO-2025-002 material in incoming bin
INSERT INTO bin_inventory (bin_id, po_item_id, stage_id, quantity, good_quantity)
SELECT 
    b.id,
    poi.id,
    s.id,
    300,
    300
FROM bins b
CROSS JOIN po_items poi
JOIN purchase_orders po ON poi.po_id = po.id
JOIN stages s ON poi.current_stage_id = s.id
WHERE b.bin_barcode = 'BIN-0002' 
  AND po.po_number = 'PO-2025-002';

-- =====================================================
-- SUMMARY VIEWS
-- =====================================================
SELECT 'Database Setup Complete' as Status;
SELECT COUNT(*) as Total_Bins FROM bins;
SELECT zone, COUNT(*) as Bins_Per_Zone FROM bins GROUP BY zone ORDER BY zone;
SELECT po_number, customer_name, status FROM purchase_orders;
SELECT 
    po.po_number,
    p.part_number,
    poi.ordered_quantity,
    s.stage_name as current_stage,
    b.bin_barcode as current_bin
FROM po_items poi
JOIN purchase_orders po ON poi.po_id = po.id
JOIN parts p ON poi.part_id = p.id
JOIN stages s ON poi.current_stage_id = s.id
LEFT JOIN bin_inventory bi ON poi.id = bi.po_item_id AND bi.quantity > 0
LEFT JOIN bins b ON bi.bin_id = b.id;
