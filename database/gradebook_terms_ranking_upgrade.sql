-- Term-based Gradebook, Ranking and Export upgrade
-- Run AFTER gradebook_and_teacher_assignment_upgrade.sql

CREATE TABLE IF NOT EXISTS `gradebook_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `branch_id` int NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `term_order` int NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gradebook_term_name` (`session_id`,`branch_id`,`name`),
  UNIQUE KEY `uq_gradebook_term_order` (`session_id`,`branch_id`,`term_order`),
  KEY `idx_gradebook_term_session` (`session_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `gradebook_scheme`
  DROP INDEX `uq_gradebook_scheme`;

ALTER TABLE `gradebook_scheme`
  ADD COLUMN `term_id` int unsigned NULL AFTER `session_id`;

ALTER TABLE `gradebook_scheme`
  ADD UNIQUE KEY `uq_gradebook_scheme_term` (`branch_id`,`session_id`,`term_id`,`class_id`,`section_id`,`subject_id`),
  ADD KEY `idx_gradebook_scheme_term` (`term_id`);

ALTER TABLE `exam`
  ADD COLUMN `grading_term_id` int unsigned NULL AFTER `term_id`,
  ADD KEY `idx_exam_grading_term` (`grading_term_id`,`session_id`,`branch_id`);

-- Existing exams remain compatible. If their old exam-term name matches a new
-- Gradebook term name, the application will automatically use that term when
-- calculating the gradebook. New/edited exams can explicitly select a term.
