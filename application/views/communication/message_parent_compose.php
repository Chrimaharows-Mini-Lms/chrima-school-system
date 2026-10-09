<div class="panel parent-chat-compose">
    <div class="panel-heading">
        <h4 class="panel-title"><i class="fas fa-paper-plane"></i> <?=translate('write_message')?></h4>
    </div>
    <?php echo form_open_multipart('communication/message_send', array('class' => 'frm-submit-data')); ?>
    <div class="panel-body">
        <?php if (empty($parent_message_recipients)): ?>
            <div class="alert alert-warning">No administrator or assigned class teacher is currently available for this child.</div>
        <?php else: ?>
            <div class="form-group">
                <label class="control-label">Send to <span class="required">*</span></label>
                <select name="recipient_key" id="parentRecipientKey" class="form-control" data-plugin-selectTwo required>
                    <option value="">Select recipient</option>
                    <?php foreach ($parent_message_recipients as $recipient): ?>
                        <option value="<?=$recipient['role_id']?>:<?=$recipient['user_id']?>" data-role="<?=$recipient['role_id']?>" data-user="<?=$recipient['user_id']?>">
                            <?=html_escape($recipient['name'])?> — <?=html_escape($recipient['type'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="role_id" id="parentRoleID">
                <input type="hidden" name="receiver_id" id="parentReceiverID">
                <span class="error"></span>
            </div>
            <div class="form-group">
                <label class="control-label">Subject <span class="required">*</span></label>
                <input name="subject" class="form-control" required>
                <span class="error"></span>
            </div>
            <div class="form-group">
                <label class="control-label">Message <span class="required">*</span></label>
                <textarea name="message_body" class="form-control summernote" rows="8" required></textarea>
                <span class="error"></span>
            </div>
            <div class="form-group">
                <label class="control-label">Attachment</label>
                <input type="file" name="attachment_file" class="form-control">
                <span class="error"></span>
            </div>
        <?php endif; ?>
    </div>
    <div class="panel-footer text-right">
        <a href="<?=base_url('communication/mailbox/inbox')?>" class="btn btn-default mr-sm">Cancel</a>
        <?php if (!empty($parent_message_recipients)): ?><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button><?php endif; ?>
    </div>
    <?php echo form_close(); ?>
</div>
<script>
$(function(){
    $('#parentRecipientKey').on('change', function(){
        var option = $(this).find(':selected');
        $('#parentRoleID').val(option.data('role') || '');
        $('#parentReceiverID').val(option.data('user') || '');
    });
});
</script>
<style>
@media (max-width: 767px) {
    .parent-chat-compose .panel-body { padding: 16px; }
    .parent-chat-compose .form-control { min-height: 46px; font-size: 16px; }
}
</style>
