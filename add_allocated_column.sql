-- Quick fix: Add allocated_quantity column to po_items
-- Run this in phpMyAdmin SQL tab

USE micron_tracking;

-- Add the column
ALTER TABLE po_items 
ADD COLUMN allocated_quantity INT DEFAULT 0 AFTER ordered_quantity;

-- Initialize with 0 for existing records
UPDATE po_items SET allocated_quantity = 0;

-- Verify
SELECT 
    po.po_number,
    p.part_number,
    poi.ordered_quantity,
    poi.allocated_quantity,
    (poi.ordered_quantity - poi.allocated_quantity) as remaining
FROM po_items poi
JOIN purchase_orders po ON poi.po_id = po.id
JOIN parts p ON poi.part_id = p.id;

SELECT 'Column added successfully!' as Status;
