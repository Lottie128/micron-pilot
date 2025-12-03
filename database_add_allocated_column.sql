-- Add allocated_quantity column to po_items table
USE micron_tracking;

-- Add column if it doesn't exist
ALTER TABLE po_items 
ADD COLUMN IF NOT EXISTS allocated_quantity INT DEFAULT 0 AFTER ordered_quantity;

-- Update existing records to set allocated_quantity = 0
UPDATE po_items SET allocated_quantity = 0 WHERE allocated_quantity IS NULL;

-- Show updated structure
DESCRIBE po_items;

SELECT 'Column added: allocated_quantity tracks how much material is allocated to bins' as Status;
