<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $usu = trim($_POST['usuario'] ?? '');
    $clave = $_POST['contrasena'] ?? '';

    $pdo = pdo();
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
    $st->execute([$usu]);
    $u = $st->fetch();

    if ($u && password_verify($clave, $u['contrasena'])) {
        $pdo->prepare("UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?")->execute([ahora(), (int)$u['id']]);
        login($u);
        audit($pdo, 'login', 'usuarios', $u['id'], 'Inició sesión');
        // RNF-11: respaldo automático diario al entrar el administrador
        if ($u['rol'] === 'admin') {
            try { respaldoDiarioAutomatico($pdo); } catch (Exception $ex) { /* no bloquea el login */ }
        }
        redirect('index.php');
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión · <?= e(APP_NAME) ?></title>
  <!-- Tipografías 100% locales (sin CDN: funciona sin internet, RNF-07) -->
  <link rel="stylesheet" href="<?= asset('assets/vendor/fonts.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/inter.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-bg-glow glow-1"></div>
  <div class="login-bg-glow glow-2"></div>
  <div class="login-bg-glow glow-3"></div>

  <div class="login-card card">
    <div class="text-center mb-4">
      <div class="login-emblem mb-3">
        <span class="pole pole-lg d-inline-block"></span>
        <div class="emblem-crown"><i class="bi bi-scissors"></i></div>
      </div>
      <h2 class="login-brand-title mb-1"><?= e(APP_NAME) ?></h2>
      <div class="login-brand-sub">
        <span class="badge badge-gold-glow"><i class="bi bi-stars me-1"></i>VIP BARBER STUDIO</span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="post" id="loginForm">
      <?= csrf_input() ?>
      <div class="mb-3">
        <label class="form-label d-flex justify-content-between align-items-center">
          <span>Usuario</span>
          <span class="badge" style="background:rgba(245,158,11,0.2);color:#fbbf24;font-family:monospace;font-size:11.5px">admin</span>
        </label>
        <div class="input-group login-input-group">
          <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
          <input type="text" id="inputUser" name="usuario" class="form-control" autocomplete="username" placeholder="Tu usuario" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label d-flex justify-content-between align-items-center">
          <span>Contraseña</span>
          <span class="badge" style="background:rgba(245,158,11,0.2);color:#fbbf24;font-family:monospace;font-size:11.5px">admin123</span>
        </label>
        <div class="input-group login-input-group">
          <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
          <input type="password" id="inputPass" name="contrasena" class="form-control" autocomplete="current-password" placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-input-eye" id="btnTogglePass" tabindex="-1" title="Mostrar contraseña">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-gold w-100 btn-lg btn-login-shine">
        <span>Ingresar al Sistema</span>
        <i class="bi bi-arrow-right-short ms-1 fs-5"></i>
      </button>
    </form>

    <div class="login-quick-access mt-4">
      <div class="quick-title text-center small mb-2" style="color:#cbd5e1;font-weight:700">
        <i class="bi bi-lightning-charge-fill text-gold me-1"></i>Acceso rápido con un toque:
      </div>
      <div class="d-flex justify-content-center gap-2">
        <button type="button" class="btn-quick-fill" onclick="quickFill('admin','admin123')">
          <i class="bi bi-shield-check me-1"></i>Autocompletar Admin
        </button>
      </div>
    </div>

    <div class="text-center mt-4 pt-2 border-top border-secondary border-opacity-25">
      <a href="privacidad.php" class="small text-decoration-none d-inline-flex align-items-center" style="color:#cbd5e1">
        <i class="bi bi-shield-lock me-1"></i>Protección de datos y privacidad (Ley 1581)
      </a>
    </div>
  </div>
</div>
<script src="<?= asset('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
<script>
  function quickFill(u, p) {
    const userField = document.getElementById('inputUser');
    const passField = document.getElementById('inputPass');
    if (userField && passField) {
      userField.value = u;
      passField.value = p;
      userField.focus();
    }
  }

  const btnToggle = document.getElementById('btnTogglePass');
  const inputPass = document.getElementById('inputPass');
  const eyeIcon = document.getElementById('eyeIcon');
  if (btnToggle && inputPass && eyeIcon) {
    btnToggle.addEventListener('click', () => {
      const isPassword = inputPass.type === 'password';
      inputPass.type = isPassword ? 'text' : 'password';
      eyeIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  }
</script>
</body>
</html>
