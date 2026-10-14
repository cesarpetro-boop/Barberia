<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login();
require_once __DIR__ . '/inc/acciones.php';

$page = 'liquidacion';
$title = 'Liquidación de Barberos';
$subtitle = 'Comisiones y adelantos por período';

$pdo = pdo();
procesar_adelantos($pdo, 'liquidacion.php');
procesar_atenciones($pdo, 'liquidacion.php');
procesar_caja($pdo, 'liquidacion.php');

// ---------- Período (RF-13) ----------
$periodo = $_GET['periodo'] ?? 'hoy';
$desde = $_GET['desde'] ?? null;
$hasta = $_GET['hasta'] ?? null;

$hoy = date('Y-m-d');
$ultDia = date('t');
switch ($periodo) {
    case 'semana':
        $lunes = date('Y-m-d', strtotime('monday this week'));
        $desde = $lunes;
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'quincena':
        if ((int)date('j') <= 15) {
            $desde = date('Y-m-01');
            $hasta = date('Y-m-15');
        } else {
            $desde = date('Y-m-16');
            $hasta = date('Y-m-t');
        }
        break;
    case 'mes':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        break;
    case 'rango':
        $desde = $desde ?: $hoy;
        $hasta = $hasta ?: $hoy;
        break;
    default:
        $desde = $hoy;
        $hasta = $hoy;
}

// Rol barbero: solo su liquidación
$miBarbero = barberoSegunRol();

$filas = liquidacion($pdo, $desde, $hasta);
if ($miBarbero > 0) {
    $filas = array_values(array_filter($filas, fn($f) => (int)$f['id'] === $miBarbero));
}

$tot = [
    'atenciones' => 0, 'facturado' => 0.0, 'comision' => 0.0,
    'subtotal' => 0.0, 'adelantos' => 0.0, 'descuentos' => 0.0, 'neto' => 0.0,
];
foreach ($filas as $f) {
    $tot['atenciones'] += (int)$f['atenciones'];
    $tot['facturado'] += (float)$f['facturado'];
    $tot['comision'] += (float)$f['comision'];
    $tot['subtotal'] += (float)$f['subtotal'];
    $tot['adelantos'] += (float)$f['adelantos'];
    $tot['descuentos'] += (float)$f['descuentos'];
    $tot['neto'] += (float)$f['pago_neto'];
}

