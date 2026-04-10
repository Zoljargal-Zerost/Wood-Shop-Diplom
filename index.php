<?php
session_start();
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="mn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Модны Зах — Дархан</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<?php if (!empty($_SESSION['toast'])): 
  $t = $_SESSION['toast'];
  unset($_SESSION['toast']);
?>
<div id="toast-server-msg" 
     data-msg="<?= htmlspecialchars($t['msg']) ?>"
     data-type="<?= htmlspecialchars($t['type']) ?>"
     data-icon="<?= htmlspecialchars($t['icon']) ?>"
     style="display:none">
</div>
<?php endif; ?>
<body>

<?php if (!empty($_SESSION['toast'])): $t = $_SESSION['toast']; unset($_SESSION['toast']); ?>
<div id="toast-server-msg" data-msg="<?= htmlspecialchars($t['msg']) ?>" data-type="<?= $t['type'] ?>" data-icon="<?= $t['icon'] ?>" style="display:none"></div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════ -->
<nav class="navbar" id="navbar">
  <div class="nav-inner">
    <a href="#hero" class="nav-logo">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <path d="M12 2L6 10h3l-4 7h5v3h4v-3h5l-4-7h3L12 2z" fill="currentColor"/>
      </svg>
      <span>Модны Зах</span>
    </a>

    <ul class="nav-links">
      <li><a href="#hero"     class="nav-link">Нүүр</a></li>
      <li><a href="#shop"     class="nav-link">Бүтээгдэхүүн</a></li>
      <li><a href="#location" class="nav-link">Байршил</a></li>
      <li><a href="#about"    class="nav-link">Бидний тухай</a></li>
      <li><a href="#contact"  class="nav-link">Холбоо барих</a></li>
    </ul>

    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="dashboard.php" class="btn-user">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          <?= htmlspecialchars($user['ner']) ?>
        </a>
        <a href="auth.php?action=logout" class="btn-outline-sm">Гарах</a>
      <?php else: ?>
        <button class="btn-outline-sm" onclick="openModal('login-modal')">Нэвтрэх</button>
        <button class="btn-primary-sm" onclick="openModal('register-modal')">Бүртгүүлэх</button>
      <?php endif; ?>
    </div>

    <button class="hamburger" id="hamburger" aria-label="Цэс">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>


<!-- ═══════════════════════════════════════════
     HERO
═══════════════════════════════════════════ -->
<section class="hero" id="hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-tree-bg">🌲</div>

  <div class="hero-content">
    <div class="hero-badge">Дархан-Уул · Дархан хот</div>
    <h1 class="hero-title">
      Чанартай мод,<br>
      <span class="hero-accent">найдвартай нийлүүлэлт</span>
    </h1>
    <p class="hero-desc">
      Монголын барилгачид, дизайнерууд болон ахуйн хэрэглэгчдэд зориулсан
      өндөр чанарын мод, модон материалын онлайн зах.
    </p>
    <div class="hero-btns">
      <a href="#shop" class="btn-hero-primary">Бүтээгдэхүүн үзэх &darr;</a>
      <?php if (!$user): ?>
        <button class="btn-hero-outline" onclick="openModal('register-modal')">Бүртгэл үүсгэх</button>
      <?php endif; ?>
    </div>

    <div class="hero-stats">
      <div class="stat"><span class="stat-num">15+</span><span class="stat-lbl">Жилийн туршлага</span></div>
      <div class="stat-div"></div>
      <div class="stat"><span class="stat-num">1000+</span><span class="stat-lbl">Хэрэглэгч</span></div>
      <div class="stat-div"></div>
      <div class="stat"><span class="stat-num">6+</span><span class="stat-lbl">Модны төрөл</span></div>
    </div>
  </div>

  <div class="hero-scroll">
    <div class="scroll-line"></div>
    <span>Доош</span>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     SHOP / БҮТЭЭГДЭХҮҮН
