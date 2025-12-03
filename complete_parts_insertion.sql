-- =====================================================
-- ALL 62 RW PARTS WITH THEIR MANUFACTURING STAGES
-- Based on Micron Enterprises Production Sheet
-- =====================================================

USE micron_tracking;

-- Delete sample parts first
DELETE FROM stages WHERE part_id IN (SELECT id FROM parts WHERE part_number IN ('RW-236A', 'RW-237', 'RW-238'));
DELETE FROM parts WHERE part_number IN ('RW-236A', 'RW-237', 'RW-238');

-- Insert all 62 parts
INSERT INTO parts (part_number, part_name, category, description) VALUES
('RW-236A', 'PTO Shaft RW 236 A', '200 Series', 'Agriculture PTO shaft component'),
('RW-237', 'PTO Shaft RW 237', '200 Series', 'Agriculture PTO shaft component'),
('RW-238', 'PTO Shaft RW 238', '200 Series', 'Agriculture PTO shaft component'),
('RW-239', 'PTO Shaft RW 239', '200 Series', 'Agriculture PTO shaft component'),
('RW-240', 'PTO Shaft RW 240', '200 Series', 'Agriculture PTO shaft component'),
('RW-241', 'PTO Shaft RW 241', '200 Series', 'Agriculture PTO shaft component'),
('RW-246A', 'PTO Shaft RW 246 A', '200 Series', 'Agriculture PTO shaft component'),
('RW-247', 'PTO Shaft RW 247', '200 Series', 'Agriculture PTO shaft component'),
('RW-248', 'PTO Shaft RW 248', '200 Series', 'Agriculture PTO shaft component'),
('RW-249', 'PTO Shaft RW 249', '200 Series', 'Agriculture PTO shaft component'),
('RW-250', 'PTO Shaft RW 250', '200 Series', 'Agriculture PTO shaft component'),
('RW-251', 'PTO Shaft RW 251', '200 Series', 'Agriculture PTO shaft component'),
('RW-256A', 'PTO Shaft RW 256 A', '200 Series', 'Agriculture PTO shaft component'),
('RW-257', 'PTO Shaft RW 257', '200 Series', 'Agriculture PTO shaft component'),
('RW-258', 'PTO Shaft RW 258', '200 Series', 'Agriculture PTO shaft component'),
('RW-259', 'PTO Shaft RW 259', '200 Series', 'Agriculture PTO shaft component'),
('RW-260', 'PTO Shaft RW 260', '200 Series', 'Agriculture PTO shaft component'),
('RW-261', 'PTO Shaft RW 261', '200 Series', 'Agriculture PTO shaft component'),
('RW-336A', 'PTO Shaft RW 336 A', '300 Series', 'Industrial PTO shaft component'),
('RW-337', 'PTO Shaft RW 337', '300 Series', 'Industrial PTO shaft component'),
('RW-338', 'PTO Shaft RW 338', '300 Series', 'Industrial PTO shaft component'),
('RW-339', 'PTO Shaft RW 339', '300 Series', 'Industrial PTO shaft component'),
('RW-340', 'PTO Shaft RW 340', '300 Series', 'Industrial PTO shaft component'),
('RW-341', 'PTO Shaft RW 341', '300 Series', 'Industrial PTO shaft component'),
('RW-346A', 'PTO Shaft RW 346 A', '300 Series', 'Industrial PTO shaft component'),
('RW-347', 'PTO Shaft RW 347', '300 Series', 'Industrial PTO shaft component'),
('RW-348', 'PTO Shaft RW 348', '300 Series', 'Industrial PTO shaft component'),
('RW-349', 'PTO Shaft RW 349', '300 Series', 'Industrial PTO shaft component'),
('RW-350', 'PTO Shaft RW 350', '300 Series', 'Industrial PTO shaft component'),
('RW-351', 'PTO Shaft RW 351', '300 Series', 'Industrial PTO shaft component'),
('RW-356A', 'PTO Shaft RW 356 A', '300 Series', 'Industrial PTO shaft component'),
('RW-357', 'PTO Shaft RW 357', '300 Series', 'Industrial PTO shaft component'),
('RW-358', 'PTO Shaft RW 358', '300 Series', 'Industrial PTO shaft component'),
('RW-359', 'PTO Shaft RW 359', '300 Series', 'Industrial PTO shaft component'),
('RW-360', 'PTO Shaft RW 360', '300 Series', 'Industrial PTO shaft component'),
('RW-361', 'PTO Shaft RW 361', '300 Series', 'Industrial PTO shaft component'),
('RW-436A', 'PTO Shaft RW 436 A', '400 Series', 'Heavy duty PTO shaft component'),
('RW-437', 'PTO Shaft RW 437', '400 Series', 'Heavy duty PTO shaft component'),
('RW-438', 'PTO Shaft RW 438', '400 Series', 'Heavy duty PTO shaft component'),
('RW-439', 'PTO Shaft RW 439', '400 Series', 'Heavy duty PTO shaft component'),
('RW-440', 'PTO Shaft RW 440', '400 Series', 'Heavy duty PTO shaft component'),
('RW-441', 'PTO Shaft RW 441', '400 Series', 'Heavy duty PTO shaft component'),
('RW-446A', 'PTO Shaft RW 446 A', '400 Series', 'Heavy duty PTO shaft component'),
('RW-447', 'PTO Shaft RW 447', '400 Series', 'Heavy duty PTO shaft component'),
('RW-448', 'PTO Shaft RW 448', '400 Series', 'Heavy duty PTO shaft component'),
('RW-449', 'PTO Shaft RW 449', '400 Series', 'Heavy duty PTO shaft component'),
('RW-450', 'PTO Shaft RW 450', '400 Series', 'Heavy duty PTO shaft component'),
('RW-451', 'PTO Shaft RW 451', '400 Series', 'Heavy duty PTO shaft component'),
('RW-456A', 'PTO Shaft RW 456 A', '400 Series', 'Heavy duty PTO shaft component'),
('RW-457', 'PTO Shaft RW 457', '400 Series', 'Heavy duty PTO shaft component'),
('RW-458', 'PTO Shaft RW 458', '400 Series', 'Heavy duty PTO shaft component'),
('RW-459', 'PTO Shaft RW 459', '400 Series', 'Heavy duty PTO shaft component'),
('RW-460', 'PTO Shaft RW 460', '400 Series', 'Heavy duty PTO shaft component'),
('RW-461', 'PTO Shaft RW 461', '400 Series', 'Heavy duty PTO shaft component'),
('RW-536A', 'PTO Shaft RW 536 A', '500 Series', 'Extra heavy duty PTO shaft component'),
('RW-537', 'PTO Shaft RW 537', '500 Series', 'Extra heavy duty PTO shaft component'),
('RW-538', 'PTO Shaft RW 538', '500 Series', 'Extra heavy duty PTO shaft component'),
('RW-539', 'PTO Shaft RW 539', '500 Series', 'Extra heavy duty PTO shaft component'),
('RW-540', 'PTO Shaft RW 540', '500 Series', 'Extra heavy duty PTO shaft component'),
('RW-541', 'PTO Shaft RW 541', '500 Series', 'Extra heavy duty PTO shaft component');

