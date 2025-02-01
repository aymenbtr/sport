-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 29, 2025 at 01:27 PM
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
-- Database: `test`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_sessions`
--

CREATE TABLE `active_sessions` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `child_sports`
--

CREATE TABLE `child_sports` (
  `id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `sport_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `opening_hours` text NOT NULL,
  `description` text DEFAULT NULL,
  `wilaya_id` int(11) NOT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_message` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `role` varchar(50) DEFAULT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `active_session_id` varchar(64) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `company_name`, `address`, `email`, `password`, `phone`, `opening_hours`, `description`, `wilaya_id`, `facebook_url`, `instagram_url`, `twitter_url`, `website_url`, `status`, `created_at`, `admin_message`, `is_deleted`, `role`, `session_token`, `last_activity`, `active_session_id`, `last_login`) VALUES
(53, 'boxing Club', 'boxing-chlef-12223', 'aymenbtr33@gmail.com', '$2y$10$EvDkaj98w0FZO.Hpkc0bge5NElSgKLZte0/NXPLY8iVJL6kjDLz0G', '0697251047', '7.30AM-9.30AM', 'the best boxing club ', 2, 'https://www.facebook.com/boxingnewsonline/', 'https://help.instagram.com/372819389498306', '', '', 'approved', '2025-01-20 18:40:42', '', 0, NULL, NULL, NULL, 'a7097b333a1f19e2c2a3d4c6e49075ce8793541f75cdbcfa22c28929fc6ff8f0', '2025-01-21 12:43:33'),
(54, 'Judo-Ain-Defla', 'Ain-Defla-44', 'aymenboutaraa093@gmail.com', '$2y$10$DUni26O3vsl61WziPqb0feza.ORAniSJVgkvaqCi5r8kjPlLWby2K', '0645203020', '4.30PM-6.25PM', 'the best martial arts club', 44, 'https://www.facebook.com/groups/533907517263470/', '', '', 'https://www.britannica.com/sports/karate', 'approved', '2025-01-20 19:57:36', 'accept', 0, NULL, '96e77571b78aa57d4cf712bc36645910d1df878f692967df452ca72f094286c9', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `facility_image`
--

CREATE TABLE `facility_image` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_image`
--

INSERT INTO `facility_image` (`id`, `facility_id`, `image_path`, `is_primary`, `created_at`, `is_deleted`, `title`) VALUES
(36, 53, 'uploads/facility/678e98aa3454f_0.jpg', 0, '2025-01-20 18:40:42', 0, NULL),
(37, 54, 'uploads/facility/678eaab0b4b05_0.jpg', 0, '2025-01-20 19:57:36', 0, NULL),
(38, 54, 'uploads/facility_images/6794d353e58f8.jpg', 0, '2025-01-25 12:04:35', 0, 'the best ring we\'ve got');

-- --------------------------------------------------------

--
-- Table structure for table `facility_images`
--

CREATE TABLE `facility_images` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_registrations`
--

CREATE TABLE `facility_registrations` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `opening_hours` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `sport_id` int(11) NOT NULL,
  `wilaya_id` int(11) NOT NULL,
  `registration_date` datetime NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_message` text DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_sports`
--

CREATE TABLE `facility_sports` (
  `facility_id` int(11) NOT NULL,
  `sport_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_sports`
--

INSERT INTO `facility_sports` (`facility_id`, `sport_id`, `created_at`, `is_deleted`) VALUES
(53, 1, '2025-01-20 18:40:42', 0),
(53, 2, '2025-01-20 18:40:42', 0),
(53, 8, '2025-01-20 18:40:42', 0),
(54, 2, '2025-01-25 11:59:19', 0),
(54, 8, '2025-01-25 11:59:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `check_in_time` time NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `sport_id` int(11) DEFAULT NULL,
  `check_out_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sport`
--

CREATE TABLE `sport` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `categorie_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sport`
--

INSERT INTO `sport` (`id`, `name`, `description`, `categorie_id`, `created_at`) VALUES
(1, 'boxing', 'the best hand to hand sport ever', 1, '2025-01-01 12:31:07'),
(2, 'Karate', 'Karate is a martial art developed in the Ryukyu Kingdom, now part of Okinawa, Japan, and it combines indigenous Ryukyuan martial arts known as “te” with influences from Chinese martial arts. It primarily focuses on striking techniques using punches, kicks, knee strikes, and elbow strikes. Karate emphasizes self-discipline and mental focus, and practitioners, known as karate-ka, train in three main areas: kihon (basics), kata (forms), and kumite (sparring). The practice of karate aims to develop physical strength, flexibility, and mental agility, while also promoting values such as courtesy and respect.\r\n\r\n', 1, '2025-01-02 11:15:24'),
(3, 'BasketBall', 'Basketball is a fast-paced team sport played between two teams of five players each on a rectangular court, usually indoors. The objective is to score points by shooting a ball through the opponent’s hoop, which is a raised horizontal hoop and net called a basket. Each successful shot through the hoop scores either two or three points, depending on the distance from which the shot is taken.', 3, '2025-01-02 11:16:53'),
(5, 'FootBall', 'Kicking the ball without using your hands', 3, '2025-01-02 11:22:33'),
(6, 'Swimming', 'Swimming is the act of propelling one’s body through water using coordinated arm and leg movements. It can be done for recreation, exercise, or as a competitive sport. Competitive swimming includes various strokes such as butterfly, backstroke, breaststroke, freestyle, and individual medley. Swimming is popular worldwide and is one of the top audience draws at the Olympic Games. It offers numerous health benefits, including improved cardiovascular health, muscle strength, and increased flexibility. Swimming as a competitive sport began to evolve in the 19th century after the construction of artificial public swimming pools.\r\n\r\n', 2, '2025-01-02 11:23:11'),
(7, 'Running', 'An athletic build typically refers to a body type characterized by broad shoulders and a narrow waist, with toned and enlarged muscles, and a lean frame. This physique is often associated with professional and amateur athletes, as well as health-conscious individuals.', 2, '2025-01-02 11:25:12'),
(8, 'Judo', 'Judo is a modern Japanese martial art and combat sport that emphasizes the use of quick movement and leverage to throw an opponent. It was created in 1882 by Kanō Jigorō, who distinguished it from its predecessors, primarily Tenjin Shin’yō-ryū and Kitō-ryū jujutsu, by focusing on free sparring (randori) rather than pre-arranged forms (kata). The objective of competitive judo is to throw an opponent, immobilize them with a pin, or force a submission through joint locks or chokes. Judo became an Olympic sport for men in 1964 and for women in 1992.\r\n\r\n', 1, '2025-01-02 11:25:47'),
(9, 'VolleyBall', 'Volleyball is a team sport played by two teams of six players each, using their hands to hit a large ball back and forth over a high net. The objective is to score points by grounding the ball on the opponent’s court, while preventing the ball from touching the ground on their own side. Each team is allowed to touch the ball up to three times before sending it over the net.\r\n\r\n', 3, '2025-01-02 11:26:32'),
(10, 'BodyBuilding', 'the best', 2, '2025-01-25 13:17:52');

-- --------------------------------------------------------

--
-- Table structure for table `sportcategorie`
--

CREATE TABLE `sportcategorie` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sportcategorie`
--

INSERT INTO `sportcategorie` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'combat sport', NULL, '2025-01-01 12:30:37'),
(2, 'athletic sport', NULL, '2025-01-01 12:30:43'),
(3, 'collective sport', NULL, '2025-01-01 12:30:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `pob` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `id_type` enum('passport','driving_licence','identity_card') DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `id_document_path` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `wilaya_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `facility_id` int(11) NOT NULL,
  `profile_pic_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `dob`, `pob`, `address`, `email`, `phone`, `gender`, `id_type`, `id_number`, `id_document_path`, `password`, `wilaya_id`, `created_at`, `status`, `rejection_reason`, `is_deleted`, `facility_id`, `profile_pic_path`) VALUES
(24, 'aymen', 'the best', '2003-01-01', 'chlef', 'chlef main best', 'aymenbtr33@gmail.com', '0697 25 10 47', 'male', 'passport', '0654123010', 'uploads/id_documents/6798c53f41a48.jpg', '$2y$10$WJfkn5ROjCPQiO2Etls6a.87I5.sLfH0ETBr2FI6.HK/cHQ6UQbBS', 44, '2025-01-28 11:53:35', 'approved', NULL, 0, 54, 'uploads/profile_pics/profile_6798db0d2567c.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `user_sports`
--

CREATE TABLE `user_sports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sport_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sports`
--

INSERT INTO `user_sports` (`id`, `user_id`, `sport_id`) VALUES
(44, 24, 2),
(45, 24, 8);

-- --------------------------------------------------------

--
-- Table structure for table `wilaya`
--

CREATE TABLE `wilaya` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wilaya`
--

INSERT INTO `wilaya` (`id`, `name`, `code`, `photo`, `created_at`) VALUES
(1, 'adrar', '', 'Mauritanie_-_Adrar2.jpg', '2025-01-02 09:58:45'),
(2, 'chlef', '', 'Centre_ville_Chlef.jpg', '2025-01-02 09:59:00'),
(9, 'Blida', '', 'Mosquée_El_Kawthar_-_Blida.jpg', '2025-01-07 13:04:09'),
(16, 'alger', '', 'fba4f04a014096e5900fe4a43ca91b20.jpg', '2025-01-02 09:59:28'),
(19, 'Setif', '', 'Sitifiana_actualidad.jpg', '2025-01-05 13:13:02'),
(21, 'Skikda', '', 'b48be5_f1e5750cf399406e905701fd7bcf5adf~mv2.jpg', '2025-01-02 11:29:23'),
(31, 'oran', '', 'ORAN_City_&_Coast.jpg', '2025-01-02 09:59:11'),
(44, 'Ain-Defla', '', 'Région_de_Miliana.jpg', '2025-01-07 13:07:01');

-- --------------------------------------------------------

--
-- Table structure for table `wilaya_sport`
--

CREATE TABLE `wilaya_sport` (
  `id` int(11) NOT NULL,
  `wilaya_id` int(11) NOT NULL,
  `sport_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_sessions`
--
ALTER TABLE `active_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_facility` (`facility_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `child_sports`
--
ALTER TABLE `child_sports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `sport_id` (`sport_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `wilaya_id` (`wilaya_id`);

--
-- Indexes for table `facility_image`
--
ALTER TABLE `facility_image`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `facility_images`
--
ALTER TABLE `facility_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `facility_registrations`
--
ALTER TABLE `facility_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sport_id` (`sport_id`),
  ADD KEY `wilaya_id` (`wilaya_id`);

--
-- Indexes for table `facility_sports`
--
ALTER TABLE `facility_sports`
  ADD PRIMARY KEY (`facility_id`,`sport_id`),
  ADD KEY `sport_id` (`sport_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_reservation_sport` (`sport_id`);

--
-- Indexes for table `sport`
--
ALTER TABLE `sport`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sport_category` (`categorie_id`);

--
-- Indexes for table `sportcategorie`
--
ALTER TABLE `sportcategorie`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `wilaya_id` (`wilaya_id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `user_sports`
--
ALTER TABLE `user_sports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_sport_unique` (`user_id`,`sport_id`),
  ADD KEY `sport_id` (`sport_id`);

--
-- Indexes for table `wilaya`
--
ALTER TABLE `wilaya`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wilaya_sport`
--
ALTER TABLE `wilaya_sport`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wilaya_sport_unique` (`wilaya_id`,`sport_id`),
  ADD KEY `idx_wilaya_sport_wilaya` (`wilaya_id`),
  ADD KEY `idx_wilaya_sport_sport` (`sport_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_sessions`
--
ALTER TABLE `active_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `child_sports`
--
ALTER TABLE `child_sports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `facility_image`
--
ALTER TABLE `facility_image`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `facility_images`
--
ALTER TABLE `facility_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `facility_registrations`
--
ALTER TABLE `facility_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `sport`
--
ALTER TABLE `sport`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sportcategorie`
--
ALTER TABLE `sportcategorie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `user_sports`
--
ALTER TABLE `user_sports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `wilaya`
--
ALTER TABLE `wilaya`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `wilaya_sport`
--
ALTER TABLE `wilaya_sport`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `child_sports`
--
ALTER TABLE `child_sports`
  ADD CONSTRAINT `child_sports_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`),
  ADD CONSTRAINT `child_sports_ibfk_2` FOREIGN KEY (`sport_id`) REFERENCES `sport` (`id`);

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`wilaya_id`) REFERENCES `wilaya` (`id`);

--
-- Constraints for table `facility_image`
--
ALTER TABLE `facility_image`
  ADD CONSTRAINT `facility_image_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facility_images`
--
ALTER TABLE `facility_images`
  ADD CONSTRAINT `facility_images_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facility_registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facility_registrations`
--
ALTER TABLE `facility_registrations`
  ADD CONSTRAINT `facility_registrations_ibfk_1` FOREIGN KEY (`sport_id`) REFERENCES `sport` (`id`),
  ADD CONSTRAINT `facility_registrations_ibfk_2` FOREIGN KEY (`wilaya_id`) REFERENCES `wilaya` (`id`);

--
-- Constraints for table `facility_sports`
--
ALTER TABLE `facility_sports`
  ADD CONSTRAINT `facility_sports_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facility_sports_ibfk_2` FOREIGN KEY (`sport_id`) REFERENCES `sport` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservation_sport` FOREIGN KEY (`sport_id`) REFERENCES `facility_sports` (`sport_id`),
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sport`
--
ALTER TABLE `sport`
  ADD CONSTRAINT `sport_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `sportcategorie` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`wilaya_id`) REFERENCES `wilaya` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`);

--
-- Constraints for table `user_sports`
--
ALTER TABLE `user_sports`
  ADD CONSTRAINT `user_sports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_sports_ibfk_2` FOREIGN KEY (`sport_id`) REFERENCES `sport` (`id`);

--
-- Constraints for table `wilaya_sport`
--
ALTER TABLE `wilaya_sport`
  ADD CONSTRAINT `wilaya_sport_ibfk_1` FOREIGN KEY (`wilaya_id`) REFERENCES `wilaya` (`id`),
  ADD CONSTRAINT `wilaya_sport_ibfk_2` FOREIGN KEY (`sport_id`) REFERENCES `sport` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
