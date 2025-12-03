<?php
require_once 'config.php';

$conn = getDBConnection();

try {
    // Check if allocated_quantity column exists
    $check_column = $conn->query("
        SELECT COUNT(*) as col_exists 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'po_items' 
        AND COLUMN_NAME = 'allocated_quantity'
    ");
    $has_allocated = $check_column->fetch()['col_exists'] > 0;
    
    // Get all purchase orders
    if ($has_allocated) {
        $query = "
            SELECT po.*, 
                   COUNT(DISTINCT poi.id) as total_items,
                   SUM(poi.ordered_quantity) as total_ordered,
                   SUM(poi.allocated_quantity) as total_allocated,
                   SUM(poi.produced_quantity) as total_produced,
                   SUM(poi.rejected_quantity) as total_rejected,
                   SUM(poi.rework_quantity) as total_rework
            FROM purchase_orders po
            LEFT JOIN po_items poi ON po.id = poi.po_id
            GROUP BY po.id
            ORDER BY po.order_date DESC
        ";
    } else {
        $query = "
            SELECT po.*, 
                   COUNT(DISTINCT poi.id) as total_items,
                   SUM(poi.ordered_quantity) as total_ordered,
                   0 as total_allocated,
                   SUM(poi.produced_quantity) as total_produced,
                   SUM(poi.rejected_quantity) as total_rejected,
                   SUM(poi.rework_quantity) as total_rework
            FROM purchase_orders po
            LEFT JOIN po_items poi ON po.id = poi.po_id
            GROUP BY po.id
            ORDER BY po.order_date DESC
        ";
    }
    
    $stmt = $conn->query($query);
    $purchase_orders = $stmt->fetchAll();
    
    // For each PO, get items and their bin assignments
    foreach ($purchase_orders as &$po) {
        // Get PO items
        if ($has_allocated) {
            $items_query = "
                SELECT poi.*,
                       p.part_number,
                       p.part_name
                FROM po_items poi
                JOIN parts p ON poi.part_id = p.id
                WHERE poi.po_id = ?
            ";
        } else {
            $items_query = "
                SELECT poi.*,
                       0 as allocated_quantity,
                       p.part_number,
                       p.part_name
                FROM po_items poi
                JOIN parts p ON poi.part_id = p.id
                WHERE poi.po_id = ?
            ";
        }
        
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->execute([$po['id']]);
        $items = $items_stmt->fetchAll();
        
        // For each item, get bin assignments
        foreach ($items as &$item) {
            $bins_query = "
                SELECT bi.*,
                       b.bin_barcode,
                       b.bin_name,
                       b.location,
                       b.zone,
                       s.stage_name
                FROM bin_inventory bi
                JOIN bins b ON bi.bin_id = b.id
                JOIN stages s ON bi.stage_id = s.id
                WHERE bi.po_item_id = ?
                ORDER BY b.bin_barcode ASC
            ";
            
            $bins_stmt = $conn->prepare($bins_query);
            $bins_stmt->execute([$item['id']]);
            $item['bins'] = $bins_stmt->fetchAll();
        }
        
        $po['items'] = $items;
    }
    
    sendJSON([
        'success' => true,
        'purchase_orders' => $purchase_orders,
        'has_allocation_tracking' => $has_allocated
    ]);
    
} catch (Exception $e) {
    error_log('PO detail error: ' . $e->getMessage());
    sendError('Failed to fetch PO details', 500, $e->getMessage());
}
?>