// Adelantos registrados en el período (para detalle)
$st = $pdo->prepare("
    SELECT ad.barbero_id, ad.fecha, ad.monto, ad.tipo, ad.motivo, b.nombre AS barbero
    FROM adelantos ad JOIN barberos b ON b.id = ad.barbero_id
    WHERE ad.fecha >= ? AND ad.fecha <= ?
    ORDER BY ad.fecha DESC, ad.id DESC
");
$st->execute([$desde, $hasta]);
$movimientos = $st->fetchAll();
if ($miBarbero > 0) {
    $movimientos = array_values(array_filter($movimientos, fn($m) => (int)$m['barbero_id'] === $miBarbero || $m['barbero'] === null));
}

$barberosTodos = $pdo->query("SELECT * FROM barberos WHERE activo = 1 ORDER BY nombre")->fetchAll();
// Un barbero solo solicita adelantos sobre sí mismo (su liquidación es la única que ve).
if ($miBarbero > 0) {
    $barberosTodos = array_values(array_filter($barberosTodos, fn($b) => (int)$b['id'] === $miBarbero));
}

// ---------- Caja ----------
$caja = cajaActual($pdo);

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ PERIODO ============ -->
<div class="filtros">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-auto">
      <label class="form-label">Período</label>
      <select name="periodo" class="form-select" id="selPeriodo">
        <option value="hoy" <?= $periodo === 'hoy' ? 'selected' : '' ?>>Hoy</option>
        <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Esta semana</option>
        <option value="quincena" <?= $periodo === 'quincena' ? 'selected' : '' ?>>Quincena</option>
        <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este mes</option>
        <option value="rango" <?= $periodo === 'rango' ? 'selected' : '' ?>>Rango personalizado</option>
      </select>
    </div>
    <div class="col-md-2" id="campoDesde" style="display:<?= $periodo === 'rango' ? '' : 'none' ?>">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
    </div>
    <div class="col-md-2" id="campoHasta" style="display:<?= $periodo === 'rango' ? '' : 'none' ?>">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
    </div>
    <div class="col-auto d-grid">
      <button class="btn btn-outline-warning"><i class="bi bi-funnel me-1"></i>Aplicar</button>
    </div>
    <div class="col ms-auto text-end">
      <span class="text-muted small">
        Del <b class="text-gold"><?= e(date('d/m', strtotime($desde))) ?></b> al <b class="text-gold"><?= e(date('d/m', strtotime($hasta))) ?></b>
      </span>
    </div>
    <div class="col-auto d-grid">
      <?php if (rolActual() === 'admin'): ?>
        <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#modalAdelanto">
          <i class="bi bi-plus-lg me-1"></i>Adelanto / descuento
        </button>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============ KPIs ============ -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <span class="k-icon"><i class="bi bi-scissors"></i></span>
      <div class="k-label">Atenciones</div>
      <div class="k-value"><?= (int)$tot['atenciones'] ?></div>
      <div class="k-sub"><?= money($tot['facturado']) ?> facturado</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-blue">
      <span class="k-icon"><i class="bi bi-person-badge"></i></span>
      <div class="k-label">Comisiones</div>
      <div class="k-value"><?= money($tot['comision']) ?></div>
      <div class="k-sub">Comisiones acumuladas del período</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-red">
      <span class="k-icon"><i class="bi bi-arrow-down-circle"></i></span>
      <div class="k-label">Adelantos / descuentos</div>
      <div class="k-value" style="color:#fca5a5"><?= money($tot['adelantos'] + $tot['descuentos']) ?></div>
      <div class="k-sub"><?= money($tot['adelantos']) ?> adel. · <?= money($tot['descuentos']) ?> desc.</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-green">
      <span class="k-icon"><i class="bi bi-piggy-bank"></i></span>
      <div class="k-label">Total a pagar</div>
      <div class="k-value" style="color:#6ee7b7"><?= money($tot['neto']) ?></div>
      <div class="k-sub">Comisiones − adelantos y descuentos</div>
    </div>
  </div>
</div>

<!-- ============ TABLA POR BARBERO ============ -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calculator me-2"></i>Liquidación por barbero</span>
    <span class="text-muted small fw-normal"><?= count($filas) ?> barbero<?= count($filas) === 1 ? '' : 's' ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (!$filas): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-calculator fs-1 d-block mb-2 opacity-50"></i>
        Sin atenciones en el período seleccionado.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Barbero</th><th>Esquema</th>
              <th class="text-end">Atenciones</th>
              <th class="text-end">Facturado</th>
              <th class="text-end">Comisión</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Adelantos</th>
              <th class="text-end">Descuentos</th>
              <th class="text-end">Pago neto</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filas as $f):
                $hue = hueNombre($f['nombre']);
            ?>
              <tr>
                <td class="text-nowrap">
                  <span class="avatar me-2" style="width:30px;height:30px;font-size:11px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($f['nombre'])) ?></span>
                  <span class="fw-semibold"><?= e($f['nombre']) ?></span>
                </td>
                <td class="small text-muted">
                  <?= $f['tipo_comision'] === 'fijo' ? money($f['comision_valor']) . ' / servicio' : (float)$f['comision_valor'] . '%' ?>
                </td>
                <td class="text-end"><?= (int)$f['atenciones'] ?></td>
                <td class="text-end"><?= money($f['facturado']) ?></td>
                <td class="text-end text-gold"><?= money($f['comision']) ?></td>
                <td class="text-end fw-semibold"><?= money($f['subtotal']) ?></td>
                <td class="text-end" style="color:#fca5a5"><?= money($f['adelantos']) ?></td>
                <td class="text-end" style="color:#fca5a5"><?= money($f['descuentos']) ?></td>
                <td class="text-end fw-bold" style="color:#6ee7b7"><?= money($f['pago_neto']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="2" style="background:var(--card2)">TOTALES</td>
              <td class="text-end" style="background:var(--card2)"><?= (int)$tot['atenciones'] ?></td>
              <td class="text-end" style="background:var(--card2)"><?= money($tot['facturado']) ?></td>
              <td class="text-end text-gold" style="background:var(--card2)"><?= money($tot['comision']) ?></td>
              <td class="text-end" style="background:var(--card2)"><?= money($tot['subtotal']) ?></td>
              <td class="text-end" style="background:var(--card2);color:#fca5a5"><?= money($tot['adelantos']) ?></td>
              <td class="text-end" style="background:var(--card2);color:#fca5a5"><?= money($tot['descuentos']) ?></td>
              <td class="text-end" style="background:var(--card2);color:#6ee7b7"><?= money($tot['neto']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ MOVIMIENTOS DEL PERÍODO ============ -->
<div class="card">
  <div class="card-header"><i class="bi bi-arrow-left-right me-2"></i>Adelantos y descuentos del período</div>
  <div class="card-body p-0">
    <?php if (!$movimientos): ?>
      <div class="text-center text-muted py-4">Sin movimientos en este período.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Fecha</th><th>Barbero</th><th>Tipo</th><th>Motivo</th><th class="text-end">Monto</th></tr>
          </thead>
          <tbody>
            <?php foreach ($movimientos as $m): ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d/m/Y', strtotime($m['fecha']))) ?></td>
                <td class="fw-semibold small"><?= e($m['barbero']) ?></td>
                <td>
                  <?php if ($m['tipo'] === 'adelanto'): ?>
                    <span class="badge" style="background:rgba(96,165,250,.15);color:#93c5fd">Adelanto</span>
                  <?php else: ?>
                    <span class="badge" style="background:rgba(248,113,113,.15);color:#fca5a5">Descuento</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= e($m['motivo'] ?: '—') ?></td>
                <td class="text-end fw-semibold"><?= money($m['monto']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ MODAL ADELANTO / DESCUENTO (RF-14) ============ -->
<div class="modal fade" id="modalAdelanto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="registrar_adelanto">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Adelanto / descuento a barbero</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Barbero</label>
            <select name="barbero_id" class="form-select" required>
              <option value="">Seleccionar…</option>
              <?php foreach ($barberosTodos as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= e($b['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label">Tipo</label>
              <select name="tipo" class="form-select">
                <option value="adelanto">Adelanto (se descuenta)</option>
                <option value="descuento">Descuento</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Fecha</label>
              <input type="date" name="fecha" class="form-control" value="<?= e($hoy) ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Monto</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text">$</span>
              <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required placeholder="0">
            </div>
            <div class="form-text">Se resta del pago neto del barbero en la liquidación del período.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Motivo <span class="text-muted">(opcional)</span></label>
            <input type="text" name="motivo" class="form-control" maxlength="255" placeholder="Ej.: anticipo de sueldo, uniforme…">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
          <button type="submit" class="btn btn-gold"><i class="bi bi-check-lg me-1"></i>Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Mostrar/ocultar campos de rango
document.getElementById('selPeriodo').addEventListener('change', function () {
    const ver = this.value === 'rango';
    document.getElementById('campoDesde').style.display = ver ? '' : 'none';
    document.getElementById('campoHasta').style.display = ver ? '' : 'none';
});
</script>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>
<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