═══════════════════════════════════════════ -->
<section class="section section-alt" id="shop">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Дэлгүүр</span>
      <h2 class="section-title">Бүтээгдэхүүн</h2>
      <p class="section-desc">Өндөр чанарын мод, модон материалыг захиалаарай</p>
    </div>

    <!-- Filter -->
    <div class="filter-bar">
      <button class="filter-btn active" data-filter="all">Бүгд</button>
      <button class="filter-btn" data-filter="shilmuust">Шилмүүст</button>
      <button class="filter-btn" data-filter="navchit">Навчит</button>
      <button class="filter-btn" data-filter="hatu">Хатуу мод</button>
      <button class="filter-btn" data-filter="bolvsuruulsan">Боловсруулсан</button>
      <button class="filter-btn" data-filter="tulsh">Түлш</button>
    </div>

    <!-- Products -->
    <div class="products-grid">

      <article class="product-card" data-type="shilmuust">
        <div class="product-img">🌲</div>
        <div class="product-body">
          <span class="product-type">Шилмүүст</span>
          <h3 class="product-name">Нарс (Pine)</h3>
          <p class="product-desc">Барилга, тавилга хийхэд тохиромжтой. Хүнд ачааллыг тэсвэрлэдэг.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Нарс (Pine)')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

      <article class="product-card" data-type="navchit">
        <div class="product-img">🍃</div>
        <div class="product-body">
          <span class="product-type">Навчит</span>
          <h3 class="product-name">Хус (Birch)</h3>
          <p class="product-desc">Гоё хээтэй, хөнгөн. Дотор засал, тавилгад хэрэглэгдэнэ.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Хус (Birch)')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

      <article class="product-card" data-type="hatu">
        <div class="product-img">🪵</div>
        <div class="product-body">
          <span class="product-type">Хатуу мод</span>
          <h3 class="product-name">Хар мод</h3>
          <p class="product-desc">Удаан эдэлгээтэй, шал болон өндөр чанарын тавилгад зориулсан.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Хар мод')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

      <article class="product-card" data-type="shilmuust">
        <div class="product-img">🌿</div>
        <div class="product-body">
          <span class="product-type">Шилмүүст</span>
          <h3 class="product-name">Хуш (Cedar)</h3>
          <p class="product-desc">Үнэртэй, хорхойд тэсвэртэй. Шүүгээ, гардероб хийхэд.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Хуш (Cedar)')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

      <article class="product-card" data-type="bolvsuruulsan">
        <div class="product-img">📦</div>
        <div class="product-body">
          <span class="product-type">Боловсруулсан</span>
          <h3 class="product-name">Модон хавтан</h3>
          <p class="product-desc">Янз бүрийн хэмжээний хавтан. Барилга, засал чимэглэлд.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Модон хавтан')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

      <article class="product-card" data-type="tulsh">
        <div class="product-img">🔥</div>
        <div class="product-body">
          <span class="product-type">Түлш</span>
          <h3 class="product-name">Түлш мод</h3>
          <p class="product-desc">Хатааж бэлдсэн, өндөр калорийн шатамхай чанартай.</p>
          <div class="product-footer">
            <span class="product-price">Үнийн санал авах</span>
            <button class="btn-order"
              onclick="<?= $user ? "openOrderModal('Түлш мод')" : "openModal('login-modal')" ?>">
              Захиалах
            </button>
          </div>
        </div>
      </article>

    </div><!-- /products-grid -->
  </div>
</section>


<!-- ═══════════════════════════════════════════
     LOCATION / БАЙРШИЛ
