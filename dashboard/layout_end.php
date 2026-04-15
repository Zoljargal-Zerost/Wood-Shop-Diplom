</div><!-- /content -->
</div><!-- /main-wrap -->

<script>
// Mobile sidebar toggle
var sidebar = document.getElementById('sidebar');
document.addEventListener('click', function(e) {
  if (e.target.closest('#sidebar-toggle')) {
    sidebar.classList.toggle('open');
  } else if (!e.target.closest('#sidebar') && window.innerWidth < 769) {
    sidebar.classList.remove('open');
  }
});

// Modal helpers
function openModal(id) {
  var el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  var el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.querySelectorAll('.modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) {
    if (e.target === o) closeModal(o.id);
  });
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(o) { closeModal(o.id); });
});

// Chat unread polling — sidebar + topbar тоолуур
(function() {
  function checkUnread() {
    fetch('/Wood-shop/chat.php?act=unread')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var cnt = (data.ok && data.unread > 0) ? data.unread : 0;
      ['sidebar-chat-badge','topbar-chat-badge'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (cnt > 0) { el.textContent = cnt; el.style.display = 'inline-block'; }
        else { el.style.display = 'none'; }
      });
    }).catch(function(){});
  }
  checkUnread();
  setInterval(checkUnread, 5000);
})();
</script>
</body>
</html>