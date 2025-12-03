<?php
require_once 'config.php';

$conn = getDBConnection();

try {
    $query = "
        SELECT poi.*,
               po.po_number,
               p.part_number,
               p.part_name,
               (poi.ordered_quantity - poi.allocated_quantity) as remaining_quantity
        FROM po_items poi
        JOIN purchase_orders po ON poi.po_id = po.id
        JOIN parts p ON poi.part_id = p.id
        WHERE (poi.ordered_quantity - poi.allocated_quantity) > 0
        ORDER BY po.order_date DESC, poi.id ASC
    ";
    
    $stmt = $conn->query($query);
    $po_items = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'po_items' => $po_items
    ]);
    
} catch (Exception $e) {
    error_log('PO items error: ' . $e->getMessage());
    sendError('Failed to fetch PO items', 500, $e->getMessage());
}
?>
