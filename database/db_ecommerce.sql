-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 05:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_ecommerce`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `admin_name`, `email`, `password`) VALUES
(1, 'admin ganteng', 'adminganteng@gmail.com', ''),
(2, 'admin', 'admin@gmail.com', 'admin123'),
(3, 'Admin', 'admin@example.com', '$2y$10$d0ICKmN8hZd.D6gXm9AmZuPuV5QKfof0dEyx9gObYEsujfNFYd3se');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Kursi', NULL, '2026-05-23 15:30:59', NULL),
(2, 'Sofa', NULL, '2026-05-23 15:30:59', NULL),
(3, 'Meja', NULL, '2026-05-23 15:30:59', NULL),
(4, 'Lemari', NULL, '2026-05-23 15:30:59', NULL),
(5, 'Tempat Tidur', NULL, '2026-05-23 15:30:59', NULL),
(7, 'TV Cabinet', '', '2026-06-03 14:16:02', NULL),
(8, 'Rak', '', '2026-06-03 14:16:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `user_id`, `sender`, `message`, `is_read`, `created_at`) VALUES
(1, 7, 'user', 'halo min', 1, '2026-05-23 14:39:32'),
(2, 7, 'admin', 'haloo kak', 1, '2026-05-23 14:40:04'),
(3, 7, 'admin', 'bos', 1, '2026-05-25 16:46:58'),
(4, 7, 'admin', 'mas maem urung', 1, '2026-05-27 17:44:58'),
(5, 7, 'user', 'wis', 1, '2026-05-30 15:01:42'),
(6, 7, 'user', 'ppp mas', 1, '2026-05-31 14:23:26'),
(7, 7, 'user', 'ppoj', 1, '2026-05-31 14:23:38'),
(8, 7, 'user', 'sd', 1, '2026-05-31 17:49:17'),
(9, 8, 'user', 'asd', 1, '2026-05-31 17:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `courier_locations`
--

CREATE TABLE `courier_locations` (
  `id` int(11) NOT NULL,
  `delivery_personnel_id` int(11) DEFAULT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_personnel`
--

CREATE TABLE `delivery_personnel` (
  `id` int(11) NOT NULL,
  `vehicle_type` varchar(100) NOT NULL COMMENT 'Jenis kendaraan (Motor, Mobil, Truck)',
  `vehicle_number` varchar(50) NOT NULL COMMENT 'Nomor kendaraan/plat',
  `driver_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `status` enum('active','inactive','on_delivery') DEFAULT 'active',
  `current_location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_personnel`
--

INSERT INTO `delivery_personnel` (`id`, `vehicle_type`, `vehicle_number`, `driver_name`, `phone_number`, `status`, `current_location`, `created_at`, `updated_at`) VALUES
(2, 'Mobil', 'rfrf', 'g', '0977', 'active', NULL, '2026-05-26 18:38:49', '2026-06-15 01:22:49'),
(3, 'Truk', 'AD 4453 DZ', 'Gendon', '09879889976', 'active', NULL, '2026-05-26 20:20:59', '2026-06-15 01:22:45'),
(4, 'Motor', 'AD 1986 PN', 'Guyon', '0989379', 'active', NULL, '2026-05-31 18:23:32', '2026-06-15 01:22:40');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `total` int(11) DEFAULT 0,
  `discount` int(11) DEFAULT 0,
  `shipping` int(11) DEFAULT 0,
  `voucher` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `items` text DEFAULT NULL,
  `payment_result` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_personnel_id` int(11) DEFAULT NULL,
  `tracking_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tracking_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_id`, `user_id`, `payment_method`, `payment_status`, `status`, `total`, `discount`, `shipping`, `voucher`, `message`, `address`, `items`, `payment_result`, `timestamp`, `created_at`, `updated_at`, `delivery_personnel_id`, `tracking_data`) VALUES
