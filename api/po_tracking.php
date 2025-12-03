<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $po_number = $_GET['po_number'] ?? null;
        
        try {
            if ($po_number) {
                // Get specific PO details
                $stmt = $conn->prepare("
                    SELECT * FROM purchase_orders WHERE po_number = ?
                ");
                $stmt->execute([$po_number]);
                $po = $stmt->fetch();
                
                if (!$po) {
                    sendError('Purchase order not found', 404);
                }
                
                // Get PO items with current status
                $stmt = $conn->prepare("
                    SELECT 
                        poi.*,
                        p.part_number,
                        p.part_name,
                        s.stage_name as current_stage,
                        s.stage_order,
                        (SELECT COUNT(*) FROM stages WHERE part_id = p.id) as total_stages
                    FROM po_items poi
                    JOIN parts p ON poi.part_id = p.id
                    LEFT JOIN stages s ON poi.current_stage_id = s.id
                    WHERE poi.po_id = ?
                ");
                $stmt->execute([$po['id']]);
                $items = $stmt->fetchAll();
                
                // Get current bin location for each item
                foreach ($items as &$item) {
                    $stmt = $conn->prepare("
                        SELECT b.bin_barcode, b.bin_name, b.location, b.zone, bi.quantity
                        FROM bin_inventory bi
                        JOIN bins b ON bi.bin_id = b.id
                        WHERE bi.po_item_id = ? AND bi.quantity > 0
                        ORDER BY bi.last_updated DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$item['id']]);
                    $item['current_bin'] = $stmt->fetch();
                }
                
                $po['items'] = $items;
                
                sendJSON([
                    'success' => true,
                    'purchase_order' => $po
                ]);
                
            } else {
                // Get all POs
                $stmt = $conn->query("
                    SELECT 
                        po.*,
                        COUNT(DISTINCT poi.id) as total_items,
                        SUM(poi.ordered_quantity) as total_ordered,
                        SUM(poi.produced_quantity) as total_produced,
                        SUM(poi.rejected_quantity) as total_rejected
                    FROM purchase_orders po
                    LEFT JOIN po_items poi ON po.id = poi.po_id
                    GROUP BY po.id
                    ORDER BY po.created_at DESC
                ");
                $orders = $stmt->fetchAll();
                
                sendJSON([
                    'success' => true,
                    'purchase_orders' => $orders
                ]);
            }
            
        } catch (Exception $e) {
            sendError('Query failed: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Create new PO with automatic bin assignment
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['po_number']) || !isset($data['items'])) {
            sendError('Missing required fields: po_number, items', 400);
        }
        
        try {
            $conn->beginTransaction();
            
            // Create PO
            $stmt = $conn->prepare("
                INSERT INTO purchase_orders 
                (po_number, customer_name, order_date, delivery_date, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['po_number'],
                $data['customer_name'] ?? '',
                $data['order_date'] ?? date('Y-m-d'),
                $data['delivery_date'] ?? null,
                $data['notes'] ?? ''
            ]);
            
            $po_id = $conn->lastInsertId();
            
            // Get next available incoming bin
            $stmt = $conn->query("
                SELECT id, bin_barcode FROM bins 
                WHERE zone = 'INCOMING' AND status = 'active'
                ORDER BY bin_barcode
                LIMIT 100
            ");
            $available_bins = $stmt->fetchAll();
            $bin_index = 0;
            
            // Add items with automatic bin assignment
            foreach ($data['items'] as $item) {
                // Get first stage for this part (Incoming)
                $stmt = $conn->prepare("
                    SELECT id FROM stages 
                    WHERE part_id = ? 
                    ORDER BY stage_order ASC 
                    LIMIT 1
                ");
                $stmt->execute([$item['part_id']]);
                $first_stage = $stmt->fetch();
                
                if (!$first_stage) {
                    throw new Exception('No stages found for part_id: ' . $item['part_id']);
                }
                
                // Create PO item
                $stmt = $conn->prepare("
                    INSERT INTO po_items
                    (po_id, part_id, ordered_quantity, current_stage_id, status)
                    VALUES (?, ?, ?, ?, 'in_progress')
                ");
                $stmt->execute([
                    $po_id,
                    $item['part_id'],
                    $item['quantity'],
                    $first_stage['id']
                ]);
                
                $po_item_id = $conn->lastInsertId();
                
                // Assign to incoming bin (use provided bin or auto-assign)
                $assigned_bin_id = null;
                
                if (isset($item['bin_id']) && !empty($item['bin_id'])) {
                    // Use specified bin
                    $assigned_bin_id = $item['bin_id'];
                } else {
                    // Auto-assign from available incoming bins
                    if ($bin_index < count($available_bins)) {
                        $assigned_bin_id = $available_bins[$bin_index]['id'];
                        $bin_index++;
                    } else {
                        // Fallback to first incoming bin if we run out
                        $assigned_bin_id = $available_bins[0]['id'];
                    }
                }
                
                // Create bin inventory record
                $stmt = $conn->prepare("
                    INSERT INTO bin_inventory
                    (bin_id, po_item_id, stage_id, quantity, good_quantity)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    good_quantity = good_quantity + VALUES(good_quantity)
                ");
                $stmt->execute([
                    $assigned_bin_id,
                    $po_item_id,
                    $first_stage['id'],
                    $item['quantity'],
                    $item['quantity']
                ]);
                
                // Record initial movement (material received)
                $stmt = $conn->prepare("
                    INSERT INTO movements
                    (po_item_id, to_bin_id, to_stage_id, quantity_moved, transfer_type, notes)
                    VALUES (?, ?, ?, ?, 'incoming', 'Initial material receipt')
                ");
                $stmt->execute([
                    $po_item_id,
                    $assigned_bin_id,
                    $first_stage['id'],
                    $item['quantity']
                ]);
            }
            
            $conn->commit();
            
            sendJSON([
                'success' => true,
                'message' => 'Purchase order created successfully with bin assignments',
                'po_id' => $po_id,
                'po_number' => $data['po_number']
            ], 201);
            
        } catch (Exception $e) {
            $conn->rollBack();
            sendError('Failed to create PO: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>
