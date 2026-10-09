# School Management System – Gradebook & Teacher Assignment Upgrade

This upgrade adds:

1. Up to **two class teachers per class section**.
2. Teacher-only messaging restrictions for **students and parents**.
3. A new **Gradebook** for subject/class/section grading out of 100%.
4. Automatic exam components from the existing **Exam Timetable**.
5. Teacher-created components for the remaining percentage.
6. Automatic collection of existing exam marks into the final subject grade.

## 1. Database update

Run:

`database/gradebook_and_teacher_assignment_upgrade.sql`

against the existing school-management database.

The SQL creates:

- `gradebook_scheme`
- `gradebook_component`
- `gradebook_mark`

### Teacher allocation index check

The original PHP code enforced one class teacher in application validation. The new PHP code allows one or two.

Normally no database change is required. If your installation has a database UNIQUE index covering:

`branch_id + session_id + class_id + section_id`

on `teacher_allocation`, remove that specific UNIQUE index after checking:

```sql
SHOW INDEX FROM teacher_allocation;
```

Do not remove the primary key.

## 2. Two class teachers

Go to:

**Classes → Assign Class Teacher**

The teacher selector is now multi-select.

- Minimum: 1 teacher
- Maximum: 2 teachers
- The same teacher cannot be assigned twice
- Existing one-teacher assignments continue to work

The assignment is still stored as separate rows in `teacher_allocation`, so existing code that checks class-teacher assignments remains compatible.

## 3. Teacher messaging restrictions

When the logged-in user is a teacher:

- Students are limited to their assigned class sections.
- Parents are limited to parents of students in their assigned class sections.
- The restriction is enforced in the backend, not only in the dropdown.
- A teacher cannot bypass the restriction by manually posting another student's/parent's ID.
- A teacher can be assigned through `teacher_allocation` or `subject_assign`.

Admins and other existing roles keep their previous messaging behavior.

## 4. Gradebook

A new:

**Marks → Gradebook**

menu item is available to users with the existing `exam_mark` permission.

For teachers, only subjects assigned to that teacher are shown.

### Exam components

The Gradebook automatically reads the existing `timetable_exam` records for the selected:

- branch
- session
- class
- section
- subject

The full marks configured in each scheduled exam become the exam component weight.

Example:

- Mid Exam = 30
- Final Exam = 40

The Gradebook automatically creates:

- Mid Exam — 30%
- Final Exam — 40%

Remaining percentage:

- 30%

### Teacher components

The subject teacher can add components such as:

- Class Work — 10%
- Assignment — 10%
- Project — 10%

The system never allows the component total to exceed 100%.

Student grades cannot be saved until the complete grading scheme equals exactly 100%.

### Exam marks

Exam components are read-only in the Gradebook.

The teacher continues entering exam marks through the existing **Exam → Mark Entries** feature.

The Gradebook then automatically reads those marks.

For example, if the Mid Exam is worth 30 and the student receives 24/30, the Gradebook contributes 24 points to the student's final 100-point subject grade.

## 5. Important workflow

Recommended workflow:

1. Create the exam.
2. Add the exam to the class/section in **Exam Timetable**.
3. Set the subject's exam full marks.
4. Open **Gradebook** for that subject.
5. The exam components appear automatically.
6. Add teacher components for the remaining percentage.
7. Enter exam marks through the existing Exam Mark Entry page.
8. Enter Class Work/Assignment/etc. in Gradebook.
9. Save student grades.

### Example

| Component | Weight |
|---|---:|
| Mid Exam | 30% |
| Final Exam | 40% |
| Class Work | 10% |
| Assignment | 10% |
| Project | 10% |
| **Total** | **100%** |

The final grade is:

`Mid Exam score + Final Exam score + Class Work + Assignment + Project`

and is always out of **100**.

## Existing functionality preserved

The existing exam, exam timetable, exam mark entry, grade range, report card, student enrollment, subject assignment, and messaging systems are not replaced.

The new Gradebook reads the existing exam data rather than creating a second exam system.

## Term / Semester Grading, Ranking and Exports

Run `database/gradebook_terms_ranking_upgrade.sql` after the original gradebook migration.

### Academic terms
Super Admin can open **Marks → Academic Terms / Semesters** and define any number of grading periods for the active academic session (1–20). Typical examples are 2 semesters, 3 terms, or 4 terms.

Each active term is independently graded out of **100**.

### Exam integration
The Exam create/edit form now has an **Academic Term / Semester** field. Exams scheduled for a class/subject are automatically collected into that term's Gradebook. Their full marks become automatic grading components. Existing exams are also supported when their old Exam Term name matches an active Gradebook term name.

### Term Gradebook
The Gradebook is now term-specific. A separate grading scheme is maintained for every:

`Academic Session + Term + Branch + Class + Section + Subject`

Every term must total exactly 100% before student marks can be saved. School exams occupy their configured marks automatically; the subject teacher defines the remaining percentage.

### Ranking and reports
Open **Marks → Term Results & Ranking**. Select branch, class, section and term. Students are ranked within that class/section by their average across the subjects assigned to that class/section for the selected term. Equal averages receive the same rank.

Available exports:
- Selected term: CSV
- Selected term: PDF
- All active terms together: CSV, including each term's subject scores, term average and annual average
- All active terms together: PDF annual summary with term averages and annual ranking

### Important
The new migration adds `grading_term_id` to `exam` and `term_id` to `gradebook_scheme`. Run the migration once before using the new term features.

## Parent Portal Upgrade

The current build also includes:
- Parent messaging restricted to branch administrators and the active child's assigned class teachers.
- Parent mobile-friendly messenger inbox/thread with newest messages shown first and older messages below.
- Parent term-by-term gradebook results with subject/component breakdowns.
- Parent homework access fixes: parents can view their child's homework/submission information but cannot submit assignments.
- Parent exam-schedule fixes with branch/child authorization on timetable details.


## Weighted exam contribution (follow-up upgrade)

Run `database/exam_gradebook_weight_upgrade.sql` once on an existing database before using the Gradebook Weight (%) field in Exam create/edit. Back up the database first. This migration adds `exam.gradebook_weight`; exams with a weight contribute that percentage to their selected academic term, while the gradebook scales marks from the scheduled exam's raw full marks into that percentage.