═══════════════════════════════════════════ -->
<section class="section" id="location">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Байршил</span>
      <h2 class="section-title">Манай байршил</h2>
      <p class="section-desc">Биечлэн ирж үзэх эсвэл утсаар холбогдоорой</p>
    </div>

    <div class="locations-grid">
      <div class="location-card">
        <div class="location-icon">🏪</div>
        <div class="location-info">
          <h3>Үндсэн дэлгүүр</h3>
          <span class="location-badge">Үндсэн дэлгүүр</span>
          <ul class="location-details">
            <li><span class="ld-icon">📍</span> Дархан-Уул, Дархан сум, 45000</li>
            <li><span class="ld-icon">📞</span> 9446-9149</li>
            <li><span class="ld-icon">🕐</span> Да–Ба: 09:00–18:00 · Бя: 10:00–16:00</li>
          </ul>
          <a href="#contact" class="btn-location">Холбоо барих &rarr;</a>
        </div>
      </div>

      <div class="location-card">
        <div class="location-icon">🏭</div>
        <div class="location-info">
          <h3>Агуулах</h3>
          <span class="location-badge secondary">Агуулах · Хүргэлт</span>
          <ul class="location-details">
            <li><span class="ld-icon">📍</span> Дархан-Уул, Дархан-2</li>
            <li><span class="ld-icon">📞</span> 1234-5678</li>
            <li><span class="ld-icon">🕐</span> Да–Ба: 09:00–17:00 · Бя: Урьдчилж захиалах</li>
          </ul>
          <a href="#contact" class="btn-location">Холбоо барих &rarr;</a>
        </div>
      </div>
    </div>

    <div class="map-placeholder">
      <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d934.3944562774516!2d105.95397578142448!3d49.51168552140104!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2smn!4v1775628281302!5m2!1sen!2smn" style="border:0;width:100%;height:260px;border-radius:16px;display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     ABOUT / БИДНИЙ ТУХАЙ
═══════════════════════════════════════════ -->
<section class="section section-alt" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text">
        <span class="section-label">Бидний тухай</span>
        <h2 class="section-title left">Манай түүх</h2>
        <p>Модны зах нь Дархан хотноо 2014 оноос хойш
          <strong>12 жилийн турш</strong> Дархан Уул аймгийн иргэддээ сайн чанарын мод нийлүүлж ирсэн.</p>
        <p>Бид байгаль орчинд ээлтэй, тогтвортой байдлыг дэмжин,
          зөвхөн сертификаттай эх сурвалжаас мод авдаг.</p>
        <a href="#contact" class="btn-text-link">Бидэнтэй холбогдох &rarr;</a>
      </div>

      <div class="about-values">
        <div class="value-card">
          <span class="value-icon">🏆</span>
          <h4>Чанар</h4>
          <p>Зөвхөн шалгагдсан, сертификаттай мод</p>
        </div>
        <div class="value-card">
          <span class="value-icon">🌱</span>
          <h4>Байгаль орчин</h4>
          <p>Тогтвортой, хариуцлагатай эх сурвалж</p>
        </div>
        <div class="value-card">
          <span class="value-icon">👥</span>
          <h4>Харилцагч</h4>
          <p>1000+ хэрэглэгч</p>
        </div>
        <div class="value-card">
          <span class="value-icon">🤝</span>
          <h4>Итгэл</h4>
          <p>12+ жилийн найдвартай туршлага</p>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     CONTACT / ХОЛБОО БАРИХ
