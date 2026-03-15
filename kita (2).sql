-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 15, 2026 at 02:52 PM
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
-- Database: `kita`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `interview_type` varchar(20) DEFAULT NULL,
  `interview_datetime` datetime DEFAULT NULL,
  `interview_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `job_id`, `student_id`, `status`, `applied_at`, `interview_type`, `interview_datetime`, `interview_notes`) VALUES
(1, 1, 2, 'interview_scheduled', '2026-03-07 11:48:44', 'interview', '2026-08-07 20:35:00', 'Bring a pencil'),
(2, 2, 2, 'interview_scheduled', '2026-03-07 12:36:26', 'interview', '2026-03-14 16:47:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `thread_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `thread_id`, `sender_id`, `body`, `created_at`) VALUES
(1, 1, 5, 'j', '2026-03-08 12:53:03'),
(2, 1, 2, 'k', '2026-03-08 12:53:34'),
(3, 2, 2, 'k', '2026-03-15 13:43:47');

-- --------------------------------------------------------

--
-- Table structure for table `chat_threads`
--

CREATE TABLE `chat_threads` (
  `thread_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_threads`
--

INSERT INTO `chat_threads` (`thread_id`, `created_at`, `updated_at`) VALUES
(1, '2026-03-08 12:53:03', '2026-03-08 12:53:34'),
(2, '2026-03-15 13:43:47', '2026-03-15 13:43:47');

-- --------------------------------------------------------

--
-- Table structure for table `chat_thread_members`
--

CREATE TABLE `chat_thread_members` (
  `thread_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_thread_members`
--

INSERT INTO `chat_thread_members` (`thread_id`, `user_id`, `last_read_at`) VALUES
(1, 2, NULL),
(1, 5, NULL),
(2, -3, NULL),
(2, 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification_codes`
--

INSERT INTO `email_verification_codes` (`id`, `user_id`, `email`, `code_hash`, `expires_at`, `used_at`, `attempts`, `created_at`) VALUES
(1, 2, 'bmi.christianballen05@gmail.com', '$2y$10$bc2mAiyCU/jnE93y4yFx0.BJwLBvyplchLiHiB6PQZm3FvrUTANR6', '2026-03-07 12:47:21', '2026-03-07 19:32:56', 0, '2026-03-07 11:32:21'),
(2, 2, 'bmi.christianballen05@gmail.com', '$2y$10$mbrcrA3cb07GrWPpWQVNR.3ewLbbGVj3WxJKKhedrlFD0s3mpUSDO', '2026-03-07 12:47:56', '2026-03-07 19:38:42', 0, '2026-03-07 11:32:56'),
(3, 2, 'bmi.christianballen05@gmail.com', '$2y$10$JONGauP7IHZS4z0GpHFhluzNrvxMxMEVVjmDVzStVilRNLTzkp4cu', '2026-03-07 12:53:42', '2026-03-07 19:42:11', 0, '2026-03-07 11:38:42'),
(4, 2, 'bmi.christianballen05@gmail.com', '$2y$10$ZZq.cdcpKV41Ruxnna4/3efl0Wv5l5zZDGsiDnf1kEY8Rp8LJS3hW', '2026-03-07 12:57:11', '2026-03-07 19:42:19', 0, '2026-03-07 11:42:11'),
(5, 2, 'bmi.christianballen05@gmail.com', '$2y$10$S6jcAstViqDOUpsW6nL9ReBzygS3MKcnzmWF.nAAR3oBc3IbGvnFW', '2026-03-07 12:57:19', '2026-03-07 19:44:13', 0, '2026-03-07 11:42:20'),
(6, 2, 'bmi.christianballen05@gmail.com', '$2y$10$AqyjaYxsgi6RbqljzY6Vd.r/./VBQ4k9zfp/7KbcH9mIxkVESl1Ae', '2026-03-07 12:59:13', NULL, 0, '2026-03-07 11:44:13');

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `id` int(11) NOT NULL,
  `company_name` varchar(120) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `email` varchar(140) NOT NULL,
  `industry` varchar(100) NOT NULL,
  `company_size` varchar(20) NOT NULL,
  `location` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`id`, `company_name`, `contact_name`, `email`, `industry`, `company_size`, `location`, `phone`, `website`, `password`, `created_at`, `profile_picture`, `bio`) VALUES
(1, 'KITA', 'Christian Ballen', 'bmi.christianballen05@gmail.com', 'Banking and Finance', '1-5', 'Baguio City', '', '', '$2y$10$s6LQwaDNBXrFWMJ9h4oHzOR4mPVMWmXOTYNxve46WkopiaNz0BgDK', '2026-03-07 04:25:20', NULL, NULL),
(2, 'asdf', 'simon', 'ballensimon50@gmail.com', 'Retail and E-commerce', '1-5', 'Baguio City', '', '', '$2y$10$syWopdmgN2/BR7kWK8h08OOyH1Uh0rrBjtw5w4fxbfimZvxfbZIxi', '2026-03-07 04:37:07', NULL, NULL),
(3, 'KITA', 'Christian Ballen', 'ballendanielle0246@gmail.com', 'Retail and E-commerce', '51-200', 'Baguio City', '', '', '$2y$10$J6u6rE.D74MCNWm5nn7vH.41QhXcmX8N.BDrVsxz5JzY8nyYABLxW', '2026-03-07 11:23:17', 'uploads/employer_pics/e3_1772885155_69ac14a3dce568.04734632.jpg', 'I am the real apple fr');

-- --------------------------------------------------------

--
-- Table structure for table `friendships`
--

CREATE TABLE `friendships` (
  `friendship_id` int(10) UNSIGNED NOT NULL,
  `user_one_id` int(10) UNSIGNED NOT NULL,
  `user_two_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `job_title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `strand_required` varchar(120) NOT NULL,
  `skills_required` text NOT NULL,
  `location` varchar(120) NOT NULL,
  `salary` varchar(80) NOT NULL,
  `job_type` enum('part-time','full-time','internship','temporary') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `job_title`, `description`, `strand_required`, `skills_required`, `location`, `salary`, `job_type`, `created_at`) VALUES
(1, 3, 'Programmer', 'i need a programmer', 'ICT', 'css, java, html', 'Baguio City, Benguet', 'PHP 100,000-20,000', 'part-time', '2026-03-07 11:31:58'),
(2, 3, 'web designer', 'i need someone who is experienced and basta', 'ICT', 'css, java, html', 'Baguio City, Benguet', 'PHP 100,000-20,000', 'part-time', '2026-03-07 12:36:06');

-- --------------------------------------------------------

--
-- Table structure for table `job_seekers`
--

CREATE TABLE `job_seekers` (
  `seeker_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `strand` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(255) NOT NULL,
  `data_json` text DEFAULT NULL,
  `external_key` varchar(120) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `body`, `data_json`, `external_key`, `is_read`, `created_at`) VALUES
(1, 2, 'appointment_scheduled', '📅 Appointment Scheduled', 'Your application for \"Programmer\" at KITA has been scheduled.\nType: Interview\nDate/Time: 2026-08-07 20:35:00\nNotes: Bring a pencil', '{\"application_id\":1,\"interview_datetime\":\"2026-08-07 20:35:00\",\"interview_type\":\"interview\"}', 'appt_1', 1, '2026-03-07 12:44:26'),
(48, 2, 'new_message', 'New message', 'ian: j', '{\"thread_id\":1,\"sender_id\":5}', NULL, 1, '2026-03-08 12:53:03'),
(50, 5, 'new_message', 'New message', 'KingIan_1st: k', '{\"thread_id\":1,\"sender_id\":2}', NULL, 0, '2026-03-08 12:53:34'),
(53, 2, 'appointment_scheduled', '📅 Appointment Scheduled', 'Your application for \"web designer\" at KITA has been scheduled.\nType: Interview\nDate/Time: 2026-03-14 16:47:00', '{\"application_id\":2,\"interview_datetime\":\"2026-03-14 16:47:00\",\"interview_type\":\"interview\"}', 'appt_2', 1, '2026-03-14 08:47:40');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `strand` varchar(100) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `content`, `image`, `created_at`, `strand`, `location`) VALUES
(1, 2, 'asdf', 'uploads/post_images/p2_1772971477_69ad65d57199a6.59370543.jpg', '2026-03-08 12:04:37', 'ICT', 'Baguio City');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_skills`
--

CREATE TABLE `seeker_skills` (
  `skill_id` int(11) NOT NULL,
  `seeker_id` int(11) DEFAULT NULL,
  `skill_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `skill_name`, `created_at`) VALUES
