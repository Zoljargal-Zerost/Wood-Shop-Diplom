/* ═══════════════════════════════════════
   main.js — Модны Зах
═══════════════════════════════════════ */

/* ── 1. Navbar: scroll болоход өнгө нэмэх ── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', function () {
  if (window.scrollY > 60) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});


/* ── 2. Navbar: идэвхтэй холбоос тодруулах ── */
const sections = document.querySelectorAll('section[id]');
const navLinks = document.querySelectorAll('.nav-link');

window.addEventListener('scroll', function () {
  let current = '';
  sections.forEach(function (sec) {
    if (window.scrollY >= sec.offsetTop - 100) {
      current = sec.getAttribute('id');
    }
  });
  navLinks.forEach(function (link) {
    link.classList.remove('active');
    if (link.getAttribute('href') === '#' + current) {
      link.classList.add('active');
    }
  });
});


/* ── 3. Hamburger (mobile) ── */
const hamburger  = document.getElementById('hamburger');
const navLinksEl = document.querySelector('.nav-links');

if (hamburger) {
  hamburger.addEventListener('click', function () {
    navLinksEl.classList.toggle('mobile-open');
  });
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.nav-inner')) {
    navLinksEl.classList.remove('mobile-open');
  }
});


/* ── 4. Modal нээх / хаах ── */
function openModal(id) {
  var overlay = document.getElementById(id);
  if (overlay) overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  var overlay = document.getElementById(id);
  if (overlay) overlay.classList.remove('open');
  document.body.style.overflow = '';
}

function switchModal(closeId, openId) {
  closeModal(closeId);
  openModal(openId);
}

document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
      m.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
});


/* ── 5. Захиалгын modal — бүтээгдэхүүн урьдчилан сонгох ── */
function openOrderModal(productName) {
  openModal('order-modal');
  var sel = document.getElementById('order-product');
  if (sel && productName) {
    sel.value = productName;
  }
}


/* ── 6. Бүтээгдэхүүн шүүлтүүр ── */
var filterBtns   = document.querySelectorAll('.filter-btn');
var productCards = document.querySelectorAll('.product-card');

filterBtns.forEach(function (btn) {
  btn.addEventListener('click', function () {
    filterBtns.forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var filter = btn.getAttribute('data-filter');
    productCards.forEach(function (card) {
      if (filter === 'all' || card.getAttribute('data-type') === filter) {
        card.classList.remove('hidden');
      } else {
        card.classList.add('hidden');
      }
    });
  });
});


/* ── 7. Scroll reveal анимейшн ── */
var reveals = document.querySelectorAll('.product-card, .location-card, .value-card, .contact-card, .section-header');
reveals.forEach(function (el) { el.classList.add('reveal'); });

