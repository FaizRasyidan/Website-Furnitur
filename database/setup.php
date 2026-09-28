<?php
/**
 * File Setup Database
 * Jalankan file ini sekali di browser untuk membuat database dan tabel
 * URL: http://localhost/Jawafurnitur/database/setup.php
 */

$host = "localhost";
$user = "root";
$password = "";

// Koneksi tanpa database
$conn = new mysqli($host, $user, $password);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$database = "db_ecommerce";

// Buat database jika belum ada
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === TRUE) {
    echo "✓ Database created or already exists.<br>";
} else {
    echo "✗ Error creating database: " . $conn->error . "<br>";
}

// Gunakan database
$conn->select_db($database);

// Buat tabel users
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'users' created or already exists.<br>";
} else {
    echo "✗ Error creating table: " . $conn->error . "<br>";
}

// Buat tabel admins (manual registered)
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'admins' created or already exists.<br>";
} else {
    echo "✗ Error creating table: " . $conn->error . "<br>";
}

// Default admin account (insert jika belum ada)
// Catatan: file ini membuat kolom admin_name, jadi pastikan tabel admins di DB memang belum berubah.
$defaultAdminEmail = 'admin@example.com';
$defaultAdminName = 'Admin';
$defaultAdminPassword = password_hash('admin123', PASSWORD_DEFAULT);

$checkAdmin = $conn->query(
    "SELECT id FROM admins WHERE email='" . $conn->real_escape_string($defaultAdminEmail) . "'"
);

if ($checkAdmin && $checkAdmin->num_rows === 0) {
    $stmt = $conn->prepare(
        "INSERT INTO admins (admin_name, email, password) VALUES (?, ?, ?)"
    );

    if ($stmt) {
        $stmt->bind_param('sss', $defaultAdminName, $defaultAdminEmail, $defaultAdminPassword);
        if ($stmt->execute()) {
            echo "✓ Default admin created: admin@example.com / admin123<br>";
        } else {
            echo "✗ Error creating default admin: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "✗ Error preparing default admin insertion: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Default admin already exists.<br>";
}

// Buat tabel alamat pengiriman user kalau belum ada
$sql = "CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) NOT NULL DEFAULT 'Rumah',
    recipient_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    province VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    address TEXT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'user_addresses' created or already exists.<br>";
} else {
    echo "✗ Error creating table user_addresses: " . $conn->error . "<br>";
}
// Buat tabel store_locations untuk menyimpan lokasi toko/gudang
$sql = "CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'store_locations' created or already exists.<br>";
} else {
    echo "✗ Error creating table store_locations: " . $conn->error . "<br>";
}
// Buat tabel categories
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'categories' created or already exists.<br>";
} else {
    echo "✗ Error creating table categories: " . $conn->error . "<br>";
}

// Buat tabel products
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    price INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    wood_type VARCHAR(50),
    color VARCHAR(50),
    description TEXT,
    image VARCHAR(255),
    image_front VARCHAR(255),
    image_side VARCHAR(255),
    image_back VARCHAR(255),
    image_45degree VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'products' created or already exists.<br>";
} else {
    echo "✗ Error creating table products: " . $conn->error . "<br>";
}

// Add new image columns if they don't exist
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $checkColumn = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
        "WHERE TABLE_NAME='" . $conn->real_escape_string($table) . "' " .
        "AND COLUMN_NAME='" . $conn->real_escape_string($column) . "' " .
        "AND TABLE_SCHEMA=DATABASE()"
    );
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $sql = "ALTER TABLE " . $conn->real_escape_string($table) . " ADD COLUMN " . $column . " " . $definition;
        if ($conn->query($sql) === TRUE) {
            echo "✓ Column '$column' added to $table table.<br>";
        } else {
            echo "✗ Error adding $column column: " . $conn->error . "<br>";
        }
    } else {
        echo "✓ Column '$column' already exists in $table.<br>";
    }
}