═══════════════════════════════════════════ -->
<section class="section" id="contact">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Холбоо барих</span>
      <h2 class="section-title">Бидэнтэй холбогдох</h2>
    </div>

    <div class="contact-grid">
      <div class="contact-card">
        <span class="contact-icon">📞</span>
        <h3>Утас</h3>
        <p class="contact-main">9446-9149</p>
        <p class="contact-note">Да–Ба: 09:00–18:00</p>
      </div>
      <div class="contact-card">
        <span class="contact-icon">✉️</span>
        <h3>Имэйл</h3>
        <p class="contact-main">info@woodshop.mn</p>
        <p class="contact-note">24 цагт хариулна</p>
      </div>
      <div class="contact-card">
        <span class="contact-icon">📍</span>
        <h3>Хаяг</h3>
        <p class="contact-main">Дархан-Уул, Дархан</p>
        <p><a style="text-decoration: underline;" href="https://maps.app.goo.gl/m9n7UdGU6VmL1EWX8" target="_blank">Газрын зураг</a></p>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════ -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2L6 10h3l-4 7h5v3h4v-3h5l-4-7h3L12 2z"/>
          </svg>
          Модны Зах
        </div>
        <p>Монголын модны захын найдвартай түнш. Чанартай мод, мэргэжлийн үйлчилгээ.</p>
      </div>

      <div class="footer-col">
        <h4>Холбоос</h4>
        <ul>
          <li><a href="#hero">Нүүр</a></li>
          <li><a href="#shop">Бүтээгдэхүүн</a></li>
          <li><a href="#location">Байршил</a></li>
          <li><a href="#about">Бидний тухай</a></li>
          <li><a href="#contact">Холбоо барих</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Ажлын цаг</h4>
        <ul>
          <li>Да–Ба: 09:00–18:00</li>
          <li>Бямба: 10:00–16:00</li>
          <li>Няам: Амарна</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; 2026 Модны Зах Darkhan, Mongolia. All rights reserved.</p>
    </div>
  </div>
</footer>


<!-- ═══════════════════════════════════════════
     MODALS
═══════════════════════════════════════════ -->

