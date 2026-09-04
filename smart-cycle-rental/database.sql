-- ======================================================================
-- SMART CYCLE RENTAL MANAGEMENT SYSTEM - DATABASE SCHEMA & SAMPLE DATA
-- Database Name: smart_cycle_rental
-- Compatible with: MySQL 8.0+ / MariaDB 10.4+ (XAMPP / phpMyAdmin)
-- ======================================================================

CREATE DATABASE IF NOT EXISTS `smart_cycle_rental` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_cycle_rental`;

-- Disable foreign key checks during schema creation
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------
-- Table 1: USER
-- Stores customer, staff, and admin credentials and financial summary
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `NID` VARCHAR(50) NOT NULL UNIQUE,
    `Email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `DOB` DATE NOT NULL,
    `Address` TEXT NOT NULL,
    `Sex` ENUM('Male', 'Female', 'Other') NOT NULL,
    `Name` VARCHAR(100) NOT NULL,
    `User_type` ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
    `wallet` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `revenue` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 2: PHONER_NUMBER
-- Stores multi-valued phone numbers per user
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `phoner_number`;
CREATE TABLE `phoner_number` (
    `user_id` INT NOT NULL,
    `Phone` VARCHAR(20) NOT NULL,
    PRIMARY KEY (`user_id`, `Phone`),
    CONSTRAINT `fk_phone_user` FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 3: STATION
-- Stores cycle stations, capacity, and manager/staff assignment
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `station`;
CREATE TABLE `station` (
    `Location` VARCHAR(100) PRIMARY KEY,
    `capacity` INT NOT NULL DEFAULT 10,
    `User_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_station_manager` FOREIGN KEY (`User_id`) 
        REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 4: AVAILABILITY
-- Real-time cycle availability per station
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `availability`;
CREATE TABLE `availability` (
    `Location` VARCHAR(100) PRIMARY KEY,
    `cycle_availability` INT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_availability_station` FOREIGN KEY (`Location`) 
        REFERENCES `station` (`Location`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 5: BICYCLE
-- Stores cycle details, current & starting location, lock state, rider
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `bicycle`;
CREATE TABLE `bicycle` (
    `ID` INT AUTO_INCREMENT PRIMARY KEY,
    `QR_code` VARCHAR(100) NOT NULL UNIQUE,
    `location` VARCHAR(100) NULL,
    `locked` TINYINT(1) NOT NULL DEFAULT 1,
    `unlocked` TINYINT(1) NOT NULL DEFAULT 0,
    `s_location` VARCHAR(100) NULL,
    `user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bicycle_curr_station` FOREIGN KEY (`location`) 
        REFERENCES `station` (`Location`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bicycle_start_station` FOREIGN KEY (`s_location`) 
        REFERENCES `station` (`Location`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bicycle_curr_user` FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 6: CONDITION_TYPE
-- Physical condition tracking per bicycle
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `condition_type`;
CREATE TABLE `condition_type` (
    `B_id` INT PRIMARY KEY,
    `condition` ENUM('Excellent', 'Good', 'Fair', 'Damaged', 'Under Maintenance') NOT NULL DEFAULT 'Good',
    `last_checked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_condition_bicycle` FOREIGN KEY (`B_id`) 
        REFERENCES `bicycle` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 7: COUPONS (Catalog table for coupon management)
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `coupon_code` VARCHAR(50) PRIMARY KEY,
    `discount_percentage` INT NOT NULL DEFAULT 10,
    `min_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 8: PAYMENT
-- Stores bill calculation and payment transaction
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `trans_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `Bill` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `base_fee` DECIMAL(10, 2) NOT NULL DEFAULT 20.00,
    `additional_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `B_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `payment_status` ENUM('Paid', 'Pending', 'Failed') NOT NULL DEFAULT 'Paid',
    CONSTRAINT `fk_payment_bicycle` FOREIGN KEY (`B_id`) 
        REFERENCES `bicycle` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 9: PAYMENT_TYPE
-- Stores payment method (Wallet, Cash, Card, Mobile Banking)
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `payment_type`;
CREATE TABLE `payment_type` (
    `payment_id` INT PRIMARY KEY,
    `payment_method` ENUM('Wallet', 'Cash', 'Card', 'Mobile Banking') NOT NULL DEFAULT 'Wallet',
    CONSTRAINT `fk_payment_type_payment` FOREIGN KEY (`payment_id`) 
        REFERENCES `payment` (`payment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 10: COUPON_DETAILS
-- Stores coupon applied to each payment
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `coupon_details`;
CREATE TABLE `coupon_details` (
    `payment_id` INT PRIMARY KEY,
    `coupon` VARCHAR(50) NOT NULL,
    CONSTRAINT `fk_coupon_details_payment` FOREIGN KEY (`payment_id`) 
        REFERENCES `payment` (`payment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Table 11: RENTAL_TRANS
-- Stores rental transactions with start/end timestamps and stations
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `rental_trans`;
CREATE TABLE `rental_trans` (
    `trans_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `B_id` INT NOT NULL,
    `payment_id` INT NULL,
    `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_time` DATETIME NULL,
    `pickup_station` VARCHAR(100) NULL,
    `return_station` VARCHAR(100) NULL,
    `status` ENUM('Active', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Active',
    CONSTRAINT `fk_rental_user` FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rental_bicycle` FOREIGN KEY (`B_id`) 
        REFERENCES `bicycle` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rental_payment` FOREIGN KEY (`payment_id`) 
        REFERENCES `payment` (`payment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_rental_pickup` FOREIGN KEY (`pickup_station`) 
        REFERENCES `station` (`Location`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_rental_return` FOREIGN KEY (`return_station`) 
        REFERENCES `station` (`Location`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;


-- ======================================================================
-- SAMPLE DATA INSERTIONS
-- ======================================================================

-- 1. Insert Users (Admin, Staff, Customers)
-- Password for admin: admin123 ($2y$10$QuDG7PRHcqmTn2vJmh62O.cW3Flv36nK9JXB.MaVnabLIVxSseZZO)
-- Password for staff: staff123 ($2y$10$Sik1nvMBOXDgixflOcqHzexvC0yxjG.Cy5mgEvmeVNvqgQDkhvvru)
-- Password for customers: customer123 ($2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y)
INSERT INTO `user` (`user_id`, `NID`, `Email`, `password`, `DOB`, `Address`, `Sex`, `Name`, `User_type`, `wallet`, `revenue`) VALUES
(1, '1995101010101', 'admin@cycle.com', '$2y$10$QuDG7PRHcqmTn2vJmh62O.cW3Flv36nK9JXB.MaVnabLIVxSseZZO', '1995-05-12', 'Dhaka Head Office, Bangladesh', 'Male', 'System Administrator', 'admin', 1000.00, 0.00),
(2, '1996202020202', 'staff@cycle.com', '$2y$10$Sik1nvMBOXDgixflOcqHzexvC0yxjG.Cy5mgEvmeVNvqgQDkhvvru', '1996-08-20', 'BRAC University Station Desk, Dhaka', 'Female', 'Sarah Ahmed (Staff)', 'staff', 500.00, 0.00),
(3, '1997303030303', 'staff2@cycle.com', '$2y$10$Sik1nvMBOXDgixflOcqHzexvC0yxjG.Cy5mgEvmeVNvqgQDkhvvru', '1997-03-15', 'Mohakhali Cycle Hub, Dhaka', 'Male', 'Kamal Hossain (Staff)', 'staff', 500.00, 0.00),
(4, '1998404040404', 'customer@cycle.com', '$2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y', '1998-11-25', 'Badda, Dhaka', 'Male', 'Md. Tanvir Customer', 'customer', 350.00, 75.00),
(5, '1999505050505', 'rahim@gmail.com', '$2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y', '1999-02-14', 'Gulshan-1, Dhaka', 'Male', 'Rahim Chowdhury', 'customer', 200.00, 30.00),
(6, '2000606060606', 'karim@gmail.com', '$2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y', '2000-06-18', 'Banani, Dhaka', 'Male', 'Karim Ullah', 'customer', 150.00, 25.00),
(7, '2001707070707', 'nusrat@gmail.com', '$2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y', '2001-09-09', 'Dhanmondi 27, Dhaka', 'Female', 'Nusrat Jahan', 'customer', 400.00, 0.00),
(8, '2002808080808', 'tanvir@gmail.com', '$2y$10$nP1oONiRYrUSUhnfJrcV3uUi4MmUt27G5zHVcI.LcCUGbrvx0sS0y', '2002-12-01', 'Mohakhali DOHS, Dhaka', 'Male', 'Tanvir Hasan', 'customer', 500.00, 0.00);

-- 2. Insert Phone Numbers (Multiple phones per user supported)
INSERT INTO `phoner_number` (`user_id`, `Phone`) VALUES
(1, '01711000001'),
(1, '01811000001'),
(2, '01722000002'),
(3, '01733000003'),
(4, '01744000004'),
(4, '01944000004'),
(5, '01755000005'),
(6, '01766000006'),
(7, '01777000007'),
(8, '01788000008');

-- 3. Insert Stations
INSERT INTO `station` (`Location`, `capacity`, `User_id`) VALUES
('BRAC University', 20, 2),
('Mohakhali', 15, 3),
('Gulshan', 25, 2),
('Banani', 15, 3),
('Dhanmondi', 20, 2);

-- 4. Insert Station Availability
INSERT INTO `availability` (`Location`, `cycle_availability`) VALUES
('BRAC University', 3),
('Mohakhali', 3),
('Gulshan', 3),
('Banani', 2),
('Dhanmondi', 2);

-- 5. Insert Bicycles (15 Bicycles across 5 stations)
INSERT INTO `bicycle` (`ID`, `QR_code`, `location`, `locked`, `unlocked`, `s_location`, `user_id`) VALUES
(1, 'QR-BRAC-001', 'BRAC University', 1, 0, 'BRAC University', NULL),
(2, 'QR-BRAC-002', 'BRAC University', 1, 0, 'BRAC University', NULL),
(3, 'QR-BRAC-003', 'BRAC University', 1, 0, 'BRAC University', NULL),
(4, 'QR-MHK-004', 'Mohakhali', 1, 0, 'Mohakhali', NULL),
(5, 'QR-MHK-005', 'Mohakhali', 1, 0, 'Mohakhali', NULL),
(6, 'QR-MHK-006', 'Mohakhali', 1, 0, 'Mohakhali', NULL),
(7, 'QR-GLS-007', 'Gulshan', 1, 0, 'Gulshan', NULL),
(8, 'QR-GLS-008', 'Gulshan', 1, 0, 'Gulshan', NULL),
(9, 'QR-GLS-009', 'Gulshan', 1, 0, 'Gulshan', NULL),
(10, 'QR-BNN-010', 'Banani', 1, 0, 'Banani', NULL),
(11, 'QR-BNN-011', 'Banani', 1, 0, 'Banani', NULL),
(12, 'QR-DHM-012', 'Dhanmondi', 1, 0, 'Dhanmondi', NULL),
(13, 'QR-DHM-013', 'Dhanmondi', 1, 0, 'Dhanmondi', NULL),
(14, 'QR-DHM-014', 'Dhanmondi', 1, 0, 'Dhanmondi', NULL),
(15, 'QR-BNN-015', 'Banani', 1, 0, 'Banani', NULL);

-- 6. Insert Bicycle Conditions
INSERT INTO `condition_type` (`B_id`, `condition`) VALUES
(1, 'Excellent'),
(2, 'Good'),
(3, 'Good'),
(4, 'Excellent'),
(5, 'Good'),
(6, 'Fair'),
(7, 'Excellent'),
(8, 'Good'),
(9, 'Excellent'),
(10, 'Good'),
(11, 'Fair'),
(12, 'Excellent'),
(13, 'Good'),
(14, 'Damaged'),
(15, 'Under Maintenance');

-- 7. Insert Coupons
INSERT INTO `coupons` (`coupon_code`, `discount_percentage`, `min_amount`, `is_active`) VALUES
('WELCOME10', 10, 0.00, 1),
('SAVE20', 20, 20.00, 1),
('STUDENT15', 15, 0.00, 1),
('SUMMER25', 25, 30.00, 1),
('EXPIRED50', 50, 50.00, 0);

-- 8. Insert Sample Completed Payments
INSERT INTO `payment` (`payment_id`, `trans_time`, `Bill`, `base_fee`, `additional_fee`, `discount`, `B_id`, `user_id`, `payment_status`) VALUES
(1, '2026-08-10 10:30:00', 45.00, 20.00, 30.00, 5.00, 1, 4, 'Paid'),
(2, '2026-08-11 14:15:00', 30.00, 20.00, 10.00, 0.00, 4, 4, 'Paid'),
(3, '2026-08-12 16:45:00', 30.00, 20.00, 10.00, 0.00, 7, 5, 'Paid'),
(4, '2026-08-13 18:00:00', 25.00, 20.00, 5.00, 0.00, 10, 6, 'Paid');

-- 9. Insert Payment Types
INSERT INTO `payment_type` (`payment_id`, `payment_method`) VALUES
(1, 'Wallet'),
(2, 'Mobile Banking'),
(3, 'Card'),
(4, 'Cash');

-- 10. Insert Coupon Details
INSERT INTO `coupon_details` (`payment_id`, `coupon`) VALUES
(1, 'WELCOME10'),
(2, 'NONE'),
(3, 'NONE'),
(4, 'NONE');

-- 11. Insert Sample Completed Rental Transactions
INSERT INTO `rental_trans` (`trans_id`, `user_id`, `B_id`, `payment_id`, `start_time`, `end_time`, `pickup_station`, `return_station`, `status`) VALUES
(1, 4, 1, 1, '2026-08-10 09:00:00', '2026-08-10 10:30:00', 'BRAC University', 'BRAC University', 'Completed'),
(2, 4, 4, 2, '2026-08-11 13:15:00', '2026-08-11 14:15:00', 'Mohakhali', 'BRAC University', 'Completed'),
(3, 5, 7, 3, '2026-08-12 15:45:00', '2026-08-12 16:45:00', 'Gulshan', 'Banani', 'Completed'),
(4, 6, 10, 4, '2026-08-13 17:15:00', '2026-08-13 18:00:00', 'Banani', 'Gulshan', 'Completed');
