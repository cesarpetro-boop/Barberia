<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login();
require_once __DIR__ . '/inc/acciones.php';

$page = 'atenciones';
$title = 'Atenciones';
$subtitle = 'Registra servicios, pagos y comisiones automáticas';

$pdo = pdo();
// Tras guardar una EDICIÓN se vuelve al historial (donde se originó el cambio);
// al registrar una atención nueva se permanece en esta pantalla.
$destinoTrasProcesar = in_array($_POST['accion'] ?? '', ['editar_atencion', 'anular_atencion'], true)
    ? 'historial.php' : 'atenciones.php';
procesar_atenciones($pdo, $destinoTrasProcesar);
procesar_caja($pdo, 'atenciones.php');
procesar_adelantos($pdo, 'atenciones.php');

// Rol barbero: fuerza su propio barbero_id
$miBarbero = barberoSegunRol();

// ---------- Datos para formulario ----------
// Un barbero solo se registra a sí mismo: evita que una atención suya quede
// a nombre de otro y no aparezca en su historial.
$barberos = $pdo->query("SELECT * FROM barberos WHERE activo = 1 ORDER BY nombre")->fetchAll();
if ($miBarbero > 0) {
    $barberos = array_values(array_filter($barberos, fn($b) => (int)$b['id'] === $miBarbero));
}
$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY es_otro_valor, nombre")->fetchAll();
$metodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();

// ---------- Modo edición ----------
$editar = null;
if (!empty($_GET['editar'])) {
    $editar = obtenerAtencion($pdo, (int)$_GET['editar']);
    if ($editar && $miBarbero > 0 && (int)$editar['barbero_id'] !== $miBarbero) {
        $editar = null; // un barbero no puede editar la atención de otro
    }
}

// ---------- Caja ----------
$caja = cajaActual($pdo);

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ CAJA ============ -->
<?php if ($caja): ?>
<div class="card caja-strip mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
    <span class="caja-dot" title="Caja abierta"></span>
    <div>
      <div class="fw-bold">Caja abierta</div>
      <small class="text-muted">desde las <?= e(date('H:i', strtotime($caja['fecha_apertura']))) ?></small>
    </div>
    <div class="caja-stat">Base<b><?= money($caja['monto_apertura']) ?></b></div>
    <div class="caja-stat">Ingresos turno<b><?= money($caja['ingresos_turno']) ?></b></div>
    <div class="caja-stat">Efectivo esperado<b class="text-gold"><?= money($caja['efectivo_esperado']) ?></b></div>
    <div class="caja-stat">Digital<b style="color:#93c5fd"><?= money($caja['digital_esperado'] ?? 0) ?></b></div>
    <div class="ms-auto">
      <?php if (esRol('barbero')): ?>
        <!-- El barbero abre caja para trabajar, pero el arqueo/cierre es del administrador -->
        <small class="text-muted"><i class="bi bi-lock me-1"></i>El cierre lo hace el administrador</small>
      <?php else: ?>
        <a href="caja.php" class="btn btn-outline-warning me-2"><i class="bi bi-wallet2 me-1"></i>Ver caja</a>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCerrarCaja">
          <i class="bi bi-cash-coin me-1"></i>Cerrar caja
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card caja-strip cerrada mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
    <i class="bi bi-wallet2 fs-4 text-muted"></i>
    <div>
      <div class="fw-bold">Caja cerrada</div>
      <small class="text-muted">Abre la caja para iniciar el turno</small>
    </div>
    <div class="ms-auto">
      <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
        <i class="bi bi-unlock me-1"></i>Abrir caja
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4 align-items-start">

  <!-- ============ FORMULARIO ============ -->
  <div class="col-lg-9 col-xl-7 mx-auto" id="registrar">
    <?php
    $formId = 'atencion-main';
    $redirectUrl = 'atenciones.php';
    require __DIR__ . '/inc/form_atencion.php';
    ?>
  </div>
</div>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>
<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
