<?php
// ============================================================
//  dashboard/chat.php — Ажилтан/Admin чат хуудас
// ============================================================
require_once __DIR__ . '/../middleware.php';
requireLogin();
loadUserRole($pdo);

$uid      = $_SESSION['user']['id'];
$userName = $_SESSION['user']['ner'];

// Ажилтан/admin/manager л хандана
if (!isRole('worker','admin','manager','director')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$pageTitle  = 'Чат';
$activePage = 'chat';
include __DIR__ . '/layout.php';
?>

<style>
.chat-layout { display: grid; grid-template-columns: 280px 1fr; gap: 0; height: calc(100vh - 120px); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; background: var(--card); }

/* Sidebar — өрөөнүүд */
.rooms-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-alt); }
.rooms-header { padding: 16px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 14px; color: var(--primary); display: flex; justify-content: space-between; align-items: center; }
.rooms-list { flex: 1; overflow-y: auto; }
.room-item { padding: 14px 16px; cursor: pointer; border-bottom: 1px solid var(--border); transition: background 0.15s; display: flex; gap: 10px; align-items: center; position: relative; }
.room-item:hover { background: var(--accent-lt); }
.room-item:hover .room-delete { display: flex; }
.room-item.active { background: var(--accent-lt); border-left: 3px solid var(--accent); }
.room-delete {
  display: none;
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  width: 24px; height: 24px; border-radius: 6px;
  background: #FCEBEB; color: #A32D2D;
  border: none; cursor: pointer; font-size: 13px;
  align-items: center; justify-content: center;
  transition: background 0.15s;
  z-index: 2;
}
.room-delete:hover { background: #F7C1C1; }
.room-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.room-info { flex: 1; min-width: 0; }
.room-name { font-weight: 600; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.room-last { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.room-unread { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.no-rooms { padding: 32px 16px; text-align: center; color: var(--muted); font-size: 13px; }

/* Main chat area */
.chat-main { display: flex; flex-direction: column; }
.chat-topbar { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; background: var(--bg-alt); flex-shrink: 0; }
.chat-topbar-dot { width: 8px; height: 8px; border-radius: 50%; background: #27AE60; }
.chat-topbar-name { font-weight: 700; font-size: 15px; color: var(--primary); }
.chat-topbar-sub { font-size: 12px; color: var(--muted); }

.chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; background: var(--bg); }

.msg-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-row.mine { flex-direction: row-reverse; }
.msg-avatar-sm { width: 28px; height: 28px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.msg-row.mine .msg-avatar-sm { background: var(--primary); }
.msg-bubble { max-width: 68%; min-width: 60px; padding: 9px 13px; border-radius: 14px; font-size: 13px; line-height: 1.6; background: var(--card); color: var(--text); border-bottom-left-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); word-break: break-word; overflow-wrap: break-word; display: inline-block; }
.msg-row.mine .msg-bubble { background: var(--primary); color: #fff; border-bottom-left-radius: 14px; border-bottom-right-radius: 3px; }
.msg-time { font-size: 10px; color: var(--muted); margin-top: 3px; }
.msg-row.mine .msg-time { text-align: right; }

.chat-input-area { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 10px; align-items: flex-end; background: var(--card); flex-shrink: 0; }
.chat-textarea { flex: 1; padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 20px; font-size: 13px; font-family: inherit; background: var(--bg); color: var(--text); outline: none; resize: none; max-height: 80px; line-height: 1.5; transition: border-color 0.2s; }
.chat-textarea:focus { border-color: var(--accent); background: #fff; }
.chat-send-btn { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: #fff; border: none; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s; flex-shrink: 0; }
.chat-send-btn:hover { opacity: 0.88; }
.chat-send-btn:disabled { opacity: 0.4; }

.empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: var(--muted); }
.empty-chat-icon { font-size: 40px; }

/* New chat modal */
.user-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; cursor: pointer; transition: background 0.15s; }
.user-item:hover { background: var(--bg-alt); }
.user-item-name { font-weight: 600; font-size: 14px; }
.user-item-role { font-size: 12px; color: var(--muted); }

@media (max-width: 768px) {
  .chat-layout { grid-template-columns: 1fr; }
  .rooms-sidebar { display: none; }
  .rooms-sidebar.show { display: flex; }
}
</style>

<div class="chat-layout">

  <!-- Sidebar: өрөөнүүд -->
  <div class="rooms-sidebar" id="rooms-sidebar">
    <div class="rooms-header">
      💬 Чатууд
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm" id="btn-active" onclick="showTab('active')"
          style="background:var(--accent);color:#fff;border:none">Идэвхтэй</button>
        <button class="btn btn-sm btn-outline" id="btn-archive" onclick="showTab('archive')">📦 Архив</button>
        <button class="btn btn-primary btn-sm" onclick="openModal('new-chat-modal')">+ Шинэ</button>
      </div>
    </div>
    <div class="rooms-list" id="rooms-list">
      <div class="no-rooms">Ачаалж байна...</div>
    </div>
  </div>

  <!-- Main: чат -->
  <div class="chat-main" id="chat-main">
    <div class="empty-chat" id="empty-chat">
      <div class="empty-chat-icon">💬</div>
      <div style="font-weight:600;color:var(--primary)">Чат сонгоно уу</div>
      <div style="font-size:13px">Зүүн талаас өрөө сонгох эсвэл шинэ чат үүсгэнэ үү</div>
    </div>

    <!-- Chat area (нуугдсан, өрөө сонгосны дараа харагдана) -->
    <div id="active-chat" style="display:none;flex:1;flex-direction:column;height:100%">
      <div class="chat-topbar">
        <div class="chat-topbar-dot"></div>
        <div>
          <div class="chat-topbar-name" id="active-chat-name">—</div>
          <div class="chat-topbar-sub" id="active-chat-sub">—</div>
        </div>
      </div>
      <div class="chat-messages" id="chat-messages"></div>
      <div class="chat-input-area">
        <textarea class="chat-textarea" id="chat-input"
                  placeholder="Мессеж бичих..." rows="1"
                  onkeydown="handleKey(event)"></textarea>
        <button class="chat-send-btn" id="chat-send" onclick="sendMessage()">&#10148;</button>
      </div>
    </div>
  </div>
</div>

<!-- Шинэ чат modal -->
<div class="modal-overlay" id="new-chat-modal">
  <div class="modal-box" style="max-width:420px">
    <button class="modal-close" onclick="closeModal('new-chat-modal')">&times;</button>
    <div class="modal-title">💬 Шинэ чат эхлүүлэх</div>
    <div id="users-list" style="margin-top:8px">
      <div style="color:var(--muted);font-size:13px;padding:12px 0">Ачаалж байна...</div>
    </div>
  </div>
</div>

<script>
var currentRoomId = null;
var lastMsgId     = 0;
var myUid         = <?= (int)$uid ?>;
var pollTimer     = null;

window.addEventListener('load', function() {
  loadRooms();
  loadUsers();
  setInterval(ping, 30000);
  setInterval(function() {
    if (currentTab === 'active') loadRooms();
    if (currentRoomId) fetchMessages();
  }, 3000);
});

function ping() {
  fetch('/Wood-shop/chat.php?act=ping').catch(function(){});
}

function loadRooms() {
  fetch('/Wood-shop/chat.php?act=my_rooms')
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok) return;
    var list = document.getElementById('rooms-list');
    if (data.rooms.length === 0) {
      list.innerHTML = '<div class="no-rooms">Чат байхгүй байна.<br>+ Шинэ дарж эхлүүлнэ үү.</div>';
      return;
    }
    list.innerHTML = '';
    data.rooms.forEach(function(room) {
      // Ажилтан талаас харахад: хэрэглэгчийн нэр + role
      // Хэрэглэгч талаас харахад: ажилтны нэр + role
      var isWorkerSide = (myUid == room.worker_id);
      var name = isWorkerSide
        ? (room.user_name || '?')
        : (room.worker_name || '?');
      var roleName = isWorkerSide
        ? (room.user_role_name || 'Хэрэглэгч')
        : (room.worker_role_name || 'Ажилтан');

      var item = document.createElement('div');
      item.className = 'room-item' + (currentRoomId == room.id ? ' active' : '');
      item.onclick   = function(e) {
        if (e.target.classList.contains('room-delete')) return;
        openRoom(room.id, name, roleName, room.room_type);
      };
      item.innerHTML =
        '<div class="room-avatar">' + (name.charAt(0) || '?') + '</div>' +
        '<div class="room-info">' +
          '<div class="room-name">' + escHtml(name) + '</div>' +
          '<div class="room-last" style="color:var(--accent);font-size:11px;font-weight:600">' + escHtml(roleName) + '</div>' +
        '</div>' +
        (room.unread > 0 ? '<div class="room-unread">' + room.unread + '</div>' : '') +
        '<button class="room-delete" onclick="deleteRoom(' + room.id + ')" title="Архивлах">✕</button>';
      list.appendChild(item);
    });
  });
}

function loadUsers() {
  fetch('/Wood-shop/chat.php?act=online_workers')
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok) return;
    var ul = document.getElementById('users-list');
    if (data.workers.length === 0) {
      ul.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:12px 0">Онлайн ажилтан байхгүй байна</div>';
      return;
    }
    ul.innerHTML = '';
    data.workers.forEach(function(w) {
      if (w.id === myUid) return;
      var item = document.createElement('div');
      item.className = 'user-item';
      item.onclick   = function() { startInternalChat(w.id, w.ner); };
      item.innerHTML =
        '<div class="room-avatar">' + w.ner.charAt(0) + '</div>' +
        '<div><div class="user-item-name">' + escHtml(w.ner) + '</div>' +
        '<div class="user-item-role" style="color:#27AE60">● Онлайн</div></div>';
      ul.appendChild(item);
    });
  });
}

