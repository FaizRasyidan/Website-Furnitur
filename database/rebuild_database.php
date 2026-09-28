<?php
// rebuild_database.php
// Jalankan dengan parameter ?force=1 untuk menghapus database lama dan membuat ulang.

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'db_ecommerce';
$force = isset($_GET['force']) && $_GET['force'] === '1';

if (!$force) {
    echo "<h2>Rebuild database</h2>";
    echo "<p>Tambahkan <code>?force=1</code> di URL untuk menghapus dan membuat ulang database.</p>";
    echo "<p>Contoh: <code>http://localhost/Jawafurnitur/database/rebuild_database.php?force=1</code></p>";
    exit;
}

$mysqli = new mysqli($host, $user, $password);
if ($mysqli->connect_errno) {
    die('Koneksi gagal: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$mysqli->query('SET NAMES utf8mb4');
$mysqli->query('SET CHARACTER SET utf8mb4');

function execQuery($conn, $sql, $description) {
    if ($conn->query($sql) === TRUE) {
        echo "✓ $description<br>\n";
        return true;
    }
    echo "✗ Gagal $description: " . $conn->error . "<br>\n";
    return false;
}

execQuery($mysqli, "DROP DATABASE IF EXISTS `$database`", "menghapus database $database");
execQuery($mysqli, "CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "membuat database $database");
$mysqli->select_db($database);

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel users");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel admins");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS user_addresses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel user_addresses");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel store_locations");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel categories");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel products");

execQuery($mysqli, "CREATE TABLE IF NOT EXISTS vouchers (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "membuat tabel vouchers");

$defaultAdminEmail = 'admin@example.com';
$defaultAdminName = 'Admin';
$defaultAdminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$checkAdmin = $mysqli->query("SELECT id FROM admins WHERE email='" . $mysqli->real_escape_string($defaultAdminEmail) . "'");
if ($checkAdmin && $checkAdmin->num_rows === 0) {
    $stmt = $mysqli->prepare("INSERT INTO admins (admin_name, email, password) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sss', $defaultAdminName, $defaultAdminEmail, $defaultAdminPassword);
        if ($stmt->execute()) {
            echo "✓ Default admin created: $defaultAdminEmail / admin123<br>\n";
        } else {
            echo "✗ Gagal membuat admin default: " . $stmt->error . "<br>\n";
        }
        $stmt->close();
    }
} else {
    echo "✓ Default admin sudah ada.<br>\n";
}

$defaultCategories = ['Kursi', 'Sofa', 'Meja', 'Lemari', 'Tempat Tidur'];
foreach ($defaultCategories as $cat) {
    $checkCat = $mysqli->query("SELECT id FROM categories WHERE name='" . $mysqli->real_escape_string($cat) . "'");
    if ($checkCat && $checkCat->num_rows === 0) {
        $stmt = $mysqli->prepare("INSERT INTO categories (name) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            $stmt->close();
            echo "✓ Category '$cat' added.<br>\n";
        }
    }
}

echo "<h3>Database db_ecommerce berhasil dibangun ulang.</h3>";
$mysqli->close();
