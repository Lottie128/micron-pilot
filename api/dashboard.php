<?php
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get dashboard overview
    $part_id = $_GET['part_id'] ?? null;
    
    // Get parts summary
    $parts_query = "
        SELECT p.*, 
               COUNT(DISTINCT s.id) as total_stages,
               COALESCE(SUM(i.quantity), 0) as total_inventory,
               COALESCE(SUM(i.good_quantity), 0) as total_good,
               COALESCE(SUM(i.rework_quantity), 0) as total_rework,
               COALESCE(SUM(i.rejected_quantity), 0) as total_rejected
        FROM parts p
        LEFT JOIN stages s ON p.id = s.part_id
        LEFT JOIN bin_inventory i ON p.id = i.part_id
    ";
    
    if ($part_id) {
        $parts_query .= " WHERE p.id = ?";
    }
    
    $parts_query .= "
        GROUP BY p.id
        ORDER BY p.part_number
    ";
    
    $parts_stmt = $conn->prepare($parts_query);
    
    if ($part_id) {
        $parts_stmt->execute([$part_id]);
    } else {
        $parts_stmt->execute();
    }
    
    $parts = $parts_stmt->fetchAll();
    
    // Get stage-wise inventory for each part
    foreach ($parts as &$part) {
        // Get inventory by stage
        $inv_stmt = $conn->prepare("
            SELECT s.id as stage_id, s.stage_name, s.stage_order, s.stage_type,
                   COALESCE(SUM(i.quantity), 0) as quantity,
                   COALESCE(SUM(i.good_quantity), 0) as good_quantity,
                   COALESCE(SUM(i.rework_quantity), 0) as rework_quantity,
                   COALESCE(SUM(i.rejected_quantity), 0) as rejected_quantity,
                   COUNT(DISTINCT i.bin_id) as bins_count
            FROM stages s
            LEFT JOIN bin_inventory i ON s.id = i.stage_id AND i.part_id = ?
            WHERE s.part_id = ?
            GROUP BY s.id
            ORDER BY s.stage_order
        ");
        $inv_stmt->execute([$part['id'], $part['id']]);
        $part['stage_inventory'] = $inv_stmt->fetchAll();
        
        // Get bin details for each stage
        foreach ($part['stage_inventory'] as &$stage) {
            $bin_stmt = $conn->prepare("
                SELECT b.bin_barcode, b.bin_name,
                       i.quantity, i.good_quantity, i.rework_quantity, i.rejected_quantity,
                       i.last_updated
                FROM bin_inventory i
                JOIN bins b ON i.bin_id = b.id
                WHERE i.part_id = ? AND i.stage_id = ? AND i.quantity > 0
            ");
            $bin_stmt->execute([$part['id'], $stage['stage_id']]);
            $stage['bins'] = $bin_stmt->fetchAll();
        }
        
        // Get recent movements
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
            WHERE m.po_item_id IN (
                SELECT id FROM po_items WHERE part_id = ?
            )
            ORDER BY m.movement_timestamp DESC
            LIMIT 20
        ");
        $mov_stmt->execute([$part['id']]);
        $part['recent_movements'] = $mov_stmt->fetchAll();
    }
    
    // Get all bins
    $bins_stmt = $conn->query("SELECT * FROM bins ORDER BY bin_barcode");
    $bins = $bins_stmt->fetchAll();
    
    // Overall statistics
    $stats = [
        'total_parts' => count($parts),
        'total_bins' => count($bins),
        'total_inventory' => (int)array_sum(array_column($parts, 'total_inventory')),
        'total_good' => (int)array_sum(array_column($parts, 'total_good')),
        'total_rework' => (int)array_sum(array_column($parts, 'total_rework')),
        'total_rejected' => (int)array_sum(array_column($parts, 'total_rejected'))
    ];
    
    sendJSON([
        'success' => true,
        'stats' => $stats,
        'parts' => $parts,
        'bins' => $bins
    ]);
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    sendError('Database error', 500, $e->getMessage());
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    sendError('Server error', 500, $e->getMessage());
}
?>
