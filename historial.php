<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login();
require_once __DIR__ . '/inc/acciones.php';

$page = 'historial';
$title = 'Historial de atenciones';
$subtitle = 'Consulta por período, edita y anula registros';

$pdo = pdo();
// Anulaciones desde esta pantalla (RF-09 / RNF-09: solo admin)
procesar_atenciones($pdo, 'historial.php');

// ---------- Filtros (RF-15/RF-18) ----------
$f = [
    'desde' => $_GET['desde'] ?? date('Y-m-d'),
    'hasta' => $_GET['hasta'] ?? date('Y-m-d'),
    'barbero_id' => $_GET['barbero_id'] ?? '',
    'servicio_id' => $_GET['servicio_id'] ?? '',
    'metodo_pago_id' => $_GET['metodo_pago_id'] ?? '',
];
[$where, $params] = queryAtenciones($f);

// Rol barbero: fuerza su propio barbero_id (solo ve lo suyo)
$miBarbero = barberoSegunRol();

// ---------- Historial ----------
$st = $pdo->prepare("
    SELECT a.*, b.nombre AS barbero, b.tipo_comision, b.comision_valor,
           mp.nombre AS metodo_nombre, mp.tipo AS metodo_tipo,
      (SELECT GROUP_CONCAT(CONCAT(s.nombre, IF(s2.cantidad > 1, CONCAT(' ×', s2.cantidad), ''), IF(s2.nota IS NOT NULL, CONCAT(' — ', s2.nota), '')) SEPARATOR ' · ')
       FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
       WHERE s2.atencion_id = a.id) AS servicios_txt
    FROM atenciones a
    JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE {$where}
    ORDER BY a.fecha DESC, a.id DESC
    LIMIT 400
");
$st->execute($params);
$atenciones = $st->fetchAll();

$tot = ['n' => count($atenciones), 'cobrado' => 0.0, 'comision' => 0.0, 'ganancia' => 0.0];
foreach ($atenciones as $a) {
    $tot['cobrado'] += (float)$a['total_cobrado'];
    $tot['comision'] += (float)$a['comision'];
    $tot['ganancia'] += (float)$a['monto_final'] - (float)$a['comision'];
}

// ---------- Datos para filtros ----------
$barberosTodos = $pdo->query("SELECT * FROM barberos ORDER BY nombre")->fetchAll();
$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$metodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ FILTROS ============ -->
<div class="filtros">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= e($f['desde']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= e($f['hasta']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Barbero</label>
      <select name="barbero_id" class="form-select" <?= $miBarbero > 0 ? 'disabled' : '' ?>>
        <option value="">Todos</option>
        <?php foreach ($barberosTodos as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (string)$f['barbero_id'] === (string)$b['id'] ? 'selected' : '' ?>>
            <?= e($b['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($miBarbero > 0): ?>
        <input type="hidden" name="barbero_id" value="<?= $miBarbero ?>">
      <?php endif; ?>
    </div>
    <div class="col-md-2">
      <label class="form-label">Servicio</label>
      <select name="servicio_id" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($servicios as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (string)$f['servicio_id'] === (string)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Pago</label>
      <select name="metodo_pago_id" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($metodos as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (string)$f['metodo_pago_id'] === (string)$m['id'] ? 'selected' : '' ?>>
            <?= e($m['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto d-grid">
      <button class="btn btn-outline-warning"><i class="bi bi-funnel me-1"></i>Filtrar</button>
    </div>
    <div class="col-auto d-grid">
      <a href="historial.php" class="btn btn-outline-light"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- ============ TABLA ============ -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clock-history me-2"></i>Historial del período</span>
    <span class="text-muted small fw-normal"><?= $tot['n'] ?> atención<?= $tot['n'] === 1 ? '' : 'es' ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Fecha</th><th>Barbero</th><th>Servicios</th><th>Pago</th>
            <th class="text-end">Cobrado</th><th class="text-end">Comisión</th><th class="text-end">Ganancia</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$atenciones): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">Sin resultados en el período seleccionado</td></tr>
          <?php endif; ?>
          <?php foreach ($atenciones as $a):
              $hue = hueNombre($a['barbero']);
          ?>
            <tr>
              <td class="text-nowrap">
                <span class="avatar me-2" style="width:28px;height:28px;font-size:10px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($a['barbero'])) ?></span>
                <?= e(date('d/m H:i', strtotime($a['fecha']))) ?>
              </td>
              <td class="fw-semibold"><?= e($a['barbero']) ?></td>
              <td class="small"><?= e($a['servicios_txt'] ?: '—') ?></td>
              <td>
                <span class="badge-pago <?= $a['metodo_tipo'] ?>" title="<?= e($a['metodo_nombre']) ?>">
                  <i class="bi <?= $a['metodo_tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
                </span>
              </td>
              <td class="text-end fw-semibold"><?= money($a['total_cobrado']) ?></td>
              <td class="text-end text-gold"><?= money($a['comision']) ?><br>
                <small class="text-muted">
                  <?= $a['tipo_comision'] === 'fijo' ? money($a['comision_valor']) . '/serv' : (float)$a['comision_valor'] . '%' ?>
                </small>
              </td>
              <td class="text-end" style="color:#6ee7b7"><?= money((float)$a['monto_final'] - (float)$a['comision']) ?></td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <a href="atenciones.php?editar=<?= (int)$a['id'] ?>#registrar" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                  <?php if (esRol('admin')): ?>
                  <button class="btn btn-sm btn-outline-danger" data-anular="<?= (int)$a['id'] ?>" title="Anular (solo admin)"><i class="bi bi-x-lg"></i></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($atenciones): ?>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="4" style="background:var(--card2)">Totales</td>
              <td class="text-end" style="background:var(--card2)"><?= money($tot['cobrado']) ?></td>
              <td class="text-end text-gold" style="background:var(--card2)"><?= money($tot['comision']) ?></td>
              <td class="text-end" style="background:var(--card2);color:#6ee7b7"><?= money($tot['ganancia']) ?></td>
              <td style="background:var(--card2)"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
