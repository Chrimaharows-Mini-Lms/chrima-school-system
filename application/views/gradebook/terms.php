<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading"><h4 class="panel-title"><i class="fas fa-calendar-alt"></i> Academic Terms / Semesters</h4></header>
            <?php echo form_open('gradebook/terms', array('class'=>'validate')); ?>
            <div class="panel-body">
                <div class="alert alert-info">
                    The Super Admin controls how many grading periods exist in the selected academic session. Every term is independently graded out of <strong>100</strong>.
                </div>
                <div class="form-group col-md-4">
                    <label>Number of Terms / Semesters <span class="required">*</span></label>
                    <input type="number" min="1" max="20" class="form-control" id="term_count" name="term_count" value="<?=count($terms) ?: 2?>">
                </div>
                <div class="clearfix"></div>
                <div id="term_fields">
                    <?php $count=max(1,count($terms)); for($i=0;$i<$count;$i++): $name=isset($terms[$i]['name'])?$terms[$i]['name']:'Term '.($i+1); ?>
                    <div class="form-group col-md-4 term-field">
                        <label>Term / Semester <?=$i+1?></label>
                        <input type="text" class="form-control" name="term_names[]" value="<?=html_escape($name)?>" placeholder="Term <?=$i+1?>">
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="clearfix"></div>
            </div>
            <footer class="panel-footer text-right"><button class="btn btn-default" type="submit"><i class="fas fa-save"></i> Save Terms</button></footer>
            <?php echo form_close(); ?>
        </section>
    </div>
</div>
<?php if (!empty($terms)): ?>
<div class="row"><div class="col-md-12"><section class="panel"><header class="panel-heading"><h4 class="panel-title">Current Academic Grading Structure</h4></header><div class="panel-body"><div class="row">
<?php foreach($terms as $t): ?><div class="col-md-3"><div class="alert alert-success"><strong><?=$t['term_order']?>. <?=html_escape($t['name'])?></strong><br>Maximum: <strong>100</strong></div></div><?php endforeach; ?>
</div></div></section></div></div>
<?php endif; ?>
<script>
$(function(){
 $('#term_count').on('input change',function(){
   var n=Math.max(1,Math.min(20,parseInt($(this).val()||1))), box=$('#term_fields'), current=box.find('.term-field').length;
   while(current<n){var i=current+1;box.append('<div class="form-group col-md-4 term-field"><label>Term / Semester '+i+'</label><input type="text" class="form-control" name="term_names[]" value="Term '+i+'" placeholder="Term '+i+'"></div>');current++;}
   while(current>n){box.find('.term-field').last().remove();current--;}
 });
});
</script>
