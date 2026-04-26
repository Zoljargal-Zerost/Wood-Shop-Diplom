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


/* ── 5. Захиалгын cart систем ── */
var CART = [];
var CART_VARIANTS = {};
var CART_CUR_VARIANT = null;
var UNIT_LABELS = {shirheg:'Ширхэгээр', kub:'м³-ээр', bagts:'Багцаар', porter:'Портер-аар'};

function openOrderModal(preselect) {
  openModal('order-modal');
  cartLoadProducts(preselect || null);
}

function cartLoadProducts(preselect) {
  fetch('/Wood-shop/cart_api.php?act=products')
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (!data.ok) return;
    var sel = document.getElementById('cart-type');
    sel.innerHTML = '<option value="">— Сонгох —</option>';
    data.products.forEach(function(p){
      var o = document.createElement('option');
      o.value = p.id; o.textContent = p.emoji + ' ' + p.name;
      if (preselect && p.id == preselect) o.selected = true;
      sel.appendChild(o);
    });
    if (preselect) cartOnTypeChange();
  });
}

function cartOnTypeChange() {
  var pid = document.getElementById('cart-type').value;
  var vsel = document.getElementById('cart-variant');
  vsel.innerHTML = '<option value="">— Хэмжээ сонгох —</option>';
  document.getElementById('cart-qty-row').style.display = 'none';
  document.getElementById('cart-price-preview').style.display = 'none';
  document.getElementById('cart-add-btn').style.display = 'none';
  document.getElementById('cart-hint').textContent = '';
  CART_CUR_VARIANT = null;
  CART_VARIANTS = {};
  if (!pid) return;

  fetch('/Wood-shop/cart_api.php?act=variants&product_id=' + pid)
  .then(function(r){ return r.json(); })
  .then(function(data){
    if (!data.ok) return;
    data.variants.forEach(function(v){
      CART_VARIANTS[v.id] = v;
      var o = document.createElement('option');
      o.value = v.id; o.textContent = v.name;
      vsel.appendChild(o);
    });
  });
}

function cartOnVariantChange() {
  var vid = document.getElementById('cart-variant').value;
  if (!vid) { CART_CUR_VARIANT = null; return; }
  CART_CUR_VARIANT = CART_VARIANTS[vid];
  if (!CART_CUR_VARIANT) return;
  var v = CART_CUR_VARIANT;

  var usel = document.getElementById('cart-unit');
  usel.innerHTML = '';
  if (v.sell_shirheg) usel.innerHTML += '<option value="shirheg">Ширхэгээр</option>';
  if (v.sell_kub)     usel.innerHTML += '<option value="kub">м³-ээр</option>';
  if (v.sell_bagts)   usel.innerHTML += '<option value="bagts">Багцаар</option>';
  if (v.sell_porter)  usel.innerHTML += '<option value="porter">Портераар</option>';

  document.getElementById('cart-qty').value = 1;
  document.getElementById('cart-qty-row').style.display = 'grid';
  document.getElementById('cart-add-btn').style.display = 'block';
  cartCalcPrice();
}

function cartCalcPrice() {
  if (!CART_CUR_VARIANT) return;
  var v    = CART_CUR_VARIANT;
  var unit = document.getElementById('cart-unit').value;
  var qty  = parseFloat(document.getElementById('cart-qty').value) || 1;
  var price = 0;

  if (unit === 'shirheg') price = (v.unit_price  || 0) * qty;
  else if (unit === 'kub')    price = (v.cube_price  || 0) * qty;
  else if (unit === 'bagts')  price = (v.pack_price  || 0) * qty;
  else if (unit === 'porter') price = (v.porter_price || 0) * qty;

  var hint = '';
  if ((unit === 'shirheg' || unit === 'kub') && v.per_cube) hint = '1 куб = ' + v.per_cube + ' ширхэг';
  if (unit === 'bagts' && v.per_pack) hint = '1 багц = ' + v.per_pack + ' ширхэг';
  document.getElementById('cart-hint').textContent = hint;

  var preview = document.getElementById('cart-price-preview');
  preview.style.display = 'flex';
  document.getElementById('cart-price-desc').textContent = qty + ' ' + UNIT_LABELS[unit].toLowerCase();
  document.getElementById('cart-price-val').textContent  = '₮' + Math.round(price).toLocaleString();
}

function cartAddItem() {
  if (!CART_CUR_VARIANT) return;
  var v    = CART_CUR_VARIANT;
  var unit = document.getElementById('cart-unit').value;
  var qty  = parseFloat(document.getElementById('cart-qty').value) || 1;
  var price = 0;

  if (unit === 'shirheg') price = (v.unit_price  || 0) * qty;
  else if (unit === 'kub')    price = (v.cube_price  || 0) * qty;
  else if (unit === 'bagts')  price = (v.pack_price  || 0) * qty;
  else if (unit === 'porter') price = (v.porter_price || 0) * qty;

  var typeEl = document.getElementById('cart-type');
  var typeName = typeEl.options[typeEl.selectedIndex].text.replace(/^.\s/, '');

  CART.push({
    id:           Date.now(),
    variant_id:   v.id,
    product_name: typeName,
    variant_name: v.name,
    sell_type:    unit,
    qty:          qty,
    price:        Math.round(price),
  });
  cartRender();
  document.getElementById('cart-qty').value = 1;
  cartCalcPrice();
}

function cartRemove(id) {
  CART = CART.filter(function(i){ return i.id !== id; });
  cartRender();
}

