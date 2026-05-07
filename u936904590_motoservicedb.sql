-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 07, 2026 at 05:51 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u936904590_motoservicedb`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `admin_id`, `admin_name`, `action_type`, `details`, `ip_address`, `created_at`) VALUES
(71, 17, 'Admin', 'mechanic_create', 'Created mechanic account: KELVIN CENTENO (Email: cvin.bpc.016@gmail.com)', '2405:8d40:440e:7be9:30e6:88ce:a40d:76f7', '2026-03-24 09:17:12');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity`
--

CREATE TABLE `admin_activity` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `mechanic_id` int(11) DEFAULT NULL,
  `brand` varchar(50) NOT NULL,
  `vehicle_type` enum('Automatic','Manual') NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `service_location` enum('home','shop') NOT NULL,
  `service_address` text DEFAULT NULL,
  `schedule` datetime NOT NULL,
  `note` text DEFAULT NULL,
  `mechanic_note` text DEFAULT NULL,
  `booking_fee` decimal(10,2) DEFAULT 100.00,
  `labor_fee` decimal(10,2) NOT NULL,
  `parts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parts`)),
  `parts_total` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','on_hold','preparing','in_progress','awaiting_customer_action','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `service_fee` decimal(10,2) NOT NULL,
  `cancelled_by` enum('customer','mechanic') DEFAULT NULL,
  `tire_size` varchar(50) DEFAULT NULL,
  `absence_id` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_parts`
--

CREATE TABLE `booking_parts` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `part_name` varchar(100) NOT NULL,
  `part_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `coverage` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `service_id`, `name`, `price`, `coverage`, `created_at`) VALUES
