<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

require_once __DIR__ . '/config.php';

$response = ['status' => 'error', 'message' => ''];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS store_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    switch ($action) {
        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM store_locations WHERE id = ?");
                $stmt->bind_param('i', $id);
            } else {
                $result = $conn->query("SELECT * FROM store_locations ORDER BY id ASC");
                if ($result) {
                    $locations = [];
                    while ($row = $result->fetch_assoc()) {
                        $locations[] = $row;
                    }
                    $response['status'] = 'success';
                    $response['data'] = $locations;
                    echo json_encode($response);
                    exit;
                }
            }
            break;
            
        case 'add':
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (!isset($data['name']) || !isset($data['latitude']) || !isset($data['longitude'])) {
                throw new Exception('Missing required fields: name, latitude, longitude');
            }
            
            $stmt = $conn->prepare("INSERT INTO store_locations (name, address, latitude, longitude) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssdd', $data['name'], $data['address'] ?? '', $data['latitude'], $data['longitude']);
            
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Store location added successfully';
                $response['id'] = $conn->insert_id;
            } else {
                throw new Exception($stmt->error);
            }
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (!isset($data['id'])) {
                throw new Exception('ID is required');
            }
            
            $stmt = $conn->prepare("UPDATE store_locations SET name = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmt->bind_param('ssddi', $data['name'], $data['address'] ?? '', $data['latitude'], $data['longitude'], $data['id']);
            
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Store location updated successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;
            
        case 'delete':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID is required');
            }
            
            $stmt = $conn->prepare("DELETE FROM store_locations WHERE id = ?");
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Store location deleted successfully';
            } else {
                throw new Exception($stmt->error);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
