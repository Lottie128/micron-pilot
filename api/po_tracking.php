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
                
                // Get stage-wise progress for each item
                foreach ($items as &$item) {
                    $stmt = $conn->prepare("
                        SELECT 
                            s.stage_name,
                            s.stage_order,
                            COALESCE(SUM(so.input_quantity), 0) as input_qty,
                            COALESCE(SUM(so.output_quantity), 0) as output_qty,
                            COALESCE(SUM(so.rejected_quantity), 0) as rejected_qty,
                            COALESCE(SUM(so.rework_quantity), 0) as rework_qty,
                            MAX(so.completed_at) as completed_at,
                            CASE 
                                WHEN COUNT(CASE WHEN so.status = 'completed' THEN 1 END) > 0 THEN 'completed'
                                WHEN COUNT(CASE WHEN so.status = 'in_progress' THEN 1 END) > 0 THEN 'in_progress'
                                ELSE 'not_started'
                            END as stage_status
                        FROM stages s
                        LEFT JOIN stage_operations so ON s.id = so.stage_id AND so.po_item_id = ?
                        WHERE s.part_id = ?
                        GROUP BY s.id
                        ORDER BY s.stage_order
                    ");
                    $stmt->execute([$item['id'], $item['part_id']]);
                    $item['stage_progress'] = $stmt->fetchAll();
                    
                    // Get current bin location
                    $stmt = $conn->prepare("
                        SELECT b.bin_barcode, b.bin_name, b.location, bi.quantity
                        FROM bin_inventory bi
                        JOIN bins b ON bi.bin_id = b.id
                        WHERE bi.po_item_id = ? AND bi.quantity > 0
                        ORDER BY bi.last_updated DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$item['id']]);
                    $item['current_bin'] = $stmt->fetch();
                    
                    // Get recent transfers
                    $stmt = $conn->prepare("
                        SELECT 
                            bt.*,
                            b1.bin_barcode as from_barcode,
                            b2.bin_barcode as to_barcode,
                            s1.stage_name as from_stage,
                            s2.stage_name as to_stage
                        FROM bin_transfers bt
                        LEFT JOIN bins b1 ON bt.from_bin_id = b1.id
                        JOIN bins b2 ON bt.to_bin_id = b2.id
                        LEFT JOIN stages s1 ON bt.from_stage_id = s1.id
                        JOIN stages s2 ON bt.to_stage_id = s2.id
                        WHERE bt.po_item_id = ?
                        ORDER BY bt.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$item['id']]);
                    $item['recent_transfers'] = $stmt->fetchAll();
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
        // Create new PO
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['po_number']) || !isset($data['items'])) {
            sendError('Missing required fields: po_number, items');
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
            
            // Add items
            foreach ($data['items'] as $item) {
                // Get first stage for this part
                $stmt = $conn->prepare("
                    SELECT id FROM stages 
                    WHERE part_id = ? 
                    ORDER BY stage_order ASC 
                    LIMIT 1
                ");
                $stmt->execute([$item['part_id']]);
                $first_stage = $stmt->fetch();
                
                $stmt = $conn->prepare("
                    INSERT INTO po_items
                    (po_id, part_id, ordered_quantity, current_stage_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $po_id,
                    $item['part_id'],
                    $item['quantity'],
                    $first_stage['id'] ?? null
                ]);
            }
            
            $conn->commit();
            
            sendJSON([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'po_id' => $po_id
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
