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
</script>
</body>
</html>
