<?php
require_once __DIR__ . '/config.php';

$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='tracking_data' AND TABLE_SCHEMA=DATABASE()";
$result = $conn->query($checkColumn);
if (!$result || $result->num_rows === 0) {
    $addColumn = "ALTER TABLE orders ADD COLUMN tracking_data JSON DEFAULT NULL";
    if ($conn->query($addColumn) === TRUE) {
        echo "✓ Column 'tracking_data' added to orders table.<br>";
    } else {
        echo "✗ Error adding tracking_data column: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Column 'tracking_data' already exists in orders table.<br>";
}

$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='delivery_personnel_id' AND TABLE_SCHEMA=DATABASE()";
$result = $conn->query($checkColumn);
if (!$result || $result->num_rows === 0) {
    $addColumn = "ALTER TABLE orders ADD COLUMN delivery_personnel_id INT DEFAULT NULL";
    if ($conn->query($addColumn) === TRUE) {
        echo "✓ Column 'delivery_personnel_id' added to orders table.<br>";
    } else {
        echo "✗ Error adding delivery_personnel_id column: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Column 'delivery_personnel_id' already exists in orders table.<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'store_locations' created or already exists.<br>";
} else {
    echo "✗ Error creating table store_locations: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS order_trackings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) NOT NULL,
    status VARCHAR(60) NOT NULL,
    stage VARCHAR(120) NOT NULL,
    progress DECIMAL(5,2) NOT NULL DEFAULT 0,
    eta_seconds INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'order_trackings' created or already exists.<br>";
} else {
    echo "✗ Error creating table order_trackings: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS courier_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_personnel_id INT DEFAULT NULL,
    order_id VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_personnel_id) REFERENCES delivery_personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'courier_locations' created or already exists.<br>";
} else {
    echo "✗ Error creating table courier_locations: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS delivery_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_type VARCHAR(100) NOT NULL COMMENT 'Jenis kendaraan (Motor, Mobil, Truck)',
    vehicle_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor kendaraan/plat',
    driver_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive', 'on_delivery') DEFAULT 'active',
    current_location VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_driver_name (driver_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'delivery_personnel' created or already exists.<br>";
} else {
    echo "✗ Error creating delivery_personnel table: " . $conn->error . "<br>";
}

echo "<br><strong>Migration completed successfully!</strong>";
?>
