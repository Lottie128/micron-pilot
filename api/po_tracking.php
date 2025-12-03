<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all purchase orders with items
    try {
        $query = "
            SELECT po.*, 
                   COUNT(DISTINCT poi.id) as total_items,
                   SUM(poi.ordered_quantity) as total_ordered,
                   SUM(poi.produced_quantity) as total_produced,
                   SUM(poi.rejected_quantity) as total_rejected,
                   SUM(poi.rework_quantity) as total_rework
            FROM purchase_orders po
            LEFT JOIN po_items poi ON po.id = poi.po_id
            GROUP BY po.id
            ORDER BY po.order_date DESC
        ";
        
        $stmt = $conn->query($query);
        $purchase_orders = $stmt->fetchAll();
        
        sendJSON([
            'success' => true,
            'purchase_orders' => $purchase_orders
        ]);
    } catch (Exception $e) {
        sendError('Failed to fetch purchase orders', 500, $e->getMessage());
    }
    
} elseif ($method === 'POST') {
    // Create new purchase order with auto bin splitting
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
        
        // Create PO items and auto-split into bins
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
            $item_stmt = $conn->prepare("
                INSERT INTO po_items (po_id, part_id, ordered_quantity, current_stage_id, status)
                VALUES (?, ?, ?, ?, 'in_progress')
            ");
            $item_stmt->execute([$po_id, $part_id, $quantity, $first_stage['id']]);
            
            $po_item_id = $conn->lastInsertId();
            
            // AUTO-SPLIT INTO BINS (200 units per bin max)
            $bin_capacity = 200;
            $remaining = $quantity;
            $bin_index = 0;
            
            while ($remaining > 0) {
                $bin_qty = min($remaining, $bin_capacity);
                
                // Find next available bin in INCOMING zone
                $bin_stmt = $conn->prepare("
                    SELECT b.id, b.bin_barcode, b.capacity,
                           COALESCE(SUM(bi.quantity), 0) as used_capacity
                    FROM bins b
                    LEFT JOIN bin_inventory bi ON b.id = bi.bin_id
                    WHERE b.zone = 'INCOMING' AND b.status = 'active'
                    GROUP BY b.id
                    HAVING (b.capacity - used_capacity) >= ?
                    ORDER BY b.bin_barcode ASC
                    LIMIT 1
                ");
                $bin_stmt->execute([$bin_qty]);
                $bin = $bin_stmt->fetch();
                
                if (!$bin) {
                    throw new Exception("No available bins with capacity for $bin_qty units. Please free up bins.");
                }
                
                // Add to bin inventory
                $inv_stmt = $conn->prepare("
                    INSERT INTO bin_inventory (bin_id, po_item_id, stage_id, quantity, good_quantity)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    good_quantity = good_quantity + VALUES(good_quantity)
                ");
                $inv_stmt->execute([
                    $bin['id'],
                    $po_item_id,
                    $first_stage['id'],
                    $bin_qty,
                    $bin_qty
                ]);
                
                // Record movement
                $mov_stmt = $conn->prepare("
                    INSERT INTO movements 
                    (po_item_id, to_bin_id, to_stage_id, quantity_moved, transfer_type, notes)
                    VALUES (?, ?, ?, ?, 'incoming', ?)
                ");
                $mov_stmt->execute([
                    $po_item_id,
                    $bin['id'],
                    $first_stage['id'],
                    $bin_qty,
                    "Auto-split: Bin " . ($bin_index + 1) . " of " . ceil($quantity / $bin_capacity)
                ]);
                
                $remaining -= $bin_qty;
                $bin_index++;
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
