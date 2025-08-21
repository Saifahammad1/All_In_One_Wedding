-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 22, 2025 at 12:32 AM
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
-- Database: `all_in_one_wedding`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_vendors_with_services`
-- (See below for the actual view)
--
CREATE TABLE `active_vendors_with_services` (
`id` int(11)
,`business_name` varchar(100)
,`owner_name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`category` enum('photographer','videographer','florist','caterer','venue','music_dj','decoration','makeup_artist','wedding_planner','transportation','cake_baker','other')
,`description` text
,`city` varchar(50)
,`state` varchar(50)
,`price_range` enum('$','$$','$$$','$$$$')
,`rating` decimal(3,2)
,`total_reviews` int(11)
,`total_services` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `phone`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@allinone-wedding.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'super_admin', 'active', '2025-07-21 10:40:02', '2025-07-21 10:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `booked_vendors`
--

CREATE TABLE `booked_vendors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_category` varchar(100) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `vendor_email` varchar(255) DEFAULT NULL,
  `vendor_phone` varchar(20) DEFAULT NULL,
  `contract_amount` decimal(10,2) DEFAULT NULL,
  `booked_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `service_date` date NOT NULL,
  `service_time` time DEFAULT NULL,
  `service_duration` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `booking_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`booking_details`)),
  `special_requests` text DEFAULT NULL,
  `contract_signed` tinyint(1) DEFAULT 0,
  `payment_status` enum('unpaid','partially_paid','fully_paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_items`
--

CREATE TABLE `budget_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task` text NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(20) DEFAULT NULL,
  `partner_name` varchar(100) DEFAULT NULL,
  `wedding_date` date DEFAULT NULL,
  `venue_location` varchar(200) DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `guest_count` int(11) DEFAULT NULL,
  `status` enum('active','inactive','married') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `password_hash` varchar(255) NOT NULL,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `newsletter_subscription` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `partner_name`, `wedding_date`, `venue_location`, `budget`, `guest_count`, `status`, `profile_image`, `address`, `city`, `state`, `zip_code`, `notes`, `created_at`, `updated_at`, `password_hash`, `email_verification_token`, `newsletter_subscription`) VALUES
(12, 'Saif', 'Ahammad', 'ahammadsaif@gmail.com', '$2y$10$fx80Zh5ofbUwLwpxu/V5.uy//VvNVLLoHg6iVcyVdWhJwJp79mXye', '(018) 581-25221', NULL, '2025-08-08', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-29 17:09:54', '2025-07-29 17:09:54', '', '351a1c2b9760eee4a7cd0a09addc3c225bf0365842eb3446c53c705add65b75f', 0);

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `plus_one` tinyint(1) DEFAULT 0,
  `status` enum('pending','confirmed','declined') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_type` enum('customer','vendor','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('customer','vendor','admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message_text` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_type` enum('customer','vendor','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('booking','payment','review','system','reminder') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_type` enum('customer','vendor','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','debit_card','bank_transfer','cash','check') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_gateway` varchar(50) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `notes` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bride_name` varchar(255) NOT NULL,
  `groom_name` varchar(255) NOT NULL,
  `wedding_date` date NOT NULL,
  `total_budget` decimal(10,2) DEFAULT 0.00,
  `expected_guests` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_logs`
--

CREATE TABLE `registration_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `registration_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `review_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`review_images`)),
  `response_text` text DEFAULT NULL,
  `response_date` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `reviews`
