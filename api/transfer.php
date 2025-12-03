<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Required fields
if (!isset($data['from_barcode']) || !isset($data['to_barcode']) || !isset($data['po_item_id'])) {
    sendError('Missing required fields: from_barcode, to_barcode, po_item_id');
}

$from_barcode = $data['from_barcode'];
$to_barcode = $data['to_barcode'];
$po_item_id = $data['po_item_id'];
$rejected_qty = $data['rejected_quantity'] ?? 0;
$rework_qty = $data['rework_quantity'] ?? 0;
$scanned_by = $data['scanned_by'] ?? 'Operator';
$notes = $data['notes'] ?? '';

try {
    $conn->beginTransaction();
    
    // Get FROM bin
    $stmt = $conn->prepare("SELECT * FROM bins WHERE bin_barcode = ?");
    $stmt->execute([$from_barcode]);
    $from_bin = $stmt->fetch();
    
    if (!$from_bin) {
        throw new Exception('Source bin not found: ' . $from_barcode);
    }
    
    // Get TO bin
    $stmt = $conn->prepare("SELECT * FROM bins WHERE bin_barcode = ?");
    $stmt->execute([$to_barcode]);
    $to_bin = $stmt->fetch();
    
    if (!$to_bin) {
        throw new Exception('Destination bin not found: ' . $to_barcode);
    }
    
    // Get current inventory in FROM bin
    $stmt = $conn->prepare("
        SELECT bi.*, s.stage_order, s.part_id, s.stage_name
        FROM bin_inventory bi
        JOIN stages s ON bi.stage_id = s.id
        WHERE bi.bin_id = ? AND bi.po_item_id = ?
        ORDER BY s.stage_order DESC
        LIMIT 1
    ");
    $stmt->execute([$from_bin['id'], $po_item_id]);
    $current_inventory = $stmt->fetch();
    
    if (!$current_inventory) {
        throw new Exception('No material found in source bin for this PO item');
    }
    
    $current_qty = $current_inventory['quantity'];
    $current_stage_id = $current_inventory['stage_id'];
    $part_id = $current_inventory['part_id'];
    
    // Calculate transfer quantity (current - rejects - rework)
    $transfer_qty = $current_qty - $rejected_qty - $rework_qty;
    
    if ($transfer_qty < 0) {
        throw new Exception('Invalid quantities: transfer would be negative');
    }
    
    if ($rejected_qty + $rework_qty > $current_qty) {
        throw new Exception('Rejected + Rework cannot exceed current quantity');
    }
    
    // Get next stage
    $stmt = $conn->prepare("
        SELECT * FROM stages 
        WHERE part_id = ? AND stage_order > ?
        ORDER BY stage_order ASC
        LIMIT 1
    ");
    $stmt->execute([$part_id, $current_inventory['stage_order']]);
    $next_stage = $stmt->fetch();
    
    if (!$next_stage) {
        // This is the last stage - stay at current stage
        $next_stage_id = $current_stage_id;
        $next_stage_name = $current_inventory['stage_name'] . ' (Final)';
    } else {
        $next_stage_id = $next_stage['id'];
        $next_stage_name = $next_stage['stage_name'];
    }
    
    // Check destination bin capacity
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) as used
        FROM bin_inventory
        WHERE bin_id = ?
    ");
    $stmt->execute([$to_bin['id']]);
    $usage = $stmt->fetch();
    $available_space = $to_bin['capacity'] - $usage['used'];
    
    if ($transfer_qty > $available_space) {
        throw new Exception("Destination bin has insufficient capacity. Available: $available_space, Needed: $transfer_qty");
    }
    
    // Remove from source bin
    $stmt = $conn->prepare("
        DELETE FROM bin_inventory 
        WHERE bin_id = ? AND po_item_id = ? AND stage_id = ?
    ");
    $stmt->execute([$from_bin['id'], $po_item_id, $current_stage_id]);
    
    // Add to destination bin (only good quantity)
    if ($transfer_qty > 0) {
        $stmt = $conn->prepare("
            INSERT INTO bin_inventory 
            (bin_id, po_item_id, stage_id, quantity, good_quantity)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            quantity = quantity + VALUES(quantity),
            good_quantity = good_quantity + VALUES(good_quantity)
        ");
        $stmt->execute([$to_bin['id'], $po_item_id, $next_stage_id, $transfer_qty, $transfer_qty]);
    }
    
    // Record movement in movements table (correct table name)
    $stmt = $conn->prepare("
        INSERT INTO movements
        (po_item_id, from_bin_id, to_bin_id, from_stage_id, to_stage_id, 
         quantity_moved, rejected_count, rework_count, transfer_type, scanned_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'stage_transfer', ?, ?)
    ");
    $stmt->execute([
        $po_item_id,
        $from_bin['id'],
        $to_bin['id'],
        $current_stage_id,
        $next_stage_id,
        $transfer_qty,
        $rejected_qty,
        $rework_qty,
        $scanned_by,
        $notes
    ]);
    
    // Update PO item totals
    $stmt = $conn->prepare("
        UPDATE po_items
        SET produced_quantity = produced_quantity + ?,
            rejected_quantity = rejected_quantity + ?,
            rework_quantity = rework_quantity + ?,
            current_stage_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$transfer_qty, $rejected_qty, $rework_qty, $next_stage_id, $po_item_id]);
    
    // Complete stage operation for source stage
    $stmt = $conn->prepare("
        UPDATE stage_operations
        SET output_quantity = output_quantity + ?,
            good_quantity = good_quantity + ?,
            rejected_quantity = rejected_quantity + ?,
            rework_quantity = rework_quantity + ?,
            completed_at = NOW(),
            status = 'completed'
        WHERE po_item_id = ? AND stage_id = ? AND bin_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([
        $transfer_qty,
        $transfer_qty,
        $rejected_qty,
        $rework_qty,
        $po_item_id,
        $current_stage_id,
        $from_bin['id']
    ]);
    
    // Create new stage operation for destination (if moving to next stage)
    if ($next_stage && $transfer_qty > 0) {
        $stmt = $conn->prepare("
            INSERT INTO stage_operations
            (po_item_id, stage_id, bin_id, input_quantity, operator_name, status)
            VALUES (?, ?, ?, ?, ?, 'in_progress')
        ");
        $stmt->execute([$po_item_id, $next_stage_id, $to_bin['id'], $transfer_qty, $scanned_by]);
    }
    
    $conn->commit();
    
    sendJSON([
        'success' => true,
        'message' => 'Transfer completed successfully',
        'transfer' => [
            'from_bin' => $from_barcode,
            'to_bin' => $to_barcode,
            'from_stage' => $current_inventory['stage_name'],
            'to_stage' => $next_stage_name,
            'quantity' => $transfer_qty,
            'rejected' => $rejected_qty,
            'rework' => $rework_qty
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log('Transfer error: ' . $e->getMessage());
    sendError('Transfer failed: ' . $e->getMessage(), 500);
}
?>
