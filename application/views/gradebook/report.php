<div class="row">
<div class="col-md-12"><section class="panel"><header class="panel-heading"><h4 class="panel-title"><i class="fas fa-trophy"></i> Term Results & Class Ranking</h4></header>
<?php echo form_open('gradebook/report',array('method'=>'get')); ?><div class="panel-body"><div class="row">
<?php if(is_superadmin_loggedin()): ?><div class="col-md-3"><label>Branch</label><?php echo form_dropdown('branch_id',$this->app_lib->getSelectList('branch'),$branch_id,"class='form-control' id='branch_id' data-plugin-selectTwo"); ?></div><?php endif; ?>
<div class="col-md-3"><label>Class</label><?php echo form_dropdown('class_id',$this->app_lib->getClass($branch_id),$class_id,"class='form-control' id='report_class_id' onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo"); ?></div>
<div class="col-md-3"><label>Section</label><?php echo form_dropdown('section_id',$this->app_lib->getSections($class_id),$section_id,"class='form-control' id='report_section_id' data-plugin-selectTwo"); ?></div>
<div class="col-md-3"><label>Term / Semester</label><select name="term_id" class="form-control" data-plugin-selectTwo><?php foreach($terms as $t): ?><option value="<?=$t['id']?>" <?=((string)$term_id===(string)$t['id'])?'selected':''?>><?=html_escape($t['name'])?></option><?php endforeach; ?></select></div>
</div></div><footer class="panel-footer text-right"><button class="btn btn-default"><i class="fas fa-filter"></i> View Results</button></footer><?php echo form_close(); ?></section></div></div>
<?php if($reportData): $r=$reportData['report']; ?>
<div class="row"><div class="col-md-12"><section class="panel"><header class="panel-heading"><h4 class="panel-title"><?=html_escape($reportData['term']['name'])?> — <?=html_escape(get_type_name_by_id('class',$class_id).' / '.get_type_name_by_id('section',$section_id))?></h4><div class="panel-actions"><a class="btn btn-xs btn-default" href="<?=base_url('gradebook/export_term_csv?branch_id='.$branch_id.'&class_id='.$class_id.'&section_id='.$section_id.'&term_id='.$term_id)?>">CSV</a> <a class="btn btn-xs btn-default" href="<?=base_url('gradebook/export_term_pdf?branch_id='.$branch_id.'&class_id='.$class_id.'&section_id='.$section_id.'&term_id='.$term_id)?>">PDF</a> <a class="btn btn-xs btn-default" target="_blank" href="<?=base_url('gradebook/report?branch_id='.$branch_id.'&class_id='.$class_id.'&section_id='.$section_id.'&term_id='.$term_id)?>">Print</a> <a class="btn btn-xs btn-primary" href="<?=base_url('gradebook/export_annual_csv?branch_id='.$branch_id.'&class_id='.$class_id.'&section_id='.$section_id)?>">All Terms CSV</a> <a class="btn btn-xs btn-primary" href="<?=base_url('gradebook/export_annual_pdf?branch_id='.$branch_id.'&class_id='.$class_id.'&section_id='.$section_id)?>">All Terms PDF</a></div></header>
<div class="panel-body"><div class="table-responsive"><table class="table table-bordered table-hover table-condensed"><thead><tr><th>Rank</th><th>Student</th><?php foreach($r['subjects'] as $s): ?><th><?=html_escape($s['subject_name'])?><br><small>/100</small></th><?php endforeach; ?><th>Total</th><th>Average / 100</th></tr></thead><tbody>
<?php foreach($r['students'] as $row): ?><tr><td><strong><?=$row['rank']?></strong></td><td><?=html_escape($row['student']['first_name'].' '.$row['student']['last_name'])?><br><small><?=html_escape($row['student']['register_no'])?></small></td><?php foreach($r['subjects'] as $s): ?><td><?=number_format((float)$row['scores'][$s['subject_id']],2)?></td><?php endforeach; ?><td><?=number_format($row['total'],2)?></td><td><strong><?=number_format($row['average'],2)?></strong></td></tr><?php endforeach; ?>
</tbody></table></div><div class="alert alert-info mb-none">Ranking is calculated within this class/section for the selected term using the average of all subjects assigned to the class.</div></div></section></div></div>
<?php endif; ?>
<script>
$(function(){
    $('#branch_id').on('change', function(){
        var branchID = $(this).val();
        $.ajax({
            url: base_url + 'ajax/getClassByBranch',
            type: 'POST',
            data: {branch_id: branchID},
            success: function(data){
                $('#report_class_id').html(data).trigger('change.select2');
                $('#report_section_id').html('<option value="">Select Class First</option>').trigger('change.select2');
            }
        });
    });

    $('#report_class_id').on('change', function(){
        var classID = $(this).val();
        if (!classID) {
            $('#report_section_id').html('<option value="">Select Class First</option>').trigger('change.select2');
            return;
        }
        $.ajax({
            url: base_url + 'ajax/getSectionByClass',
            type: 'POST',
            data: {class_id: classID, all: 0, multi: 0},
            success: function(data){
                $('#report_section_id').html(data).trigger('change.select2');
            }
        });
    });
});
</script>