--
DELIMITER $$
CREATE TRIGGER `update_vendor_rating_after_review_insert` AFTER INSERT ON `reviews` FOR EACH ROW BEGIN
    UPDATE vendors 
    SET 
        rating = (
            SELECT ROUND(AVG(rating), 2) 
            FROM reviews 
            WHERE vendor_id = NEW.vendor_id AND status = 'approved'
        ),
        total_reviews = (
            SELECT COUNT(*) 
            FROM reviews 
            WHERE vendor_id = NEW.vendor_id AND status = 'approved'
        )
    WHERE id = NEW.vendor_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_vendor_rating_after_review_update` AFTER UPDATE ON `reviews` FOR EACH ROW BEGIN
    UPDATE vendors 
    SET 
        rating = (
            SELECT ROUND(AVG(rating), 2) 
            FROM reviews 
            WHERE vendor_id = NEW.vendor_id AND status = 'approved'
        ),
        total_reviews = (
            SELECT COUNT(*) 
            FROM reviews 
            WHERE vendor_id = NEW.vendor_id AND status = 'approved'
        )
    WHERE id = NEW.vendor_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `data_type` enum('string','number','boolean','json') DEFAULT 'string',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `data_type`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'All in One Wedding', 'Website name', 'string', '2025-07-21 10:40:02', '2025-07-21 10:40:02'),
(2, 'site_email', 'info@allinone-wedding.com', 'Main contact email', 'string', '2025-07-21 10:40:02', '2025-07-21 10:40:02'),
(3, 'booking_advance_days', '30', 'Minimum days in advance for bookings', 'number', '2025-07-21 10:40:02', '2025-07-21 10:40:02'),
(4, 'commission_rate', '0.10', 'Platform commission rate (10%)', 'number', '2025-07-21 10:40:02', '2025-07-21 10:40:02'),
(5, 'auto_approve_vendors', 'false', 'Automatically approve vendor registrations', 'boolean', '2025-07-21 10:40:02', '2025-07-21 10:40:02'),
(6, 'max_upload_size', '5', 'Maximum file upload size in MB', 'number', '2025-07-21 10:40:02', '2025-07-21 10:40:02');

-- --------------------------------------------------------

--
-- Stand-in structure for view `upcoming_bookings`
-- (See below for the actual view)
--
CREATE TABLE `upcoming_bookings` (
`booking_id` int(11)
,`service_date` date
,`service_time` time
,`total_amount` decimal(10,2)
,`booking_status` enum('pending','confirmed','cancelled','completed')
,`customer_name` varchar(101)
,`customer_email` varchar(100)
,`customer_phone` varchar(20)
,`vendor_name` varchar(100)
,`vendor_category` enum('photographer','videographer','florist','caterer','venue','music_dj','decoration','makeup_artist','wedding_planner','transportation','cake_baker','other')
,`vendor_email` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `business_name` varchar(100) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL,
  `category` enum('photographer','videographer','florist','caterer','venue','music_dj','decoration','makeup_artist','wedding_planner','transportation','cake_baker','other') NOT NULL,
  `description` text DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `service_areas` text DEFAULT NULL,
  `price_range` enum('$','$$','$$$','$$$$') DEFAULT '$$',
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `experience_years` int(11) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `insurance_info` text DEFAULT NULL,
  `portfolio_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`portfolio_images`)),
  `availability_calendar` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`availability_calendar`)),
  `status` enum('active','inactive','pending_approval') DEFAULT 'pending_approval',
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `service_area` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `newsletter_subscription` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `business_name`, `owner_name`, `email`, `password`, `phone`, `category`, `description`, `website`, `address`, `city`, `state`, `zip_code`, `service_areas`, `price_range`, `rating`, `total_reviews`, `experience_years`, `license_number`, `insurance_info`, `portfolio_images`, `availability_calendar`, `status`, `featured`, `created_at`, `updated_at`, `first_name`, `last_name`, `business_type`, `service_area`, `password_hash`, `email_verification_token`, `newsletter_subscription`) VALUES
(26, 'Paragon Caterings', '', 'helal@gmail.com', '$2y$10$4ciEpBfnt8XVS6YHiPERb./CkdWhl/XTE4HiGkbWUjUOjzcC42bPy', '(018) 581-25221', 'photographer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$$', 0.00, 0, NULL, NULL, NULL, NULL, NULL, 'pending_approval', 0, '2025-07-29 19:07:22', '2025-07-29 19:07:22', 'Helal', 'Uddin', 'catering', NULL, '', '791a8eb7e8681873bb9804cd6c04d5f5c4c34b6a33e87ef39052b9864091054c', 0);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_advertisements`
--

CREATE TABLE `vendor_advertisements` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `price` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_services`
--

