<style>
.parent-messenger{background:#eef2f6;border-radius:18px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.parent-messenger-head{background:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5e7eb}
.parent-chat-list{padding:10px;max-height:calc(100vh - 240px);overflow-y:auto}
.parent-chat-item{display:block;background:#fff;border-radius:14px;padding:13px 14px;margin-bottom:8px;color:#222;text-decoration:none;border:1px solid #edf0f2}
.parent-chat-item:hover{background:#f8fafc;text-decoration:none}.parent-chat-item.unread{border-left:4px solid #0088cc}.parent-chat-time{font-size:11px;color:#8a96a3}.parent-chat-preview{font-size:13px;color:#66717d;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:767px){.parent-messenger{border-radius:0;margin:0 -15px}.parent-chat-list{max-height:calc(100vh - 190px);padding:8px}.parent-chat-item{padding:14px 12px}.parent-messenger-head{padding:12px}}
</style>
<div class="parent-messenger">
  <div class="parent-messenger-head"><strong><i class="far fa-comments"></i> Messages</strong><a href="<?=base_url('communication/mailbox/compose')?>" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i> New</a></div>
  <div class="parent-chat-list">
  <?php
  $activeUser = $active_user;
  $messages = $this->db->where('reciever',$activeUser)->or_where('sender',$activeUser)->order_by('id','DESC')->get('message')->result();
  foreach ($messages as $message):
      $other = $message->sender === $activeUser ? $message->reciever : $message->sender;
      $parts = explode('-', $other); $otherRole = isset($parts[0])?$parts[0]:0; $otherID = isset($parts[1])?$parts[1]:0;
      $person = $this->application_model->getUserNameByRoleID($otherRole,$otherID); $name = !empty($person['name'])?$person['name']:'Unknown';
  ?>
    <a class="parent-chat-item <?=($message->reciever === $activeUser && $message->read_status == 0)?'unread':''?>" href="<?=base_url('communication/mailbox/read?type=inbox&id='.$message->id)?>">
      <div class="row"><div class="col-xs-8"><strong><?=html_escape($name)?></strong></div><div class="col-xs-4 text-right parent-chat-time"><?=get_nicetime($message->created_at)?></div></div>
      <div><strong><?=html_escape($message->subject)?></strong></div>
      <div class="parent-chat-preview"><?=html_escape(strip_tags($message->body))?></div>
    </a>
  <?php endforeach; if (empty($messages)): ?><div class="text-center text-muted p-lg">No messages yet.</div><?php endif; ?>
  </div>
</div>
