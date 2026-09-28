<?php

session_start();
require_once "config.php";

if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $checkEmail = $conn->query("SELECT email FROM users WHERE LOWER(email)='" . $conn->real_escape_string($email) . "'");
    if ($checkEmail->num_rows > 0) {
        $_SESSION['alerts'][] = [
            'type' => 'error',
            'message' => 'Email already registered!'
        ];
        $_SESSION['active_form'] = 'register';
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $name, $email, $password);
            if ($stmt->execute()) {
                $_SESSION['alerts'][] = [
                    'type' => 'success',
                    'message' => 'Registration successful'
                ];
                $_SESSION['active_form'] = 'login';
            } else {
                $_SESSION['alerts'][] = [
                    'type' => 'error',
                    'message' => 'Error: ' . $stmt->error
                ];
                $_SESSION['active_form'] = 'register';
            }
            $stmt->close();
        } else {
            $_SESSION['alerts'][] = [
                'type' => 'error',
                'message' => 'Database error: ' . $conn->error
            ];
            $_SESSION['active_form'] = 'register';
        }
    }
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $_SESSION['alerts'][] = [
            'type' => 'error',
            'message' => 'Email atau password tidak valid.'
        ];
        $_SESSION['active_form'] = 'login';
        header("Location: ../index.php");
        exit();
    }

    // 1) Cek admin terlebih dahulu (admin terdaftar manual di tabel admins)
    $adminStmt = $conn->prepare("SELECT * FROM admins WHERE LOWER(email) = ?");
    $adminStmt->bind_param("s", $email);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $admin = $adminResult->num_rows > 0 ? $adminResult->fetch_assoc() : null;
    $adminStmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['alerts'][] = [
            'type' => 'success',
            'message' => 'Admin login successful'
        ];
        header("Location: ../admin-page/admin_page.php");
        exit();
    }

    // 2) Jika bukan admin, cek user normal
    $userStmt = $conn->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->num_rows > 0 ? $userResult->fetch_assoc() : null;
    $userStmt->close();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['alerts'][] = [
            'type' => 'success',
            'message' => 'Login successful'
        ];
        header("Location: ../user-page/user_page.php");
        exit();
    }

    $_SESSION['alerts'][] = [
        'type' => 'error',
        'message' => 'Incorrect email or password!'
    ];
    $_SESSION['active_form'] = 'login';
    header("Location: ../index.php");
    exit();
}
?>

