-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 06:28 AM
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
-- Database: `hospital_db`
--

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
  `patient_id` int(11) NOT NULL,
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

INSERT INTO `medical_reports` (`report_id`, `patient_id`, `doctor_id`, `symptoms`, `diagnosis`, `vitals`, `prescription`, `remarks`, `created_at`) VALUES
(1, 3, 4, 'good', 'fever ', 'hu8', 'penadol', 'nap', '2026-04-24 06:19:37'),
(2, 3, 4, 'good', 'fever', '11', 'penadeen', 'mii', '2026-04-24 06:48:05');

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
  `dob` date NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `dob`, `gender`, `nic`, `guardian_name`, `guardian_nic`, `guardian_relation`, `phone`, `address`, `blood_group`, `allergies`, `emergency_contact_name`, `emergency_phone`, `created_at`) VALUES
(1, 'Pawan Nimsara', '2019-06-12', 'Male', 'CHILD-1777005906', 'M B P N Dharmasena', '200228602990', 'Father', '0778253387', NULL, 'B+', 'yyyy', 'pawan', '0778253387', '2026-04-24 04:45:06'),
(2, 'Pawan Nimsara', '2002-02-12', 'Male', '200228602990', '', '', '', '0778253387', NULL, 'B+', 'hhhh', 'pawan', '0778253387', '2026-04-24 04:47:37'),
(3, 'Pawan Nimsara2', '2012-06-12', 'Male', 'CHILD-1777006145', 'M B P N Dharmasena', '200228602990', 'Father', '0778253387', NULL, 'B+', 'll,', 'pawan', '0778253387', '2026-04-24 04:49:05'),
(4, 'Pawan Nimsara', '2026-04-16', 'Male', 'CHILD-1777015679', 'M B P N Dharmasena', '200228602990', 'Father', '0778253387', NULL, 'B-', 'bug', 'pawan', '0778253387', '2026-04-24 07:27:59'),
(5, 'Pawan Nimsara', '2017-07-18', 'Male', 'CHILD-1777025094', 'M B P N Dharmasena', '200228602990', 'Father', '0778253387', NULL, 'O-', 'bncjh', 'pawan', '0778253387', '2026-04-24 10:04:54'),
(6, 'Pawan Nimsara', '2026-05-01', 'Male', 'CHILD-1777867705', 'M B P N Dharmasena', '200228602991', 'Father', '0778253388', NULL, 'B+', 'tuutud', 'pawan', '0778253387', '2026-05-04 04:08:25'),
(8, 'Pawan Nimsara12', '2002-08-08', 'Male', '2002286029988', '', '', '', '0778253388', NULL, 'B-', 'hvv', 'pawan', '0778253387', '2026-05-04 04:09:25');

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
  ADD KEY `doctor_id` (`doctor_id`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `doctor_details`
--
ALTER TABLE `doctor_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `medical_reports`
--
ALTER TABLE `medical_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `nurse_details`
--
ALTER TABLE `nurse_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `medical_reports_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nurse_details`
--
ALTER TABLE `nurse_details`
  ADD CONSTRAINT `nurse_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
