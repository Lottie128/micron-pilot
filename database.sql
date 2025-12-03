-- Micron Production Tracking Database Schema

CREATE DATABASE IF NOT EXISTS micron_tracking;
USE micron_tracking;

-- Parts table
CREATE TABLE IF NOT EXISTS parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(50) NOT NULL UNIQUE,
    part_name VARCHAR(100),
    category VARCHAR(50),
    total_stages INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_part_number (part_number)
);

-- Stages table
CREATE TABLE IF NOT EXISTS stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_id INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_order INT NOT NULL,
    stage_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    INDEX idx_part_stage (part_id, stage_order)
);

-- Bins table
CREATE TABLE IF NOT EXISTS bins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bin_code VARCHAR(50) NOT NULL UNIQUE,
    bin_name VARCHAR(100),
    location VARCHAR(100),
    capacity INT DEFAULT 1000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bin_code (bin_code)
);

-- Inventory table (tracks current quantities at each stage)
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_id INT NOT NULL,
    stage_id INT NOT NULL,
    bin_id INT NOT NULL,
    quantity INT DEFAULT 0,
    good_quantity INT DEFAULT 0,
    rework_quantity INT DEFAULT 0,
    rejected_quantity INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    UNIQUE KEY unique_inventory (part_id, stage_id, bin_id),
    INDEX idx_part_inventory (part_id),
    INDEX idx_stage_inventory (stage_id)
);

-- Movements table (tracks all bin transfers)
CREATE TABLE IF NOT EXISTS movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_id INT NOT NULL,
    from_stage_id INT,
    to_stage_id INT NOT NULL,
    from_bin_id INT,
    to_bin_id INT NOT NULL,
    quantity INT NOT NULL,
    movement_type ENUM('incoming', 'transfer', 'rework', 'rejection') DEFAULT 'transfer',
    notes TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
    FOREIGN KEY (to_stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    FOREIGN KEY (from_bin_id) REFERENCES bins(id) ON DELETE SET NULL,
    FOREIGN KEY (to_bin_id) REFERENCES bins(id) ON DELETE CASCADE,
    INDEX idx_part_movements (part_id),
    INDEX idx_created_at (created_at)
);

-- Insert default bins
INSERT INTO bins (bin_code, bin_name, location, capacity) VALUES
('BIN-IN', 'Incoming Bin', 'Receiving Area', 5000),
('BIN-001', 'Production Bin 1', 'Floor 1', 1000),
('BIN-002', 'Production Bin 2', 'Floor 1', 1000),
('BIN-003', 'Production Bin 3', 'Floor 1', 1000),
('BIN-004', 'Production Bin 4', 'Floor 2', 1000),
('BIN-005', 'Production Bin 5', 'Floor 2', 1000),
('BIN-QC', 'Quality Check Bin', 'QC Area', 500),
('BIN-REWORK', 'Rework Bin', 'Rework Area', 500),
('BIN-REJECT', 'Rejection Bin', 'Rejection Area', 500),
('BIN-FG', 'Finished Goods', 'Warehouse', 10000);

-- Insert sample parts from CSV (200 Series)
INSERT INTO parts (part_number, part_name, category, total_stages) VALUES
('RW 236 A', 'RW 236 A', '200 Series', 13),
('RW 236 B', 'RW 236 B', '200 Series', 11),
('RW 237', 'RW 237', '200 Series', 11),
('RW 238', 'RW 238', '200 Series', 11),
('RW 239', 'RW 239', '200 Series', 11),
('RW 211', 'RW 211', '200 Series', 12),
('RW 212 A', 'RW 212 A', '200 Series', 13);

-- Insert stages for RW 236 A (13 stages)
INSERT INTO stages (part_id, stage_name, stage_order, stage_type) 
SELECT id, 'Incoming (IN)', 1, 'receiving' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Body Drill (Rough)', 2, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'CNC -1', 3, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Ear Drill (Rough)', 4, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Ear Bore (EB)', 5, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Tapping', 6, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Face Drill (D1)', 7, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Spot Face', 8, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Champer', 9, 'machining' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Heat Treatment (1)', 10, 'heat_treatment' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Heat Treatment (2)', 11, 'heat_treatment' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Quality Check (QC)', 12, 'quality' FROM parts WHERE part_number = 'RW 236 A'
UNION ALL
SELECT id, 'Finished Goods (FG)', 13, 'finished' FROM parts WHERE part_number = 'RW 236 A';

-- Insert stages for RW 237 (11 stages)
INSERT INTO stages (part_id, stage_name, stage_order, stage_type) 
SELECT id, 'Incoming (IN)', 1, 'receiving' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'CNC-1', 2, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Back Champer', 3, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Ear Drill (Rough)', 4, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Broach', 5, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Ear Bore (EB)', 6, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Pin Drill (PD)', 7, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Re -Broach', 8, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Champer', 9, 'machining' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Quality Check (QC)', 10, 'quality' FROM parts WHERE part_number = 'RW 237'
UNION ALL
SELECT id, 'Finished Goods (FG)', 11, 'finished' FROM parts WHERE part_number = 'RW 237';
