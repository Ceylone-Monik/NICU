-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2026 at 09:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pm_hospital_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `babies`
--

CREATE TABLE `babies` (
  `baby_id` int(11) NOT NULL,
  `mother_id` int(11) NOT NULL,
  `admission_bed_number` varchar(50) DEFAULT NULL,
  `baby_name` varchar(100) DEFAULT 'Baby of Pink Card Holder',
  `baby_gender` enum('Male','Female','Other') NOT NULL,
  `birth_date` datetime NOT NULL,
  `weight_kg` decimal(4,3) DEFAULT NULL,
  `condition_notes` text DEFAULT NULL,
  `ward_name` enum('Normal','Critical','Other','To Discharge') NOT NULL DEFAULT 'Normal',
  `recommended_ward` enum('Normal','Critical','Other','To Discharge') DEFAULT NULL,
  `recommendation_status` enum('None','Pending') DEFAULT 'None',
  `status` enum('Active','Discharged') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `babies`
--

INSERT INTO `babies` (`baby_id`, `mother_id`, `admission_bed_number`, `baby_name`, `baby_gender`, `birth_date`, `weight_kg`, `condition_notes`, `ward_name`, `recommended_ward`, `recommendation_status`, `status`, `created_at`) VALUES
(1, 10, NULL, '', 'Male', '2026-05-21 11:43:00', 2.000, 'good', 'To Discharge', NULL, 'None', 'Active', '2026-05-22 06:14:06'),
(4, 12, NULL, 'baby3', 'Male', '2026-05-12 16:29:00', 3.000, 'good', 'Other', NULL, 'None', 'Active', '2026-05-22 11:00:06'),
(5, 13, NULL, 'Baby of Fourth', 'Male', '0000-00-00 00:00:00', 0.000, '', 'Normal', NULL, 'None', 'Active', '2026-05-23 04:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_details`
--

CREATE TABLE `doctor_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_details`
--

INSERT INTO `doctor_details` (`id`, `user_id`, `specialization`, `license_no`) VALUES
(1, 4, 'Heart Surgeon ', '45'),
(2, 7, 'Heart Surgeon ', '46'),
(3, 10, 'Heart Surgeon ', '48'),
(4, 11, 'Heart Surgeon ', '49'),
(5, 15, 'Heart Surgeon ', '50'),
(6, 16, 'Heart Surgeon ', '51'),
(7, 18, 'Heart Surgeon ', '55'),
(9, 22, 'Heart Surgeon ', '52');

-- --------------------------------------------------------

--
-- Table structure for table `medical_reports`
--

CREATE TABLE `medical_reports` (
  `report_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `baby_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `vitals` varchar(255) DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_reports`
--

INSERT INTO `medical_reports` (`report_id`, `patient_id`, `baby_id`, `doctor_id`, `symptoms`, `diagnosis`, `vitals`, `prescription`, `remarks`, `created_at`) VALUES
(3, NULL, 1, 4, 'bad', 'jjjjj', 'birth weught', 'jkkkkk', 'lllll', '2026-05-22 07:45:28'),
(4, NULL, 1, 4, 'bad', 'jnjinjji', 'birth weught', 'hini', ' uuu', '2026-05-22 07:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `nurse_details`
--

CREATE TABLE `nurse_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `shift` enum('Morning','Evening','Night') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nurse_details`
--

INSERT INTO `nurse_details` (`id`, `user_id`, `department`, `shift`) VALUES
(1, 5, 'ICU', 'Morning'),
(2, 6, 'OPD', 'Night'),
(3, 8, 'ICU', 'Evening'),
(4, 13, 'OPD', 'Morning'),
(5, 14, 'ICU', 'Morning'),
(6, 17, 'ICU', 'Night'),
(7, 19, 'ICU', 'Morning'),
(8, 23, 'OPD', 'Night');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `patient_type` enum('Normal','Pregnant') NOT NULL DEFAULT 'Normal',
  `ward_name` enum('Normal','Critical','Other','To Discharge') NOT NULL DEFAULT 'Normal',
  `dob` date NOT NULL,
  `clinic_book_no` varchar(50) DEFAULT NULL,
  `lmp_date` date DEFAULT NULL,
  `edd_date` date DEFAULT NULL,
  `gravida` int(11) DEFAULT NULL,
  `para` int(11) DEFAULT NULL,
  `pregnancy_risk_factors` text DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `nic` varchar(20) NOT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_nic` varchar(20) DEFAULT NULL,
  `guardian_relation` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active_inpatient` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `patient_type`, `ward_name`, `dob`, `clinic_book_no`, `lmp_date`, `edd_date`, `gravida`, `para`, `pregnancy_risk_factors`, `gender`, `nic`, `guardian_name`, `guardian_nic`, `guardian_relation`, `phone`, `address`, `blood_group`, `allergies`, `emergency_contact_name`, `emergency_phone`, `created_at`, `is_active_inpatient`) VALUES
(10, 'first', 'Pregnant', 'Normal', '1997-07-07', 'BAD/2026/001', '2026-05-01', '2027-02-08', 1, 0, 'none', 'Female', '200228602995', '', '', '', '0778253387', NULL, 'B+', 'pppppppppp', 'rrrrrrrrrrrrrrrrrrrrrr', '0778253389', '2026-05-22 05:21:54', 1),
(11, 'second', 'Pregnant', 'Normal', '1997-07-07', 'BAD/2026/001', '2026-05-01', '2027-02-08', 1, 0, 'none', 'Female', '200228602999', '', '', '', '0778253389', NULL, 'B+', 'ssssssss', 'rrrrrrrrrrrrrrrrrrrrrrssss', '0778253385', '2026-05-22 07:04:54', 1),
(12, 'Third', 'Pregnant', 'Normal', '2002-05-12', 'BAD/2026/003', '2026-04-29', '2027-02-06', 1, 0, 'none', 'Female', '200228602100', '', '', '', '0778253100', NULL, 'O+', 'nkjnkkkj', 'rrrrrrrrrrrrrrkkkk', '0778253100', '2026-05-22 10:24:18', 1),
(13, 'Fourth', 'Pregnant', 'Normal', '2000-06-07', NULL, NULL, NULL, NULL, NULL, NULL, 'Male', '200120020202', NULL, NULL, NULL, '0778255666', NULL, NULL, NULL, NULL, NULL, '2026-05-23 04:25:46', 1);

-- --------------------------------------------------------

--
-- Table structure for table `patient_admissions`
--

CREATE TABLE `patient_admissions` (
  `admission_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `clinic_book_no` varchar(100) DEFAULT NULL,
  `lmp_date` date DEFAULT NULL,
  `edd_date` date DEFAULT NULL,
  `gravida` int(11) DEFAULT 1,
  `para` int(11) DEFAULT 0,
  `pregnancy_risk_factors` text DEFAULT NULL,
  `admitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_admissions`
--

INSERT INTO `patient_admissions` (`admission_id`, `patient_id`, `clinic_book_no`, `lmp_date`, `edd_date`, `gravida`, `para`, `pregnancy_risk_factors`, `admitted_at`) VALUES
(1, 13, 'BAD/2026/004', '2026-05-01', '2027-02-05', 1, 0, 'none', '2026-05-23 04:25:46'),
(2, 13, 'BAD/2026/005', '2026-05-02', '2027-02-06', 1, 0, 'none', '2026-05-23 06:43:23');

-- --------------------------------------------------------

--
-- Table structure for table `patient_movement_logs`
--

CREATE TABLE `patient_movement_logs` (
  `log_id` int(11) NOT NULL,
  `baby_id` int(11) NOT NULL,
  `action_type` enum('Admission','Transfer','Discharge') NOT NULL,
  `from_ward` varchar(50) DEFAULT 'None',
  `to_ward` varchar(50) NOT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` timestamp NULL DEFAULT NULL,
  `duration_days` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_movement_logs`
--

INSERT INTO `patient_movement_logs` (`log_id`, `baby_id`, `action_type`, `from_ward`, `to_ward`, `entered_at`, `left_at`, `duration_days`) VALUES
(8, 1, 'Transfer', 'Critical', 'To Discharge', '2026-05-22 10:47:57', NULL, NULL),
(11, 4, 'Admission', 'None', 'Normal', '2026-05-22 11:00:06', '2026-05-22 11:00:11', 0.00),
(12, 4, 'Transfer', 'Normal', 'Critical', '2026-05-22 11:00:11', '2026-05-22 11:00:21', 0.00),
(13, 4, 'Transfer', 'Critical', 'Other', '2026-05-22 11:00:21', '2026-05-22 11:00:34', 0.00),
(14, 4, 'Transfer', 'Other', 'To Discharge', '2026-05-22 11:00:34', '2026-05-22 11:01:04', 0.00),
(15, 4, 'Discharge', 'To Discharge', 'Discharged Home', '2026-05-22 11:01:04', '2026-05-23 04:15:51', 0.72),
(16, 4, 'Admission', 'Outpatient', 'Critical', '2026-05-23 04:15:41', '2026-05-23 04:15:51', 0.00),
(17, 4, 'Transfer', 'Critical', 'Normal', '2026-05-23 04:15:51', '2026-05-23 04:16:02', 0.00),
(18, 4, 'Transfer', 'Normal', 'Other', '2026-05-23 04:16:02', '2026-05-23 04:16:09', 0.00),
(19, 4, 'Transfer', 'Other', 'To Discharge', '2026-05-23 04:16:09', '2026-05-23 04:16:16', 0.00),
(20, 4, 'Discharge', 'To Discharge', 'Discharged Home', '2026-05-23 04:16:16', '2026-05-23 06:42:40', 0.10),
(21, 5, 'Admission', 'None', 'Normal', '2026-05-23 04:25:46', NULL, NULL),
(22, 4, 'Admission', 'Outpatient', 'Critical', '2026-05-23 04:37:03', '2026-05-23 06:42:40', 0.09),
(28, 4, 'Transfer', 'Critical', 'Other', '2026-05-23 06:42:40', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Doctor','Nurse') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Pawan Nimsara', 'admin@hospital.com', '$2y$10$L/iNi8eh9TMRhkzAA80KreOPU2a4vzfjjWVmJr6bsyZt6IynHmUiq', 'Admin', '2026-04-23 04:31:01'),
(4, 'Pawan Nimsara', 'pawanimsara12@gmail.com', '$2y$10$hoo51ZcFOs.3lrKpu1wcR.Jp0vMYY7KVWCaKren1.6wdzl6clAhKa', 'Doctor', '2026-04-23 04:44:42'),
(5, 'pawan1', 'pawanimsara10@gmail.com', '$2y$10$GrXCG8B33hYCc8AsVgaOZetXzLqixQZL7MeT/k63Xxgbv6FaOSXkC', 'Nurse', '2026-04-23 04:54:08'),
(6, 'pawan2', 'pawanimsara2@gmail.com', '$2y$10$utbZIZUYAeXV7tANMdAjr.m4v0KnEtHaHHSQGT.wnkLrEGaeFL482', 'Nurse', '2026-04-23 05:18:36'),
(7, 'pawan3', 'pawanimsara3@gmail.com', '$2y$10$mEYZAvr4X4YfXC1paU9CEuXEFhlFTMvTeAGDhd56SdtbMnR2j17Qa', 'Doctor', '2026-04-23 06:24:28'),
(8, 'pawan7', 'pawanimsara7@gmail.com', '$2y$10$faO./egANKxIP504w06vQOpEQepRR11YNktGwGyLwA83i/yXvYYei', 'Nurse', '2026-04-23 06:26:01'),
(10, 'pawan8', 'pawanimsara8@gmail.com', '$2y$10$kT8G7WULssskpXhbv2RTfuiSnpQvWAZk2CKUfquJk4.c4foJcqEHm', 'Doctor', '2026-04-23 06:31:07'),
(11, 'pawan9', 'pawanimsara9@gmail.com', '$2y$10$nwdXIuc9cazrOpQKpVnuwOZJTp9NYOaR8CBWcDLiVHwSMfeiWgYvO', 'Doctor', '2026-04-23 07:07:40'),
(13, 'pawan11', 'pawanimsara11@gmail.com', '$2y$10$qyLujSAll11oF/UXIQey9eq7dbpnAVoJDb38ryr3aiykklFpIZ.S.', 'Nurse', '2026-04-23 07:08:18'),
(14, 'pawan14', 'pawanimsara14@gmail.com', '$2y$10$xWQxLAPzVn3GbWser6dBQO9U/KrigAV4F1qmhlBxgyp7OS.fZ1AZi', 'Nurse', '2026-04-23 07:08:40'),
(15, 'pawan15', 'pawanimsara15@gmail.com', '$2y$10$hSPbA2dYPJNOz5w0ybeH8.e78P6sEbMHaIyzSjsA3DEowvWuqVf2e', 'Doctor', '2026-04-23 07:09:24'),
(16, 'pawan16', 'pawanimsara16@gmail.com', '$2y$10$BPLR1H9XoecVBuLkWRa7eugE4629vcxlCmK1rzF3cYF3aPnv5JB5G', 'Doctor', '2026-04-23 10:53:57'),
(17, 'pawan17', 'pawanimsara17@gmail.com', '$2y$10$rVcxowUVK4D/x44LA4x9DeYmz6pjQBeYEktglAxNdrBHRV51rg1EK', 'Nurse', '2026-04-23 10:54:18'),
(18, 'pawan20', 'pawanimsara20@gmail.com', '$2y$10$DlxXeZc0G63ikXbF47pM1OCBq1pqRn4hBqnQpwLYvY3a/ii6aZ1/6', 'Doctor', '2026-04-24 07:33:04'),
(19, 'pawan21', 'pawanimsara21@gmail.com', '$2y$10$YMeCt0neT5i927s765OtiuXt/SXX17zwouWDtuOAFOPkNS6NR9c8y', 'Nurse', '2026-04-24 07:33:27'),
(22, 'pawan22', 'pawanimsara22@gmail.com', '$2y$10$WLWuvTwAaMt705VK5X4pEeWOIzw1lwo7BBO.vsF5RGDvvECXtEbM2', 'Doctor', '2026-05-04 03:58:54'),
(23, 'pawan23', 'pawanimsara23@gmail.com', '$2y$10$LnyJrDnyffUHC8Hjbd/pMOPUjpJi03GUBZ2q2iH42xw3jGDrvU7lG', 'Nurse', '2026-05-04 03:59:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `babies`
--
ALTER TABLE `babies`
  ADD PRIMARY KEY (`baby_id`),
  ADD KEY `mother_id` (`mother_id`),
  ADD KEY `admission_bed_number` (`admission_bed_number`);

--
-- Indexes for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_no` (`license_no`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `medical_reports`
--
ALTER TABLE `medical_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `baby_id` (`baby_id`);

--
-- Indexes for table `nurse_details`
--
ALTER TABLE `nurse_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nic` (`nic`);

--
-- Indexes for table `patient_admissions`
--
ALTER TABLE `patient_admissions`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_movement_logs`
--
ALTER TABLE `patient_movement_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `baby_id` (`baby_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `babies`
--
ALTER TABLE `babies`
  MODIFY `baby_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `doctor_details`
--
ALTER TABLE `doctor_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `medical_reports`
--
ALTER TABLE `medical_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `nurse_details`
--
ALTER TABLE `nurse_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `patient_admissions`
--
ALTER TABLE `patient_admissions`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patient_movement_logs`
--
ALTER TABLE `patient_movement_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `babies`
--
ALTER TABLE `babies`
  ADD CONSTRAINT `babies_ibfk_1` FOREIGN KEY (`mother_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD CONSTRAINT `doctor_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_reports`
--
ALTER TABLE `medical_reports`
  ADD CONSTRAINT `medical_reports_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_reports_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_reports_ibfk_3` FOREIGN KEY (`baby_id`) REFERENCES `babies` (`baby_id`) ON DELETE CASCADE;

--
-- Constraints for table `nurse_details`
--
ALTER TABLE `nurse_details`
  ADD CONSTRAINT `nurse_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_admissions`
--
ALTER TABLE `patient_admissions`
  ADD CONSTRAINT `patient_admissions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_movement_logs`
--
ALTER TABLE `patient_movement_logs`
  ADD CONSTRAINT `patient_movement_logs_ibfk_1` FOREIGN KEY (`baby_id`) REFERENCES `babies` (`baby_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
