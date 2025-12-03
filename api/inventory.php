<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get inventory for a specific part
        $part_id = $_GET['part_id'] ?? null;
        
        if (!$part_id) {
            // Get all inventory
            $stmt = $conn->query("
                SELECT i.*, 
                       p.part_number, p.part_name,
                       s.stage_name, s.stage_order,
                       b.bin_code, b.bin_name
                FROM inventory i
                JOIN parts p ON i.part_id = p.id
                JOIN stages s ON i.stage_id = s.id
                JOIN bins b ON i.bin_id = b.id
                WHERE i.quantity > 0
                ORDER BY p.part_number, s.stage_order
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT i.*, 
                       s.stage_name, s.stage_order,
                       b.bin_code, b.bin_name
                FROM inventory i
                JOIN stages s ON i.stage_id = s.id
                JOIN bins b ON i.bin_id = b.id
                WHERE i.part_id = ? AND i.quantity > 0
                ORDER BY s.stage_order
            ");
            $stmt->execute([$part_id]);
        }
        
        $inventory = $stmt->fetchAll();
        sendJSON(['inventory' => $inventory]);
        break;
        
    case 'POST':
        // Update inventory (manual input or auto transfer)
        $data = json_decode(file_get_contents('php://input'), true);
        
        $action = $data['action'] ?? 'update';
        
        try {
            $conn->beginTransaction();
            
            if ($action === 'update') {
                // Manual inventory update
                $stmt = $conn->prepare("
                    INSERT INTO inventory 
                    (part_id, stage_id, bin_id, quantity, good_quantity, rework_quantity, rejected_quantity)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = VALUES(quantity),
                    good_quantity = VALUES(good_quantity),
                    rework_quantity = VALUES(rework_quantity),
                    rejected_quantity = VALUES(rejected_quantity)
                ");
                
                $stmt->execute([
                    $data['part_id'],
                    $data['stage_id'],
                    $data['bin_id'],
                    $data['quantity'],
                    $data['good_quantity'] ?? 0,
                    $data['rework_quantity'] ?? 0,
                    $data['rejected_quantity'] ?? 0
                ]);
                
            } elseif ($action === 'transfer') {
                // Auto bin transfer
                $from_stage_id = $data['from_stage_id'];
                $to_stage_id = $data['to_stage_id'];
                $from_bin_id = $data['from_bin_id'];
                $to_bin_id = $data['to_bin_id'];
                $quantity = $data['quantity'];
                $part_id = $data['part_id'];
                
                // Deduct from source
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET quantity = quantity - ?,
                        good_quantity = good_quantity - ?
                    WHERE part_id = ? AND stage_id = ? AND bin_id = ?
                ");
                $stmt->execute([$quantity, $quantity, $part_id, $from_stage_id, $from_bin_id]);
                
                // Add to destination
                $stmt = $conn->prepare("
                    INSERT INTO inventory 
                    (part_id, stage_id, bin_id, quantity, good_quantity)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    good_quantity = good_quantity + VALUES(good_quantity)
                ");
                $stmt->execute([$part_id, $to_stage_id, $to_bin_id, $quantity, $quantity]);
                
                // Record movement
                $stmt = $conn->prepare("
                    INSERT INTO movements 
                    (part_id, from_stage_id, to_stage_id, from_bin_id, to_bin_id, quantity, movement_type, notes)
                    VALUES (?, ?, ?, ?, ?, ?, 'transfer', ?)
                ");
                $stmt->execute([
                    $part_id, $from_stage_id, $to_stage_id, 
                    $from_bin_id, $to_bin_id, $quantity,
                    $data['notes'] ?? 'Auto transfer'
                ]);
                
            } elseif ($action === 'rework') {
                // Handle rework
                $part_id = $data['part_id'];
                $stage_id = $data['stage_id'];
                $bin_id = $data['bin_id'];
                $rework_qty = $data['rework_quantity'];
                
                // Calculate: rework items go back to previous stage
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET rework_quantity = rework_quantity + ?,
                        quantity = quantity - ?
                    WHERE part_id = ? AND stage_id = ? AND bin_id = ?
                ");
                $stmt->execute([$rework_qty, $rework_qty, $part_id, $stage_id, $bin_id]);
                
                // Get rework bin
                $stmt = $conn->prepare("SELECT id FROM bins WHERE bin_code = 'BIN-REWORK'");
                $stmt->execute();
                $rework_bin = $stmt->fetch();
                
                // Add to rework bin
                $stmt = $conn->prepare("
                    INSERT INTO inventory 
                    (part_id, stage_id, bin_id, quantity, rework_quantity)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    rework_quantity = rework_quantity + VALUES(rework_quantity)
                ");
                $stmt->execute([$part_id, $stage_id, $rework_bin['id'], $rework_qty, $rework_qty]);
            }
            
            $conn->commit();
            sendJSON(['message' => 'Inventory updated successfully']);
            
        } catch (Exception $e) {
            $conn->rollBack();
            sendError('Failed to update inventory: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>
