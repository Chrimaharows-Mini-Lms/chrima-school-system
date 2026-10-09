<?php
$isTeacher = (loggedin_role_id() == 3);
$schemeReady = isset($component_total) && abs((float)$component_total - 100) < 0.0001;

function gradebook_grade_for_mark($mark, $ranges) {
    foreach ($ranges as $range) {
        if ($mark >= (float)$range['lower_mark'] && $mark <= (float)$range['upper_mark']) {
            return $range;
        }
    }
    return null;
}
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-chart-line"></i> Gradebook</h4>
            </header>
            <?php echo form_open('gradebook', array('class' => 'validate')); ?>
            <div class="panel-body">
                <?php if ($isTeacher): ?>
                    <div class="form-group">
                        <label class="control-label">Class / Section / Subject / Term <span class="required">*</span></label>
                        <select class="form-control" id="teacher_assignment" data-plugin-selectTwo data-width="100%">
                            <option value="">Select</option>
                            <?php foreach ($teacher_subjects as $item): ?>
                                <option value="<?=$item['class_id']?>:<?=$item['section_id']?>:<?=$item['subject_id']?>"
                                    <?=($class_id == $item['class_id'] && $section_id == $item['section_id'] && $subject_id == $item['subject_id']) ? 'selected' : ''?>>
                                    <?=html_escape($item['class_name'] . ' - ' . $item['section_name'] . ' - ' . $item['subject_name'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="class_id" id="class_id" value="<?=html_escape($class_id)?>">
                        <input type="hidden" name="section_id" id="section_id" value="<?=html_escape($section_id)?>">
                        <input type="hidden" name="subject_id" id="subject_id" value="<?=html_escape($subject_id)?>">
                        <input type="hidden" name="term_id" id="term_id" value="<?=html_escape($term_id)?>">
                        <div class="form-group mt-md">
                            <label class="control-label">Academic Term / Semester <span class="required">*</span></label>
                            <select class="form-control" id="teacher_term" data-plugin-selectTwo data-width="100%">
                                <?php foreach ($terms as $t): ?>
                                    <option value="<?=$t['id']?>" <?=((string)$term_id === (string)$t['id']) ? 'selected' : ''?>><?=html_escape($t['name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php if (is_superadmin_loggedin()): ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label"><?=translate('branch')?></label>
                                <?php
                                $arrayBranch = $this->app_lib->getSelectList('branch');
                                echo form_dropdown('branch_id', $arrayBranch, $branch_id, "class='form-control' id='branch_id' data-plugin-selectTwo data-width='100%'");
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                                <?php
                                $arrayClass = $this->app_lib->getClass($branch_id);
                                echo form_dropdown('class_id', $arrayClass, $class_id, "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo data-width='100%'");
                                ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
                                <?php
                                $arraySection = $this->app_lib->getSections($class_id);
                                echo form_dropdown('section_id', $arraySection, $section_id, "class='form-control' id='section_id' data-plugin-selectTwo data-width='100%'");
                                ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label"><?=translate('subject')?> <span class="required">*</span></label>
                                <?php
                                $arraySubject = array("" => translate('select'));
                                if (!empty($class_id) && !empty($section_id)) {
                                    $query = $this->subject_model->getSubjectByClassSection($class_id, $section_id);
                                    foreach ($query->result_array() as $row) {
                                        $arraySubject[$row['subject_id']] = $row['subjectname'];
                                    }
                                }
                                echo form_dropdown('subject_id', $arraySubject, $subject_id, "class='form-control' id='subject_id' data-plugin-selectTwo data-width='100%'");
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Academic Term / Semester <span class="required">*</span></label>
                                <select class="form-control" name="term_id" id="admin_term_id" data-plugin-selectTwo data-width="100%">
                                    <?php foreach ($terms as $t): ?>
                                        <option value="<?=$t['id']?>" <?=((string)$term_id === (string)$t['id']) ? 'selected' : ''?>><?=html_escape($t['name'])?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <footer class="panel-footer">
                <button type="submit" class="btn btn-default pull-right"><i class="fas fa-filter"></i> <?=translate('filter')?></button>
            </footer>
            <?php echo form_close(); ?>
        </section>
    </div>
</div>

<?php if (isset($scheme)): ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-calculator"></i>
                    <?=html_escape($class_name . ' - ' . $section_name . ' - ' . $subject_name)?>
                    <span class="label label-primary"><?=html_escape($term['name'])?></span>
                </h4>
            </header>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="alert alert-info mb-none">
                            <strong>Term Total:</strong> <?=number_format($component_total, 2)?> / 100%
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert <?=($remaining_mark > 0 ? 'alert-warning' : 'alert-success')?> mb-none">
                            <strong>Remaining:</strong> <?=number_format($remaining_mark, 2)?>%
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-default mb-none">
                            <strong>Term:</strong> <?=html_escape($term['name'])?> · Exam marks are collected automatically
                        </div>
                    </div>
                </div>

                <?php if (!$schemeReady): ?>
                    <div class="alert alert-warning mt-md mb-none">
                        <i class="fas fa-exclamation-triangle"></i>
                        The grading scheme must total exactly <strong>100%</strong> before student grades can be saved.
                        Add teacher components for the remaining <?=number_format($remaining_mark, 2)?>%.
                    </div>
                <?php endif; ?>

                <div class="table-responsive mt-md">
                    <table class="table table-bordered table-hover table-condensed mb-none">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Component</th>
                                <th>Type</th>
                                <th>Weight</th>
                                <th>Exam source</th>
                                <?php if (get_permission('exam_mark', 'is_delete')): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($components)): foreach ($components as $component): ?>
                            <tr>
                                <td><?=$component['sort_order']?></td>
                                <td><?=html_escape($component['name'])?></td>
                                <td>
                                    <?php if ($component['component_type'] === 'exam'): ?>
                                        <span class="label label-info">Automatic Exam</span>
                                    <?php else: ?>
                                        <span class="label label-default">Teacher</span>
                                    <?php endif; ?>
                                </td>
                                <td><?=number_format($component['max_mark'], 2)?>%</td>
                                <td><?=$component['component_type'] === 'exam' ? 'Existing exam marks' : 'Teacher entered'?></td>
                                <?php if (get_permission('exam_mark', 'is_delete')): ?>
                                <td>
                                    <?php if ($component['component_type'] === 'teacher'): ?>
                                        <?=btn_delete('gradebook/delete_component/' . $component['id'])?>
                                    <?php else: ?>
                                        <span class="text-muted">Automatic</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">No grading components yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($remaining_mark > 0): ?>
                <hr>
                <h5><i class="fas fa-plus-circle"></i> Add teacher grading component</h5>
                <?php echo form_open('gradebook/save_component', array('class' => 'frm-submit')); ?>
                    <input type="hidden" name="branch_id" value="<?=$branch_id?>">
                    <input type="hidden" name="class_id" value="<?=$class_id?>">
                    <input type="hidden" name="section_id" value="<?=$section_id?>">
                    <input type="hidden" name="subject_id" value="<?=$subject_id?>">
                    <input type="hidden" name="term_id" value="<?=$term_id?>">
                    <div class="row">
                        <div class="col-md-5">
                            <input type="text" name="name" class="form-control" placeholder="e.g. Class Work, Assignment, Project">
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="max_mark" class="form-control" min="0.01" max="<?=html_escape($remaining_mark)?>" step="0.01" placeholder="Percentage / marks">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-default"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </div>
                    <small class="text-muted">This percentage comes from the remaining <?=number_format($remaining_mark, 2)?>% only.</small>
                <?php echo form_close(); ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php if (!empty($students)): ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-users"></i> Student Grades</h4>
            </header>
            <?php echo form_open('gradebook/save_marks', array('class' => 'frm-submit-msg')); ?>
            <input type="hidden" name="branch_id" value="<?=$branch_id?>">
            <input type="hidden" name="class_id" value="<?=$class_id?>">
            <input type="hidden" name="section_id" value="<?=$section_id?>">
            <input type="hidden" name="subject_id" value="<?=$subject_id?>">
            <input type="hidden" name="term_id" value="<?=$term_id?>">
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover mb-none">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <?php foreach ($components as $component): ?>
                                    <th>
                                        <?=html_escape($component['name'])?><br>
                                        <small>/ <?=number_format($component['max_mark'], 2)?></small>
                                    </th>
                                <?php endforeach; ?>
                                <th>Total / 100</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $idx => $student): 
                            $studentID = $student['student_id'];
                            $total = 0;
                        ?>
                            <tr>
                                <td><?=$idx + 1?></td>
                                <td>
                                    <strong><?=html_escape($student['first_name'] . ' ' . $student['last_name'])?></strong><br>
                                    <small><?=html_escape($student['register_no'])?></small>
                                </td>
                                <?php foreach ($components as $component):
                                    $value = 0;
                                    $isExam = ($component['component_type'] === 'exam');
                                    $absent = false;
                                    if ($isExam) {
                                        $examInfo = isset($exam_scores[$component['id']][$studentID]) ? $exam_scores[$component['id']][$studentID] : array('score' => 0, 'absent' => false, 'entered' => false);
                                        $value = (float)$examInfo['score'];
                                        $absent = !empty($examInfo['absent']);
                                    } elseif (isset($teacher_marks[$component['id']][$studentID])) {
                                        $value = (float)$teacher_marks[$component['id']][$studentID]['raw_mark'];
                                    }
                                    $total += $value;
                                ?>
                                    <td class="min-w-sm">
                                    <?php if ($isExam): ?>
                                        <span class="label <?=($absent ? 'label-danger' : 'label-info')?>"><?=($absent ? 'Absent' : number_format($value, 2))?></span>
                                    <?php else: ?>
                                        <input type="number" class="form-control" name="marks[<?=$studentID?>][<?=$component['id']?>]"
                                            min="0" max="<?=html_escape($component['max_mark'])?>" step="0.01"
                                            value="<?=isset($teacher_marks[$component['id']][$studentID]) ? html_escape($teacher_marks[$component['id']][$studentID]['raw_mark']) : ''?>">
                                    <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php $grade = gradebook_grade_for_mark($total, $grade_ranges); ?>
                                <td><strong><?=number_format($total, 2)?></strong></td>
                                <td><?=!empty($grade) ? html_escape($grade['name']) : '<span class="text-muted">-</span>'?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$schemeReady): ?>
                    <div class="alert alert-warning mt-md mb-none">
                        Complete the grading components to 100% before saving student grades.
                    </div>
                <?php endif; ?>
            </div>
            <footer class="panel-footer">
                <button type="submit" class="btn btn-default pull-right" <?=(!$schemeReady ? 'disabled' : '')?>>
                    <i class="fas fa-save"></i> Save Student Grades
                </button>
            </footer>
            <?php echo form_close(); ?>
        </section>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
$(document).ready(function () {
    $('#teacher_assignment').on('change', function () {
        var parts = ($(this).val() || '').split(':');
        if (parts.length === 3) {
            window.location = base_url + 'gradebook?class_id=' + parts[0] + '&section_id=' + parts[1] + '&subject_id=' + parts[2] + '&term_id=' + ($('#teacher_term').val() || '');
        }
    });

    $('#teacher_term').on('change', function () {
        $('#term_id').val($(this).val());
        var assignment = $('#teacher_assignment').val() || '';
        var parts = assignment.split(':');
        if (parts.length === 3) window.location = base_url + 'gradebook?class_id=' + parts[0] + '&section_id=' + parts[1] + '&subject_id=' + parts[2] + '&term_id=' + $(this).val();
    });

    $('#admin_term_id').on('change', function () { $('#term_id').val($(this).val()); });

    $('#branch_id').on('change', function () {
        getClassByBranch($(this).val());
        $('#subject_id').html('<option value="">Select</option>').trigger('change');
    });

    $('#section_id').on('change', function () {
        var classID = $('#class_id').val();
        var sectionID = $(this).val();
        $.ajax({
            url: base_url + 'subject/getByClassSection',
            type: 'POST',
            data: {classID: classID, sectionID: sectionID},
            success: function (data) {
                $('#subject_id').html(data).trigger('change');
            }
        });
    });
});
</script>
