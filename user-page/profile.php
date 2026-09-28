<?php
session_start();
require_once __DIR__ . '/../database/config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Ensure optional columns exist (phone, profile_photo, address)
$altered = false;
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}

$need = [];
if (!in_array('phone', $cols)) $need[] = "ADD COLUMN phone VARCHAR(30) NULL";
if (!in_array('profile_photo', $cols)) $need[] = "ADD COLUMN profile_photo VARCHAR(255) NULL";
if (!in_array('address', $cols)) $need[] = "ADD COLUMN address TEXT NULL";
if ($need) {
    $sql = "ALTER TABLE users " . implode(', ', $need);
    $conn->query($sql);
}

// Fetch current user data
$stmt = $conn->prepare("SELECT id, name, email, phone, profile_photo, address FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') $errors[] = 'Nama tidak boleh kosong.';

    if ($newPassword !== '') {
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Password baru dan konfirmasi tidak cocok.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    // Handle file upload
    if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_photo'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['png','jpg','jpeg','gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Format foto tidak didukung. Gunakan png/jpg/jpeg/gif.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Ukuran file maksimal 2MB.';
            } else {
                $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/../images/profile/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // delete old file if exists and not default
                    if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../' . $user['profile_photo'])) {
                        @unlink(__DIR__ . '/../' . $user['profile_photo']);
                    }
                    $profilePhotoPath = 'images/profile/' . $filename;
                } else {
                    $errors[] = 'Gagal mengunggah foto.';
                }
            }
        } else {
            $errors[] = 'Error saat mengunggah file.';
        }
    }

    if (!$errors) {
        // Build update query
        $parts = [];
        $params = [];
        $types = '';

        $parts[] = 'name = ?'; $params[] = $name; $types .= 's';
        $parts[] = 'phone = ?'; $params[] = $phone; $types .= 's';
        $parts[] = 'address = ?'; $params[] = $address; $types .= 's';
        if (!empty($passwordHash)) { $parts[] = 'password = ?'; $params[] = $passwordHash; $types .= 's'; }
        if (!empty($profilePhotoPath)) { $parts[] = 'profile_photo = ?'; $params[] = $profilePhotoPath; $types .= 's'; }

        $params[] = $userId; $types .= 'i';
        $sql = "UPDATE users SET " . implode(', ', $parts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $success = 'Profil berhasil diperbarui.';
            // update session name if changed
            $_SESSION['user_name'] = $name;
            // refresh user data
            $stmt->close();
            $stmt2 = $conn->prepare("SELECT id, name, email, phone, profile_photo, address FROM users WHERE id = ? LIMIT 1");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $errors[] = 'Gagal menyimpan perubahan: ' . $stmt->error;
            $stmt->close();
        }
    }
}

// Default photo
$defaultPhoto = 'images/user.svg';
$photoToShow = (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../' . $user['profile_photo'])) ? $user['profile_photo'] : $defaultPhoto;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">
  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <title>Profil Saya</title>
</head>
<body>
<div class="container mt-4">
  <div class="profile-header">PROFIL SAYA</div>
  <div class="row mt-3">
    <div class="col-md-4">
      <div class="profile-card text-center">
        <div class="avatar-circle mx-auto mb-3"><img src="../<?php echo htmlspecialchars($photoToShow); ?>" alt="avatar"></div>
        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
      </div>
    </div>
    <div class="col-md-8">
      <?php if ($errors): ?>
        <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="profile-card">
        <h5>Data Profil</h5>
        <div class="mb-3">
          <label class="form-label">Nama Pengguna</label>
          <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
          <div class="form-text">E-mail tidak dapat dirubah melalui halaman ini</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Nomor Telepon</label>
          <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Password Baru</label>
          <input type="password" name="password" class="form-control" placeholder="Kosongkan Jika Tidak Ingin Mengganti Password">
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi Password Baru</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi Password Baru">
        </div>
        <div class="mb-3">
          <label class="form-label">Unggah Foto Profil</label>
          <input type="file" name="profile_photo" class="form-control">
          <div class="form-text">Ukuran maksimal 2MB</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat Pengiriman</label>
          <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
        </div>
        <div class="mb-3">
          <a href="addresses.php" class="btn btn-outline-secondary w-100">Kelola Alamat Pengiriman</a>
        </div>
        <div class="d-grid">
          <button class="btn btn-success">Simpan perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>
</div>
<script src="../js/custom.js"></script>
</body>
</html>