<!-- Login Modal -->
<div class="modal-overlay" id="login-modal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('login-modal')">&times;</button>
    <h2 class="modal-title">Нэвтрэх</h2>

    <?php if (isset($_SESSION['auth_error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['auth_error']; unset($_SESSION['auth_error']); ?></div>
    <?php endif; ?>

    <form action="auth.php" method="POST" class="auth-form">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <div class="form-group">
        <label>Имэйл</label>
        <input type="email" name="email" placeholder="example@email.com" required>
      </div>
      <div class="form-group">
        <label>Нууц үг</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-form">Нэвтрэх</button>
      <p class="form-switch">Бүртгэлгүй юу?
        <a href="#" onclick="switchModal('login-modal','register-modal')">Бүртгүүлэх</a>
      </p>
    </form>
  </div>
</div>

<!-- Register Modal -->
<div class="modal-overlay" id="register-modal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('register-modal')">&times;</button>
    <h2 class="modal-title">Бүртгүүлэх</h2>

    <?php if (isset($_SESSION['auth_error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['auth_error']; unset($_SESSION['auth_error']); ?></div>
    <?php endif; ?>

    <form action="auth.php" method="POST" class="auth-form">
      <input type="hidden" name="action" value="register">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <div class="form-group">
        <label>Нэр</label>
        <input type="text" name="ner" placeholder="Таны нэр" required>
      </div>
      <div class="form-group">
        <label>Имэйл</label>
        <input type="email" name="email" placeholder="example@email.com" required>
      </div>
      <div class="form-group">
        <label>Утасны дугаар</label>
        <input type="tel" name="phone" placeholder="+97699XXXXXX" required>
      </div>
      <div class="form-group">
  <label>Нууц үг</label>
  <input type="password" name="password" id="reg-password" 
         placeholder="••••••••" required minlength="8">
  <!-- Шаардлагын жагсаалт -->
  <ul class="pwd-rules" id="pwd-rules">
    <li id="rule-len"  class="rule">✗ 8-аас дээш тэмдэгт</li>
    <li id="rule-upp"  class="rule">✗ Том үсэг (A-Z)</li>
    <li id="rule-num"  class="rule">✗ Тоо (0-9)</li>
  </ul>
</div>

<div class="form-group">
  <label>Нууц үг давтах</label>
  <input type="password" name="password_confirm" id="reg-password-confirm"
         placeholder="••••••••" required>
  <span class="pwd-match-msg" id="pwd-match-msg"></span>
</div>

      <!-- OTP Method Selection -->
      <div class="form-group">
        <label>Баталгаажуулах код хаана илгээх вэ?</label>
        <div class="otp-method-toggle">
          <label class="otp-method-option">
            <input type="radio" name="otp_method" value="email" checked>
            <span>📧 Имэйл</span>
          </label>
          <label class="otp-method-option">
            <input type="radio" name="otp_method" value="sms">
            <span>📱 Утас (SMS)</span>
          </label>
        </div>
      </div>

      <button type="submit" class="btn-form">Код илгээх →</button>
      <p class="form-switch">Бүртгэлтэй юу?
        <a href="#" onclick="switchModal('register-modal','login-modal')">Нэвтрэх</a>
      </p>
    </form>
  </div>
</div>

<!-- OTP Verify Modal (бүртгэлийн дараа нээгдэнэ) -->
<div class="modal-overlay <?= isset($_SESSION['otp_pending']) ? 'open' : '' ?>" id="otp-modal">
  <div class="modal">
    <h2 class="modal-title">Баталгаажуулах</h2>
    <p class="otp-modal-sub">
      <?php
        $otpMethod = $_SESSION['otp_method'] ?? 'email';
        $otpTarget = $_SESSION['otp_target'] ?? '';
        // mask target
        if ($otpMethod === 'email' && strpos($otpTarget, '@') !== false) {
          [$u, $d] = explode('@', $otpTarget);
          $masked = substr($u,0,2) . str_repeat('*', max(2,strlen($u)-2)) . '@' . $d;
        } else {
          $masked = substr($otpTarget,0,4) . str_repeat('*', max(2,strlen($otpTarget)-6)) . substr($otpTarget,-2);
        }
        $icon = $otpMethod === 'sms' ? '📱' : '📧';
        echo $icon . ' Баталгаажуулах код илгээлээ: <strong>' . htmlspecialchars($masked) . '</strong>';
      ?>
    </p>

    <?php if (isset($_SESSION['otp_error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['otp_error']; unset($_SESSION['otp_error']); ?></div>
    <?php endif; ?>

    <form action="auth.php" method="POST" class="auth-form" id="otpForm">
      <input type="hidden" name="action" value="verify_otp">
      <div class="form-group">
        <label>6 оронтой код</label>
        <div class="otp-boxes-row" id="otpBoxes">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-single" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        </div>
        <input type="hidden" name="otp" id="otpHidden">
      </div>
      <div class="otp-timer-row">
        Код дуусах хугацаа: <span id="otpCountdown" style="font-weight:700;color:#2d6a4f;">5:00</span>
        &nbsp;·&nbsp; <a href="auth.php?action=resend_otp" style="color:#2d6a4f;">Дахин илгээх</a>
      </div>
      <button type="submit" class="btn-form" id="otpSubmitBtn" disabled>Баталгаажуулах</button>
    </form>
  </div>
</div>

<!-- Order Modal -->
<div class="modal-overlay" id="order-modal">
  <div class="modal modal-wide">
    <button class="modal-close" onclick="closeModal('order-modal')">&times;</button>
    <h2 class="modal-title">Захиалга өгөх</h2>

    <form action="process_order.php" method="POST" class="auth-form">
      <div class="form-row">
        <div class="form-group">
          <label>Модны төрөл <span class="req">*</span></label>
          <select name="product" id="order-product" required>
            <option value="">Сонгох...</option>
            <option value="Нарс (Pine)">Нарс (Pine)</option>
            <option value="Хус (Birch)">Хус (Birch)</option>
            <option value="Хар мод">Хар мод</option>
            <option value="Хуш (Cedar)">Хуш (Cedar)</option>
            <option value="Модон хавтан">Модон хавтан</option>
            <option value="Түлш мод">Түлш мод</option>
            <option value="Бусад">Бусад</option>
          </select>
        </div>
        <div class="form-group">
          <label>Тоо ширхэг <span class="req">*</span></label>
          <input type="number" name="shirheg" min="1" placeholder="10" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Урт (метр) <span class="req">*</span></label>
          <input type="number" name="urt_m" step="0.1" min="0.1" placeholder="3.0" required>
        </div>
        <div class="form-group">
          <label>Өргөн (см) <span class="req">*</span></label>
          <input type="number" name="urgun_cm" min="1" placeholder="15" required>
        </div>
        <div class="form-group">
          <label>Зузаан (см) <span class="req">*</span></label>
          <input type="number" name="zuzaan_cm" step="0.1" min="0.1" placeholder="2.5" required>
        </div>
      </div>

      <div class="form-group">
        <label>Нэмэлт тэмдэглэл</label>
        <textarea name="notes" rows="3" placeholder="Онцгой шаардлага, тайлбар..."></textarea>
      </div>

      <button type="submit" class="btn-form">Захиалга илгээх &rarr;</button>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════
     CHAT WIDGET
═══════════════════════════════════════════ -->
<div class="chat-widget" id="chat-widget">
  <div class="chat-box" id="chat-box">
    <div class="chat-header">
      <span>💬 Тусламж</span>
      <button onclick="toggleChat()">&times;</button>
    </div>
    <div class="chat-messages" id="chat-messages">
      <div class="chat-msg bot">Сайн байна уу! Та ямар мод сонирхож байна вэ? 🌲</div>
    </div>
    <div class="chat-input-row">
      <input type="text" id="chat-input" placeholder="Мессеж бичих..." onkeydown="if(event.key==='Enter') sendChat()">
      <button onclick="sendChat()">&#10148;</button>
    </div>
  </div>
  <button class="chat-btn" onclick="toggleChat()" title="Чат нээх">💬</button>
</div>


<script src="js/main.js"></script>
<script>
// ── OTP Modal auto-open if pending ──
<?php if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending']): ?>
document.addEventListener('DOMContentLoaded', function() {
  openModal('otp-modal');
});
<?php endif; ?>

// ── OTP box keyboard navigation ──
(function() {
  var boxes   = document.querySelectorAll('.otp-single');
  var hidden  = document.getElementById('otpHidden');
  var submitBtn = document.getElementById('otpSubmitBtn');
  if (!boxes.length) return;

  function getCode() {
    return Array.from(boxes).map(function(b){ return b.value; }).join('');
  }
  function checkFull() {
    var code = getCode();
    if (hidden) hidden.value = code;
    if (submitBtn) submitBtn.disabled = code.length < 6;
  }

  boxes.forEach(function(box, i) {
    box.addEventListener('input', function(e) {
      box.value = box.value.replace(/\D/g,'');
      if (box.value && i < boxes.length - 1) boxes[i+1].focus();
      checkFull();
    });
    box.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        boxes[i-1].value = '';
        boxes[i-1].focus();
        checkFull();
      }
    });
    box.addEventListener('paste', function(e) {
      e.preventDefault();
      var pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      pasted.split('').forEach(function(ch, idx){ if(boxes[idx]) boxes[idx].value = ch; });
      boxes[Math.min(pasted.length, boxes.length-1)].focus();
      checkFull();
    });
  });

  // countdown timer
  var secs = 300;
  var el = document.getElementById('otpCountdown');
  if (el) {
    var t = setInterval(function() {
      secs--;
      var m = Math.floor(secs/60), s = secs%60;
      el.textContent = m + ':' + (s<10?'0':'') + s;
      if (secs <= 0) {
        clearInterval(t);
        el.textContent = 'Дууссан';
        el.style.color = '#e63946';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Код хугацаа дууссан'; }
      }
    }, 1000);
  }
})();
</script>
</body>
<!-- Toast -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon"></span>
  <span id="toast-msg"></span>
</div>
</html>