function startInternalChat(targetId, targetName) {
  closeModal('new-chat-modal');
  fetch('/Wood-shop/chat.php?act=get_room', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_type=internal&target_id=' + targetId
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      openRoom(data.room.id, targetName, 'Ажилтан', 'internal');
      loadRooms();
    }
  });
}

function openRoom(roomId, name, roleName, type) {
  currentRoomId = roomId;
  lastMsgId     = 0;

  document.getElementById('empty-chat').style.display    = 'none';
  document.getElementById('active-chat').style.display   = 'flex';
  document.getElementById('active-chat-name').textContent = name;
  document.getElementById('active-chat-sub').textContent  = roleName || '—';

  document.getElementById('chat-messages').innerHTML = '';
  document.getElementById('chat-input').focus();

  // Active room тодруулах
  document.querySelectorAll('.room-item').forEach(function(el) { el.classList.remove('active'); });
  loadRooms();

  fetchMessages();
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(fetchMessages, 3000);
}

function fetchMessages() {
  if (!currentRoomId) return;
  var url = '/Wood-shop/chat.php?act=fetch&room_id=' + currentRoomId + '&after_id=' + lastMsgId;
  fetch(url)
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok && data.messages.length > 0) {
      data.messages.forEach(function(msg) {
        appendMessage(msg);
        if (msg.id > lastMsgId) lastMsgId = msg.id;
      });
      scrollToBottom();
    }
  });
}

