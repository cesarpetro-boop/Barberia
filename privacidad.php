<?php
require_once __DIR__ . '/inc/bootstrap.php';

// Página pública (sin inicio de sesión): se ve desde el login.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Protección de datos · <?= e(APP_NAME) ?></title>
  <!-- Tipografías 100% locales (sin CDN: funciona sin internet, RNF-07) -->
  <link rel="stylesheet" href="<?= asset('assets/vendor/fonts.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/inter.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<div class="container py-4" style="max-width:820px">
  <div class="text-center mb-4">
    <span class="pole pole-lg d-inline-block mb-3"></span>
    <h2 class="fw-bold mb-1"><?= e(APP_NAME) ?></h2>
    <p class="text-muted">Política de tratamiento de datos personales</p>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="bi bi-shield-lock me-2"></i>Política de privacidad — Ley 1581 de 2012 (Colombia)
    </div>
    <div class="card-body">
      <p class="text-muted small">Vigente desde <?= e(date('d/m/Y')) ?></p>

      <h5 class="mt-3">1. Responsable</h5>
      <p>
        El responsable del tratamiento es <b><?= e(APP_NAME) ?></b>, encargada de la
        operación de la barbería y de este sistema de control.
      </p>

      <h5>2. Datos que se recopilan</h5>
      <p>Únicamente los estrictamente necesarios para prestar el servicio:</p>
      <ul>
        <li><b>Barberos:</b> nombre, documento de identidad, teléfono, fecha de ingreso y comisión.</li>
        <li><b>Usuarios del sistema:</b> nombre, usuario y contraseña cifrada, y rol.</li>
        <li><b>Operaciones:</b> servicios, precios, pagos, comisiones, adelantos y auditoría de cambios.</li>
      </ul>
      <p class="small"><b>No</b> se recopilan datos sensibles, de menores de edad ni de salud.</p>

      <h5>3. Finalidad</h5>
      <p>
        Gestión de atenciones, cálculo de comisiones, control de caja,
        liquidaciones, control de inventario y reportes internos de la barbería.
      </p>

      <h5>4. Conservación</h5>
      <p>
        Los registros históricos <b>no se eliminan</b>: las atenciones se anulan
        (quedan marcadas como anuladas y fuera de los totales) y los catálogos
        (barberos, servicios, productos) se desactivan, preservando el historial.
      </p>

      <h5>5. Derechos del titular (Ley 1581, art. 14)</h5>
      <p>Puedes ejercer los derechos de <b>acceso, actualización, rectificación y supresión</b>:</p>
      <ul>
        <li><b>Rectificar:</b> pídele al administrador que corrija tus datos en la pantalla correspondiente.</li>
        <li><b>Suprimir (derecho al olvido):</b> este sistema <b>no almacena datos de clientes</b>;
          el registro de cada corte solo guarda el servicio, el barbero, el monto y la fecha.</li>
      </ul>

      <h5>6. Seguridad</h5>
      <p>
        La información se almacena en el computador local de la barbería, protegida por
        inicio de sesión con contraseña cifrada (hash unidireccional), roles de acceso,
        registro de auditoría y respaldos automáticos diarios.
      </p>

      <h5>7. Cookies</h5>
      <p>
        Solo se usa una cookie de sesión técnica para mantener el inicio de sesión
        en el navegador. No hay cookies de rastreo ni publicidad.
      </p>

      <h5>8. Contacto</h5>
      <p>Para ejercer tus derechos, comunícate con el administrador en la barbería.</p>
    </div>
    <div class="card-footer text-center">
      <a href="login.php" class="btn btn-outline-warning">
        <i class="bi bi-box-arrow-in-right me-1"></i>Ir a iniciar sesión
      </a>
    </div>
  </div>
</div>
<script src="<?= asset('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
