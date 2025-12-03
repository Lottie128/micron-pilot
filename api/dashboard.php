<?php
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get dashboard overview - PO-based tracking
    $po_id = $_GET['po_id'] ?? null;
    
    // Get all Purchase Orders with their items
    $po_query = "
        SELECT po.*, 
               COUNT(DISTINCT poi.id) as total_items,
               SUM(poi.ordered_quantity) as total_ordered,
               SUM(poi.produced_quantity) as total_produced,
               SUM(poi.rejected_quantity) as total_rejected,
               SUM(poi.rework_quantity) as total_rework
        FROM purchase_orders po
        LEFT JOIN po_items poi ON po.id = poi.po_id
    ";
    
    if ($po_id) {
        $po_query .= " WHERE po.id = ?";
    }
    
    $po_query .= "
        GROUP BY po.id
        ORDER BY po.order_date DESC
    ";
    
    $po_stmt = $conn->prepare($po_query);
    
    if ($po_id) {
        $po_stmt->execute([$po_id]);
    } else {
        $po_stmt->execute();
    }
    
    $purchase_orders = $po_stmt->fetchAll();
    
    // Get detailed items for each PO
    foreach ($purchase_orders as &$po) {
        // Get PO items
        $items_stmt = $conn->prepare("
            SELECT poi.*, 
                   p.part_number, p.part_name, p.category,
                   s.stage_name as current_stage_name, s.stage_order
            FROM po_items poi
            JOIN parts p ON poi.part_id = p.id
            LEFT JOIN stages s ON poi.current_stage_id = s.id
            WHERE poi.po_id = ?
            ORDER BY p.part_number
        ");
        $items_stmt->execute([$po['id']]);
        $po['items'] = $items_stmt->fetchAll();
        
        // For each item, get stage-wise inventory
        foreach ($po['items'] as &$item) {
            // Get all stages for this part
            $stages_stmt = $conn->prepare("
                SELECT s.id as stage_id, s.stage_name, s.stage_order, s.stage_type,
                       COALESCE(SUM(bi.quantity), 0) as quantity,
                       COALESCE(SUM(bi.good_quantity), 0) as good_quantity,
                       COALESCE(SUM(bi.rework_quantity), 0) as rework_quantity,
                       COALESCE(SUM(bi.rejected_quantity), 0) as rejected_quantity,
                       COUNT(DISTINCT bi.bin_id) as bins_count
                FROM stages s
                LEFT JOIN bin_inventory bi ON s.id = bi.stage_id AND bi.po_item_id = ?
                WHERE s.part_id = ?
                GROUP BY s.id
                ORDER BY s.stage_order
            ");
            $stages_stmt->execute([$item['id'], $item['part_id']]);
            $item['stage_inventory'] = $stages_stmt->fetchAll();
            
            // Get bin details for each stage
            foreach ($item['stage_inventory'] as &$stage) {
                $bin_stmt = $conn->prepare("
                    SELECT b.bin_barcode, b.bin_name, b.zone,
                           bi.quantity, bi.good_quantity, bi.rework_quantity, bi.rejected_quantity,
                           bi.last_updated
                    FROM bin_inventory bi
                    JOIN bins b ON bi.bin_id = b.id
                    WHERE bi.po_item_id = ? AND bi.stage_id = ? AND bi.quantity > 0
                    ORDER BY b.bin_barcode
                ");
                $bin_stmt->execute([$item['id'], $stage['stage_id']]);
                $stage['bins'] = $bin_stmt->fetchAll();
            }
            
            // Get recent movements for this item
            $mov_stmt = $conn->prepare("
                SELECT m.*,
                       s1.stage_name as from_stage,
                       s2.stage_name as to_stage,
                       b1.bin_barcode as from_bin,
                       b2.bin_barcode as to_bin
                FROM movements m
                LEFT JOIN stages s1 ON m.from_stage_id = s1.id
                JOIN stages s2 ON m.to_stage_id = s2.id
                LEFT JOIN bins b1 ON m.from_bin_id = b1.id
                JOIN bins b2 ON m.to_bin_id = b2.id
                WHERE m.po_item_id = ?
                ORDER BY m.movement_timestamp DESC
                LIMIT 10
            ");
            $mov_stmt->execute([$item['id']]);
            $item['recent_movements'] = $mov_stmt->fetchAll();
        }
    }
    
    // Get parts list (for sidebar)
    $parts_stmt = $conn->query("
        SELECT p.*,
               COUNT(DISTINCT s.id) as total_stages
        FROM parts p
        LEFT JOIN stages s ON p.id = s.part_id
        GROUP BY p.id
        ORDER BY p.part_number
    ");
    $parts = $parts_stmt->fetchAll();
    
    // Get bins summary by zone
    $bins_stmt = $conn->query("
        SELECT zone, COUNT(*) as bin_count
        FROM bins
        WHERE status = 'active'
        GROUP BY zone
        ORDER BY zone
    ");
    $bins_summary = $bins_stmt->fetchAll();
    
    // Overall statistics
    $stats = [
        'total_pos' => count($purchase_orders),
        'total_parts' => count($parts),
        'total_bins' => array_sum(array_column($bins_summary, 'bin_count')),
        'total_ordered' => (int)array_sum(array_column($purchase_orders, 'total_ordered')),
        'total_produced' => (int)array_sum(array_column($purchase_orders, 'total_produced')),
        'total_rejected' => (int)array_sum(array_column($purchase_orders, 'total_rejected')),
        'total_rework' => (int)array_sum(array_column($purchase_orders, 'total_rework'))
    ];
    
    sendJSON([
        'success' => true,
        'stats' => $stats,
        'purchase_orders' => $purchase_orders,
        'parts' => $parts,
        'bins_summary' => $bins_summary
    ]);
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    sendError('Database error', 500, $e->getMessage());
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    sendError('Server error', 500, $e->getMessage());
}
?>
