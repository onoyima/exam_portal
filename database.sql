-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 08:24 PM
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
-- Database: `assessment_portal`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `LogExport` (IN `p_user_id` INT, IN `p_username` VARCHAR(100), IN `p_export_type` VARCHAR(100), IN `p_file_name` VARCHAR(255), IN `p_record_count` INT, IN `p_ip_address` VARCHAR(45))   BEGIN
  INSERT INTO export_history (user_id, username, export_type, file_name, record_count, ip_address)
  VALUES (p_user_id, p_username, p_export_type, p_file_name, p_record_count, p_ip_address);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LogLogin` (IN `p_user_id` INT, IN `p_username` VARCHAR(100), IN `p_user_type` VARCHAR(20), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT, IN `p_status` VARCHAR(20))   BEGIN
  INSERT INTO login_history (user_id, username, user_type, ip_address, user_agent, status)
  VALUES (p_user_id, p_username, p_user_type, p_ip_address, p_user_agent, p_status);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LogSystemEvent` (IN `p_event_type` VARCHAR(100), IN `p_event_description` TEXT, IN `p_severity` VARCHAR(20), IN `p_user_id` INT, IN `p_username` VARCHAR(100), IN `p_ip_address` VARCHAR(45))   BEGIN
  INSERT INTO system_events (event_type, event_description, severity, user_id, username, ip_address)
  VALUES (p_event_type, p_event_description, p_severity, p_user_id, p_username, p_ip_address);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `SetAuditContext` (IN `p_user_id` INT, IN `p_user_type` VARCHAR(20), IN `p_username` VARCHAR(100), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
  SET @current_user_id = p_user_id;
  SET @current_user_type = p_user_type;
  SET @current_username = p_username;
  SET @current_ip = p_ip_address;
  SET @current_user_agent = p_user_agent;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `examiner_id` int(11) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers`
--

INSERT INTO `answers` (`id`, `exam_id`, `question_id`, `student_id`, `examiner_id`, `score`, `created_at`) VALUES
(38, 7, 30, 5, 4, 0.50, '2025-06-27 16:19:42'),
(39, 7, 31, 5, 4, 0.50, '2025-06-27 16:19:42'),
(40, 7, 32, 5, 4, 0.13, '2025-06-27 16:19:42'),
(41, 7, 33, 5, 4, 0.13, '2025-06-27 16:19:42'),
(42, 7, 34, 5, 4, 0.25, '2025-06-27 16:19:42'),
(43, 7, 35, 5, 4, 0.13, '2025-06-27 16:19:42'),
(44, 7, 36, 5, 4, 0.13, '2025-06-27 16:19:42'),
(45, 7, 37, 5, 4, 0.13, '2025-06-27 16:19:42'),
(46, 7, 38, 5, 4, 0.50, '2025-06-27 16:19:42'),
(47, 7, 39, 5, 4, 0.13, '2025-06-27 16:19:42'),
(48, 7, 40, 5, 4, 1.00, '2025-06-27 16:19:42'),
(49, 7, 41, 5, 4, 0.13, '2025-06-27 16:19:42'),
(50, 7, 42, 5, 4, 0.25, '2025-06-27 16:19:42'),
(51, 7, 43, 5, 4, 0.13, '2025-06-27 16:19:42'),
(52, 7, 44, 5, 4, 0.75, '2025-06-27 16:19:42'),
(53, 7, 45, 5, 4, 0.13, '2025-06-27 16:19:42'),
(54, 7, 46, 5, 4, 0.13, '2025-06-27 16:19:42'),
(55, 7, 30, 12, 4, 0.50, '2025-06-27 16:22:53'),
(56, 7, 31, 12, 4, 0.50, '2025-06-27 16:22:53'),
(57, 7, 32, 12, 4, 0.13, '2025-06-27 16:22:53'),
(58, 7, 33, 12, 4, 0.00, '2025-06-27 16:22:53'),
(59, 7, 34, 12, 4, 0.25, '2025-06-27 16:22:53'),
(60, 7, 35, 12, 4, 0.13, '2025-06-27 16:22:53'),
(61, 7, 36, 12, 4, 0.13, '2025-06-27 16:22:53'),
(62, 7, 37, 12, 4, 0.13, '2025-06-27 16:22:53'),
(63, 7, 38, 12, 4, 0.25, '2025-06-27 16:22:53'),
(64, 7, 39, 12, 4, 0.13, '2025-06-27 16:22:53'),
(65, 7, 40, 12, 4, 0.00, '2025-06-27 16:22:53'),
(66, 7, 41, 12, 4, 0.00, '2025-06-27 16:22:53'),
(67, 7, 42, 12, 4, 0.13, '2025-06-27 16:22:53'),
(68, 7, 43, 12, 4, 0.13, '2025-06-27 16:22:53'),
(69, 7, 44, 12, 4, 0.75, '2025-06-27 16:22:53'),
(70, 7, 45, 12, 4, 0.13, '2025-06-27 16:22:53'),
(71, 7, 46, 12, 4, 0.13, '2025-06-27 16:22:53'),
(72, 7, 30, 13, 4, 1.00, '2025-06-27 16:23:55'),
(73, 7, 31, 13, 4, 1.00, '2025-06-27 16:23:55'),
(74, 7, 32, 13, 4, 0.25, '2025-06-27 16:23:55'),
(75, 7, 33, 13, 4, 0.25, '2025-06-27 16:23:55'),
(76, 7, 34, 13, 4, 0.50, '2025-06-27 16:23:55'),
(77, 7, 35, 13, 4, 0.25, '2025-06-27 16:23:55'),
(78, 7, 36, 13, 4, 0.25, '2025-06-27 16:23:55'),
(79, 7, 37, 13, 4, 0.25, '2025-06-27 16:23:55'),
(80, 7, 38, 13, 4, 0.50, '2025-06-27 16:23:55'),
(81, 7, 39, 13, 4, 0.25, '2025-06-27 16:23:55'),
(82, 7, 40, 13, 4, 2.00, '2025-06-27 16:23:55'),
(83, 7, 41, 13, 4, 0.25, '2025-06-27 16:23:55'),
(84, 7, 42, 13, 4, 0.25, '2025-06-27 16:23:55'),
(85, 7, 43, 13, 4, 0.25, '2025-06-27 16:23:55'),
(86, 7, 44, 13, 4, 1.50, '2025-06-27 16:23:55'),
(87, 7, 45, 13, 4, 0.25, '2025-06-27 16:23:55'),
(88, 7, 46, 13, 4, 0.25, '2025-06-27 16:23:55'),
(89, 8, 48, 11, 5, 1.00, '2025-06-27 17:06:58'),
(90, 8, 49, 11, 5, 1.00, '2025-06-27 17:06:58'),
(91, 8, 50, 11, 5, 0.25, '2025-06-27 17:06:58'),
(92, 8, 51, 11, 5, 0.25, '2025-06-27 17:06:58'),
(93, 8, 52, 11, 5, 0.50, '2025-06-27 17:06:58'),
(94, 8, 53, 11, 5, 0.25, '2025-06-27 17:06:58'),
(95, 8, 54, 11, 5, 0.25, '2025-06-27 17:06:58'),
(96, 8, 55, 11, 5, 0.25, '2025-06-27 17:06:58'),
(97, 8, 56, 11, 5, 0.50, '2025-06-27 17:06:58'),
(98, 8, 57, 11, 5, 0.25, '2025-06-27 17:06:58'),
(99, 8, 58, 11, 5, 2.00, '2025-06-27 17:06:58'),
(100, 8, 59, 11, 5, 0.25, '2025-06-27 17:06:58'),
(101, 8, 60, 11, 5, 0.25, '2025-06-27 17:06:58'),
(102, 8, 61, 11, 5, 0.25, '2025-06-27 17:06:58'),
(103, 8, 62, 11, 5, 1.50, '2025-06-27 17:06:58'),
(104, 8, 63, 11, 5, 0.25, '2025-06-27 17:06:58'),
(105, 8, 64, 11, 5, 0.25, '2025-06-27 17:06:58'),
(106, 8, 48, 13, 5, 1.00, '2025-06-27 17:08:18'),
(107, 8, 49, 13, 5, 1.00, '2025-06-27 17:08:18'),
(108, 8, 50, 13, 5, 0.25, '2025-06-27 17:08:18'),
(109, 8, 51, 13, 5, 0.25, '2025-06-27 17:08:18'),
(110, 8, 52, 13, 5, 0.50, '2025-06-27 17:08:18'),
(111, 8, 53, 13, 5, 0.25, '2025-06-27 17:08:18'),
(112, 8, 54, 13, 5, 0.25, '2025-06-27 17:08:18'),
(113, 8, 55, 13, 5, 0.25, '2025-06-27 17:08:18'),
(114, 8, 56, 13, 5, 0.50, '2025-06-27 17:08:18'),
(115, 8, 57, 13, 5, 0.25, '2025-06-27 17:08:19'),
(116, 8, 58, 13, 5, 2.00, '2025-06-27 17:08:19'),
(117, 8, 59, 13, 5, 0.25, '2025-06-27 17:08:19'),
(118, 8, 60, 13, 5, 0.25, '2025-06-27 17:08:19'),
(119, 8, 61, 13, 5, 0.25, '2025-06-27 17:08:19'),
(120, 8, 62, 13, 5, 1.50, '2025-06-27 17:08:19'),
(121, 8, 63, 13, 5, 0.25, '2025-06-27 17:08:19'),
(122, 8, 64, 13, 5, 0.25, '2025-06-27 17:08:19'),
(123, 8, 48, 14, 5, 1.00, '2025-06-27 17:09:04'),
(124, 8, 49, 14, 5, 1.00, '2025-06-27 17:09:04'),
(125, 8, 50, 14, 5, 0.25, '2025-06-27 17:09:04'),
(126, 8, 51, 14, 5, 0.25, '2025-06-27 17:09:04'),
(127, 8, 52, 14, 5, 0.50, '2025-06-27 17:09:04'),
(128, 8, 53, 14, 5, 0.25, '2025-06-27 17:09:04'),
(129, 8, 54, 14, 5, 0.25, '2025-06-27 17:09:04'),
(130, 8, 55, 14, 5, 0.25, '2025-06-27 17:09:04'),
(131, 8, 56, 14, 5, 0.50, '2025-06-27 17:09:04'),
(132, 8, 57, 14, 5, 0.25, '2025-06-27 17:09:04'),
(133, 8, 58, 14, 5, 2.00, '2025-06-27 17:09:04'),
(134, 8, 59, 14, 5, 0.25, '2025-06-27 17:09:04'),
(135, 8, 60, 14, 5, 0.25, '2025-06-27 17:09:04'),
(136, 8, 61, 14, 5, 0.25, '2025-06-27 17:09:04'),
(137, 8, 62, 14, 5, 1.50, '2025-06-27 17:09:04'),
(138, 8, 63, 14, 5, 0.25, '2025-06-27 17:09:04'),
(139, 8, 64, 14, 5, 0.13, '2025-06-27 17:09:04'),
(140, 7, 30, 11, 5, 0.50, '2025-06-27 17:09:44'),
(141, 7, 31, 11, 5, 0.50, '2025-06-27 17:09:44'),
(142, 7, 32, 11, 5, 0.13, '2025-06-27 17:09:44'),
(143, 7, 33, 11, 5, 0.13, '2025-06-27 17:09:44'),
(144, 7, 34, 11, 5, 0.25, '2025-06-27 17:09:44'),
(145, 7, 35, 11, 5, 0.13, '2025-06-27 17:09:44'),
(146, 7, 36, 11, 5, 0.13, '2025-06-27 17:09:44'),
(147, 7, 37, 11, 5, 0.13, '2025-06-27 17:09:44'),
(148, 7, 38, 11, 5, 0.25, '2025-06-27 17:09:44'),
(149, 7, 39, 11, 5, 0.13, '2025-06-27 17:09:44'),
(150, 7, 40, 11, 5, 1.00, '2025-06-27 17:09:44'),
(151, 7, 41, 11, 5, 0.13, '2025-06-27 17:09:44'),
(152, 7, 42, 11, 5, 0.13, '2025-06-27 17:09:44'),
(153, 7, 43, 11, 5, 0.13, '2025-06-27 17:09:44'),
(154, 7, 44, 11, 5, 0.75, '2025-06-27 17:09:44'),
(155, 7, 45, 11, 5, 0.13, '2025-06-27 17:09:44'),
(156, 7, 46, 11, 5, 0.13, '2025-06-27 17:09:44'),
(157, 7, 30, 14, 5, 0.50, '2025-06-27 17:10:21'),
(158, 7, 31, 14, 5, 0.50, '2025-06-27 17:10:21'),
(159, 7, 32, 14, 5, 0.25, '2025-06-27 17:10:21'),
(160, 7, 33, 14, 5, 0.25, '2025-06-27 17:10:21'),
(161, 7, 34, 14, 5, 0.50, '2025-06-27 17:10:21'),
(162, 7, 35, 14, 5, 0.25, '2025-06-27 17:10:21'),
(163, 7, 36, 14, 5, 0.25, '2025-06-27 17:10:21'),
(164, 7, 37, 14, 5, 0.25, '2025-06-27 17:10:21'),
(165, 7, 38, 14, 5, 0.50, '2025-06-27 17:10:21'),
(166, 7, 39, 14, 5, 0.25, '2025-06-27 17:10:21'),
(167, 7, 40, 14, 5, 2.00, '2025-06-27 17:10:21'),
(168, 7, 41, 14, 5, 0.25, '2025-06-27 17:10:21'),
(169, 7, 42, 14, 5, 0.25, '2025-06-27 17:10:21'),
(170, 7, 43, 14, 5, 0.25, '2025-06-27 17:10:21'),
(171, 7, 44, 14, 5, 1.50, '2025-06-27 17:10:21'),
(172, 7, 45, 14, 5, 0.25, '2025-06-27 17:10:21'),
(173, 7, 46, 14, 5, 0.25, '2025-06-27 17:10:21');

--
-- Triggers `answers`
--
DELIMITER $$
CREATE TRIGGER `audit_answers_insert` AFTER INSERT ON `answers` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    NEW.examiner_id,
    'examiner',
    (SELECT username FROM examiners WHERE id = NEW.examiner_id),
    'ASSESSMENT_SUBMITTED',
    'answers',
    NEW.id,
    JSON_OBJECT('exam_id', NEW.exam_id, 'student_id', NEW.student_id, 'question_id', NEW.question_id, 'score', NEW.score),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','examiner','student') NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `media_blob` longblob DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `group_id`, `message`, `media_type`, `media_blob`, `created_at`, `is_read`) VALUES
(1, 3, NULL, NULL, '', NULL, NULL, '2025-06-27 19:18:45', 0),
(2, 3, 5, NULL, 'hello', NULL, NULL, '2025-06-27 19:18:51', 0),
(3, 3, NULL, 1, 'hi', NULL, NULL, '2025-06-27 19:18:57', 0),
(4, 4, NULL, NULL, '', NULL, NULL, '2025-06-27 19:23:33', 0);

-- --------------------------------------------------------

--
-- Table structure for table `examiners`
--

CREATE TABLE `examiners` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'examiner'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `examiners`
--

INSERT INTO `examiners` (`id`, `name`, `username`, `password`, `role`) VALUES
(3, 'Admin User', 'admin', 'admin@1', 'admin'),
(4, 'Examiner One', 'examiner1', 'examiner123', 'examiner'),
(5, 'Examiner Two', 'examiner2', 'examiner223', 'examiner');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `name`, `is_active`, `created_at`) VALUES
(7, 'ICT Module 1', 1, '2025-06-27 16:16:43'),
(8, 'ICT Module 2', 1, '2025-06-27 16:49:14');

--
-- Triggers `exams`
--
DELIMITER $$
CREATE TRIGGER `audit_exams_insert` AFTER INSERT ON `exams` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'exams',
    NEW.id,
    JSON_OBJECT('name', NEW.name, 'is_active', NEW.is_active),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_exams_update` AFTER UPDATE ON `exams` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'exams',
    NEW.id,
    JSON_OBJECT('name', OLD.name, 'is_active', OLD.is_active),
    JSON_OBJECT('name', NEW.name, 'is_active', NEW.is_active),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `exam_examiners`
--

CREATE TABLE `exam_examiners` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `examiner_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_examiners`
--

INSERT INTO `exam_examiners` (`id`, `exam_id`, `examiner_id`) VALUES
(15, 7, 4),
(16, 7, 5),
(17, 8, 4),
(18, 8, 5);

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_id`) VALUES
(38, 7, 30),
(39, 7, 31),
(40, 7, 32),
(41, 7, 33),
(42, 7, 34),
(43, 7, 35),
(44, 7, 36),
(45, 7, 37),
(46, 7, 38),
(47, 7, 39),
(48, 7, 40),
(49, 7, 41),
(50, 7, 42),
(51, 7, 43),
(52, 7, 44),
(53, 7, 45),
(54, 7, 46),
(56, 8, 48),
(57, 8, 49),
(58, 8, 50),
(59, 8, 51),
(60, 8, 52),
(61, 8, 53),
(62, 8, 54),
(63, 8, 55),
(64, 8, 56),
(65, 8, 57),
(66, 8, 58),
(67, 8, 59),
(68, 8, 60),
(69, 8, 61),
(70, 8, 62),
(71, 8, 63),
(72, 8, 64);

-- --------------------------------------------------------

--
-- Table structure for table `exam_students`
--

CREATE TABLE `exam_students` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_students`
--

INSERT INTO `exam_students` (`id`, `exam_id`, `student_id`) VALUES
(13, 7, 5),
(14, 7, 10),
(15, 7, 11),
(16, 7, 12),
(17, 7, 13),
(18, 7, 14),
(24, 8, 5),
(23, 8, 10),
(21, 8, 11),
(20, 8, 12),
(22, 8, 13),
(19, 8, 14);

-- --------------------------------------------------------

--
-- Table structure for table `export_history`
--

CREATE TABLE `export_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `export_type` varchar(100) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `record_count` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `user_type` enum('admin','examiner','student') NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `username`, `user_type`, `login_time`, `logout_time`, `ip_address`, `user_agent`, `status`) VALUES
(1, NULL, 'examiner1', 'examiner', '2025-06-27 00:32:28', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(2, NULL, 'admin', 'admin', '2025-06-27 00:40:57', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(3, NULL, 'admin', 'admin', '2025-06-27 01:21:57', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(4, NULL, 'examiner1', 'examiner', '2025-06-27 01:39:20', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(5, NULL, 'admin', 'admin', '2025-06-27 01:56:58', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(6, NULL, 'examiner2', 'examiner', '2025-06-27 02:07:03', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(7, NULL, 'examiner1', 'examiner', '2025-06-27 02:08:02', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(8, NULL, 'admin', 'admin', '2025-06-27 02:10:46', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(9, NULL, 'examiner1', 'examiner', '2025-06-27 02:20:45', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(10, NULL, 'examiner2', 'examiner', '2025-06-27 02:23:01', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(11, NULL, 'admin', 'admin', '2025-06-27 02:23:26', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(12, NULL, 'admin', 'admin', '2025-06-27 08:20:19', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(13, NULL, 'examiner1', 'examiner', '2025-06-27 08:30:35', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(14, NULL, 'admin', 'admin', '2025-06-27 08:37:23', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(15, NULL, 'examiner1', 'examiner', '2025-06-27 10:19:45', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) minimal-repro/1.0.0 Chrome/138.0.7204.35 Electron/37.1.0 Safari/537.36', 'success'),
(16, NULL, 'admin', 'admin', '2025-06-27 10:27:51', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) minimal-repro/1.0.0 Chrome/138.0.7204.35 Electron/37.1.0 Safari/537.36', 'success'),
(17, NULL, 'examiner1', 'examiner', '2025-06-27 11:38:08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(18, NULL, 'admin', 'admin', '2025-06-27 11:39:20', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(19, NULL, 'admin', 'admin', '2025-06-27 11:43:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(20, NULL, 'examiner1', '', '2025-06-27 11:44:31', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(21, NULL, 'examiner1', '', '2025-06-27 11:44:59', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(22, NULL, 'examiner1', '', '2025-06-27 11:45:11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(23, NULL, 'examiner1', 'examiner', '2025-06-27 11:45:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'success'),
(24, NULL, 'admin', 'admin', '2025-06-27 12:19:58', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(25, NULL, 'admin', '', '2025-06-27 14:11:27', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(26, NULL, 'admin', 'admin', '2025-06-27 14:11:35', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'success'),
(27, NULL, 'examiner1', '', '2025-06-27 14:12:48', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(28, NULL, 'examiner1', '', '2025-06-27 14:13:34', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'failed'),
(29, NULL, 'examiner1', 'examiner', '2025-06-27 14:13:44', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'success'),
(30, NULL, 'examiner1', 'examiner', '2025-06-27 15:15:27', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(31, NULL, 'admin', 'admin', '2025-06-27 15:44:00', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(32, NULL, 'examiner1', '', '2025-06-27 15:45:11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'failed'),
(33, NULL, 'examiner1', 'examiner', '2025-06-27 15:45:25', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(34, NULL, 'admin', 'admin', '2025-06-27 16:08:28', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(35, NULL, 'Admin', 'admin', '2025-06-27 16:56:52', NULL, '10.10.7.149', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'success'),
(36, NULL, 'Examiner2', 'examiner', '2025-06-27 17:05:13', NULL, '10.10.7.149', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'success'),
(37, NULL, 'admin', 'admin', '2025-06-27 19:16:25', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(38, NULL, 'admin', 'admin', '2025-06-27 19:18:06', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(39, NULL, 'examiner1', 'examiner', '2025-06-27 19:19:12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success'),
(40, NULL, 'examiner1', 'examiner', '2025-06-27 19:23:18', NULL, '10.10.11.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 'success');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `text` varchar(500) DEFAULT NULL,
  `score_values` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `text`, `score_values`) VALUES
(30, 'Appearance', '0,0.5,1'),
(31, 'Comportment', '0,0.5,1'),
(32, 'Greets patient and introduces self', '0,0.125,0.25'),
(33, 'Explains procedure to patient & obtain consent', '0,0.125,0.25'),
(34, 'Use blood form/transfusion prescription collect right blood from the laboratory', '0,0.25,0.5'),
(35, 'Set a tray for the procedure including blood given set', '0,0.125,0.25'),
(36, 'Check pre transfusion vital signs and record', '0,0.125,0.25'),
(37, 'Position patient, provide privacy and administer any pre – transfusion medications', '0,0.125,0.25'),
(38, 'Re-check blood bag profiles with blood form', '0,0.25,0.5'),
(39, 'Fix blood given set to blood bag, hang it and expel air', '0,0.125,0.25'),
(40, 'Open IV line with big size cannula, secure with adhesive plaster and connect blood set with cannula (doctor procedure)', '0,1,2'),
(41, 'Regulate blood flow to 40 – 60dpm', '0,0.125,0.25'),
(42, 'Make patient comfortable/ provide support and discard used items', '0,0.125,0.25'),
(43, 'Monitor V/S 1 hourly until 4hours after transfusion', '0,0.125,0.25'),
(44, 'Watch out for signs of reaction during and 4hours after transfusion, e.g. restlessness, cool, shivery, raised temperature and breathing. If any, clip and stop transfusion, call for help and give prescribed hydrocortisone', '0,0.75,1.5'),
(45, 'Observe hand hygiene', '0,0.125,0.25'),
(46, 'Record and document', '0,0.125,0.25 '),
(48, 'Appearance', '0,0.5,1'),
(49, 'Comportment', '0,0.5,1'),
(50, 'Greets patient and introduces self', '0,0.125,0.25'),
(51, 'Explains procedure to patient & obtain consent', '0,0.125,0.25'),
(52, 'Use blood form/transfusion prescription collect right blood from the laboratory', '0,0.25,0.5'),
(53, 'Set a tray for the procedure including blood given set', '0,0.125,0.25'),
(54, 'Check pre transfusion vital signs and record', '0,0.125,0.25'),
(55, 'Position patient, provide privacy and administer any pre – transfusion medications', '0,0.125,0.25'),
(56, 'Re-check blood bag profiles with blood form', '0,0.25,0.5'),
(57, 'Fix blood given set to blood bag, hang it and expel air', '0,0.125,0.25'),
(58, 'Open IV line with big size cannula, secure with adhesive plaster and connect blood set with cannula (doctor procedure)', '0,1,2'),
(59, 'Regulate blood flow to 40 – 60dpm', '0,0.125,0.25'),
(60, 'Make patient comfortable/ provide support and discard used items', '0,0.125,0.25'),
(61, 'Monitor V/S 1 hourly until 4hours after transfusion', '0,0.125,0.25'),
(62, 'Watch out for signs of reaction during and 4hours after transfusion, e.g. restlessness, cool, shivery, raised temperature and breathing. If any, clip and stop transfusion, call for help and give prescribed hydrocortisone', '0,0.75,1.5'),
(63, 'Observe hand hygiene', '0,0.125,0.25'),
(64, 'Record and document', '0,0.125,0.25 ');

--
-- Triggers `questions`
--
DELIMITER $$
CREATE TRIGGER `audit_questions_delete` AFTER DELETE ON `questions` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'DELETE',
    'questions',
    OLD.id,
    JSON_OBJECT('text', OLD.text, 'score_values', OLD.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_questions_insert` AFTER INSERT ON `questions` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'questions',
    NEW.id,
    JSON_OBJECT('text', NEW.text, 'score_values', NEW.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_questions_update` AFTER UPDATE ON `questions` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'questions',
    NEW.id,
    JSON_OBJECT('text', OLD.text, 'score_values', OLD.score_values),
    JSON_OBJECT('text', NEW.text, 'score_values', NEW.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `matric_no` varchar(50) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `matric_no`, `username`) VALUES
(5, 'OGAJI STELLA OKEBECHI', 'VUGACC226541', 'VUGACC226541'),
(10, 'ODUNKA', 'VUG/CSC/12/123', 'VUG/CSC/12/123'),
(11, 'GBILEKAA JOY DOOCIVIR', 'VUGACC226694', 'VUGACC226694'),
(12, 'AGBOEZE ISABELLE ADAEZE', 'VUGACC226703', 'VUGACC226703'),
(13, 'KAYODE OLUWATOBI OLAOLUWA', 'VUGACC226712', 'VUGACC226712'),
(14, 'ADAGBOYI EMMANUELLA UGBANYA', 'VUGACC226718', 'VUGACC226718');

--
-- Triggers `students`
--
DELIMITER $$
CREATE TRIGGER `audit_students_delete` AFTER DELETE ON `students` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'DELETE',
    'students',
    OLD.id,
    JSON_OBJECT('name', OLD.name, 'matric_no', OLD.matric_no, 'username', OLD.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_students_insert` AFTER INSERT ON `students` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'students',
    NEW.id,
    JSON_OBJECT('name', NEW.name, 'matric_no', NEW.matric_no, 'username', NEW.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_students_update` AFTER UPDATE ON `students` FOR EACH ROW BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'students',
    NEW.id,
    JSON_OBJECT('name', OLD.name, 'matric_no', OLD.matric_no, 'username', OLD.username),
    JSON_OBJECT('name', NEW.name, 'matric_no', NEW.matric_no, 'username', NEW.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_chat_log`
--

CREATE TABLE `system_chat_log` (
  `id` int(11) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `user_role` varchar(20) NOT NULL,
  `user_message` text NOT NULL,
  `bot_response` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_chat_log`
--

INSERT INTO `system_chat_log` (`id`, `user_id`, `user_role`, `user_message`, `bot_response`, `created_at`) VALUES
(1, 'examiner1', 'examiner', 'yes', 'I\'m here to help! Try asking about:\\n• Exams\\n• Students\\n• Questions\\n• Analytics\\n• Reports\\n• Chat\\n• Help', '2025-06-27 01:55:01'),
(2, 'examiner1', 'examiner', 'how is exam', 'To manage exams:\\n1. Go to \"Manage Exams\"\\n2. Create a new exam\\n3. Add students and questions\\n4. Activate when ready', '2025-06-27 01:55:18'),
(3, 'examiner1', 'examiner', '1', 'I\'m here to help! Try asking about:\\n• Exams\\n• Students\\n• Questions\\n• Analytics\\n• Reports\\n• Chat\\n• Help', '2025-06-27 01:55:31'),
(4, 'admin', 'admin', 'ok', 'I\'m here to help! Try asking about:\\n• Exams\\n• Students\\n• Questions\\n• Analytics\\n• Reports\\n• Chat\\n• Help', '2025-06-27 01:57:12'),
(5, 'admin', 'admin', 'students', 'To manage students:\\n1. Go to \"Manage Students\"\\n2. Add individual students or bulk upload\\n3. Edit or delete as needed', '2025-06-27 01:57:16'),
(6, 'admin', 'admin', 'ok', 'I\'m here to help! Try asking about:\\n• Exams\\n• Students\\n• Questions\\n• Analytics\\n• Reports\\n• Chat\\n• Help', '2025-06-27 01:57:29'),
(7, 'admin', 'admin', 'analytics', 'View analytics in the \"Analytics\" section to see:\\n• Student performance\\n• Exam statistics\\n• Score distributions', '2025-06-27 01:57:34'),
(8, 'admin', 'admin', 'xsdx', 'I\'m here to help! Try asking about:\\n• Exams\\n• Students\\n• Questions\\n• Analytics\\n• Reports\\n• Chat\\n• Help', '2025-06-27 02:05:12');

-- --------------------------------------------------------

--
-- Table structure for table `system_events`
--

CREATE TABLE `system_events` (
  `id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_description` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'low',
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_events`
--

INSERT INTO `system_events` (`id`, `event_type`, `event_description`, `severity`, `user_id`, `username`, `ip_address`, `created_at`) VALUES
(1, 'system_startup', 'Assessment Portal System Initialized', 'low', NULL, NULL, NULL, '2025-06-27 16:05:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `examiner_id` (`examiner_id`),
  ADD KEY `idx_exam_student_question` (`exam_id`,`student_id`,`question_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `examiners`
--
ALTER TABLE `examiners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exam_examiners`
--
ALTER TABLE `exam_examiners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `examiner_id` (`examiner_id`),
  ADD KEY `idx_exam_examiner` (`exam_id`,`examiner_id`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_exam_question` (`exam_id`,`question_id`);

--
-- Indexes for table `exam_students`
--
ALTER TABLE `exam_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_exam_student` (`exam_id`,`student_id`);

--
-- Indexes for table `export_history`
--
ALTER TABLE `export_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_export` (`user_id`,`created_at`),
  ADD KEY `idx_export_type` (`export_type`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_login` (`user_id`,`login_time`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matric_no` (`matric_no`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `system_chat_log`
--
ALTER TABLE `system_chat_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_events`
--
ALTER TABLE `system_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=234;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `examiners`
--
ALTER TABLE `examiners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `exam_examiners`
--
ALTER TABLE `exam_examiners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `exam_students`
--
ALTER TABLE `exam_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `export_history`
--
ALTER TABLE `export_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `system_chat_log`
--
ALTER TABLE `system_chat_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `system_events`
--
ALTER TABLE `system_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `answers_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `answers_ibfk_4` FOREIGN KEY (`examiner_id`) REFERENCES `examiners` (`id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `examiners` (`id`),
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `examiners` (`id`);

--
-- Constraints for table `exam_examiners`
--
ALTER TABLE `exam_examiners`
  ADD CONSTRAINT `exam_examiners_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_examiners_ibfk_2` FOREIGN KEY (`examiner_id`) REFERENCES `examiners` (`id`);

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `exam_students`
--
ALTER TABLE `exam_students`
  ADD CONSTRAINT `exam_students_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