-- Generic stages for all parts (7 standard stages)
-- Stage 1: Incoming
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Incoming', 1, 'receiving', 'RECV-01', FALSE FROM parts;

-- Stage 2: CNC-1
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'CNC-1', 2, 'machining', 'CNC-101', FALSE FROM parts;

-- Stage 3: Drilling
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Drilling', 3, 'machining', 'DRILL-201', FALSE FROM parts;

-- Stage 4: Broaching
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Broaching', 4, 'machining', 'BROACH-301', FALSE FROM parts;

-- Stage 5: Heat Treatment
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Heat Treatment', 5, 'heat_treatment', 'HT-401', TRUE FROM parts;

-- Stage 6: Quality Check
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Quality Check', 6, 'quality', 'QC-501', TRUE FROM parts;

-- Stage 7: Finished Goods
INSERT INTO stages (part_id, stage_name, stage_order, stage_type, machine_code, requires_qc)
SELECT id, 'Finished Goods', 7, 'finished', 'FG-601', FALSE FROM parts;

SELECT 'Parts insertion complete' as Status;
SELECT COUNT(*) as Total_Parts FROM parts;
SELECT COUNT(*) as Total_Stages FROM stages;
SELECT part_number, COUNT(s.id) as stages_count 
FROM parts p 
JOIN stages s ON p.id = s.part_id 
GROUP BY p.id 
LIMIT 5;
