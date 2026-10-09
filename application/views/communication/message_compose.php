<div class="panel">
    <div class="panel-heading">
        <h4 class="panel-title"><?=translate('write_message')?></h4>
    </div>
    <?php $isTeacher = !empty($is_teacher_sender); ?>
    <?php echo form_open_multipart('communication/message_send', array('class' => 'frm-submit-data', 'id' => 'messageComposeForm')); ?>
        <div class="panel-body">
        <?php if (is_superadmin_loggedin()) { ?>
            <div class="form-group">
                <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                <?php
                    $arrayBranch = $this->app_lib->getSelectList('branch');
                    echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' id='branchID' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                ?>
                <span class="error"></span>
            </div>
        <?php } ?>
            <div class="form-group">
                <label class="control-label"><?=translate('role')?> <span class="required">*</span></label>
                <?php
                    $role_list = $this->app_lib->getRoles(1);
                    echo form_dropdown("role_id", $role_list, set_value('role_id'), "class='form-control' data-plugin-selectTwo id='roleID' data-width='100%' data-minimum-results-for-search='Infinity'");
                ?>
                <span class="error"></span>
                <?php if ($isTeacher): ?>
                    <small class="text-muted">For students and parents, only recipients from your assigned class sections are available.</small>
                <?php endif; ?>
            </div>

            <?php if ($isTeacher): ?>
            <div class="form-group class_div" style="display:none">
                <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                <?php
                    $arrayClass = array("" => translate('select'));
                    foreach ($teacher_class_sections as $item) {
                        $className = get_type_name_by_id('class', $item['class_id']);
                        $sectionName = get_type_name_by_id('section', $item['section_id']);
                        $arrayClass[$item['class_id'] . ':' . $item['section_id']] = $className . ' - ' . $sectionName;
                    }
                    echo form_dropdown("class_section", $arrayClass, set_value('class_section'), "class='form-control' id='classSectionID' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                ?>
                <input type="hidden" name="class_id" id="classID">
                <input type="hidden" name="section_id" id="sectionID">
                <span class="error"></span>
            </div>
            <?php else: ?>
            <div class="form-group class_div" style="display:none">
                <label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
                <?php
                    $arrayClass = array("" => translate('select'));
                    echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='messageClassID' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                ?>
                <span class="error"></span>
            </div>
            <div class="form-group section_div" style="display:none">
                <label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
                <?php
                    $arraySection = array("" => translate('select_class_first'));
                    echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='messageSectionID' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                ?>
                <span class="error"></span>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="control-label"><?=translate('receiver')?> <span class="required">*</span></label>
                <?php
                    $arrayUser = array("" => translate('select'));
                    echo form_dropdown("receiver_id", $arrayUser, set_value('receiver_id'), "class='form-control' id='receiverID' data-plugin-selectTwo data-width='100%'");
                ?>
                <span class="error"></span>
            </div>
            <div class="form-group">
                <label class="control-label"><?=translate('subject')?> <span class="required">*</span></label>
                <input id="subject" name="subject" type="text" class="form-control" value="">
                <span class="error"></span>
            </div>
            <div class="form-group">
                <label class="control-label"><?=translate('message')?> <span class="required">*</span></label>
                <textarea name="message_body" class="form-control summernote" id="summernote" rows="10"></textarea>
                <span class="error"></span>
            </div>

            <div class="form-group">
                <label class="control-label">Attachment File</label>
                <div class="col-md-12 row">
                    <div class="fileupload fileupload-new" data-provides="fileupload">
                        <div class="input-append">
                            <div class="uneditable-input">
                                <i class="fas fa-file fileupload-exists"></i>
                                <span class="fileupload-preview"></span>
                            </div>
                            <span class="btn btn-default btn-file">
                                <span class="fileupload-exists">Change</span>
                                <span class="fileupload-new">Select file</span>
                                <input type="file" name="attachment_file" />
                            </span>
                            <a href="#" class="btn btn-default fileupload-exists" data-dismiss="fileupload">Remove</a>
                        </div>
                    </div>
                    <span class="error"></span>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <div class="row">
                <div class="col-md-12">
                    <div class="pull-right">
                        <a href="<?php echo base_url('communication/mailbox/compose') ?>" class="btn btn-default mr-xs"><i class="fas fa-times"></i><span> <?=translate('discard')?></a>
                        <button type="submit" name="submit" value="send" class="btn btn-default" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
                            <i class="fas fa-paper-plane"></i><span> <?=translate('send')?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
$(document).ready(function () {
    var isTeacher = <?= $isTeacher ? 'true' : 'false' ?>;

    function clearRecipients() {
        $('#receiverID').empty().html("<option value=''><?=translate('select_user')?></option>").trigger('change');
    }

    function loadRecipients(roleID) {
        var branchID = $('#branchID').val() || '';
        var classID = '';
        var sectionID = '';

        if (isTeacher) {
            var classSection = $('#classSectionID').val() || '';
            if (classSection) {
                var parts = classSection.split(':');
                classID = parts[0] || '';
                sectionID = parts[1] || '';
                $('#classID').val(classID);
                $('#sectionID').val(sectionID);
            } else {
                $('#classID, #sectionID').val('');
            }
        } else {
            classID = $('#messageClassID').val() || '';
            sectionID = $('#messageSectionID').val() || '';
        }

        if ((roleID == 6 || roleID == 7) && (!classID || !sectionID)) {
            clearRecipients();
            return;
        }

        var url = roleID == 6
            ? base_url + "communication/getParentListBranch"
            : base_url + "communication/getStudentByClass";

        $.ajax({
            url: url,
            type: 'POST',
            data: {branch_id: branchID, class_id: classID, section_id: sectionID},
            success: function (data) {
                $('#receiverID').html(data).trigger('change');
            }
        });
    }

    $(document).on('change', '#branchID', function() {
        $('#roleID').val('').trigger('change.select2');
        clearRecipients();
        if (!isTeacher) {
            $('#messageClassID').html('<option value="">Select</option>').trigger('change.select2');
            $('#messageSectionID').html('<option value="">Select Class First</option>').trigger('change.select2');
            if ($(this).val()) {
                $.ajax({
                    url: base_url + 'ajax/getClassByBranch',
                    type: 'POST',
                    data: {branch_id: $(this).val()},
                    success: function(data){ $('#messageClassID').html(data).trigger('change.select2'); }
                });
            }
        }
    });

    $(document).on('change', '#roleID', function() {
        var roleID = $(this).val();
        if (roleID == 6 || roleID == 7) {
            $('.class_div').show(400);
            if (!isTeacher) $('.section_div').show(400);
            clearRecipients();
            if (!isTeacher && $('#messageClassID').children().length <= 1 && $('#branchID').val()) {
                $.ajax({
                    url: base_url + 'ajax/getClassByBranch', type: 'POST',
                    data: {branch_id: $('#branchID').val()},
                    success: function(data){ $('#messageClassID').html(data).trigger('change.select2'); }
                });
            }
        } else {
            $('.class_div, .section_div').hide(400);
            if (isTeacher) $('#classSectionID').val('').trigger('change.select2');
            else $('#messageClassID, #messageSectionID').val('').trigger('change.select2');
            $('#classID, #sectionID').val('');
            var branchID = $('#branchID').val();
            $.ajax({
                url: base_url + "communication/getStafflistRole", type: 'POST',
                data: {branch_id: branchID, role_id: roleID},
                success: function(data) { $('#receiverID').html(data).trigger('change'); }
            });
        }
    });

    $(document).on('change', '#classSectionID', function() { loadRecipients($('#roleID').val()); });

    $(document).on('change', '#messageClassID', function() {
        var classID = $(this).val();
        $('#messageSectionID').html('<option value="">Select</option>').trigger('change.select2');
        clearRecipients();
        if (!classID) {
            $('#messageSectionID').html('<option value="">Select Class First</option>').trigger('change.select2');
            return;
        }
        $.ajax({
            url: base_url + 'ajax/getSectionByClass', type: 'POST',
            data: {class_id: classID, all: 0, multi: 0},
            success: function(data){ $('#messageSectionID').html(data).trigger('change.select2'); }
        });
    });

    $(document).on('change', '#messageSectionID', function() { loadRecipients($('#roleID').val()); });
});
</script>
