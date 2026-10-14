<?php
require_once __DIR__ . '/inc/bootstrap.php';
requerir_login('admin');
require_once __DIR__ . '/inc/acciones.php';

$page = 'caja';
$title = 'Caja';
$subtitle = 'Apertura, arqueo de efectivo y pagos digitales del turno';

$pdo = pdo();
procesar_caja($pdo, 'caja.php');
procesar_atenciones($pdo, 'caja.php');
procesar_adelantos($pdo, 'caja.php');

// ---------- Caja actual ----------
$caja = cajaActual($pdo);

// ---------- Detalle del turno ----------
$turnoAtenciones = [];
$turnoProductos = [];
if ($caja) {
    $st = $pdo->prepare("
         SELECT a.fecha, b.nombre AS barbero, mp.nombre AS metodo, mp.tipo,
           a.total_cobrado,
               (SELECT GROUP_CONCAT(CONCAT(s.nombre, IF(s2.nota IS NOT NULL, CONCAT(' — ', s2.nota), '')) SEPARATOR ' · ')
                FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
                WHERE s2.atencion_id = a.id) AS servicios
        FROM atenciones a
        JOIN barberos b ON b.id = a.barbero_id
        JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE a.fecha >= ? AND a.estado = 'activa'
        ORDER BY a.fecha DESC
    ");
    $st->execute([$caja['fecha_apertura']]);
    $turnoAtenciones = $st->fetchAll();

    $st = $pdo->prepare("
        SELECT vp.fecha, p.nombre, vp.cantidad, vp.total, mp.nombre AS metodo, mp.tipo
        FROM ventas_productos vp
        JOIN productos p ON p.id = vp.producto_id
        JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
        WHERE vp.fecha >= ?
        ORDER BY vp.fecha DESC
    ");
    $st->execute([$caja['fecha_apertura']]);
    $turnoProductos = $st->fetchAll();
}

// ---------- Historial ----------
$historial = $pdo->query("
    SELECT *, (monto_cierre - monto_esperado) AS dif
    FROM cajas
    WHERE fecha_cierre IS NOT NULL
    ORDER BY id DESC
    LIMIT 60
")->fetchAll();

// ---------- Resumen histórico ----------
$resumen = $pdo->query("
    SELECT COUNT(*) cajas,
           COALESCE(SUM(ingresos_turno),0) ingresos,
           COALESCE(SUM(efectivo_turno),0) efectivo,
           COALESCE(SUM(digital_turno),0) digital,
           COALESCE(SUM(diferencia),0) diferencia
    FROM cajas WHERE fecha_cierre IS NOT NULL
")->fetch();

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ ESTADO DE CAJA ============ -->
<?php if ($caja): ?>
<div class="card caja-strip mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
    <span class="caja-dot" title="Caja abierta"></span>
    <div>
      <div class="fw-bold">Turno en curso</div>
      <small class="text-muted">abierta <?= e(date('d/m H:i', strtotime($caja['fecha_apertura']))) ?></small>
    </div>
    <div class="caja-stat">Base<b><?= money($caja['monto_apertura']) ?></b></div>
    <div class="caja-stat">Servicios<b><?= money($caja['ingresos_turno'] - $caja['ventas_productos']) ?></b></div>
    <div class="caja-stat">Productos<b style="color:#6ee7b7"><?= money($caja['ventas_productos']) ?></b></div>
    <div class="caja-stat">Ingresos<b><?= money($caja['ingresos_turno']) ?></b></div>
    <div class="caja-stat">Efectivo esperado<b class="text-gold"><?= money($caja['efectivo_esperado']) ?></b></div>
    <div class="caja-stat">Digital<b style="color:#93c5fd"><?= money($caja['digital_esperado'] ?? 0) ?></b></div>
    <div class="ms-auto">
      <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCerrarCaja">
        <i class="bi bi-cash-coin me-1"></i>Cerrar caja
      </button>
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

<?php if ($caja): ?>
<!-- ============ DETALLE DEL TURNO ============ -->
<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-scissors me-2"></i>Atenciones del turno</div>
      <div class="card-body p-0">
        <?php if (!$turnoAtenciones): ?>
          <div class="text-center text-muted py-4">Aún no hay atenciones en este turno.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th>Hora</th><th>Barbero</th><th>Servicios</th><th>Pago</th><th class="text-end">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($turnoAtenciones as $a):
                    $hue = hueNombre($a['barbero']);
                ?>
                  <tr>
                    <td class="text-nowrap small">
                      <span class="avatar me-2" style="width:26px;height:26px;font-size:10px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($a['barbero'])) ?></span>
                      <?= e(date('H:i', strtotime($a['fecha']))) ?>
                    </td>
                    <td class="fw-semibold small"><?= e($a['barbero']) ?></td>
                    <td class="small"><?= e($a['servicios'] ?: '—') ?></td>
                    <td>
                      <span class="badge-pago <?= $a['tipo'] ?>" title="<?= e($a['metodo']) ?>">
                        <i class="bi <?= $a['tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
                      </span>
                    </td>
                    <td class="text-end fw-semibold"><?= money($a['total_cobrado']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-box-seam me-2"></i>Ventas de productos del turno</div>
      <div class="card-body p-0">
        <?php if (!$turnoProductos): ?>
          <div class="text-center text-muted py-4">Sin ventas de productos en este turno.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th>Hora</th><th>Producto</th><th class="text-end">Cant.</th><th>Pago</th><th class="text-end">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($turnoProductos as $v): ?>
                  <tr>
                    <td class="text-nowrap small"><?= e(date('H:i', strtotime($v['fecha']))) ?></td>
                    <td class="fw-semibold small"><?= e($v['nombre']) ?></td>
                    <td class="text-end small"><?= (float)$v['cantidad'] ?></td>
                    <td>
                      <span class="badge-pago <?= $v['tipo'] ?>" title="<?= e($v['metodo']) ?>">
                        <i class="bi <?= $v['tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
                      </span>
                    </td>
                    <td class="text-end fw-semibold"><?= money($v['total']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============ RESUMEN HISTÓRICO ============ -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <span class="k-icon"><i class="bi bi-wallet2"></i></span>
      <div class="k-label">Cajas cerradas</div>
      <div class="k-value"><?= (int)$resumen['cajas'] ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <span class="k-icon"><i class="bi bi-cash-stack"></i></span>
      <div class="k-label">Efectivo histórico</div>
      <div class="k-value text-gold"><?= money($resumen['efectivo']) ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-blue">
      <span class="k-icon"><i class="bi bi-bank"></i></span>
      <div class="k-label">Digital histórico</div>
      <div class="k-value" style="color:#93c5fd"><?= money($resumen['digital']) ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-green">
      <span class="k-icon"><i class="bi bi-scale"></i></span>
      <div class="k-label">Diferencia neta</div>
      <div class="k-value" style="color:<?= (float)$resumen['diferencia'] >= 0 ? '#6ee7b7' : '#fca5a5' ?>">
        <?= money($resumen['diferencia']) ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ HISTORIAL DE CAJAS ============ -->
<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-2"></i>Historial de cajas</div>
  <div class="card-body p-0">
    <?php if (!$historial): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-50"></i>
        Aún no hay cajas cerradas.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Apertura</th><th>Cierre</th><th class="text-end">Base</th>
              <th class="text-end">Efectivo turno</th><th class="text-end">Esperado</th>
              <th class="text-end">Contado</th><th class="text-end">Dif.</th>
              <th class="text-end">Digital</th><th class="text-end">Dif.</th>
              <th class="text-end">Ingresos</th><th>Notas</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $c):
                $dif = (float)$c['diferencia'];
                $dDif = (float)$c['digital_diferencia'];
            ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d/m H:i', strtotime($c['fecha_apertura']))) ?></td>
                <td class="text-nowrap small"><?= e(date('d/m H:i', strtotime($c['fecha_cierre']))) ?></td>
                <td class="text-end"><?= money($c['monto_apertura']) ?></td>
                <td class="text-end"><?= money($c['efectivo_turno']) ?></td>
                <td class="text-end text-gold"><?= money($c['monto_esperado']) ?></td>
                <td class="text-end fw-semibold"><?= money($c['monto_cierre']) ?></td>
                <td class="text-end">
                  <?php if (abs($dif) < 0.005): ?>
                    <span class="badge" style="background:rgba(52,211,153,.15);color:#6ee7b7">Exacto</span>
                  <?php else: ?>
                    <span class="badge" style="background:rgba(<?= $dif > 0 ? '212,175,55' : '248,113,113' ?>,.15);color:<?= $dif > 0 ? '#f2d06b' : '#fca5a5' ?>">
                      <?= $dif > 0 ? '+' : '' ?><?= money($dif) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= money($c['digital_declarado']) ?></td>
                <td class="text-end">
                  <?php if (abs($dDif) < 0.005): ?>
                    <span class="badge" style="background:rgba(52,211,153,.15);color:#6ee7b7">Exacto</span>
                  <?php else: ?>
                    <span class="text-muted small"><?= $dDif > 0 ? '+' : '' ?><?= money($dDif) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-semibold"><?= money($c['ingresos_turno']) ?></td>
                <td class="small text-muted"><?= e($c['notas'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>
<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
