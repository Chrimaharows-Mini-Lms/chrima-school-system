<style>
.parent-grade-card{border-radius:16px;overflow:hidden}.grade-subject{border:1px solid #e8edf1;border-radius:12px;margin-bottom:12px;background:#fff}.grade-subject-head{padding:13px 15px;display:flex;justify-content:space-between;align-items:center;background:#f8fafb}.grade-component{padding:8px 15px;border-top:1px solid #f0f2f4;font-size:13px}.grade-score{font-size:20px;font-weight:700}.grade-term-title{margin:0}
@media(max-width:767px){.grade-subject-head{align-items:flex-start}.grade-component{padding:9px 12px}.parent-grade-card{margin-left:-5px;margin-right:-5px}}
</style>
<div class="row">
  <div class="col-md-12">
    <section class="panel parent-grade-card">
      <header class="panel-heading"><h4 class="panel-title"><i class="fas fa-chart-line"></i> Grades &amp; Results</h4></header>
      <div class="panel-body">
        <?php if(empty($grade_reports)): ?><div class="alert alert-info text-center">No grade results are available yet.</div><?php endif; ?>
        <?php foreach($grade_reports as $report): ?>
          <div class="panel panel-default mb-lg">
            <div class="panel-heading"><h4 class="grade-term-title"><strong><?=html_escape($report['term']['name'])?></strong> <small>— <?=html_escape($report['student']['class_name'].' / '.$report['student']['section_name'])?></small></h4></div>
            <div class="panel-body">
              <?php foreach($report['subjects'] as $subject): ?>
                <div class="grade-subject">
                  <div class="grade-subject-head"><strong><?=html_escape($subject['subject_name'])?></strong><span class="grade-score"><?=number_format($subject['score'],2)?> / 100</span></div>
                  <?php if(!empty($subject['components'])): ?>
                    <?php foreach($subject['components'] as $component): ?>
                      <div class="grade-component"><span><?=html_escape($component['name'])?></span><span class="pull-right"><?=number_format($component['score'],2)?> / <?=number_format($component['max_mark'],2)?></span></div>
                    <?php endforeach; ?>
                  <?php else: ?><div class="grade-component text-muted">No assessment components have been recorded for this subject yet.</div><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
