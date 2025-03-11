-- -------------------------------------------------------------
-- TablePlus 6.1.6(570)
--
-- https://tableplus.com/
--
-- Database: defaultdb
-- Generation Time: 2025-03-12 00:29:01.1750
-- -------------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


CREATE TABLE `access_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_identifier` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_until` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `medical_specialty_id` int NOT NULL,
  `hospital_service_id` int NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `time_slot_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `IDX_6A41727A6B899279` (`patient_id`),
  KEY `IDX_6A41727A87F4FB17` (`doctor_id`),
  KEY `IDX_6A41727ABFC81879` (`medical_specialty_id`),
  KEY `IDX_6A41727A9DFBA0F2` (`hospital_service_id`),
  KEY `IDX_6A41727AD62B0FA` (`time_slot_id`),
  CONSTRAINT `FK_6A41727A6B899279` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
  CONSTRAINT `FK_6A41727A87F4FB17` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `FK_6A41727A9DFBA0F2` FOREIGN KEY (`hospital_service_id`) REFERENCES `hospital_services` (`id`),
  CONSTRAINT `FK_6A41727ABFC81879` FOREIGN KEY (`medical_specialty_id`) REFERENCES `medical_specialties` (`id`),
  CONSTRAINT `FK_6A41727AD62B0FA` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`)
);

CREATE TABLE `doctor_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doctor_id` int NOT NULL,
  `stamp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_E18B682787F4FB17` (`doctor_id`),
  CONSTRAINT `FK_E18B682787F4FB17` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
);

CREATE TABLE `doctor_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doctor_id` int NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_FE29BD6587F4FB17` (`doctor_id`),
  CONSTRAINT `FK_FE29BD6587F4FB17` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
);

CREATE TABLE `doctor_to_hospital_service` (
  `doctor_id` int NOT NULL,
  `hospital_service_id` int NOT NULL,
  PRIMARY KEY (`doctor_id`,`hospital_service_id`),
  KEY `IDX_A9C5CCF687F4FB17` (`doctor_id`),
  KEY `IDX_A9C5CCF69DFBA0F2` (`hospital_service_id`),
  CONSTRAINT `FK_A9C5CCF687F4FB17` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_A9C5CCF69DFBA0F2` FOREIGN KEY (`hospital_service_id`) REFERENCES `hospital_services` (`id`) ON DELETE CASCADE
);

CREATE TABLE `doctor_to_medical_specialty` (
  `doctor_id` int NOT NULL,
  `medical_specialty_id` int NOT NULL,
  PRIMARY KEY (`doctor_id`,`medical_specialty_id`),
  KEY `IDX_3C3D22487F4FB17` (`doctor_id`),
  KEY `IDX_3C3D224BFC81879` (`medical_specialty_id`),
  CONSTRAINT `FK_3C3D22487F4FB17` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_3C3D224BFC81879` FOREIGN KEY (`medical_specialty_id`) REFERENCES `medical_specialties` (`id`) ON DELETE CASCADE
);

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
);

CREATE TABLE `hospital_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `duration` int NOT NULL,
  `mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `medical_specialty_id` int NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_59AF779CBFC81879` (`medical_specialty_id`),
  CONSTRAINT `FK_59AF779CBFC81879` FOREIGN KEY (`medical_specialty_id`) REFERENCES `medical_specialties` (`id`)
);

CREATE TABLE `hospital_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reminder_enabled` tinyint(1) NOT NULL,
  `reminder_sms_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reminder_email_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmation_enabled` tinyint(1) NOT NULL,
  `confirmation_sms_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmation_email_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
);

CREATE TABLE `medical_specialties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `time_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schedule_id` int NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_booked` tinyint(1) NOT NULL,
  `hospital_service_id` int DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_8D06D4ACA40BC2D5` (`schedule_id`),
  KEY `IDX_8D06D4AC9DFBA0F2` (`hospital_service_id`),
  CONSTRAINT `FK_8D06D4AC9DFBA0F2` FOREIGN KEY (`hospital_service_id`) REFERENCES `hospital_services` (`id`),
  CONSTRAINT `FK_8D06D4ACA40BC2D5` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`id`)
);

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_1483A5E9E7927C74` (`email`)
);

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `date`) VALUES
(355, 77, '2025-03-03'),
(356, 77, '2025-03-10'),
(357, 77, '2025-03-17'),
(358, 77, '2025-03-24');