var revealObserver = new IntersectionObserver(function (entries) {
  entries.forEach(function (entry) {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

reveals.forEach(function (el) { revealObserver.observe(el); });


/* ── 8. Chat widget ── */
function toggleChat() {
  var box = document.getElementById('chat-box');
  box.classList.toggle('open');
}

var chatBotReplies = [
  'Тийм ээ, тантай удахгүй холбогдоно. Утас: 9446-9149 🌲',
  'Захиалга хийхийн тулд Нэвтрэх товч дарна уу.',
  'Манай дэлгүүрт Нарс, Хус, Хар мод болон бусад мод байдаг.',
  'Хүргэлт боломжтой. Дэлгэрэнгүйг 9446-9149 дугаараас лавлана уу.',
  'Ажлын цаг: Да–Ба 09:00–18:00, Бямба 10:00–16:00.',
];
var replyIndex = 0;

function sendChat() {
  var input    = document.getElementById('chat-input');
  var messages = document.getElementById('chat-messages');
  var text     = input.value.trim();
  if (!text) return;

  var userMsg = document.createElement('div');
  userMsg.className   = 'chat-msg user';
  userMsg.textContent = text;
  messages.appendChild(userMsg);
  input.value = '';

  setTimeout(function () {
    var botMsg = document.createElement('div');
    botMsg.className   = 'chat-msg bot';
    botMsg.textContent = chatBotReplies[replyIndex % chatBotReplies.length];
    replyIndex++;
    messages.appendChild(botMsg);
    messages.scrollTop = messages.scrollHeight;
  }, 600);

  messages.scrollTop = messages.scrollHeight;
}


/* ── 9. Toast notification ── */
function showToast(message, type, icon) {
  var toast  = document.getElementById('toast');
  var msgEl  = document.getElementById('toast-msg');
  var iconEl = document.getElementById('toast-icon');
  if (!toast) return;

  // Өнгө төрлөөр icon автоматаар сонгох
  if (!icon) {
    if (type === 'success') icon = '✅';
    else if (type === 'error') icon = '❌';
    else icon = 'ℹ️';
  }

  msgEl.textContent  = message;
  iconEl.textContent = icon;

  // Өмнөх toast хаах
  toast.classList.remove('show', 'success', 'error', 'info');

  // Дараагийн frame-д нэмэх (CSS transition ажиллахын тулд)
  setTimeout(function () {
    toast.className = 'toast ' + type + ' show';
  }, 10);

  // 3.5 секундын дараа алга болох
  clearTimeout(toast._timer);
  toast._timer = setTimeout(function () {
    toast.classList.remove('show');
  }, 3500);
}


/* ── 10. Хуудас ачаалахад: hash modal + server toast ── */
(function () {
  // Hash байвал modal нээх
  var hash = window.location.hash;
  if (hash === '#login-modal' || hash === '#register-modal' || hash === '#otp-modal' || hash === '#forgot-modal' || hash === '#reset-modal') {
    openModal(hash.replace('#', ''));
    history.replaceState(null, '', window.location.pathname);
  }

  // PHP-с ирсэн toast мессеж харуулах
  var toastEl = document.getElementById('toast-server-msg');
  if (toastEl) {
    var msg  = toastEl.dataset.msg;
    var type = toastEl.dataset.type  || 'info';
    var icon = toastEl.dataset.icon  || '';
    if (msg) {
      // Жижиг хоцролттой харуулах (хуудас бүрэн ачаалагдсаны дараа)
      setTimeout(function () {
        showToast(msg, type, icon);
      }, 300);
    }
  }
})();


/* ── 11. Password validation — бүртгэлийн modal ── */
var regPwd     = document.getElementById('reg-password');
var regConfirm = document.getElementById('reg-password-confirm');
var matchMsg   = document.getElementById('pwd-match-msg');

function checkRule(id, ok) {
  var el = document.getElementById(id);
  if (!el) return;
  var text = el.textContent.slice(2);
  el.textContent = (ok ? '✓ ' : '✗ ') + text;
  el.classList.toggle('ok', ok);
}

if (regPwd) {
  regPwd.addEventListener('input', function () {
    var v = regPwd.value;
    checkRule('rule-len', v.length >= 8);
    checkRule('rule-upp', /[A-Z]/.test(v));
    checkRule('rule-num', /[0-9]/.test(v));
  });
}

if (regConfirm) {
  regConfirm.addEventListener('input', function () {
    if (!regConfirm.value) {
      matchMsg.textContent = '';
      matchMsg.className   = 'pwd-match-msg';
    } else if (regConfirm.value === regPwd.value) {
      matchMsg.textContent = '✓ Нууц үг таарч байна';
      matchMsg.className   = 'pwd-match-msg ok';
    } else {
      matchMsg.textContent = '✗ Нууц үг таарахгүй байна';
      matchMsg.className   = 'pwd-match-msg err';
    }
  });
}


/* ── 12. Password validation — reset modal ── */
var resetPwd     = document.getElementById('reset-password');
var resetConfirm = document.getElementById('reset-password-confirm');
var resetMatch   = document.getElementById('reset-match-msg');

if (resetPwd) {
  resetPwd.addEventListener('input', function () {
    var v = resetPwd.value;
    checkRule('reset-rule-len', v.length >= 8);
    checkRule('reset-rule-upp', /[A-Z]/.test(v));
    checkRule('reset-rule-num', /[0-9]/.test(v));
  });
}

if (resetConfirm) {
  resetConfirm.addEventListener('input', function () {
    if (!resetConfirm.value) {
      resetMatch.textContent = '';
      resetMatch.className   = 'pwd-match-msg';
    } else if (resetConfirm.value === resetPwd.value) {
      resetMatch.textContent = '✓ Нууц үг таарч байна';
      resetMatch.className   = 'pwd-match-msg ok';
    } else {
      resetMatch.textContent = '✗ Нууц үг таарахгүй байна';
      resetMatch.className   = 'pwd-match-msg err';
    }
  });
}


/* ── 10. Хуудас ачаалахад: hash modal + server toast ── */