$imageColumns = [
    'image' => 'VARCHAR(255) DEFAULT NULL',
    'image_front' => 'VARCHAR(255) DEFAULT NULL',
    'image_side' => 'VARCHAR(255) DEFAULT NULL',
    'image_back' => 'VARCHAR(255) DEFAULT NULL',
    'image_45degree' => 'VARCHAR(255) DEFAULT NULL'
];
foreach ($imageColumns as $col => $definition) {
    addColumnIfNotExists($conn, 'products', $col, $definition);
}

// Migrate existing product image data, so semua foto dapat tampil
$migrationSql = "UPDATE products SET
    image_front = CASE WHEN image_front IS NULL OR image_front = '' THEN image ELSE image_front END,
    image_side = CASE WHEN image_side IS NULL OR image_side = '' THEN image_front ELSE image_side END,
    image_back = CASE WHEN image_back IS NULL OR image_back = '' THEN image_front ELSE image_back END,
    image_45degree = CASE WHEN image_45degree IS NULL OR image_45degree = '' THEN image_front ELSE image_45degree END,
    image = CASE WHEN image IS NULL OR image = '' THEN COALESCE(image_front, image_side, image_back, image_45degree) ELSE image END
";
if ($conn->query($migrationSql) === TRUE) {
    echo "✓ Product image data migrated successfully.<br>";
} else {
    echo "✗ Error migrating product image data: " . $conn->error . "<br>";
}

// Insert default categories
$conn->select_db($database);
$defaultCategories = ['Kursi', 'Sofa', 'Meja', 'Lemari', 'Tempat Tidur'];
foreach ($defaultCategories as $cat) {
    $checkCat = $conn->query("SELECT id FROM categories WHERE name='" . $conn->real_escape_string($cat) . "'");
    if ($checkCat && $checkCat->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            $stmt->close();
            echo "✓ Category '$cat' added.<br>";
        }
    }
}
// Buat tabel vouchers jika belum ada
$sql = "CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('fixed','percent','shipping') DEFAULT 'fixed',
    discount_value DECIMAL(10,2) DEFAULT 0,
    min_spend INT DEFAULT 0,
    max_discount INT DEFAULT 0,
    quota INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'vouchers' created or already exists.<br>";
} else {
    echo "✗ Error creating table vouchers: " . $conn->error . "<br>";
}

// Buat tabel orders jika belum ada
$sql = "CREATE TABLE IF NOT EXISTS orders (
    order_id VARCHAR(100) PRIMARY KEY,
    user_id INT NOT NULL,
    payment_method VARCHAR(100) DEFAULT NULL,
    payment_status VARCHAR(50) DEFAULT 'pending',
    status VARCHAR(50) DEFAULT 'pending',
    total INT NOT NULL,
    discount INT DEFAULT 0,
    shipping INT DEFAULT 0,
    voucher VARCHAR(50) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    address JSON DEFAULT NULL,
    items JSON DEFAULT NULL,
    payment_result JSON DEFAULT NULL,
    tracking_data JSON DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'orders' created or already exists.<br>";
} else {
    echo "✗ Error creating table orders: " . $conn->error . "<br>";
}

// Create order_trackings table if it doesn't exist
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

// Add tracking_data column if it doesn't exist
$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='tracking_data' AND TABLE_SCHEMA=DATABASE()";
$result = $conn->query($checkColumn);
if (!$result || $result->num_rows === 0) {
    $addColumn = "ALTER TABLE orders ADD COLUMN tracking_data JSON DEFAULT NULL";
    if ($conn->query($addColumn) === TRUE) {
        echo "✓ Column 'tracking_data' added to orders table.<br>";
    } else {
        echo "✗ Error adding tracking_data column: " . $conn->error . "<br>";
    }
}

// Add delivery_personnel_id column if it doesn't exist
$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='delivery_personnel_id' AND TABLE_SCHEMA=DATABASE()";
$result = $conn->query($checkColumn);
if (!$result || $result->num_rows === 0) {
    $addColumn = "ALTER TABLE orders ADD COLUMN delivery_personnel_id INT DEFAULT NULL";
    if ($conn->query($addColumn) === TRUE) {
        echo "✓ Column 'delivery_personnel_id' added to orders table.<br>";
    } else {
        echo "✗ Error adding delivery_personnel_id column: " . $conn->error . "<br>";
    }
}

$conn->close();
?>
