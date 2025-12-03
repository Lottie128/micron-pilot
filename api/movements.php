<?php
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get filters from query params
    $po_id = $_GET['po_id'] ?? null;
    $part_id = $_GET['part_id'] ?? null;
    $stage_id = $_GET['stage_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    // Build query
    $query = "
        SELECT 
            m.*,
            po.po_number,
            p.part_number,
            p.part_name,
            s1.stage_name as from_stage,
            s2.stage_name as to_stage,
            b1.bin_barcode as from_bin,
            b2.bin_barcode as to_bin
        FROM movements m
        JOIN po_items poi ON m.po_item_id = poi.id
        JOIN purchase_orders po ON poi.po_id = po.id
        JOIN parts p ON poi.part_id = p.id
        LEFT JOIN stages s1 ON m.from_stage_id = s1.id
        JOIN stages s2 ON m.to_stage_id = s2.id
        LEFT JOIN bins b1 ON m.from_bin_id = b1.id
        JOIN bins b2 ON m.to_bin_id = b2.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($po_id) {
        $query .= " AND po.id = ?";
        $params[] = $po_id;
    }
    
    if ($part_id) {
        $query .= " AND p.id = ?";
        $params[] = $part_id;
    }
    
    if ($stage_id) {
        $query .= " AND m.to_stage_id = ?";
        $params[] = $stage_id;
    }
    
    if ($from_date) {
        $query .= " AND m.movement_timestamp >= ?";
        $params[] = $from_date . ' 00:00:00';
    }
    
    if ($to_date) {
        $query .= " AND m.movement_timestamp <= ?";
        $params[] = $to_date . ' 23:59:59';
    }
    
    $query .= "
        ORDER BY m.movement_timestamp DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Get summary stats
    $stats_query = "
        SELECT 
            COUNT(*) as total_movements,
            SUM(m.quantity_moved) as total_quantity,
            SUM(m.rejected_count) as total_rejected,
            SUM(m.rework_count) as total_rework
        FROM movements m
        JOIN po_items poi ON m.po_item_id = poi.id
        JOIN purchase_orders po ON poi.po_id = po.id
        JOIN parts p ON poi.part_id = p.id
        WHERE 1=1
    ";
    
    $stats_params = [];
    
    if ($po_id) {
        $stats_query .= " AND po.id = ?";
        $stats_params[] = $po_id;
    }
    
    if ($part_id) {
        $stats_query .= " AND p.id = ?";
        $stats_params[] = $part_id;
    }
    
    if ($stage_id) {
        $stats_query .= " AND m.to_stage_id = ?";
        $stats_params[] = $stage_id;
    }
    
    if ($from_date) {
        $stats_query .= " AND m.movement_timestamp >= ?";
        $stats_params[] = $from_date . ' 00:00:00';
    }
    
    if ($to_date) {
        $stats_query .= " AND m.movement_timestamp <= ?";
        $stats_params[] = $to_date . ' 23:59:59';
    }
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute($stats_params);
    $stats = $stats_stmt->fetch();
    
    sendJSON([
        'success' => true,
        'movements' => $movements,
        'stats' => [
            'total_movements' => (int)$stats['total_movements'],
            'total_quantity' => (int)$stats['total_quantity'],
            'total_rejected' => (int)$stats['total_rejected'],
            'total_rework' => (int)$stats['total_rework']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Movements API error: " . $e->getMessage());
    sendError('Database error', 500, $e->getMessage());
} catch (Exception $e) {
    error_log("Movements API error: " . $e->getMessage());
    sendError('Server error', 500, $e->getMessage());
}
?>
