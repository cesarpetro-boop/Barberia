<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
requerir_login();

$u = usuarioActual();
$rol = rolActual();

// ---------- Menú según actor ----------
// El barbero solo trabaja su turno: registrar, ver SU historial y SU liquidación.
// El dashboard (datos globales del negocio) y la caja son del administrador.
if ($rol === 'barbero') {
    $menu = [
        ['atenciones', 'atenciones.php', 'Registrar', 'bi-scissors'],
        ['historial', 'historial.php', 'Historial', 'bi-clock-history'],
        ['liquidacion', 'liquidacion.php', 'Mi liquidación', 'bi-calculator'],
    ];
} else {
    $menu = [
        ['dashboard', 'index.php', 'Dashboard', 'bi-speedometer2'],
        ['atenciones', 'atenciones.php', 'Registrar', 'bi-scissors'],
        ['historial', 'historial.php', 'Historial', 'bi-clock-history'],
        ['caja', 'caja.php', 'Caja', 'bi-wallet2'],
        ['liquidacion', 'liquidacion.php', 'Liquidación', 'bi-calculator'],
        ['barberos', 'barberos.php', 'Barberos', 'bi-person-badge'],
        ['servicios', 'servicios.php', 'Servicios', 'bi-list-task'],
        ['metodos', 'metodos.php', 'Métodos de pago', 'bi-credit-card'],
        ['productos', 'productos.php', 'Productos', 'bi-box-seam'],
        ['reportes', 'reportes.php', 'Reportes', 'bi-graph-up-arrow'],
        ['respaldos', 'respaldos.php', 'Respaldos', 'bi-cloud-arrow-down'],
        ['auditoria', 'auditoria.php', 'Auditoría', 'bi-journal-text'],
        ['usuarios', 'usuarios.php', 'Usuarios', 'bi-shield-lock'],
    ];
}

$hue = hueNombre($u['nombre']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Panel') ?> · <?= e(APP_NAME) ?></title>
  <!-- Hora del servidor: los formularios proponen ESTE reloj (fuente única de tiempo) -->
  <script>window.APP_AHORA = '<?= date('Y-m-d\TH:i') ?>';</script>
  <!-- Tipografías 100% locales (sin CDN: funciona sin internet, RNF-07) -->
  <link rel="stylesheet" href="<?= asset('assets/vendor/fonts.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/inter.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
  <script src="<?= asset('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
</head>
<body>
<div class="app">

  <!-- ============ SIDEBAR ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-crest">
        <i class="bi bi-scissors brand-icon"></i>
      </div>
      <span class="pole pole-brand" aria-hidden="true"></span>
      <div class="brand-text">
        <strong class="brand-title">BARBERIA</strong>
        <span class="brand-sub"><i class="bi bi-stars me-1 text-gold"></i>BAR-DEMBOW</span>
      </div>
    </div>

    <nav class="sidenav">
      <?php foreach ($menu as [$key, $href, $label, $icon]): ?>
        <a class="nav-link<?= (($page ?? '') === $key) ? ' active' : '' ?>" href="<?= $href ?>">
          <i class="bi <?= $icon ?>"></i><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <a href="atenciones.php#registrar" class="btn-gold w-100"><i class="bi bi-plus-lg me-1"></i>Registrar atención</a>
      <div class="side-date text-center mt-3">
        <i class="bi bi-calendar3 me-1"></i><?= e(date('d/m/Y')) ?>
      </div>
    </div>
  </aside>
  <div class="overlay" id="overlay"></div>

  <!-- ============ MAIN ============ -->
  <div class="main">
    <header class="topbar">
      <button class="btn-icon d-lg-none me-2" id="btnMenu" aria-label="Menú"><i class="bi bi-list"></i></button>
      <div class="page-title">
        <h1><?= e($title ?? 'Panel') ?></h1>
        <?php if (!empty($subtitle)): ?><p style="color:#cbd5e1;font-weight:500"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <div class="topbar-right">
        <a href="atenciones.php#registrar" class="btn-gold d-none d-md-inline-flex"><i class="bi bi-scissors me-1"></i>Nueva atención</a>
        <div class="dropdown">
          <a href="#" class="d-flex align-items-center text-decoration-none user-chip" data-bs-toggle="dropdown" role="button">
            <span class="avatar avatar-sm" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($u['nombre'])) ?></span>
            <span class="d-flex flex-column ms-2 align-items-start">
              <span class="small fw-bold text-truncate" style="max-width:130px;color:#ffffff"><?= e($u['nombre']) ?></span>
              <span class="badge rol-<?= $rol ?>"><?= $rol === 'admin' ? 'Admin' : 'Barbero' ?></span>
            </span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Sesión · <?= e($u['usuario']) ?></h6></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
          </ul>
        </div>
      </div>
    </header>

    <div class="content">
<?php render_flash(); ?>