(1, 1, 'Shell Advance', 450.00, NULL, '2026-01-27 10:33:54'),
(2, 1, 'Motul', 650.00, NULL, '2026-01-27 10:33:54'),
(3, 1, 'Castrol', 550.00, NULL, '2026-01-27 10:33:54'),
(4, 1, 'Pennzoil', 600.00, NULL, '2026-01-27 10:33:54'),
(13, 5, 'Yuasa Battery', 600.00, NULL, '2026-01-27 10:33:54'),
(14, 5, 'Century Battery', 550.00, NULL, '2026-01-27 10:33:54'),
(15, 5, 'Exide Battery', 650.00, NULL, '2026-01-27 10:33:54'),
(16, 5, 'GS Battery', 700.00, NULL, '2026-01-27 10:33:54'),
(17, 6, 'DID Chain', 900.00, NULL, '2026-01-27 10:33:54'),
(18, 6, 'RK Chain', 850.00, NULL, '2026-01-27 10:33:54'),
(19, 6, 'Regina Chain', 950.00, NULL, '2026-01-27 10:33:54'),
(20, 6, 'Tsubaki Chain', 1000.00, NULL, '2026-01-27 10:33:54'),
(21, 7, 'NGK Spark Plug', 400.00, NULL, '2026-01-27 10:33:54'),
(22, 7, 'Denso Spark Plug', 450.00, NULL, '2026-01-27 10:33:54'),
(23, 7, 'Bosch Spark Plug', 500.00, NULL, '2026-01-27 10:33:54'),
(24, 7, 'Champion Spark Plug', 350.00, NULL, '2026-01-27 10:33:54'),
(29, 9, 'Prestone Coolant', 700.00, NULL, '2026-01-27 10:33:54'),
(30, 9, 'Zerex Coolant', 650.00, NULL, '2026-01-27 10:33:54'),
(31, 9, 'Peak Coolant', 750.00, NULL, '2026-01-27 10:33:54'),
(32, 9, 'Valvoline Coolant', 800.00, NULL, '2026-01-27 10:33:54'),
(51, 3, 'Michelin Pilot Street', 2800.00, '[\"90/80-17\", \"100/80-17\", \"110/80-17\", \"120/80-17\", \"130/70-17\", \"140/70-17\"]', '2026-01-28 21:57:58'),
(52, 3, 'Michelin City Grip', 2500.00, '[\"100/90-18\", \"110/80-18\", \"120/70-18\", \"130/70-18\"]', '2026-01-28 21:57:58'),
(53, 3, 'Bridgestone Battlax', 2600.00, '[\"90/90-18\", \"100/90-18\", \"110/80-18\", \"120/80-18\", \"130/70-17\", \"140/60-17\"]', '2026-01-28 21:57:58'),
(54, 3, 'Bridgestone Turanza', 2450.00, '[\"80/90-17\", \"90/90-17\", \"100/90-17\", \"110/90-17\", \"120/80-17\"]', '2026-01-28 21:57:58'),
(55, 3, 'Goodyear Assurance', 2400.00, '[\"90/90-17\", \"100/90-17\", \"110/80-17\", \"120/80-17\", \"130/70-17\"]', '2026-01-28 21:57:58'),
(56, 3, 'Goodyear Comfort Tyre', 2350.00, '[\"100/90-18\", \"110/80-18\", \"120/70-18\", \"130/60-18\"]', '2026-01-28 21:57:58'),
(57, 3, 'Dunlop TT23', 2200.00, '[\"90/90-18\", \"100/90-18\", \"110/80-18\", \"120/80-18\", \"130/70-17\"]', '2026-01-28 21:57:58'),
(58, 3, 'Dunlop Sportmax', 2550.00, '[\"120/70-17\", \"130/70-17\", \"140/60-17\", \"150/60-17\"]', '2026-01-28 21:57:58'),
(59, 3, 'Continental ContiMotion', 2700.00, '[\"100/90-17\", \"110/80-17\", \"120/80-17\", \"130/70-17\", \"140/70-17\"]', '2026-01-28 21:57:58'),
(60, 3, 'Continental Road Attack', 2900.00, '[\"120/70-17\", \"130/70-17\", \"140/70-17\", \"150/60-17\", \"160/60-17\"]', '2026-01-28 21:57:58'),
(61, 3, 'Pirelli City Demon', 2300.00, '[\"90/90-18\", \"100/90-18\", \"110/80-18\", \"120/80-18\"]', '2026-01-28 21:57:58'),
(62, 3, 'Pirelli Diablo Rosso', 2800.00, '[\"120/70-17\", \"130/70-17\", \"140/70-17\", \"150/60-17\"]', '2026-01-28 21:57:58'),
(63, 3, 'Metzeler Feelfree', 2100.00, '[\"100/90-18\", \"110/80-18\", \"120/80-18\", \"130/70-18\"]', '2026-01-28 21:57:58'),
(64, 3, 'Metzeler Sportec', 2650.00, '[\"120/70-17\", \"130/70-17\", \"140/70-17\"]', '2026-01-28 21:57:58'),
(65, 3, 'Kenda Karoo', 1800.00, '[\"90/90-18\", \"100/90-18\", \"110/80-18\", \"120/80-18\"]', '2026-01-28 21:57:58'),
(66, 3, 'Kenda Roadgo', 1950.00, '[\"100/90-17\", \"110/80-17\", \"120/80-17\", \"130/70-17\"]', '2026-01-28 21:57:58'),
(67, 3, 'Maxxis Advance', 2150.00, '[\"90/90-18\", \"100/90-18\", \"110/80-18\", \"120/80-18\", \"130/70-17\"]', '2026-01-28 21:57:58'),
(68, 3, 'Maxxis S70', 2000.00, '[\"100/80-17\", \"110/80-17\", \"120/80-17\"]', '2026-01-28 21:57:58'),
(69, 10, 'Basic Maintenance Kit', 400.00, '[\"Oil Change\", \"Oil Filter Replacement\", \"Engine Inspection\", \"Fluid Level Check\"]', '2026-01-28 22:02:19'),
(70, 10, 'Standard Maintenance Package', 650.00, '[\"Oil Change\", \"Oil Filter Replacement\", \"Air Filter Replacement\", \"Spark Plug Inspection\", \"Engine Inspection\", \"Fluid Level Check\", \"Battery Check\"]', '2026-01-28 22:02:19'),
(71, 10, 'Premium Maintenance Package', 1200.00, '[\"Oil Change\", \"Oil Filter Replacement\", \"Air Filter Replacement\", \"Spark Plug Replacement\", \"Engine Tune-up\", \"Brake Inspection\", \"Tire Rotation\", \"Fluid Level Check\", \"Battery Check\", \"Chain Lubrication\", \"Coolant Check\"]', '2026-01-28 22:02:19'),
(72, 10, 'Complete Overhaul Package', 1800.00, '[\"Full Engine Oil Change\", \"Oil Filter Replacement\", \"Air Filter Replacement\", \"Cabin Air Filter\", \"Spark Plugs Replacement\", \"Engine Tune-up\", \"Brake System Check & Clean\", \"Tire Rotation & Balance\", \"Fluid Levels & Top-up\", \"Battery Test & Clean\", \"Chain Cleaning & Lubrication\", \"Coolant Flush Check\", \"Suspension Inspection\", \"Safety Systems Check\"]', '2026-01-28 22:02:19'),
(73, 4, 'Basic Engine Tune-Up', 300.00, '[\"Spark Plug Inspection\", \"Engine Compression Check\", \"Idle Speed Adjustment\", \"Engine Oil Check\"]', '2026-01-28 22:04:17'),
(74, 4, 'Standard Engine Tune-Up', 500.00, '[\"Spark Plug Replacement\", \"Engine Compression Check\", \"Carburetor Adjustment\", \"Ignition Timing Check\", \"Engine Oil Change\", \"Oil Filter Replacement\", \"Fuel Filter Inspection\"]', '2026-01-28 22:04:17'),
(75, 4, 'Premium Engine Tune-Up', 850.00, '[\"Spark Plug Replacement\", \"Engine Compression Check\", \"Complete Carburetor Overhaul\", \"Ignition System Check & Adjust\", \"Engine Oil Change\", \"Oil Filter Replacement\", \"Fuel Filter Replacement\", \"Air Filter Replacement\", \"Valve Clearance Adjustment\", \"Engine Cooling System Check\"]', '2026-01-28 22:04:17'),
(76, 4, 'Complete Performance Tune-Up', 1500.00, '[\"Spark Plugs Replacement\", \"Complete Engine Compression Test\", \"Full Carburetor Overhaul & Cleaning\", \"Ignition System Complete Check & Adjustment\", \"Engine Oil Change with Premium Oil\", \"Oil Filter Replacement\", \"Fuel Filter Replacement\", \"Air Filter Replacement\", \"Valve Clearance & Timing Adjustment\", \"Cooling System Flush & Refill\", \"Fuel System Cleaning\", \"Engine Performance Test\", \"Emissions Check\"]', '2026-01-28 22:04:17'),
(95, 69, 'K&N', 300.00, NULL, '2026-02-06 22:23:00'),
(96, 69, 'Sprint Filter', 400.00, NULL, '2026-02-06 22:23:00'),
(97, 69, 'Fram Air Filter', 259.00, NULL, '2026-02-06 22:23:00'),
(98, 69, 'WIX Air Filter', 400.00, NULL, '2026-02-06 22:23:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address`, `city`, `barangay`, `lat`, `lng`, `created_at`) VALUES
(31, 52, 'Meycauayan Camalig', 'Malolos', 'Caingin', 0.00000000, 0.00000000, '2026-03-24 09:16:15'),
(33, 54, '383 kabatuhan st.', 'Meycauayan', 'Libtong', 0.00000000, 0.00000000, '2026-04-06 20:02:13'),
(34, 55, '311 bayan', 'Meycauayan', 'Hulo', 0.00000000, 0.00000000, '2026-04-07 02:47:38');

-- --------------------------------------------------------

--
-- Table structure for table `customer_notifications`
--

CREATE TABLE `customer_notifications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_unread_notifications`
-- (See below for the actual view)
--
CREATE TABLE `customer_unread_notifications` (
`customer_id` int(11)
,`unread_count` bigint(21)
,`latest_notification` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `date`, `name`) VALUES
(1, '2026-01-01', 'New Year\'s Day'),
(2, '2026-02-09', 'Chinese New Year'),
(3, '2026-02-25', 'EDSA People Power Revolution Anniversary'),
(4, '2026-04-02', 'Maundy Thursday'),
(5, '2026-04-03', 'Good Friday'),
(6, '2026-04-04', 'Black Saturday'),
(7, '2026-04-09', 'Araw ng Kagitingan'),
(8, '2026-05-01', 'Labor Day'),
(9, '2026-06-12', 'Independence Day'),
(10, '2026-08-21', 'Ninoy Aquino Day'),
(11, '2026-08-25', 'National Heroes Day'),
(12, '2026-11-01', 'All Saints\' Day'),
(13, '2026-11-30', 'Bonifacio Day'),
(14, '2026-12-25', 'Christmas Day'),
(15, '2026-12-30', 'Rizal Day'),
(16, '2026-12-31', 'Last Day of the Year');

-- --------------------------------------------------------

--
-- Table structure for table `mechanics`
--

CREATE TABLE `mechanics` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `status` enum('available','busy') DEFAULT 'available',
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mechanic_absences`
--

CREATE TABLE `mechanic_absences` (
  `id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mechanic_absences`
--

INSERT INTO `mechanic_absences` (`id`, `mechanic_id`, `reason`, `start_date`, `end_date`, `notes`, `created_at`) VALUES
(75, 53, 'sa', '2026-04-07', '2026-04-08', NULL, '2026-04-07 04:21:20'),
(76, 53, 'z', '2026-04-09', '2026-04-09', NULL, '2026-04-07 05:35:54');

-- --------------------------------------------------------

--
-- Table structure for table `mechanic_ratings`
--

CREATE TABLE `mechanic_ratings` (
  `id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `status` enum('sent','read') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `receiver_type` varchar(50) NOT NULL,
  `booking_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motorcycle_brands`
--

CREATE TABLE `motorcycle_brands` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `motorcycle_brands`
--

INSERT INTO `motorcycle_brands` (`id`, `name`) VALUES
(1, 'Honda'),
(4, 'Kawasaki'),
(5, 'Other'),
(2, 'Suzuki'),
(3, 'Yamaha');

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` int(11) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` enum('register','reset') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otps`
--

INSERT INTO `otps` (`id`, `email`, `phone`, `otp_code`, `purpose`, `expires_at`, `created_at`) VALUES
(92, 'marcoronos34@gmail.com', '', '447696', 'register', '2026-04-07 10:57:13', '2026-04-07 10:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `parts`
--

CREATE TABLE `parts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parts`
--

INSERT INTO `parts` (`id`, `name`, `price`, `category`) VALUES
(1, 'Shell Advance Oil', 450.00, 'Oil'),
(2, 'Motul Oil', 650.00, 'Oil'),
(3, 'Castrol Oil', 550.00, 'Oil'),
(4, 'Pennzoil Oil', 600.00, 'Oil'),
(5, 'Oil Filter', 200.00, 'Filters'),
(6, 'Brake Fluid', 300.00, 'Brakes'),
(7, 'Michelin Tire', 3500.00, 'Tires'),
(8, 'Pirelli Tire', 4200.00, 'Tires'),
(9, 'Bridgestone Tire', 3800.00, 'Tires'),
(10, 'Dunlop Tire', 4000.00, 'Tires'),
(11, 'Tire Valve', 150.00, 'Tires'),
(12, 'Battery', 500.00, 'Electrical'),
(13, 'Chain', 800.00, 'Drive'),
(14, 'Spark Plug', 300.00, 'Ignition'),
(15, 'Air Filter', 250.00, 'Filters'),
(16, 'Coolant', 400.00, 'Cooling');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `base_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_key` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration_hours` decimal(4,1) NOT NULL DEFAULT 2.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `slug`, `base_price`, `created_at`, `service_key`, `description`, `duration_hours`) VALUES
(1, 'Change Oil', 'change_oil', 200.00, '2026-01-27 10:33:28', 'change_oil', NULL, 2.0),
(3, 'Tire Change', 'tire', 150.00, '2026-01-27 10:33:28', 'tire_change', NULL, 2.0),
(4, 'Engine Tune-Up', 'engine-tune-up', 300.00, '2026-01-27 10:33:28', 'engine_tune-up', NULL, 2.0),
(5, 'Battery Replacement', 'battery', 250.00, '2026-01-27 10:33:28', 'battery_replacement', NULL, 2.0),
(6, 'Chain Replacement', 'chain', 180.00, '2026-01-27 10:33:28', 'chain_replacement', NULL, 2.0),
(7, 'Spark Plug Replacement', 'spark_plug', 120.00, '2026-01-27 10:33:28', 'spark_plug_replacement', NULL, 2.0),
(9, 'Coolant Flush', 'coolant', 220.00, '2026-01-27 10:33:28', 'coolant_flush', NULL, 2.0),
(10, 'General Maintenance', 'general-maintenance', 400.00, '2026-01-27 10:33:28', 'general_maintenance', NULL, 2.0),
(69, 'Air Filter Change', 'air-filter-change', 50.00, '2026-02-06 22:23:00', 'air filter', NULL, 2.0);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('home_service_fee', '150'),
('receipt_title', 'MotorService Booking Receipt');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `role` enum('customer','admin','mechanic') DEFAULT 'customer',
  `is_disabled` tinyint(1) DEFAULT 0,
  `no_show_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `display_name` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `no_show_until` date DEFAULT NULL,
  `no_show_last_date` date DEFAULT NULL,
  `no_show_month` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_disabled`, `no_show_count`, `created_at`, `display_name`, `profile_pic`, `address`, `is_available`, `no_show_until`, `no_show_last_date`, `no_show_month`) VALUES
