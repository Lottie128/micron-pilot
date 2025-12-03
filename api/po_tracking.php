<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all purchase orders
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
        
        if ($has_allocated) {
            $query = "
                SELECT po.*, 
                       COUNT(DISTINCT poi.id) as total_items,
                       SUM(poi.ordered_quantity) as total_ordered,
                       SUM(poi.produced_quantity) as total_produced,
                       SUM(poi.rejected_quantity) as total_rejected,
                       SUM(poi.rework_quantity) as total_rework,
                       SUM(poi.allocated_quantity) as total_allocated,
                       SUM(poi.ordered_quantity - poi.allocated_quantity) as total_remaining
                FROM purchase_orders po
                LEFT JOIN po_items poi ON po.id = poi.po_id
                GROUP BY po.id
                ORDER BY po.order_date DESC
            ";
        } else {
            // Fallback if column doesn't exist
            $query = "
                SELECT po.*, 
                       COUNT(DISTINCT poi.id) as total_items,
                       SUM(poi.ordered_quantity) as total_ordered,
                       SUM(poi.produced_quantity) as total_produced,
                       SUM(poi.rejected_quantity) as total_rejected,
                       SUM(poi.rework_quantity) as total_rework,
                       0 as total_allocated,
                       SUM(poi.ordered_quantity) as total_remaining
                FROM purchase_orders po
                LEFT JOIN po_items poi ON po.id = poi.po_id
                GROUP BY po.id
                ORDER BY po.order_date DESC
            ";
        }
        
        $stmt = $conn->query($query);
        $purchase_orders = $stmt->fetchAll();
        
        sendJSON([
            'success' => true,
            'purchase_orders' => $purchase_orders,
            'has_allocation_tracking' => $has_allocated
        ]);
    } catch (Exception $e) {
        error_log('PO tracking error: ' . $e->getMessage());
        sendError('Failed to fetch purchase orders', 500, $e->getMessage());
    }
    
} elseif ($method === 'POST') {
    // Create new purchase order
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $conn->beginTransaction();
        
        // Create purchase order
        $stmt = $conn->prepare("
            INSERT INTO purchase_orders (po_number, customer_name, order_date, delivery_date, notes, status)
            VALUES (?, ?, ?, ?, ?, 'in_progress')
        ");
        $stmt->execute([
            $data['po_number'],
            $data['customer_name'],
            $data['order_date'],
            $data['delivery_date'],
            $data['notes'] ?? null
        ]);
        
        $po_id = $conn->lastInsertId();
        
        // Check if allocated_quantity column exists
        $check_column = $conn->query("
            SELECT COUNT(*) as col_exists 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'po_items' 
            AND COLUMN_NAME = 'allocated_quantity'
        ");
        $has_allocated = $check_column->fetch()['col_exists'] > 0;
        
        // Create PO items
        foreach ($data['items'] as $item) {
            $part_id = $item['part_id'];
            $quantity = $item['quantity'];
            
            // Get first stage for this part
            $stage_stmt = $conn->prepare("
                SELECT id FROM stages 
                WHERE part_id = ? AND stage_order = 1
                ORDER BY stage_order ASC
                LIMIT 1
            ");
            $stage_stmt->execute([$part_id]);
            $first_stage = $stage_stmt->fetch();
            
            if (!$first_stage) {
                throw new Exception("No stages found for part ID: $part_id");
            }
            
            // Create PO item
            if ($has_allocated) {
                $item_stmt = $conn->prepare("
                    INSERT INTO po_items 
                    (po_id, part_id, ordered_quantity, allocated_quantity, produced_quantity, current_stage_id, status)
                    VALUES (?, ?, ?, 0, 0, ?, 'not_started')
                ");
                $item_stmt->execute([$po_id, $part_id, $quantity, $first_stage['id']]);
            } else {
                $item_stmt = $conn->prepare("
                    INSERT INTO po_items 
                    (po_id, part_id, ordered_quantity, produced_quantity, current_stage_id, status)
                    VALUES (?, ?, ?, 0, ?, 'not_started')
                ");
                $item_stmt->execute([$po_id, $part_id, $quantity, $first_stage['id']]);
            }
        }
        
        $conn->commit();
        
        sendJSON([
            'success' => true,
            'message' => 'Purchase order created successfully',
            'po_id' => $po_id,
            'po_number' => $data['po_number']
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("PO creation error: " . $e->getMessage());
        sendError('Failed to create purchase order', 500, $e->getMessage());
    }
    
} else {
    sendError('Method not allowed', 405);
}
?>