INSERT INTO `doctor_to_hospital_service` (`doctor_id`, `hospital_service_id`) VALUES
(77, 1971);

INSERT INTO `doctor_to_medical_specialty` (`doctor_id`, `medical_specialty_id`) VALUES
(77, 98);

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20250213091011', '2025-02-13 11:10:36', 190),
('DoctrineMigrations\\Version20250215132959', '2025-02-15 15:31:41', 129),
('DoctrineMigrations\\Version20250215135452', '2025-02-15 15:54:59', 82),
('DoctrineMigrations\\Version20250216212311', '2025-02-16 23:23:28', 125),
('DoctrineMigrations\\Version20250216220506', '2025-02-17 00:05:10', 105),
('DoctrineMigrations\\Version20250217104145', '2025-02-17 12:41:52', 140),
('DoctrineMigrations\\Version20250217224454', '2025-02-18 00:44:59', 179),
('DoctrineMigrations\\Version20250217230658', '2025-02-18 01:07:06', 88),
('DoctrineMigrations\\Version20250217230904', '2025-02-18 01:09:06', 61),
('DoctrineMigrations\\Version20250218064055', '2025-02-18 08:40:59', 163),
('DoctrineMigrations\\Version20250218064348', '2025-02-18 08:43:54', 69),
('DoctrineMigrations\\Version20250218090226', '2025-02-18 11:03:15', 115),
('DoctrineMigrations\\Version20250224001430', '2025-02-24 02:14:41', 236),
('DoctrineMigrations\\Version20250225045703', '2025-02-25 06:57:11', 137),
('DoctrineMigrations\\Version20250225051518', '2025-02-25 07:16:20', 201),
('DoctrineMigrations\\Version20250228105931', '2025-02-28 12:59:56', 1047),
('DoctrineMigrations\\Version20250303065903', '2025-03-03 08:59:15', 1749);

INSERT INTO `hospital_services` (`id`, `name`, `created`, `updated`, `description`, `price`, `is_active`, `duration`, `mode`, `code`, `medical_specialty_id`, `color`) VALUES
(1971, 'Consultatie ambulator', '2025-02-25 07:18:49', '2025-03-04 13:51:19', '-', '100', 1, 15, 'AMBULATOR', '001', 98, '#14c7eb'),
(1972, 'Test ambulator', '2025-02-28 13:02:07', '2025-03-03 15:49:21', '-', '100', 0, 15, 'LABORATOR', '002', 98, '#f50505');

INSERT INTO `hospital_settings` (`id`, `reminder_enabled`, `reminder_sms_message`, `reminder_email_message`, `confirmation_enabled`, `confirmation_sms_message`, `confirmation_email_message`) VALUES
(1, 1, 'abd', 'abc', 0, NULL, NULL);

INSERT INTO `medical_specialties` (`id`, `code`, `name`, `created`, `updated`, `is_active`) VALUES
(97, '001', 'Medicina Interna', '2025-02-24 17:27:25', '2025-03-11 22:27:27', 1),
(98, '002', 'Cardiologie', '2025-02-24 17:27:25', '2025-03-11 22:27:27', 1),
(100, '004', 'Recuperare Medicala', '2025-02-24 17:27:25', '2025-03-11 22:27:57', 1);