(1, 'CSS', '2026-03-07 05:02:21'),
(2, 'HTML', '2026-03-07 05:02:21'),
(3, 'Javascript', '2026-03-07 05:02:21'),
(4, 'Java', '2026-03-07 11:44:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `email` varchar(140) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'student',
  `strand` varchar(100) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `career_plan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `full_name`, `email`, `email_verified`, `email_verified_at`, `password`, `role`, `strand`, `location`, `bio`, `profile_picture`, `created_at`, `career_plan`) VALUES
(1, 'sophiathegaylord', NULL, 'preciousdaniel.ballen@gmail.com', 0, NULL, '$2y$10$xVjq22WL37dB6QWJETu49unP.eKR.UXtYH2vQ0hk7LxuCqNxlVQZS', 'student', 'STEM', 'Baguio City', 'i am the gayest person in existence', 'uploads/profile_pics/u1_1772859783_69abb187064a14.75892709.jpg', '2026-03-07 05:02:21', NULL),
(2, 'KingIan_1st', NULL, 'bmi.christianballen05@gmail.com', 0, NULL, '$2y$10$HFnQ96EONFd1i2qsAoYMq.De./lLoaCiU4OmHkZggcDO6by/UHdq6', 'student', 'ICT', 'Baguio City', '', 'uploads/profile_pics/u2_1772872713_69abe4090f5461.53895141.jpg', '2026-03-07 05:08:18', NULL),
(3, 'asdf', NULL, 'babiballen@gmail.com', 0, NULL, '$2y$10$tZJ9DBLc566Pyj25yR1GDeG5x.I9tpWfcGInVwA7UloAK3Iwf09L6', 'student', 'ICT', 'Baguio City', NULL, NULL, '2026-03-07 08:55:55', NULL),
(4, 'asd', NULL, 'asdf@gmail.com', 0, NULL, '$2y$10$hfyp42YbGXYb.4QICRn/QOUbqufz5UvLp1uIZZ2Kh4afTMtV00e66', 'student', 'ICT', 'Baguio City', NULL, NULL, '2026-03-07 09:09:49', NULL),
(5, 'ian', NULL, 'dlmykyne@gmail.com', 0, NULL, '$2y$10$9EiLRkR35IJhxewoQd2/D.otF8UkcwEMOhCcgpKTSK2ocNBQefiHK', 'student', 'STEM', 'Baguio City', NULL, 'uploads/profile_pics/u5_1772876956_69abf49c62d3a2.60943525.jpg', '2026-03-07 09:48:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_skills`
--

CREATE TABLE `user_skills` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `skill_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_skills`
--

INSERT INTO `user_skills` (`user_id`, `skill_id`, `created_at`) VALUES
(1, 1, '2026-03-07 05:03:26'),
(1, 2, '2026-03-07 05:03:26'),
(1, 3, '2026-03-07 05:03:26'),
(2, 1, '2026-03-07 11:44:40'),
(2, 2, '2026-03-07 11:44:40'),
(2, 4, '2026-03-07 11:44:40'),
(3, 1, '2026-03-07 08:55:55'),
(3, 2, '2026-03-07 08:55:55'),
(3, 3, '2026-03-07 08:55:55'),
(4, 1, '2026-03-07 09:09:49'),
(4, 2, '2026-03-07 09:09:49'),
(4, 3, '2026-03-07 09:09:49'),
(5, 1, '2026-03-07 09:48:56'),
(5, 2, '2026-03-07 09:48:56'),
(5, 3, '2026-03-07 09:48:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `idx_applications_job` (`job_id`),
  ADD KEY `idx_applications_student` (`student_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_chat_messages_thread` (`thread_id`,`created_at`),
  ADD KEY `idx_chat_messages_sender` (`sender_id`);

--
-- Indexes for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD PRIMARY KEY (`thread_id`),
  ADD KEY `idx_chat_threads_updated` (`updated_at`);

--
-- Indexes for table `chat_thread_members`
--
ALTER TABLE `chat_thread_members`
  ADD PRIMARY KEY (`thread_id`,`user_id`),
  ADD KEY `idx_ctm_user` (`user_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `idx_comments_post` (`post_id`),
  ADD KEY `idx_comments_user` (`user_id`);

--
-- Indexes for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_verify_user` (`user_id`),
  ADD KEY `idx_email_verify_email` (`email`),
  ADD KEY `idx_email_verify_expires` (`expires_at`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employers_email` (`email`);

--
-- Indexes for table `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`friendship_id`),
  ADD UNIQUE KEY `uq_friendship_pair` (`user_one_id`,`user_two_id`),
  ADD KEY `idx_friendship_user_two` (`user_two_id`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uq_friend_req_pair` (`from_user_id`,`to_user_id`),
  ADD KEY `idx_friend_req_to_status` (`to_user_id`,`status`),
  ADD KEY `idx_friend_req_from_status` (`from_user_id`,`status`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_jobs_employer` (`employer_id`),
  ADD KEY `idx_jobs_created_at` (`created_at`);

--
-- Indexes for table `job_seekers`
--
ALTER TABLE `job_seekers`
  ADD PRIMARY KEY (`seeker_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD UNIQUE KEY `uq_notifications_external` (`user_id`,`external_key`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `idx_posts_user` (`user_id`),
  ADD KEY `idx_posts_created_at` (`created_at`);

--
-- Indexes for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD KEY `seeker_id` (`seeker_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `uq_skills_name` (`skill_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- Indexes for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`user_id`,`skill_id`),
  ADD KEY `idx_user_skills_skill` (`skill_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_threads`
--
ALTER TABLE `chat_threads`
  MODIFY `thread_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `friendship_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_seekers`
--
ALTER TABLE `job_seekers`
  MODIFY `seeker_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=695;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_msg_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads` (`thread_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_thread_members`
--
ALTER TABLE `chat_thread_members`
  ADD CONSTRAINT `fk_ctm_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads` (`thread_id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD CONSTRAINT `fk_email_verify_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `fk_friendships_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friendships_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `fk_friend_req_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friend_req_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  ADD CONSTRAINT `seeker_skills_ibfk_1` FOREIGN KEY (`seeker_id`) REFERENCES `job_seekers` (`seeker_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD CONSTRAINT `fk_user_skills_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_skills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