(1, '0', 7, 'E-Wallet', 'success', 'processing', 4150000, 0, 50000, '', '', '{\"id\":1,\"jalan\":\"blablabla\",\"kota\":\"Karanganyar\",\"provinsi\":\"JawaTengah\",\"kodepos\":\"57771\",\"PRIMARY_ADDRESS\":1}', '[{\"name\":\"Sofa L\",\"price\":1750000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"},{\"name\":\"Lemari Pakaian\",\"price\":2350000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-2.png\"}]', '{\"ok\":true,\"order_id\":\"ORDER-1779826790839\",\"payment_status\":\"success\",\"success\":true,\"orderId\":\"ORDER-1779826790839\"}', '2026-05-26 22:19:50', '2026-05-25 07:31:43', '2026-05-26 20:19:52', NULL, NULL),
(20, 'TEST-ORDER-15373', 1, 'Bank Transfer', 'success', 'delivered', 100000, 0, 50000, '', 'Test order', '[]', '[]', '[]', '2026-05-27 00:00:00', '2026-05-26 21:07:34', '2026-05-30 14:58:07', NULL, NULL),
(21, 'ORDER-1779841692419', 7, 'E-Wallet', 'success', 'delivered', 3530000, 300000, 50000, 'VC89FD9A', '', '[]', '[{\"name\":\"Sofa Sudut\",\"price\":2450000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"},{\"name\":\"Lampu Lantai\",\"price\":680000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-3.png\"},{\"name\":\"Kursi Kantor\",\"price\":650000,\"wood\":\"sonokeling\",\"color\":\"coklat\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-2.png\"}]', '{\"simulated\":true,\"method\":\"E-Wallet\"}', '2026-05-27 02:28:14', '2026-05-27 00:28:14', '2026-05-27 16:50:56', NULL, NULL),
(23, 'ORDER-1779842397640', 7, 'Bank Transfer', 'success', 'delivered', 3830000, 0, 50000, '', '', '[]', '[{\"name\":\"Sofa Sudut\",\"price\":2450000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"},{\"name\":\"Lampu Lantai\",\"price\":680000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-3.png\"},{\"name\":\"Kursi Kantor\",\"price\":650000,\"wood\":\"sonokeling\",\"color\":\"coklat\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-2.png\"}]', '{\"simulated\":true,\"method\":\"Bank Transfer\"}', '2026-05-27 02:39:58', '2026-05-27 00:39:58', '2026-05-27 06:01:49', 2, NULL),
(24, 'ORDER-1780238203731', 7, 'Retail', 'success', 'delivered', 4800000, 0, 50000, '', '', '[]', '[{\"name\":\"Sofa Bed\",\"price\":1850000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/couch.png\"},{\"name\":\"Sofa Tamu\",\"price\":2250000,\"wood\":\"sonokeling\",\"color\":\"hitam\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"},{\"name\":\"Kursi Kantor\",\"price\":650000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/couch.png\"}]', '{\"simulated\":true,\"method\":\"Retail\"}', '2026-05-31 16:36:45', '2026-05-31 14:36:45', '2026-05-31 14:39:09', 3, NULL),
(25, 'ORDER-1780249632635', 8, 'E-Wallet', 'success', 'delivered', 3150000, 0, 50000, '', '', '{\"id\":4,\"jalan\":\"Bekonang, Sukoharjo, Central Java, Java, 57554, Indonesia\",\"kota\":\"Sukoharjo\",\"provinsi\":\"JawaTengah\",\"kodepos\":\"57554\",\"PRIMARY_ADDRESS\":0}', '[{\"name\":\"Kursi Kantor\",\"price\":650000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/couch.png\"},{\"name\":\"Sofa Sudut\",\"price\":2450000,\"wood\":\"sonokeling\",\"color\":\"hitam\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"}]', '{\"simulated\":true,\"method\":\"E-Wallet\"}', '2026-05-31 19:47:13', '2026-05-31 17:47:13', '2026-06-02 16:56:32', 2, NULL),
(26, 'ORDER-1780251727744', 8, 'Bank Transfer', 'success', 'delivered', 1800000, 0, 50000, '', '', '{\"id\":4,\"jalan\":\"Bekonang, Sukoharjo, Central Java, Java, 57554, Indonesia\",\"kota\":\"Sukoharjo\",\"provinsi\":\"JawaTengah\",\"kodepos\":\"57554\",\"PRIMARY_ADDRESS\":0}', '[{\"name\":\"Sofa L\",\"price\":1750000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"}]', '{\"simulated\":true,\"method\":\"Bank Transfer\"}', '2026-05-31 20:22:09', '2026-05-31 18:22:09', '2026-05-31 18:55:53', 4, '{\"status\":\"on_delivery\",\"stage\":\"Kurir Menuju Lokasi\",\"store_id\":3,\"start\":{\"lat\":-6.1973253,\"lng\":106.7698359},\"destination\":{\"lat\":-6.1848253,\"lng\":106.7568359},\"current_location\":{\"lat\":-6.1973253,\"lng\":106.7698359},\"progress\":0,\"started_at\":1780253474,\"duration_seconds\":480,\"distance_km\":2,\"route\":[{\"lat\":-6.1973253,\"lng\":106.7698359},{\"lat\":-6.1848253,\"lng\":106.7568359}]}'),
(27, 'ORDER-1780254144612', 8, 'Bank Transfer', 'success', 'delivered', 2500000, 0, 50000, '', '', '{\"id\":4,\"jalan\":\"Bekonang, Sukoharjo, Central Java, Java, 57554, Indonesia\",\"kota\":\"Sukoharjo\",\"provinsi\":\"JawaTengah\",\"kodepos\":\"57554\",\"PRIMARY_ADDRESS\":0}', '[{\"name\":\"Sofa Sudut\",\"price\":2450000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/sofa.png\"}]', '{\"simulated\":true,\"method\":\"Bank Transfer\"}', '2026-05-31 21:02:26', '2026-05-31 19:02:26', '2026-05-31 19:10:56', 3, '{\"status\":\"on_delivery\",\"stage\":\"Kurir Menuju Lokasi\",\"store_id\":3,\"start\":{\"lat\":-6.1973253,\"lng\":106.7698359},\"destination\":{\"lat\":-6.1848253,\"lng\":106.7568359},\"current_location\":{\"lat\":-6.1973253,\"lng\":106.7698359},\"progress\":0,\"started_at\":1780254174,\"duration_seconds\":480,\"distance_km\":2,\"route\":[{\"lat\":-6.1973253,\"lng\":106.7698359},{\"lat\":-6.1848253,\"lng\":106.7568359}]}'),
(28, 'ORDER-1780413792314', 8, 'Bank Transfer', 'success', 'delivered', 600000, 0, 50000, '', '', '{\"id\":4,\"jalan\":\"Bekonang, Sukoharjo, Central Java, Java, 57554, Indonesia\",\"kota\":\"Sukoharjo\",\"provinsi\":\"JawaTengah\",\"kodepos\":\"57554\",\"PRIMARY_ADDRESS\":0}', '[{\"name\":\"Kursi Makan\",\"price\":550000,\"wood\":\"jati\",\"color\":\"natural\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/product-3.png\"}]', '{\"simulated\":true,\"method\":\"Bank Transfer\"}', '2026-06-02 17:23:13', '2026-06-02 15:23:13', '2026-06-02 16:56:58', 3, '{\"status\":\"on_delivery\",\"stage\":\"Kurir Menuju Lokasi\",\"store_id\":2,\"start\":{\"lat\":-7.5516995,\"lng\":110.7361364},\"destination\":{\"lat\":-7.5391995,\"lng\":110.7231364},\"current_location\":{\"lat\":-7.5516995,\"lng\":110.7361364},\"progress\":0,\"started_at\":1780413847,\"duration_seconds\":480,\"distance_km\":2,\"route\":[{\"lat\":-7.5516995,\"lng\":110.7361364},{\"lat\":-7.5391995,\"lng\":110.7231364}]}'),
(29, 'ORD-7-1781486327671-1401', 7, 'bank_transfer', 'success', 'delivered', 6125000, 675000, 50000, 'DISKON10', '', '{\"label\":\"Rumah\",\"street\":\"Jetis, Karanganyar, Central Java, Java, 57771, Indonesia\",\"city\":\"Karanganyar\",\"province\":\"JawaTengah\",\"postalCode\":\"57771\"}', '[{\"id\":15,\"name\":\"Office Chair Upholstered\",\"price\":2250000,\"wood\":\"\",\"color\":\"\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/products\\/produk_1780936230_505f169c.png\"},{\"id\":16,\"name\":\"Dining Table 4 Seat\",\"price\":4500000,\"wood\":\"\",\"color\":\"\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/products\\/produk_1780936420_e62d7b0e.png\"}]', '{\"simulated\":true,\"method\":\"bank_transfer\"}', '2026-06-15 03:18:47', '2026-06-15 01:18:47', '2026-06-15 01:22:26', 4, '{\"status\":\"on_delivery\",\"stage\":\"Kurir Menuju Lokasi\",\"store_id\":2,\"start\":{\"lat\":-7.5516995,\"lng\":110.7361364},\"destination\":{\"lat\":-7.5391995,\"lng\":110.7231364},\"current_location\":{\"lat\":-7.5516995,\"lng\":110.7361364},\"progress\":0,\"started_at\":1781486389,\"duration_seconds\":480,\"distance_km\":2,\"route\":[{\"lat\":-7.5516995,\"lng\":110.7361364},{\"lat\":-7.5391995,\"lng\":110.7231364}]}'),
(30, 'ORD-7-1781488915248-5600', 7, 'cod', 'cod', 'pending', 1800000, 0, 50000, '', '', '{\"label\":\"Kantor\",\"street\":\"Jalan Jayawijaya, Mojosongo, Surakarta, Central Java, Java, 57127, Indonesia\",\"city\":\"Karanganyar\",\"province\":\"JawaTengah\",\"postalCode\":\"57127\"}', '[{\"id\":13,\"name\":\"Dining Chair Luxury\",\"price\":1750000,\"wood\":\"\",\"color\":\"\",\"quantity\":1,\"image\":\"http:\\/\\/localhost\\/Jawafurnitur\\/images\\/products\\/produk_1780936048_63aa7a29.png\"}]', '{\"simulated\":true,\"method\":\"cod\"}', '2026-06-15 04:01:55', '2026-06-15 02:01:55', '2026-06-15 02:01:55', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_trackings`
--

CREATE TABLE `order_trackings` (
  `id` int(11) NOT NULL,
  `order_id` varchar(100) NOT NULL,
  `status` varchar(60) NOT NULL,
  `stage` varchar(120) NOT NULL,
  `progress` decimal(5,2) NOT NULL DEFAULT 0.00,
  `eta_seconds` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `harga` int(11) NOT NULL DEFAULT 0,
  `stok` int(11) NOT NULL DEFAULT 0,
  `jenis_kayu` varchar(110) NOT NULL,
  `warna` varchar(110) NOT NULL,
  `deskirpsi` varchar(225) NOT NULL,
  `gambar` varchar(255) NOT NULL DEFAULT '',
  `image_front` varchar(255) NOT NULL,
  `image_back` varchar(255) NOT NULL,
  `image_side` varchar(255) NOT NULL,
  `image_45degree` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `nama`, `kategori`, `harga`, `stok`, `jenis_kayu`, `warna`, `deskirpsi`, `gambar`, `image_front`, `image_back`, `image_side`, `image_45degree`, `image`) VALUES
(8, 'Dining Chair Nordic', '1', 850000, 20, 'Natural Oak, White Oak, Walnut Dark, Walnut Teak, Natural Black Wood', 'Ivory White, Cream Beige, Sand Beige, Light Grey, Charcoal Grey, Olive Green, Navy Blue, Mocha Brown', '', '', 'images/products/produk_1780672684_893469e4.png', 'images/products/produk_1780672684_8187d403.png', 'images/products/produk_1780672684_d1fcb829.png', 'images/products/produk_1780672684_0cb2d8a5.png', 'images/products/produk_1780672684_893469e4.png'),
(11, 'Dining Chair Premium', 'Kursi', 1150000, 30, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', 'Putih Gading,Cream Beige, Pasir Beige, Abu-abu Muda, Abu-abu Arang, Hijau Zaitun, Biru Tua, Cokelat Moka', '', '', 'images/products/produk_1780934172_6bb12c0a.png', 'images/products/produk_1780934172_ae71cd3b.png', 'images/products/produk_1780934172_756569ec.png', 'images/products/produk_1780934172_a241180c.png', 'images/products/produk_1780934172_6bb12c0a.png'),
(12, 'Arm Chair Modern', 'Kursi', 1550000, 20, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', 'Putih Gading,Cream Beige, Pasir Beige, Abu-abu Muda, Abu-abu Arang, Hijau Zaitun, Biru Tua, Cokelat Moka', '', '', 'images/products/produk_1780934284_bc603b30.png', 'images/products/produk_1780934284_eb8f6c4c.png', 'images/products/produk_1780934284_63a5fbb4.png', 'images/products/produk_1780934284_780d6cac.png', 'images/products/produk_1780934284_bc603b30.png'),
(13, 'Dining Chair Luxury', 'Kursi', 1750000, 10, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', 'Putih Gading,Cream Beige, Pasir Beige, Abu-abu Muda, Abu-abu Arang, Hijau Zaitun, Biru Tua, Cokelat Moka', '', '', 'images/products/produk_1780936048_63aa7a29.png', 'images/products/produk_1780936048_f1b55269.png', 'images/products/produk_1780936048_f0466be0.png', 'images/products/produk_1780936048_2b805b88.png', 'images/products/produk_1780936048_63aa7a29.png'),
(14, 'Bar Chair Modern', 'Kursi', 1250000, 13, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', 'Putih Gading,Cream Beige, Pasir Beige, Abu-abu Muda, Abu-abu Arang, Hijau Zaitun, Biru Tua, Cokelat Moka', '', '', 'images/products/produk_1780936137_7665c724.png', 'images/products/produk_1780936137_c113a308.png', 'images/products/produk_1780936137_fa50b467.png', 'images/products/produk_1780936137_29270d11.png', 'images/products/produk_1780936137_7665c724.png'),
(15, 'Office Chair Upholstered', 'Kursi', 2250000, 17, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', 'Putih Gading,Cream Beige, Pasir Beige, Abu-abu Muda, Abu-abu Arang, Hijau Zaitun, Biru Tua, Cokelat Moka', '', '', 'images/products/produk_1780936230_505f169c.png', 'images/products/produk_1780936230_78c982d5.png', 'images/products/produk_1780936230_6de601ce.png', 'images/products/produk_1780936230_2485092f.png', 'images/products/produk_1780936230_505f169c.png'),
(16, 'Dining Table 4 Seat', 'Meja', 4500000, 10, 'White Oak, Natural Oak, Walnut, Dark Walnut, Jati Natural', '-', '', '', 'images/products/produk_1780936420_e62d7b0e.png', 'images/products/produk_1780936420_f2ea4982.png', 'images/products/produk_1780936420_7a2dc431.png', 'images/products/produk_1780936420_bc2a52f6.png', 'images/products/produk_1780936420_e62d7b0e.png'),
(17, 'Dining Table 8 Seat', 'Meja', 10500000, 10, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', '-', '', '', 'images/products/produk_1780938113_0683cab0.png', 'images/products/produk_1780938113_f37e49ab.png', 'images/products/produk_1780938113_f39129cc.png', 'images/products/produk_1780938113_b8adfaff.png', 'images/products/produk_1780938113_0683cab0.png'),
(18, 'Dining Table 6 Seat', 'Meja', 7500000, 18, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', '-', '', '', 'images/products/produk_1780938525_e2825f0c.png', 'images/products/produk_1780938525_3e0e44ab.png', 'images/products/produk_1780938525_91082641.png', 'images/products/produk_1780938525_b8efa237.png', 'images/products/produk_1780938525_e2825f0c.png'),
(19, 'Extendable Dining Table', 'Meja', 13700000, 11, 'Kayu Oak Alami, Kayu Oak Putih, Kayu Kenari, Kayu Kenari Gelap, Kayu Jati Alami, Kayu Hitam', '-', '', '', 'images/products/produk_1780938939_3da02c0b.png', 'images/products/produk_1780938939_d1c6171b.png', 'images/products/produk_1780938939_54bc811a.png', 'images/products/produk_1780938939_85c693ff.png', 'images/products/produk_1780938939_3da02c0b.png'),
(20, 'Round Coffee Table', 'Meja', 1880000, 42, 'Jati, Mahoni, Trembesi, Pinus', 'Ivory White, Cream Beige, Sand Beige, Light Grey, Charcoal Grey, Olive Green, Navy Blue, Mocha Brown', '', '', 'images/products/produk_1780939161_7fa999c9.png', 'images/products/produk_1780939161_905e4bd1.png', 'images/products/produk_1780939161_fbe22813.png', 'images/products/produk_1780939161_0408c5e9.png', 'images/products/produk_1780939161_7fa999c9.png');

-- --------------------------------------------------------

--
-- Table structure for table `store_locations`
--

CREATE TABLE `store_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_locations`
--

INSERT INTO `store_locations` (`id`, `name`, `phone`, `address`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(2, 'Gudang senjata', '0929870334', '0', -7.5516995, 110.7361364, '2026-05-28 16:36:32', NULL),
(3, 'Gebong Narkoba', '099887909', '0', -6.1973253, 106.7698359, '2026-05-28 16:38:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `profile_photo`, `address`, `username`, `created_at`) VALUES
(1, 'Abang', 'user@gmail.com', '$2y$10$R64NLsIcW/J61/B873tg7OnidsMziGbayd0abGKsKnHsvjhnILi.q', NULL, NULL, NULL, NULL, '2026-05-26 19:01:44'),
(2, 'user', 'user1@gmail.com', '$2y$10$4aKOvsAcX7q/xIpeczDOT.akRQV7BDA7LtGJiY1UlGNInDzqoAIPe', NULL, NULL, NULL, NULL, '2026-05-26 19:01:44'),
(7, 'Mas Ganteng', 'abangganteng@gmail.com', '$2y$10$BaibBXozlaZ7qUnQqRhG/OStjY7l33xq6BX4w1DIlJ9v8ZVXmQW1q', '', 'images/profile/user_7_1779523305.jpg', '', NULL, '2026-05-26 19:01:44'),
(8, 'fajar', 'bagus@example.com', '$2y$10$PLLE/ajIbg60jKuZi/mA2OYaHn.HYBppYY4yGQY51lLbwe4ieurLi', NULL, NULL, NULL, NULL, '2026-05-30 15:03:49'),
(9, 'denis', 'denisganteng@example.com', '$2y$10$mYcRtawDXjlWKJsmGj44qeXPmzUrbrY8ubSW/1ENKufb9ZrSoxiP6', NULL, NULL, NULL, NULL, '2026-06-05 09:30:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT 'Rumah',
  `recipient_name` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `recipient_name`, `phone`, `province`, `city`, `district`, `postal_code`, `address`, `is_primary`, `created_at`, `updated_at`, `latitude`, `longitude`) VALUES
(1, 7, 'Rumah', 'Faiz Rasyidan', '09656789', 'JawaTengah', 'Karanganyar', 'Jaten', '57771', 'Jetis, Karanganyar, Central Java, Java, 57771, Indonesia', 1, '2026-05-23 08:57:57', '2026-05-27 20:13:00', -7.5509943, 110.8897170),
(3, 7, 'Kantor', 'AbangKuhh', '0899365734827', 'JawaTengah', 'Karanganyar', 'Mojosongo', '57127', 'Jalan Jayawijaya, Mojosongo, Surakarta, Central Java, Java, 57127, Indonesia', 0, '2026-05-27 20:11:31', NULL, -7.5372792, 110.8419201),
(4, 8, 'Rumah', 'Gus Bahlul', '08972667823', 'JawaTengah', 'Sukoharjo', 'bekonang', '57554', 'Bekonang, Sukoharjo, Central Java, Java, 57554, Indonesia', 0, '2026-05-31 15:59:00', NULL, -7.6044561, 110.8710033);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_spend` int(11) NOT NULL DEFAULT 0,
  `max_discount` int(11) NOT NULL DEFAULT 0,
  `quota` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `name`, `type`, `discount_value`, `min_spend`, `max_discount`, `quota`, `active`, `valid_from`, `valid_until`, `created_at`, `updated_at`) VALUES