function sendMessage() {
  var input = document.getElementById('chat-input');
  var text  = input.value.trim();
  if (!text || !currentRoomId) return;

  var btn = document.getElementById('chat-send');
  btn.disabled = true;
  input.value  = '';
  input.style.height = 'auto';

  fetch('/Wood-shop/chat.php?act=send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_id=' + currentRoomId + '&message=' + encodeURIComponent(text)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    btn.disabled = false;
    if (data.ok) {
      appendMessage(data.message);
      if (data.message.id > lastMsgId) lastMsgId = data.message.id;
      scrollToBottom();
    }
  })
  .catch(function() { btn.disabled = false; });
}

function appendMessage(msg) {
  var wrap = document.getElementById('chat-messages');
  var row  = document.createElement('div');
  row.className = 'msg-row' + (msg.is_mine ? ' mine' : '');
  row.innerHTML =
    '<div class="msg-avatar-sm">' + (msg.sender_name ? msg.sender_name.charAt(0) : '?') + '</div>' +
    '<div><div class="msg-bubble">' + escHtml(msg.message) + '</div>' +
    '<div class="msg-time">' + msg.time + '</div></div>';
  wrap.appendChild(row);
}

function scrollToBottom() {
  var el = document.getElementById('chat-messages');
  el.scrollTop = el.scrollHeight;
}

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

