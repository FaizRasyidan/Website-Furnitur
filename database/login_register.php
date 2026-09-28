<?php
session_start();
require_once "inc_koneksi.php";

// Handle Register
if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $akses = $_POST['akses'];

    // Cek apakah email sudah terdaftar
    $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
    if ($checkEmail->num_rows > 0) {
        $_SESSION['error'] = "Email sudah terdaftar!";
        $_SESSION['active_tab'] = 'register';
        echo "<script>alert('Email sudah terdaftar!');</script>";
    } else {
        // Simpan data pengguna ke database
        $conn->query("INSERT INTO users (nama, email, password, akses) VALUES ('$nama', '$email', '$password', '$akses')");
        echo "<script>alert('Registrasi berhasil! Silakan login.');</script>";
    }

    header("Location: index.php");
    exit();
}

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Cek apakah email terdaftar
    $result = $conn->query("SELECT * FROM users WHERE email = '$email'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['akses']; // 'admin' atau 'user'

            if ($user['akses'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user-dashboard.php");
            }
            exit();
        }

        $_SESSION['error'] = "Password salah!";
        $_SESSION['active_tab'] = 'login';
        echo "<script>alert('Password salah!');</script>";
    } else {
        $_SESSION['error'] = "Email tidak ditemukan!";
        $_SESSION['active_tab'] = 'login';
        echo "<script>alert('Email tidak ditemukan!');</script>";
    }

    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
?>