(1, 'VC658BAD', 'Idul Adha', 'percent', 0.10, 500000, 300000, 1, 1, '2026-05-25', '2026-05-27', '2026-05-25 05:17:26', '2026-05-26 20:04:25'),
(6, 'VC89FD9A', 'Idul Adha', 'percent', 0.10, 500000, 300000, 2, 1, '2026-05-25', '2026-05-27', '2026-05-25 05:20:33', '2026-05-27 00:28:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sender` (`sender`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `courier_locations`
--
ALTER TABLE `courier_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_personnel_id` (`delivery_personnel_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `delivery_personnel`
--
ALTER TABLE `delivery_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_number` (`vehicle_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_driver_name` (`driver_name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`);

--
-- Indexes for table `order_trackings`
--
ALTER TABLE `order_trackings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_locations`
--
ALTER TABLE `store_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `courier_locations`
--
ALTER TABLE `courier_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_personnel`
--
ALTER TABLE `delivery_personnel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `order_trackings`
--
ALTER TABLE `order_trackings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `store_locations`
--
ALTER TABLE `store_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courier_locations`
--
ALTER TABLE `courier_locations`
  ADD CONSTRAINT `courier_locations_ibfk_1` FOREIGN KEY (`delivery_personnel_id`) REFERENCES `delivery_personnel` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courier_locations_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_trackings`
--
ALTER TABLE `order_trackings`
  ADD CONSTRAINT `order_trackings_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
