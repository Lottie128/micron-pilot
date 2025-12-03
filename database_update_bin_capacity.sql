-- Update bin capacities to 200 units max
USE micron_tracking;

-- Update all bins to have 200 capacity
UPDATE bins SET capacity = 200;

-- Show updated bins
SELECT zone, COUNT(*) as bin_count, capacity 
FROM bins 
GROUP BY zone, capacity 
ORDER BY zone;

SELECT 'Bin capacities updated to 200 units each' as Status;