INSERT INTO `time_slots` (`id`, `schedule_id`, `start_time`, `end_time`, `is_booked`, `hospital_service_id`, `status`) VALUES
(1818, 355, '12:00:00', '12:15:00', 0, 1971, NULL),
(1819, 355, '12:15:00', '12:30:00', 0, 1971, NULL),
(1834, 356, '12:00:00', '12:15:00', 1, 1971, NULL),
(1835, 356, '12:15:00', '12:30:00', 1, 1971, NULL),
(1836, 356, '12:30:00', '12:45:00', 1, 1971, NULL),
(1837, 356, '12:45:00', '13:00:00', 1, 1971, NULL),
(1838, 356, '13:00:00', '13:15:00', 1, 1971, NULL),
(1839, 356, '13:15:00', '13:30:00', 1, 1971, NULL),
(1840, 356, '13:30:00', '13:45:00', 1, 1971, NULL),
(1841, 356, '13:45:00', '14:00:00', 1, 1971, NULL),
(1842, 356, '14:00:00', '14:15:00', 1, 1971, NULL),
(1843, 356, '14:15:00', '14:30:00', 1, 1971, NULL),
(1844, 356, '14:30:00', '14:45:00', 1, 1971, NULL),
(1845, 356, '14:45:00', '15:00:00', 1, 1971, NULL),
(1846, 356, '15:00:00', '15:15:00', 1, 1971, NULL),
(1847, 356, '15:15:00', '15:30:00', 1, 1971, NULL),
(1848, 356, '15:30:00', '15:45:00', 1, 1971, NULL),
(1849, 356, '15:45:00', '16:00:00', 1, 1971, NULL),
(1850, 357, '12:00:00', '12:15:00', 1, 1971, NULL),
(1851, 357, '12:15:00', '12:30:00', 0, 1971, NULL),
(1852, 357, '12:30:00', '12:45:00', 0, 1971, NULL),
(1853, 357, '12:45:00', '13:00:00', 0, 1971, NULL),
(1854, 357, '13:00:00', '13:15:00', 0, 1971, NULL),
(1855, 357, '13:15:00', '13:30:00', 0, 1971, NULL),
(1856, 357, '13:30:00', '13:45:00', 0, 1971, NULL),
(1857, 357, '13:45:00', '14:00:00', 0, 1971, NULL),
(1858, 357, '14:00:00', '14:15:00', 0, 1971, NULL),
(1859, 357, '14:15:00', '14:30:00', 0, 1971, NULL),
(1860, 357, '14:30:00', '14:45:00', 0, 1971, NULL),
(1861, 357, '14:45:00', '15:00:00', 0, 1971, NULL),
(1862, 357, '15:00:00', '15:15:00', 0, 1971, NULL),
(1863, 357, '15:15:00', '15:30:00', 0, 1971, NULL),
(1864, 357, '15:30:00', '15:45:00', 0, 1971, NULL),
(1865, 357, '15:45:00', '16:00:00', 0, 1971, NULL),
(1866, 358, '12:00:00', '12:15:00', 1, 1971, NULL),
(1869, 358, '12:45:00', '13:00:00', 0, 1971, NULL),
(1870, 358, '13:00:00', '13:15:00', 0, 1971, NULL),
(1871, 358, '13:15:00', '13:30:00', 0, 1971, NULL),
(1872, 358, '13:30:00', '13:45:00', 0, 1971, NULL),
(1873, 358, '13:45:00', '14:00:00', 0, 1971, NULL),
(1874, 358, '14:00:00', '14:15:00', 0, 1971, NULL),
(1875, 358, '14:15:00', '14:30:00', 0, 1971, NULL),
(1876, 358, '14:30:00', '14:45:00', 0, 1971, NULL),
(1877, 358, '14:45:00', '15:00:00', 0, 1971, NULL),
(1878, 358, '15:00:00', '15:15:00', 0, 1971, NULL),
(1879, 358, '15:15:00', '15:30:00', 0, 1971, NULL),
(1880, 358, '15:30:00', '15:45:00', 0, 1971, NULL),
(1881, 358, '15:45:00', '16:00:00', 0, 1971, NULL);

INSERT INTO `users` (`id`, `email`, `first_name`, `last_name`, `cnp`, `phone`, `photo`, `roles`, `password`, `is_active`, `created`, `updated`, `user_type`) VALUES
(77, 'medic@test.com', 'Medic', 'Test', '1920530440044', 'test', NULL, '[\"ROLE_DOCTOR\"]', '$2y$13$RcoY5qBkbJUSz7/ZisCiN.JLXq.N7bbM8GOIy3YaoZi4BRfUesK4m', 1, '2025-02-24 19:29:01', '2025-03-03 16:04:36', 'doctor'),
(78, 'pacient@test.com', 'Pacient', 'Test', 'test', 'test', NULL, '[\"ROLE_PATIENT\"]', '$2y$13$fhq9wgEIyCxUmEotDLoCZO4GcfPicUvLpIQ5mYIbIwhiTJ50e9SkG', 1, '2025-02-24 19:47:06', '2025-02-24 19:47:06', 'patient'),
(79, 'admin@test.com', 'Admin', 'Test', 'test', 'test', NULL, '[\"ROLE_ADMIN\"]', '$2y$13$8auW/hlTZclNXjM/E9btJOCcXTxJeVGUOM4UiR47Nf3yjUDJbTW.q', 1, '2025-02-24 19:47:27', '2025-02-24 17:47:44', 'manager');



/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;