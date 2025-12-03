<?php
require_once 'config.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all parts with their stages
        $stmt = $conn->query("
            SELECT p.*, 
                   COUNT(s.id) as stage_count
            FROM parts p
            LEFT JOIN stages s ON p.id = s.part_id
            GROUP BY p.id
            ORDER BY p.part_number
        ");
        $parts = $stmt->fetchAll();
        
        // Get stages for each part
        foreach ($parts as &$part) {
            $stmt = $conn->prepare("
                SELECT * FROM stages 
                WHERE part_id = ? 
                ORDER BY stage_order
            ");
            $stmt->execute([$part['id']]);
            $part['stages'] = $stmt->fetchAll();
        }
        
        sendJSON(['parts' => $parts]);
        break;
        
    case 'POST':
        // Add new part
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['part_number'])) {
            sendError('Part number is required');
        }
        
        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("
                INSERT INTO parts (part_number, part_name, category, total_stages) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['part_number'],
                $data['part_name'] ?? $data['part_number'],
                $data['category'] ?? '200 Series',
                count($data['stages'] ?? [])
            ]);
            
            $part_id = $conn->lastInsertId();
            
            // Insert stages
            if (!empty($data['stages'])) {
                $stmt = $conn->prepare("
                    INSERT INTO stages (part_id, stage_name, stage_order, stage_type) 
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($data['stages'] as $index => $stage) {
                    $stmt->execute([
                        $part_id,
                        $stage['name'],
                        $index + 1,
                        $stage['type'] ?? 'machining'
                    ]);
                }
            }
            
            $conn->commit();
            sendJSON(['message' => 'Part added successfully', 'part_id' => $part_id], 201);
            
        } catch (Exception $e) {
            $conn->rollBack();
            sendError('Failed to add part: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>
