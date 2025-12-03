-- =====================================================
-- MICRON BARCODE-BASED DYNAMIC BIN TRACKING SYSTEM
-- Real-time PTO Manufacturing with 1500 Dynamic Bins
-- =====================================================

CREATE DATABASE IF NOT EXISTS micron_tracking;
USE micron_tracking;

-- Drop existing tables if rebuilding
-- DROP TABLE IF EXISTS bin_transfers;
-- DROP TABLE IF EXISTS stage_operations;
-- DROP TABLE IF EXISTS po_items;
-- DROP TABLE IF EXISTS purchase_orders;
-- DROP TABLE IF EXISTS bin_inventory;
-- DROP TABLE IF EXISTS bins;
-- DROP TABLE IF EXISTS stages;
-- DROP TABLE IF EXISTS parts;

-- =====================================================
-- PARTS MASTER
-- =====================================================
CREATE TABLE IF NOT EXISTS parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(50) NOT NULL UNIQUE,
    part_name VARCHAR(100),
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_part_number (part_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MANUFACTURING STAGES
-- =====================================================
CREATE TABLE IF NOT EXISTS stages (
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
-- DYNAMIC BINS (1500 bins with permanent barcodes)
-- =====================================================
CREATE TABLE IF NOT EXISTS bins (
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
-- PURCHASE ORDERS
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
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
-- PURCHASE ORDER ITEMS (Parts in each PO)
-- =====================================================
CREATE TABLE IF NOT EXISTS po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    part_id INT NOT NULL,
    ordered_quantity INT NOT NULL,
    produced_quantity INT DEFAULT 0,
    rejected_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    current_stage_id INT,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY (current_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
    INDEX idx_po_part (po_id, part_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- BIN INVENTORY (Current contents of each bin)
-- =====================================================
CREATE TABLE IF NOT EXISTS bin_inventory (
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
-- STAGE OPERATIONS (Track progress at each stage)
-- =====================================================
CREATE TABLE IF NOT EXISTS stage_operations (
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
-- BIN TRANSFERS (Complete audit trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS bin_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_item_id INT NOT NULL,
    from_bin_id INT,
    to_bin_id INT NOT NULL,
    from_stage_id INT,
    to_stage_id INT NOT NULL,
    quantity INT NOT NULL,
    transfer_type ENUM('incoming', 'stage_transfer', 'rework', 'quality_check', 'finished') DEFAULT 'stage_transfer',
    rejected_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    scanned_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_item_id) REFERENCES po_items(id) ON DELETE CASCADE,
    FOREIGN KEY (from_bin_id) REFERENCES bins(id) ON DELETE SET NULL,
    FOREIGN KEY (to_bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
    FOREIGN KEY (to_stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    INDEX idx_po_item (po_item_id),
    INDEX idx_created (created_at),
    INDEX idx_from_bin (from_bin_id),
    INDEX idx_to_bin (to_bin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Generate 1500 bins with permanent barcodes
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity) VALUES
-- Zone A: Incoming & Raw Material (Bins 001-100)
('BIN-001', 'Incoming Bin 1', 'Receiving Area', 'Zone-A', 5000),
('BIN-002', 'Incoming Bin 2', 'Receiving Area', 'Zone-A', 5000),
('BIN-003', 'Raw Material 1', 'Receiving Area', 'Zone-A', 3000),
('BIN-004', 'Raw Material 2', 'Receiving Area', 'Zone-A', 3000),
('BIN-005', 'Raw Material 3', 'Receiving Area', 'Zone-A', 3000);

-- Zone B: CNC & Machining (Bins 101-500)
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 3, '0')),
    CONCAT('CNC Bin ', n - 100),
    'Floor 1 - CNC Section',
    'Zone-B',
    1000
FROM (SELECT 101 + a.N + b.N * 10 + c.N * 100 AS n
      FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) c
     ) numbers
WHERE n BETWEEN 101 AND 500;

-- Zone C: Drilling & Boring (Bins 501-800)
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 3, '0')),
    CONCAT('Drill Bin ', n - 500),
    'Floor 2 - Drilling Section',
    'Zone-C',
    800
FROM (SELECT 501 + a.N + b.N * 10 + c.N * 100 AS n
      FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2) c
     ) numbers
WHERE n BETWEEN 501 AND 800;

-- Zone D: Heat Treatment & QC (Bins 801-1000)
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('HT Bin ', n - 800),
    'Floor 3 - Heat Treatment',
    'Zone-D',
    500
FROM (SELECT 801 + a.N + b.N * 10 + c.N * 100 AS n
      FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1) c
     ) numbers
WHERE n BETWEEN 801 AND 1000;

-- Zone E: Finishing & Packaging (Bins 1001-1400)
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('Finish Bin ', n - 1000),
    'Floor 4 - Finishing',
    'Zone-E',
    1000
FROM (SELECT 1001 + a.N + b.N * 10 + c.N * 100 AS n
      FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) c
     ) numbers
WHERE n BETWEEN 1001 AND 1400;

-- Zone F: Finished Goods (Bins 1401-1500)
INSERT INTO bins (bin_barcode, bin_name, location, zone, capacity)
SELECT 
    CONCAT('BIN-', LPAD(n, 4, '0')),
    CONCAT('FG Bin ', n - 1400),
    'Warehouse - Finished Goods',
    'Zone-F',
    10000
FROM (SELECT 1401 + a.N + b.N * 10 AS n
      FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
     ) numbers
WHERE n BETWEEN 1401 AND 1500;

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

-- Sample Purchase Order
INSERT INTO purchase_orders (po_number, customer_name, order_date, delivery_date, status) VALUES
('PO-2025-001', 'ABC Agriculture Ltd', '2025-12-01', '2025-12-15', 'in_progress');

-- Sample PO Items
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

-- =====================================================
-- SUMMARY
-- =====================================================
SELECT 'Database Setup Complete' as Status;
SELECT COUNT(*) as Total_Bins FROM bins;
SELECT zone, COUNT(*) as Bins_Per_Zone FROM bins GROUP BY zone;