var currentTab = 'active';

function showTab(tab) {
  currentTab = tab;
  document.getElementById('btn-active').style.background  = tab === 'active'  ? 'var(--accent)' : 'transparent';
  document.getElementById('btn-active').style.color       = tab === 'active'  ? '#fff' : 'var(--text)';
  document.getElementById('btn-archive').style.background = tab === 'archive' ? 'var(--accent)' : 'transparent';
  document.getElementById('btn-archive').style.color      = tab === 'archive' ? '#fff' : 'var(--text)';
  if (tab === 'active') loadRooms();
  else loadArchive();
}

function loadArchive() {
  fetch('/Wood-shop/chat.php?act=my_archived')
  .then(function(r) { return r.json(); })
  .then(function(data) {
    var list = document.getElementById('rooms-list');
    if (!data.ok || data.rooms.length === 0) {
      list.innerHTML = '<div class="no-rooms">Архивт чат байхгүй байна.</div>';
      return;
    }
    list.innerHTML = '';
    data.rooms.forEach(function(room) {
      var name = room.user_name || '?';
      var item = document.createElement('div');
      item.className = 'room-item';
      item.style.opacity = '0.7';
      item.innerHTML =
        '<div class="room-avatar" style="background:var(--muted)">' + name.charAt(0) + '</div>' +
        '<div class="room-info">' +
          '<div class="room-name">' + escHtml(name) + '</div>' +
          '<div class="room-last">' + (room.last_msg ? escHtml(room.last_msg.substring(0,30)) : '—') + '</div>' +
        '</div>' +
        '<button class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 8px;flex-shrink:0" ' +
          'onclick="restoreRoom(' + room.id + ')" title="Сэргээх">↩</button>';
      item.querySelector('.room-info').onclick = function() { openRoom(room.id, name, room.room_type); };
      list.appendChild(item);
    });
  });
}

function restoreRoom(roomId) {
  fetch('/Wood-shop/chat.php?act=restore_room', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_id=' + roomId
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) { showTab('active'); }
  });
}

function deleteRoom(roomId) {
  var msg = currentTab === 'archive'
    ? 'Архиваас бүрмөсөн устгах уу?'
    : 'Энэ чатыг архивд оруулах уу?';
  if (!confirm(msg)) return;

  fetch('/Wood-shop/chat.php?act=delete_room', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_id=' + roomId
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      if (currentRoomId === roomId) {
        currentRoomId = null;
        if (pollTimer) clearInterval(pollTimer);
        document.getElementById('active-chat').style.display = 'none';
        document.getElementById('empty-chat').style.display  = 'flex';
      }
      if (currentTab === 'archive') loadArchive();
      else loadRooms();
    }
  });
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.getElementById('chat-input').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 80) + 'px';
});
</script>

<?php include __DIR__ . '/layout_end.php'; ?>