function cartRender() {
  var empty     = document.getElementById('cart-empty-msg');
  var table     = document.getElementById('cart-table');
  var tbody     = document.getElementById('cart-tbody');
  var totalRow  = document.getElementById('cart-total-row');
  var submitBtn = document.getElementById('cart-submit-btn');

  if (CART.length === 0) {
    empty.style.display     = 'block';
    table.style.display     = 'none';
    totalRow.style.display  = 'none';
    submitBtn.style.display = 'none';
    return;
  }
  empty.style.display     = 'none';
  table.style.display     = 'table';
  totalRow.style.display  = 'flex';
  submitBtn.style.display = 'block';

  var total = 0;
  tbody.innerHTML = '';
  CART.forEach(function(item){
    total += item.price;
    var tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #DDD0BC';
    tr.innerHTML =
      '<td style="padding:8px;font-weight:600;font-size:13px">' + item.product_name + '</td>' +
      '<td style="padding:8px;color:#7A6248;font-size:12px">' + item.variant_name + '</td>' +
      '<td style="padding:8px;text-align:center">' + item.qty + '</td>' +
      '<td style="padding:8px;font-size:11px"><span style="background:#F2EDE3;padding:2px 6px;border-radius:4px">' + UNIT_LABELS[item.sell_type] + '</span></td>' +
      '<td style="padding:8px;text-align:right;font-weight:700;color:#C8833A">₮' + item.price.toLocaleString() + '</td>' +
      '<td style="padding:4px"><button onclick="cartRemove(' + item.id + ')" style="background:none;border:none;cursor:pointer;color:#7A6248;font-size:15px">✕</button></td>';
    tbody.appendChild(tr);
  });
  document.getElementById('cart-total-price').textContent = '₮' + total.toLocaleString();
}

function cartPrepareSubmit() {
  if (CART.length === 0) { alert('Сагс хоосон байна.'); return false; }
  var cartData = CART.map(function(i){
    return { variant_id: i.variant_id, sell_type: i.sell_type, qty: i.qty };
  });
  document.getElementById('cart-json-input').value = JSON.stringify(cartData);
  return true;
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


/* ── 8. Chat widget (AI) ── */
function toggleChat() {
  var box = document.getElementById('chat-box');
  box.classList.toggle('open');
}

var chatSending = false;

function sendChat() {
  var input    = document.getElementById('chat-input');
  var messages = document.getElementById('chat-messages');
  var text     = input.value.trim();
  if (!text || chatSending) return;

  // Хэрэглэгчийн мессеж харуулах
  var userMsg = document.createElement('div');
  userMsg.className   = 'chat-msg user';
  userMsg.textContent = text;
  messages.appendChild(userMsg);
  input.value = '';
  messages.scrollTop = messages.scrollHeight;

  // "Бодож байна..." харуулах
  var typing = document.createElement('div');
  typing.className = 'chat-msg bot typing';
  typing.textContent = '...';
  messages.appendChild(typing);
  messages.scrollTop = messages.scrollHeight;

  chatSending = true;

  // AI backend руу илгээх
  var formData = new FormData();
  formData.append('message', text);

  fetch('/Wood-shop/ai_chat.php', { method: 'POST', body: formData })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    messages.removeChild(typing);
    var botMsg = document.createElement('div');
    botMsg.className = 'chat-msg bot';
    botMsg.textContent = data.reply || 'Алдаа гарлаа. 9446-9149 руу залгана уу.';
    messages.appendChild(botMsg);
    messages.scrollTop = messages.scrollHeight;
  })
  .catch(function() {
    messages.removeChild(typing);
    var errMsg = document.createElement('div');
    errMsg.className = 'chat-msg bot';
    errMsg.textContent = 'Холболт тасарлаа. Дахин оролдоно уу.';
    messages.appendChild(errMsg);
    messages.scrollTop = messages.scrollHeight;
  })
  .finally(function() {
    chatSending = false;
  });
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
  if (hash === '#login-modal' || hash === '#register-modal' || hash === '#otp-modal') {
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


/* ── 11. Password validation ── */
var regPwd     = document.getElementById('reg-password');
var regConfirm = document.getElementById('reg-password-confirm');
var matchMsg   = document.getElementById('pwd-match-msg');

function checkRule(id, ok) {
  var el = document.getElementById(id);
  if (!el) return;
  var text = el.textContent.slice(2); // '✓ ' эсвэл '✗ ' хасах
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

/*  Dropdown toggle  */
function toggleUserDropdown() {
  var menu = document.getElementById('dropdown-menu');
  menu.classList.toggle('open');
}
 
// Гадна дарахад хаах
document.addEventListener('click', function(e) {
  var dd = document.getElementById('user-dropdown');
  if (dd && !dd.contains(e.target)) {
    document.getElementById('dropdown-menu').classList.remove('open');
  }
});
 
//── Chat unread polling (нэвтэрсэн хэрэглэгчид л) ──
(function() {
  function checkUnread() {
    fetch('/Wood-shop/chat.php?act=unread')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var badge = document.getElementById('chat-badge');
      if (badge) {
        if (data.ok && data.unread > 0) {
          badge.textContent    = data.unread;
          badge.style.display  = 'inline-block';
        } else {
          badge.style.display  = 'none';
        }
      }
    }).catch(function(){});
  }
  checkUnread();
  setInterval(checkUnread, 10000); // 10 секунд тутам
})();