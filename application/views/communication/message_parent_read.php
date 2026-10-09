<style>
.parent-thread{background:#eef2f6;border-radius:18px;overflow:hidden;min-height:calc(100vh - 190px)}
.parent-thread-head{background:#fff;padding:14px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px}.parent-thread-body{padding:14px;max-height:calc(100vh - 285px);overflow-y:auto;display:flex;flex-direction:column;gap:10px}
.parent-bubble{max-width:86%;padding:11px 14px;border-radius:16px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);align-self:flex-start}.parent-bubble.me{align-self:flex-end;background:#dff3ff}.parent-bubble .meta{font-size:10px;color:#7b8794;margin-top:6px}.parent-thread-compose{background:#fff;padding:10px;border-top:1px solid #e5e7eb}.parent-thread-compose textarea{resize:none;border-radius:14px}.parent-thread-compose .btn{border-radius:14px}
@media(max-width:767px){.parent-thread{border-radius:0;margin:0 -15px}.parent-thread-body{max-height:calc(100vh - 235px);padding:10px}.parent-bubble{max-width:92%}}
</style>
<?php
$message = $this->db->where('id',$message_id)->get('message')->row();
if (!$message) { redirect(base_url('communication/mailbox/inbox')); exit; }
$activeUser = $active_user;
$sender = explode('-', $message->sender); $receiver = explode('-', $message->reciever);
$other = $message->sender === $activeUser ? $receiver : $sender;
$otherPerson = $this->application_model->getUserNameByRoleID($other[0],$other[1]);
$replies = $this->db->where('message_id',$message_id)->order_by('created_at','DESC')->get('message_reply')->result();
?>
<div class="parent-thread">
  <div class="parent-thread-head"><a href="<?=base_url('communication/mailbox/inbox')?>" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i></a><div><strong><?=html_escape($otherPerson['name'])?></strong><div class="text-muted small"><?=html_escape($message->subject)?></div></div></div>
  <div class="parent-thread-body">
    <?php
    $items = array(array('body'=>$message->body,'created_at'=>$message->created_at,'is_me'=>($message->sender === $activeUser)));
    foreach($replies as $reply) {
        $isMe = (($reply->identity == 1 && $message->sender === $activeUser) || ($reply->identity == 0 && $message->reciever === $activeUser));
        $items[] = array('body'=>$reply->body,'created_at'=>$reply->created_at,'is_me'=>$isMe);
    }
    usort($items,function($a,$b){ return strtotime($b['created_at']) <=> strtotime($a['created_at']); });
    foreach($items as $item):
    ?>
      <div class="parent-bubble <?= $item['is_me']?'me':'' ?>"><div><?=$item['body']?></div><div class="meta"><?=date('d M Y, g:i A',strtotime($item['created_at']))?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="parent-thread-compose">
    <?php echo form_open_multipart('communication/message_reply',array('class'=>'frm-submit-data')); echo form_hidden(array('user_identity'=>$message->sender===$activeUser?'sender':'reciever','message_id'=>$message_id)); ?>
      <div class="input-group"><textarea name="message" class="form-control" rows="2" placeholder="Write a message..." required></textarea><span class="input-group-btn"><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i></button></span></div>
    <?php echo form_close(); ?>
  </div>
</div>
<script>$(function(){var box=document.querySelector('.parent-thread-body');if(box){box.scrollTop=0;}});</script>
