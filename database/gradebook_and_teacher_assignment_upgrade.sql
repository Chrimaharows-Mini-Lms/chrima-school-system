-- Zohar School Management System
-- Gradebook + two class-teacher support
-- Run this once against the EXISTING school-management database.
--
-- The application already stores class teachers as rows in teacher_allocation,
-- so no data migration is required for existing assignments. The application
-- now permits a maximum of two rows for the same class/section/session.

CREATE TABLE IF NOT EXISTS `gradebook_scheme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `session_id` int NOT NULL,
  `class_id` int NOT NULL,
  `section_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `total_mark` decimal(6,2) NOT NULL DEFAULT 100.00,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gradebook_scheme` (`branch_id`,`session_id`,`class_id`,`section_id`,`subject_id`),
  KEY `idx_gradebook_scheme_class` (`branch_id`,`session_id`,`class_id`,`section_id`),
  KEY `idx_gradebook_scheme_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gradebook_component` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scheme_id` int unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `component_type` enum('exam','teacher') NOT NULL DEFAULT 'teacher',
  `exam_id` int DEFAULT NULL,
  `max_mark` decimal(6,2) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 999,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gradebook_exam_component` (`scheme_id`,`exam_id`),
  KEY `idx_gradebook_component_scheme` (`scheme_id`),
  KEY `idx_gradebook_component_exam` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gradebook_mark` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `component_id` int unsigned NOT NULL,
  `student_id` int NOT NULL,
  `raw_mark` decimal(8,2) DEFAULT NULL,
  `is_absent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gradebook_student_component` (`component_id`,`student_id`),
  KEY `idx_gradebook_mark_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IMPORTANT:
-- The original application prevented a second class teacher in PHP validation.
-- The new application validation allows up to TWO teachers.
-- If your database has a UNIQUE index directly on
-- (class_id, section_id, session_id, branch_id) in teacher_allocation,
-- remove ONLY that unique index. Do not remove the primary key.
--
-- Check first:
-- SHOW INDEX FROM teacher_allocation;
--
-- If such a unique index exists, drop that specific index, for example:
-- ALTER TABLE teacher_allocation DROP INDEX `your_actual_unique_index_name`;
--
-- Do not blindly use an index name from another installation because
-- installations can have different index names.
