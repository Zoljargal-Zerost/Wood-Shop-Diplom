<?php
// ============================================================
//  chat-user.php — Хэрэглэгчийн чат хуудас
//  /Wood-shop/chat-user.php
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: /Wood-shop/?login=1');
    exit;
}

$uid      = $_SESSION['user']['id'];
$userName = $_SESSION['user']['ner'];
?>
<!DOCTYPE html>
<html lang="mn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Чат — Модны Зах</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary:   #5C3D1E;
      --accent:    #C8833A;
      --accent-lt: #F0D5B0;
      --bg:        #F9F5EE;
      --bg-alt:    #F2EDE3;
      --card:      #FFFFFF;
      --border:    #DDD0BC;
      --text:      #2A1A0A;
      --muted:     #7A6248;
    }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); height: 100vh; display: flex; flex-direction: column; }

    /* Navbar */
    .navbar {
      height: 56px; background: var(--primary);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px; flex-shrink: 0;
    }
    .navbar-logo { display: flex; align-items: center; gap: 8px; color: #fff; font-weight: 700; font-size: 16px; text-decoration: none; }
    .navbar-logo svg { opacity: 0.9; }
    .navbar-back { color: rgba(255,255,255,0.8); font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 6px; }
    .navbar-back:hover { color: #fff; }

    /* Chat layout */
    .chat-wrap { flex: 1; display: flex; flex-direction: column; max-width: 700px; width: 100%; margin: 0 auto; padding: 0; }

    /* Status bar */
    .chat-status {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 12px 20px;
      display: flex; align-items: center; gap: 10px;
      flex-shrink: 0;
    }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #ccc; flex-shrink: 0; }
    .status-dot.online { background: #27AE60; }
    .status-info { flex: 1; }
    .status-name { font-weight: 600; font-size: 14px; color: var(--text); }
    .status-label { font-size: 12px; color: var(--muted); }

    /* Messages */
    .chat-messages {
      flex: 1; overflow-y: auto; padding: 20px;
      display: flex; flex-direction: column; gap: 12px;
      background: var(--bg-alt);
    }

    .msg-row { display: flex; gap: 8px; align-items: flex-end; }
    .msg-row.mine { flex-direction: row-reverse; }

    .msg-avatar {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--accent); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; flex-shrink: 0;
    }
    .msg-row.mine .msg-avatar { background: var(--primary); }

    .msg-bubble {
      max-width: 72%;
      min-width: 60px;
      padding: 10px 14px;
      border-radius: 16px; font-size: 14px; line-height: 1.6;
      background: var(--card); color: var(--text);
      border-bottom-left-radius: 4px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
      word-break: break-word;
      overflow-wrap: break-word;
      display: inline-block;
    }
    .msg-row.mine .msg-bubble {
      background: var(--primary); color: #fff;
      border-bottom-left-radius: 16px;
      border-bottom-right-radius: 4px;
    }
    .msg-time { font-size: 10px; color: var(--muted); margin-top: 4px; }
    .msg-row.mine .msg-time { text-align: right; color: rgba(255,255,255,0.6); }

    /* System message */
    .msg-system {
      text-align: center; font-size: 12px; color: var(--muted);
      padding: 6px 16px; background: rgba(255,255,255,0.6);
      border-radius: 20px; align-self: center;
    }

    /* Input */
    .chat-input-area {
      background: var(--card);
      border-top: 1px solid var(--border);
      padding: 12px 16px;
      display: flex; gap: 10px; align-items: flex-end;
      flex-shrink: 0;
    }
    .chat-textarea {
      flex: 1; padding: 10px 14px;
      border: 1.5px solid var(--border); border-radius: 20px;
      font-size: 14px; font-family: inherit; background: var(--bg);
      color: var(--text); outline: none; resize: none;
      max-height: 100px; line-height: 1.5;
      transition: border-color 0.2s;
    }
    .chat-textarea:focus { border-color: var(--accent); background: #fff; }
    .chat-send {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--accent); color: #fff; border: none;
      font-size: 18px; cursor: pointer; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      transition: opacity 0.2s, transform 0.1s;
    }
    .chat-send:hover { opacity: 0.88; transform: scale(1.05); }
    .chat-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

    /* Waiting state */
    .waiting-box {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 16px;
      padding: 40px; text-align: center; background: var(--bg-alt);
    }
    .waiting-icon { font-size: 48px; }
    .waiting-title { font-size: 18px; font-weight: 700; color: var(--primary); }
    .waiting-sub { font-size: 14px; color: var(--muted); line-height: 1.6; }
    .spinner {
      width: 32px; height: 32px; border: 3px solid var(--border);
      border-top-color: var(--accent); border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<nav class="navbar">
  <a href="/Wood-shop/" class="navbar-logo">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L6 10h3l-4 7h5v3h4v-3h5l-4-7h3L12 2z"/></svg>
    Модны Зах
  </a>
  <a href="/Wood-shop/" class="navbar-back">← Нүүр хуудас</a>
</nav>

<div class="chat-wrap">
  <!-- Status -->
  <div class="chat-status" id="chat-status">
    <div class="status-dot" id="status-dot"></div>
    <div class="status-info">
      <div class="status-name" id="status-name">Ажилтан хайж байна...</div>
      <div class="status-label" id="status-label">Холбогдож байна</div>
    </div>
  </div>

  <!-- Messages -->
  <div class="chat-messages" id="chat-messages">
    <div class="waiting-box" id="waiting-box">
      <div class="spinner"></div>
      <div class="waiting-title">Ажилтантай холбогдож байна</div>
      <div class="waiting-sub">Онлайн байгаа ажилтантай таныг холбож байна.<br>Түр хүлээнэ үү...</div>
    </div>
  </div>

  <!-- Input -->
  <div class="chat-input-area">
    <textarea class="chat-textarea" id="chat-input"
              placeholder="Мессеж бичих..." rows="1"
              disabled
              onkeydown="handleKey(event)"></textarea>
    <button class="chat-send" id="chat-send" onclick="sendMessage()" disabled>&#10148;</button>
  </div>
</div>

<script>
var roomId    = null;
var lastMsgId = 0;
var myUid     = <?= (int)$uid ?>;
var myName    = <?= json_encode($userName) ?>;
var pollTimer = null;

// Хуудас нээгдэхэд өрөө авах
window.addEventListener('load', function() {
  getRoom();
  // Ping — онлайн байгааг мэдэгдэх
  setInterval(ping, 30000);
});

function ping() {
  fetch('/Wood-shop/chat.php?act=ping', { method: 'GET' }).catch(function(){});
}

function getRoom() {
  fetch('/Wood-shop/chat.php?act=get_room', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_type=user_worker'
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      roomId = data.room.id;
      // Status bar — нэр + role
      document.getElementById('status-dot').classList.add('online');
      document.getElementById('status-name').textContent = data.room.worker_name || 'Ажилтан';
      document.getElementById('status-label').textContent = data.room.worker_role || 'Ажилтан';
      // Input идэвхжүүлэх
      document.getElementById('chat-input').disabled  = false;
      document.getElementById('chat-send').disabled   = false;
      document.getElementById('chat-input').focus();
      // Waiting box нуух
      document.getElementById('waiting-box').style.display = 'none';
      // Мессежүүд татах
      fetchMessages();
      // Polling эхлэх
      startPolling();
    } else {
      // Онлайн ажилтан байхгүй
      document.getElementById('waiting-box').innerHTML =
        '<div class="waiting-icon">😴</div>' +
        '<div class="waiting-title">Одоогоор онлайн ажилтан байхгүй байна</div>' +
        '<div class="waiting-sub">Ажлын цаг: Да–Ба 09:00–18:00<br>Утас: <strong>9446-9149</strong></div>';
      document.getElementById('status-name').textContent = 'Оффлайн';
      document.getElementById('status-label').textContent = 'Ажилтан байхгүй';
      // 15 секундын дараа дахин оролдох
      setTimeout(getRoom, 15000);
    }
  })
  .catch(function() {
    setTimeout(getRoom, 10000);
  });
}

function fetchMessages() {
  if (!roomId) return;
  var url = '/Wood-shop/chat.php?act=fetch&room_id=' + roomId + '&after_id=' + lastMsgId;
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
  if (!text || !roomId) return;

  var btn = document.getElementById('chat-send');
  btn.disabled = true;
  input.value  = '';
  input.style.height = 'auto';

  fetch('/Wood-shop/chat.php?act=send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'room_id=' + roomId + '&message=' + encodeURIComponent(text)
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

  var row = document.createElement('div');
  row.className = 'msg-row' + (msg.is_mine ? ' mine' : '');

  var initial = msg.sender_name ? msg.sender_name.charAt(0) : '?';
  row.innerHTML =
    '<div class="msg-avatar">' + initial + '</div>' +
    '<div>' +
      '<div class="msg-bubble">' + escHtml(msg.message) + '</div>' +
      '<div class="msg-time">' + msg.time + '</div>' +
    '</div>';

  wrap.appendChild(row);
}

function startPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(fetchMessages, 3000);
}

function scrollToBottom() {
  var el = document.getElementById('chat-messages');
  el.scrollTop = el.scrollHeight;
}

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Textarea auto-resize
document.getElementById('chat-input').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});
</script>
</body>
</html>