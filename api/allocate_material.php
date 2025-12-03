<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Required: bin barcode, po_item_id, quantity to allocate
if (!isset($data['bin_barcode']) || !isset($data['po_item_id'])) {
    sendError('Missing required fields: bin_barcode, po_item_id');
}

$bin_barcode = $data['bin_barcode'];
$po_item_id = $data['po_item_id'];
$requested_qty = $data['quantity'] ?? 200; // Default to bin capacity
$operator = $data['operator'] ?? 'Operator';

try {
    $conn->beginTransaction();
    
    // Get bin info
    $stmt = $conn->prepare("SELECT * FROM bins WHERE bin_barcode = ? AND status = 'active'");
    $stmt->execute([$bin_barcode]);
    $bin = $stmt->fetch();
    
    if (!$bin) {
        throw new Exception('Bin not found or inactive: ' . $bin_barcode);
    }
    
    // Get PO item with remaining quantity
    $stmt = $conn->prepare("
        SELECT poi.*, 
               (poi.ordered_quantity - poi.allocated_quantity) as remaining_qty,
               p.part_number,
               po.po_number,
               s.id as first_stage_id,
               s.stage_name as first_stage_name
        FROM po_items poi
        JOIN parts p ON poi.part_id = p.id
        JOIN purchase_orders po ON poi.po_id = po.id
        JOIN stages s ON poi.part_id = s.part_id AND s.stage_order = 1
        WHERE poi.id = ?
    ");
    $stmt->execute([$po_item_id]);
    $po_item = $stmt->fetch();
    
    if (!$po_item) {
        throw new Exception('PO item not found');
    }
    
    $remaining = $po_item['remaining_qty'];
    
    if ($remaining <= 0) {
        throw new Exception('No material remaining in PO. All ' . $po_item['ordered_quantity'] . ' units already allocated.');
    }
    
    // Check bin available capacity
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) as used
        FROM bin_inventory
        WHERE bin_id = ?
    ");
    $stmt->execute([$bin['id']]);
    $usage = $stmt->fetch();
    $available_space = $bin['capacity'] - $usage['used'];
    
    if ($available_space <= 0) {
        throw new Exception('Bin is full. Capacity: ' . $bin['capacity'] . ', Used: ' . $usage['used']);
    }
    
    // Calculate actual allocation (min of requested, remaining, and bin space)
    $allocate_qty = min($requested_qty, $remaining, $available_space, $bin['capacity']);
    
    if ($allocate_qty <= 0) {
        throw new Exception('Cannot allocate material. Check bin capacity and PO remaining quantity.');
    }
    
    // Update po_items: increase allocated_quantity
    $stmt = $conn->prepare("
        UPDATE po_items
        SET allocated_quantity = allocated_quantity + ?,
            status = CASE 
                WHEN (allocated_quantity + ?) >= ordered_quantity THEN 'in_progress'
                ELSE 'not_started'
            END
        WHERE id = ?
    ");
    $stmt->execute([$allocate_qty, $allocate_qty, $po_item_id]);
    
    // Add to bin_inventory
    $stmt = $conn->prepare("
        INSERT INTO bin_inventory 
        (bin_id, po_item_id, stage_id, quantity, good_quantity)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        quantity = quantity + VALUES(quantity),
        good_quantity = good_quantity + VALUES(good_quantity)
    ");
    $stmt->execute([
        $bin['id'],
        $po_item_id,
        $po_item['first_stage_id'],
        $allocate_qty,
        $allocate_qty
    ]);
    
    // Record movement
    $stmt = $conn->prepare("
        INSERT INTO movements
        (po_item_id, to_bin_id, to_stage_id, quantity_moved, transfer_type, scanned_by, notes)
        VALUES (?, ?, ?, ?, 'incoming', ?, ?)
    ");
    $stmt->execute([
        $po_item_id,
        $bin['id'],
        $po_item['first_stage_id'],
        $allocate_qty,
        $operator,
        "Allocated from PO queue: {$allocate_qty} units"
    ]);
    
    // Create stage operation
    $stmt = $conn->prepare("
        INSERT INTO stage_operations
        (po_item_id, stage_id, bin_id, input_quantity, operator_name, status)
        VALUES (?, ?, ?, ?, ?, 'in_progress')
    ");
    $stmt->execute([
        $po_item_id,
        $po_item['first_stage_id'],
        $bin['id'],
        $allocate_qty,
        $operator
    ]);
    
    $conn->commit();
    
    $new_remaining = $remaining - $allocate_qty;
    
    sendJSON([
        'success' => true,
        'message' => "Material allocated successfully",
        'allocation' => [
            'po_number' => $po_item['po_number'],
            'part_number' => $po_item['part_number'],
            'bin' => $bin_barcode,
            'allocated' => $allocate_qty,
            'remaining_in_po' => $new_remaining,
            'total_ordered' => $po_item['ordered_quantity'],
            'stage' => $po_item['first_stage_name']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log('Allocation error: ' . $e->getMessage());
    sendError('Allocation failed: ' . $e->getMessage(), 500);
}
?>