(17, 'admin@gmail.com', '$2y$10$PTEuT0HAQ25A2hRvy9Wa3uH4MwuO8FHzufWz8Jds8Vji16Ikgwy6i', 'Admin', 'Jhai', 'admin', 0, 0, '2025-11-13 19:53:54', NULL, 'profile_17_1770556708.jpeg', NULL, 1, NULL, NULL, NULL),
(53, 'cvin.bpc.016@gmail.com', '$2y$10$sUSnbk89US7U0g.poNmZBuJgxX10rSc0lKLGYqRE4iAKsTFwnc0ye', 'KELVIN', 'CENTENO', 'mechanic', 0, 0, '2026-03-24 17:17:12', NULL, NULL, NULL, 1, NULL, NULL, NULL),
(54, 'jrchtrndd25@gmail.com', '$2y$10$vKeBz7lua9i7uoGvojZPYejA4AVXKCiKVcn3qGbD2xjnal77HQ1Mi', 'jericho', 'trinidad', 'customer', 0, 1, '2026-04-07 04:02:13', NULL, NULL, NULL, 1, NULL, NULL, NULL),
(55, 'marcoronos34@gmail.com', '$2y$10$rIJcW3syAn5K5l9o56aNp.MRnh3WKOEiksw4ieQJxKB92r2kmWKVS', 'marc', 'oronos', 'customer', 0, 0, '2026-04-07 10:47:38', NULL, NULL, NULL, 1, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action_type` (`action_type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_activity_logs_date` (`created_at`),
  ADD KEY `idx_activity_logs_type` (`action_type`);

--
-- Indexes for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_customer` (`customer_id`),
  ADD KEY `fk_booking_mechanic` (`mechanic_id`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `absence_id` (`absence_id`),
  ADD KEY `idx_status_customer` (`status`,`customer_id`),
  ADD KEY `idx_status_mechanic` (`status`,`mechanic_id`);

--
-- Indexes for table `booking_parts`
--
ALTER TABLE `booking_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_customer_unread` (`customer_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`);

--
-- Indexes for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mechanic_absences`
--
ALTER TABLE `mechanic_absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mechanic_absence_dates` (`mechanic_id`,`start_date`,`end_date`);

--
-- Indexes for table `mechanic_ratings`
--
ALTER TABLE `mechanic_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mechanic_id` (`mechanic_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender_receiver` (`sender_id`,`receiver_id`);

--
-- Indexes for table `motorcycle_brands`
--
ALTER TABLE `motorcycle_brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phone` (`phone`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mechanic_id` (`mechanic_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `service_key` (`service_key`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`email`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `admin_activity`
--
ALTER TABLE `admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `booking_parts`
--
ALTER TABLE `booking_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `mechanics`
--
ALTER TABLE `mechanics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mechanic_absences`
--
ALTER TABLE `mechanic_absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `mechanic_ratings`
--
ALTER TABLE `mechanic_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=229;

--
-- AUTO_INCREMENT for table `motorcycle_brands`
--
ALTER TABLE `motorcycle_brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

-- --------------------------------------------------------

--
-- Structure for view `customer_unread_notifications`
--
DROP TABLE IF EXISTS `customer_unread_notifications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u936904590_motoservice123`@`127.0.0.1` SQL SECURITY DEFINER VIEW `customer_unread_notifications`  AS SELECT `customer_notifications`.`customer_id` AS `customer_id`, count(0) AS `unread_count`, max(`customer_notifications`.`created_at`) AS `latest_notification` FROM `customer_notifications` WHERE `customer_notifications`.`is_read` = 0 GROUP BY `customer_notifications`.`customer_id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD CONSTRAINT `admin_activity_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `admin_activity_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`absence_id`) REFERENCES `mechanic_absences` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_booking_mechanic` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `booking_parts`
--
ALTER TABLE `booking_parts`
  ADD CONSTRAINT `booking_parts_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `brands`
--
ALTER TABLE `brands`
  ADD CONSTRAINT `brands_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD CONSTRAINT `customer_notifications_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_notifications_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD CONSTRAINT `mechanics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mechanic_absences`
--
ALTER TABLE `mechanic_absences`
  ADD CONSTRAINT `mechanic_absences_ibfk_1` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mechanic_ratings`
--
ALTER TABLE `mechanic_ratings`
  ADD CONSTRAINT `mechanic_ratings_ibfk_1` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