CREATE TABLE `vendor_services` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_description` text DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `price_type` enum('fixed','per_hour','per_day','per_person','custom') DEFAULT 'fixed',
  `duration_hours` int(11) DEFAULT NULL,
  `max_guests` int(11) DEFAULT NULL,
  `included_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`included_items`)),
  `additional_charges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_charges`)),
  `availability_status` enum('available','unavailable','seasonal') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wedding_checklist`
--

CREATE TABLE `wedding_checklist` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `task_title` varchar(200) NOT NULL,
  `task_description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `assigned_vendor_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `active_vendors_with_services`
--
DROP TABLE IF EXISTS `active_vendors_with_services`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_vendors_with_services`  AS SELECT `v`.`id` AS `id`, `v`.`business_name` AS `business_name`, `v`.`owner_name` AS `owner_name`, `v`.`email` AS `email`, `v`.`phone` AS `phone`, `v`.`category` AS `category`, `v`.`description` AS `description`, `v`.`city` AS `city`, `v`.`state` AS `state`, `v`.`price_range` AS `price_range`, `v`.`rating` AS `rating`, `v`.`total_reviews` AS `total_reviews`, count(`vs`.`id`) AS `total_services` FROM (`vendors` `v` left join `vendor_services` `vs` on(`v`.`id` = `vs`.`vendor_id`)) WHERE `v`.`status` = 'active' GROUP BY `v`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `upcoming_bookings`
--
DROP TABLE IF EXISTS `upcoming_bookings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `upcoming_bookings`  AS SELECT `b`.`id` AS `booking_id`, `b`.`service_date` AS `service_date`, `b`.`service_time` AS `service_time`, `b`.`total_amount` AS `total_amount`, `b`.`status` AS `booking_status`, concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`, `c`.`email` AS `customer_email`, `c`.`phone` AS `customer_phone`, `v`.`business_name` AS `vendor_name`, `v`.`category` AS `vendor_category`, `v`.`email` AS `vendor_email` FROM ((`bookings` `b` join `customers` `c` on(`b`.`customer_id` = `c`.`id`)) join `vendors` `v` on(`b`.`vendor_id` = `v`.`id`)) WHERE `b`.`service_date` >= curdate() ORDER BY `b`.`service_date` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `booked_vendors`
--
ALTER TABLE `booked_vendors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bookings_customer` (`customer_id`),
  ADD KEY `idx_bookings_vendor` (`vendor_id`),
  ADD KEY `idx_bookings_date` (`service_date`),
  ADD KEY `idx_bookings_status` (`status`);

--
-- Indexes for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_customers_email` (`email`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_type`,`sender_id`),
  ADD KEY `idx_receiver` (`receiver_type`,`receiver_id`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_unread` (`is_read`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_type`,`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registration_logs`
--
ALTER TABLE `registration_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_reviews_vendor` (`vendor_id`),
  ADD KEY `idx_reviews_rating` (`rating`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_vendors_email` (`email`),
  ADD KEY `idx_vendors_category` (`category`),
  ADD KEY `idx_vendors_status` (`status`);

--
-- Indexes for table `vendor_advertisements`
--
ALTER TABLE `vendor_advertisements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_services`
--
ALTER TABLE `vendor_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `wedding_checklist`
--
ALTER TABLE `wedding_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_vendor_id` (`assigned_vendor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booked_vendors`
--
ALTER TABLE `booked_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_items`
--
ALTER TABLE `budget_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_logs`
--
ALTER TABLE `registration_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `vendor_advertisements`
--
ALTER TABLE `vendor_advertisements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_services`
--
ALTER TABLE `vendor_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wedding_checklist`
--
ALTER TABLE `wedding_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_logs`
--
ALTER TABLE `registration_logs`
  ADD CONSTRAINT `registration_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_services`
--
ALTER TABLE `vendor_services`
  ADD CONSTRAINT `vendor_services_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wedding_checklist`
--
ALTER TABLE `wedding_checklist`
  ADD CONSTRAINT `wedding_checklist_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wedding_checklist_ibfk_2` FOREIGN KEY (`assigned_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
