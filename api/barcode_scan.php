<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Scan barcode and get bin/material info
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['barcode'])) {
            sendError('Barcode is required');
        }
        
        $barcode = $data['barcode'];
        
        try {
            // Get bin information
            $stmt = $conn->prepare("
                SELECT b.*, 
                       COUNT(DISTINCT bi.po_item_id) as active_materials
                FROM bins b
                LEFT JOIN bin_inventory bi ON b.id = bi.bin_id AND bi.quantity > 0
                WHERE b.bin_barcode = ?
                GROUP BY b.id
            ");
            $stmt->execute([$barcode]);
            $bin = $stmt->fetch();
            
            if (!$bin) {
                sendError('Bin not found with barcode: ' . $barcode, 404);
            }
            
            // Get current contents of the bin
            $stmt = $conn->prepare("
                SELECT 
                    bi.*,
                    po.po_number,
                    po.customer_name,
                    p.part_number,
                    p.part_name,
                    s.stage_name,
                    s.stage_order,
                    poi.ordered_quantity,
                    poi.produced_quantity
                FROM bin_inventory bi
                JOIN po_items poi ON bi.po_item_id = poi.id
                JOIN purchase_orders po ON poi.po_id = po.id
                JOIN parts p ON poi.part_id = p.id
                JOIN stages s ON bi.stage_id = s.id
                WHERE bi.bin_id = ? AND bi.quantity > 0
                ORDER BY bi.last_updated DESC
            ");
            $stmt->execute([$bin['id']]);
            $contents = $stmt->fetchAll();
            
            sendJSON([
                'success' => true,
                'bin' => $bin,
                'contents' => $contents,
                'message' => 'Bin scanned successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Scan failed: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'GET':
        // Search bins or get bin details
        $barcode = $_GET['barcode'] ?? null;
        $zone = $_GET['zone'] ?? null;
        
        try {
            if ($barcode) {
                // Get specific bin
                $stmt = $conn->prepare("
                    SELECT b.*, 
                           COUNT(DISTINCT bi.po_item_id) as active_materials,
                           SUM(bi.quantity) as total_quantity
                    FROM bins b
                    LEFT JOIN bin_inventory bi ON b.id = bi.bin_id AND bi.quantity > 0
                    WHERE b.bin_barcode = ?
                    GROUP BY b.id
                ");
                $stmt->execute([$barcode]);
                $bin = $stmt->fetch();
                
                if (!$bin) {
                    sendError('Bin not found', 404);
                }
                
                sendJSON(['bin' => $bin]);
                
            } elseif ($zone) {
                // Get bins by zone
                $stmt = $conn->prepare("
                    SELECT b.*,
                           COUNT(DISTINCT bi.po_item_id) as active_materials,
                           SUM(bi.quantity) as total_quantity
                    FROM bins b
                    LEFT JOIN bin_inventory bi ON b.id = bi.bin_id AND bi.quantity > 0
                    WHERE b.zone = ?
                    GROUP BY b.id
                    ORDER BY b.bin_barcode
                ");
                $stmt->execute([$zone]);
                $bins = $stmt->fetchAll();
                
                sendJSON(['bins' => $bins, 'count' => count($bins)]);
                
            } else {
                // Get all bins summary
                $stmt = $conn->query("
                    SELECT zone, 
                           COUNT(*) as total_bins,
                           COUNT(DISTINCT CASE WHEN bi.quantity > 0 THEN b.id END) as occupied_bins
                    FROM bins b
                    LEFT JOIN bin_inventory bi ON b.id = bi.bin_id
                    GROUP BY zone
                    ORDER BY zone
                ");
                $zones = $stmt->fetchAll();
                
                sendJSON(['zones' => $zones]);
            }
            
        } catch (Exception $e) {
            sendError('Query failed: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>
