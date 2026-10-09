-- Run once against the school's existing database after backing it up.
-- Adds an explicit percentage weight for each exam in the academic-term gradebook.
ALTER TABLE `exam`
    ADD COLUMN `gradebook_weight` DECIMAL(6,2) NULL DEFAULT NULL AFTER `grading_term_id`;

ALTER TABLE `exam`
    ADD KEY `idx_exam_gradebook_weight` (`grading_term_id`, `gradebook_weight`, `session_id`, `branch_id`);
