<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/acciones.php';

$page = 'reportes';
$title = 'Reportes';
$subtitle = 'Cortes por barbero, ingresos, comparativos y horas pico';

// RNF-09: reportes globales solo para el administrador
requerir_login('admin');

$pdo = pdo();
procesar_atenciones($pdo, 'reportes.php');
procesar_caja($pdo, 'reportes.php');

// ---------- Filtros ----------
$f = [
    'desde' => $_GET['desde'] ?? date('Y-m-d'),
    'hasta' => $_GET['hasta'] ?? date('Y-m-d'),
    'barbero_id' => $_GET['barbero_id'] ?? '',
    'servicio_id' => $_GET['servicio_id'] ?? '',
    'metodo_pago_id' => $_GET['metodo_pago_id'] ?? '',
];

// Rol barbero: solo sus datos
$miBarbero = barberoSegunRol();
if ($miBarbero > 0) {
    $f['barbero_id'] = (string)$miBarbero;
}

// ---------- Exportar CSV (RF-18) ----------
if (($_GET['exportar'] ?? '') === 'csv') {
    [$where, $params] = queryAtenciones($f);
    $st = $pdo->prepare("
        SELECT a.fecha, b.nombre AS barbero,
          (SELECT GROUP_CONCAT(CONCAT(s.nombre, IF(s2.cantidad > 1, CONCAT(' x', s2.cantidad), ''), IF(s2.nota IS NOT NULL, CONCAT(' - ', s2.nota), '')) SEPARATOR '; ')
           FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
           WHERE s2.atencion_id = a.id) AS servicios,
           mp.nombre AS metodo,
           a.monto_servicios, a.monto_final, a.total_cobrado, a.comision
        FROM atenciones a
        JOIN barberos b ON b.id = a.barbero_id
        JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE {$where}
        ORDER BY a.fecha DESC
    ");
    $st->execute($params);
    $filas = $st->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM para Excel
    fputcsv($out, ['Fecha', 'Barbero', 'Servicios', 'Método de pago',
      'Subtotal', 'Total cobrado', 'Comisión', 'Ganancia']);
    foreach ($filas as $r) {
        fputcsv($out, [
            $r['fecha'], $r['barbero'], $r['servicios'] ?: '', $r['metodo'],
            (float)$r['monto_servicios'], (float)$r['total_cobrado'], (float)$r['comision'],
            (float)$r['monto_final'] - (float)$r['comision'],
        ]);
    }
    fclose($out);
    exit;
}

// ---------- Resumen del período ----------
$res = resumenPeriodo($pdo, $f['desde'], $f['hasta']);

// ---------- Comparativo vs período anterior (RF-17) ----------
$dias = max(1, (strtotime($f['hasta']) - strtotime($f['desde'])) / 86400 + 1);
$antHasta = date('Y-m-d', strtotime($f['desde'] . ' -1 day'));
$antDesde = date('Y-m-d', strtotime($f['desde'] . ' -' . $dias . ' days'));
$resAnt = resumenPeriodo($pdo, $antDesde, $antHasta);

function cmp($actual, $anterior) {
    if ($anterior <= 0) return ['pct' => $actual > 0 ? 100 : 0, 'up' => true];
    $pct = round(($actual - $anterior) / $anterior * 100, 1);
    return ['pct' => abs($pct), 'up' => $pct >= 0];
}

// ---------- Tabla diaria (RF-15) ----------
$st = $pdo->prepare("
    SELECT DATE(a.fecha) dia, COUNT(*) atenciones,
           COALESCE(SUM(a.monto_final),0) ingresos,
           COALESCE(SUM(a.comision),0) comisiones
    FROM atenciones a
    JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE a.fecha >= ? AND a.fecha < DATE_ADD(?, INTERVAL 1 DAY) AND a.estado = 'activa'
    " . ($f['barbero_id'] !== '' ? " AND a.barbero_id = " . (int)$f['barbero_id'] : '') . "
    " . ($f['metodo_pago_id'] !== '' ? " AND a.metodo_pago_id = " . (int)$f['metodo_pago_id'] : '') . "
    " . ($f['servicio_id'] !== '' ? " AND EXISTS (SELECT 1 FROM atencion_servicios s2 WHERE s2.atencion_id = a.id AND s2.servicio_id = " . (int)$f['servicio_id'] . ")" : '') . "
    GROUP BY DATE(a.fecha) ORDER BY dia DESC
");
$st->execute([$f['desde'], $f['hasta']]);
$porDia = $st->fetchAll();

// ---------- Por barbero (RF-15/17) ----------
$st = $pdo->prepare("
    SELECT b.id, b.nombre, COUNT(*) atenciones,
           COALESCE(SUM(a.monto_final),0) ingresos,
           COALESCE(SUM(a.comision),0) comisiones
    FROM atenciones a JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE a.fecha >= ? AND a.fecha < DATE_ADD(?, INTERVAL 1 DAY) AND a.estado = 'activa'
    " . ($f['barbero_id'] !== '' ? " AND a.barbero_id = " . (int)$f['barbero_id'] : '') . "
    " . ($f['metodo_pago_id'] !== '' ? " AND a.metodo_pago_id = " . (int)$f['metodo_pago_id'] : '') . "
    " . ($f['servicio_id'] !== '' ? " AND EXISTS (SELECT 1 FROM atencion_servicios s2 WHERE s2.atencion_id = a.id AND s2.servicio_id = " . (int)$f['servicio_id'] . ")" : '') . "
    GROUP BY a.barbero_id, b.nombre ORDER BY ingresos DESC
");
$st->execute([$f['desde'], $f['hasta']]);
$porBarbero = $st->fetchAll();

// ---------- Por método de pago (RF-16) ----------
$porMetodo = resumenPago($pdo, $f['desde'], $f['hasta']);

// ---------- Servicios más solicitados (RF-19) ----------
$st = $pdo->prepare("
    SELECT s.id, s.nombre, COALESCE(SUM(s2.cantidad),0) unidades,
           COALESCE(SUM(s2.cantidad * s2.precio_unit),0) ingresos,
           COUNT(DISTINCT s2.atencion_id) atenciones
    FROM atencion_servicios s2
    JOIN servicios s ON s.id = s2.servicio_id
    JOIN atenciones a ON a.id = s2.atencion_id
    WHERE a.fecha >= ? AND a.fecha < DATE_ADD(?, INTERVAL 1 DAY) AND a.estado = 'activa'
    " . ($f['barbero_id'] !== '' ? " AND a.barbero_id = " . (int)$f['barbero_id'] : '') . "
    " . ($f['metodo_pago_id'] !== '' ? " AND a.metodo_pago_id = " . (int)$f['metodo_pago_id'] : '') . "
    GROUP BY s2.servicio_id, s.nombre ORDER BY unidades DESC
");
$st->execute([$f['desde'], $f['hasta']]);
$topServicios = $st->fetchAll();

// ---------- Horas pico (RF-19) ----------
$st = $pdo->prepare("
    SELECT HOUR(a.fecha) hora, COUNT(*) c
    FROM atenciones a
    WHERE a.fecha >= ? AND a.fecha < DATE_ADD(?, INTERVAL 1 DAY) AND a.estado = 'activa'
    " . ($f['barbero_id'] !== '' ? " AND a.barbero_id = " . (int)$f['barbero_id'] : '') . "
    GROUP BY HOUR(a.fecha) ORDER BY hora
");
$st->execute([$f['desde'], $f['hasta']]);
$porHora = [];
foreach ($st as $r) $porHora[(int)$r['hora']] = (int)$r['c'];
$maxHora = max($porHora ?: [0]) ?: 1;

// ---------- Datos filtros ----------
$barberosTodos = $pdo->query("SELECT * FROM barberos ORDER BY nombre")->fetchAll();
$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$metodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();

// ---------- Caja ----------
$caja = cajaActual($pdo);

// ---------- Gráfico de líneas y métricas del reporte ----------
$diasCronologico = array_reverse($porDia);
$puntosReporte = [];
$maxDiaRep = null;
$totalIngresosRep = 0;
foreach ($diasCronologico as $d) {
    $ing = (float)$d['ingresos'];
    $totalIngresosRep += $ing;
    if (!$maxDiaRep || $ing > (float)$maxDiaRep['ingresos']) {
        $maxDiaRep = $d;
    }
    $puntosReporte[] = [
        'label' => date('d/m', strtotime($d['dia'])),
        'val' => $ing,
    ];
}
$diasContados = max(1, count($puntosReporte));
$promedioDiarioRep = $totalIngresosRep / $diasContados;
$ticketPromedioRep = (int)$res['atenciones'] > 0 ? (float)$res['ingresos'] / (int)$res['atenciones'] : 0;
$chartReporteSvg = generar_grafico_lineas_svg($puntosReporte, ['chartId' => 'repIngresos', 'width' => 760, 'height' => 260]);

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ FILTROS ============ -->
<div class="filtros no-print">
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
      <label class="form-label">Método de pago</label>
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
      <a href="reportes.php" class="btn btn-outline-light"><i class="bi bi-x-lg"></i></a>
    </div>
    <div class="col-auto d-grid">
      <a href="reportes.php?exportar=csv&<?= http_build_query($f) ?>" class="btn btn-outline-success" title="Exportar CSV (Excel)">
        <i class="bi bi-file-earmark-excel me-1"></i>CSV
      </a>
    </div>
    <div class="col-auto d-grid">
      <button class="btn btn-outline-info" data-imprimir title="Imprimir / guardar como PDF">
        <i class="bi bi-printer me-1"></i>Imprimir
      </button>
    </div>
  </form>
</div>

<!-- ============ RESUMEN + COMPARATIVO ============ -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <span class="k-icon"><i class="bi bi-scissors"></i></span>
      <div class="k-label">Cortes del Período</div>
      <div class="k-value"><?= (int)$res['atenciones'] ?></div>
      <div class="k-sub">
        <?php $c = cmp((float)$res['atenciones'], (float)$resAnt['atenciones']); ?>
        <span class="trend <?= $c['up'] ? 'up' : 'down' ?>"><?= $c['up'] ? '▲' : '▼' ?> <?= $c['pct'] ?>%</span> vs período anterior
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <span class="k-icon"><i class="bi bi-cash-stack"></i></span>
      <div class="k-label">Facturación Total</div>
      <div class="k-value text-gold"><?= money($res['ingresos']) ?></div>
      <div class="k-sub">
        <?php $c = cmp((float)$res['ingresos'], (float)$resAnt['ingresos']); ?>
        <span class="trend <?= $c['up'] ? 'up' : 'down' ?>"><?= $c['up'] ? '▲' : '▼' ?> <?= $c['pct'] ?>%</span>
        <span class="text-muted">(antes <?= money($resAnt['ingresos']) ?>)</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-blue">
      <span class="k-icon"><i class="bi bi-person-badge"></i></span>
      <div class="k-label">Pago a Barberos</div>
      <div class="k-value"><?= money($res['comisiones']) ?></div>
      <div class="k-sub">Comisiones del período</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi k-green">
      <span class="k-icon"><i class="bi bi-piggy-bank"></i></span>
      <div class="k-label">Ganancia Barbería</div>
      <div class="k-value" style="color:#6ee7b7"><?= money($res['ganancia']) ?></div>
      <div class="k-sub">
        <?php $c = cmp((float)$res['ganancia'], (float)$resAnt['ganancia']); ?>
        <span class="trend <?= $c['up'] ? 'up' : 'down' ?>"><?= $c['up'] ? '▲' : '▼' ?> <?= $c['pct'] ?>%</span> vs período anterior
      </div>
    </div>
  </div>
</div>

<!-- ============ EVOLUCIÓN GRÁFICA DE INGRESOS (LÍNEA Y MÉTRICAS) ============ -->
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <i class="bi bi-graph-up text-gold me-2"></i>
      <strong>Evolución y Curva de Ingresos</strong>
      <span class="text-muted ms-2 small d-none d-md-inline">Comportamiento diario de la facturación en el período</span>
    </div>
    <span class="badge" style="background:rgba(212,175,55,0.15);color:var(--gold);border:1px solid rgba(212,175,55,0.3)">
      <?= e(date('d/m/Y', strtotime($f['desde']))) ?> al <?= e(date('d/m/Y', strtotime($f['hasta']))) ?>
    </span>
  </div>
  <div class="card-body">
    <!-- Métricas clave del gráfico -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06)">
          <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:0.5px">Total Facturado</div>
          <div class="fs-4 fw-bold text-gold mt-1"><?= money($res['ingresos']) ?></div>
          <small class="text-muted"><?= count($porDia) ?> días con facturación</small>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06)">
          <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:0.5px">Promedio Diario</div>
          <div class="fs-4 fw-bold text-white mt-1"><?= money($promedioDiarioRep) ?></div>
          <small class="text-muted">Ingreso medio por jornada</small>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06)">
          <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:0.5px">Ticket Promedio</div>
          <div class="fs-4 fw-bold mt-1" style="color:#38bdf8"><?= money($ticketPromedioRep) ?></div>
          <small class="text-muted">Ingreso promedio por atención</small>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06)">
          <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:0.5px">Día con Mayor Venta</div>
          <div class="fs-4 fw-bold mt-1" style="color:#6ee7b7"><?= $maxDiaRep ? money($maxDiaRep['ingresos']) : '$0' ?></div>
          <small class="text-muted"><?= $maxDiaRep ? e(date('D d/m/Y', strtotime($maxDiaRep['dia']))) : 'Sin datos' ?></small>
        </div>
      </div>
    </div>

    <!-- Gráfico de líneas SVG -->
    <div class="chart-container" style="min-height:240px">
      <?php if (empty($puntosReporte)): ?>
        <div class="text-center text-muted py-5">No hay movimientos registrados en el rango de fechas seleccionado.</div>
      <?php else: ?>
        <?= $chartReporteSvg ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ TABLA DIARIA + RANKING ============ -->
<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-calendar3 me-2"></i><strong>Desglose Detallado por Día</strong></div>
        <small class="text-muted"><?= count($porDia) ?> registros</small>
      </div>
      <div class="card-body p-0">
        <?php if (!$porDia): ?>
          <div class="text-center text-muted py-5">Sin datos en el período.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th>Día</th>
                  <th class="text-end">Atenciones</th>
                  <th class="text-end">Ingresos</th>
                  <th class="text-end">Comisiones</th>
                  <th class="text-end">Ganancia Local</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($porDia as $d): ?>
                  <tr>
                    <td class="fw-semibold"><?= e(date('D d/m', strtotime($d['dia']))) ?></td>
                    <td class="text-end"><span class="badge bg-secondary-subtle text-light"><?= (int)$d['atenciones'] ?></span></td>
                    <td class="text-end fw-semibold text-gold"><?= money($d['ingresos']) ?></td>
                    <td class="text-end" style="color:#93c5fd"><?= money($d['comisiones']) ?></td>
                    <td class="text-end fw-semibold" style="color:#6ee7b7"><?= money((float)$d['ingresos'] - (float)$d['comisiones']) ?></td>
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
      <div class="card-header"><i class="bi bi-trophy me-2"></i>Ranking de productividad (RF-17)</div>
      <div class="card-body">
        <?php if (!$porBarbero): ?>
          <p class="text-muted mb-0">Sin datos.</p>
        <?php else: ?>
          <?php $maxIng = max(array_map(fn($b) => (float)$b['ingresos'], $porBarbero)) ?: 1; ?>
          <?php $i = 1; foreach ($porBarbero as $b):
              $hue = hueNombre($b['nombre']);
          ?>
            <div class="d-flex align-items-center gap-3 mb-4<?= $i === count($porBarbero) ? ' mb-0' : '' ?>">
              <span class="rank<?= $i === 1 ? ' r1' : '' ?>"><?= $i ?></span>
              <span class="avatar" style="width:40px;height:40px;font-size:14px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($b['nombre'])) ?></span>
              <div class="flex-grow-1" style="min-width:0">
                <div class="d-flex justify-content-between align-items-baseline mb-1">
                  <strong class="small"><?= e($b['nombre']) ?></strong>
                  <span class="text-muted" style="font-size:11.5px"><?= (int)$b['atenciones'] ?> atenciones · <?= money($b['ingresos']) ?></span>
                </div>
                <div class="progress"><div class="progress-bar" style="width:<?= max(3, round((float)$b['ingresos'] / $maxIng * 100)) ?>%"></div></div>
              </div>
              <div class="text-end" style="min-width:76px">
                <div class="text-gold fw-bold"><?= money($b['comisiones']) ?></div>
                <small class="text-muted">comisión</small>
              </div>
            </div>
          <?php $i++; endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ SERVICIOS + METODOS + HORAS ============ -->
<div class="row g-4 mb-4">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-list-task me-2"></i>Servicios más solicitados (RF-19)</div>
      <div class="card-body p-0">
        <?php if (!$topServicios): ?>
          <div class="text-center text-muted py-5">Sin datos.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th>Servicio</th><th class="text-end">Unidades</th><th class="text-end">Atenciones</th><th class="text-end">Ingresos</th></tr>
              </thead>
              <tbody>
                <?php foreach ($topServicios as $s): ?>
                  <tr>
                    <td class="fw-semibold small"><?= e($s['nombre']) ?></td>
                    <td class="text-end"><?= (int)$s['unidades'] ?></td>
                    <td class="text-end text-muted"><?= (int)$s['atenciones'] ?></td>
                    <td class="text-end"><?= money($s['ingresos']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-credit-card me-2"></i>Por método de pago (RF-16)</div>
      <div class="card-body">
        <?php if (!$porMetodo): ?>
          <p class="text-muted mb-0">Sin datos.</p>
        <?php else: ?>
          <?php $maxM = max(array_map(fn($m) => (float)$m['monto'], $porMetodo)) ?: 1; ?>
          <?php foreach ($porMetodo as $m): ?>
            <div class="mb-4<?= $m === end($porMetodo) ? ' mb-0' : '' ?>">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">
                  <i class="bi <?= $m['tipo'] === 'efectivo' ? 'bi-cash-coin text-success' : 'bi-bank text-primary' ?> me-1"></i><?= e($m['nombre']) ?>
                </span>
                <span class="text-muted small"><?= (int)$m['atenciones'] ?> atenc. · <?= money($m['monto']) ?></span>
              </div>
              <div class="progress" style="height:10px">
                <div class="progress-bar" style="width:<?= max(3, round((float)$m['monto'] / $maxM * 100)) ?>%;background:<?= $m['tipo'] === 'efectivo' ? 'linear-gradient(90deg,#6ee7b7,#34d399)' : 'linear-gradient(90deg,#93c5fd,#60a5fa)' ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock me-2"></i>Horas pico (RF-19)</div>
      <div class="card-body">
        <?php if (!$porHora): ?>
          <p class="text-muted mb-0">Sin datos.</p>
        <?php else: ?>
          <?php for ($h = 0; $h <= 23; $h++):
              $c = $porHora[$h] ?? 0;
              if ($c === 0) continue;
          ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="small text-muted text-end" style="width:44px;flex-shrink:0"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</span>
              <div class="flex-grow-1">
                <div class="progress" style="height:14px">
                  <div class="progress-bar" style="width:<?= max(4, round($c / $maxHora * 100)) ?>%"></div>
                </div>
              </div>
              <span class="small fw-semibold" style="width:26px;text-align:right;flex-shrink:0"><?= $c ?></span>
            </div>
          <?php endfor; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ DETALLE DE ATENCIONES ============ -->
<div class="card">
  <div class="card-header"><i class="bi bi-list-ul me-2"></i>Detalle de atenciones del período</div>
  <div class="card-body p-0">
    <?php
    [$where, $params] = queryAtenciones($f);
    $st = $pdo->prepare("
        SELECT a.fecha, b.nombre AS barbero, mp.nombre AS metodo,
          (SELECT GROUP_CONCAT(CONCAT(s.nombre, IF(s2.cantidad > 1, CONCAT(' x', s2.cantidad), ''), IF(s2.nota IS NOT NULL, CONCAT(' — ', s2.nota), '')) SEPARATOR ' · ')
           FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
           WHERE s2.atencion_id = a.id) AS servicios,
          a.total_cobrado, a.comision
        FROM atenciones a
        JOIN barberos b ON b.id = a.barbero_id
        JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE {$where}
        ORDER BY a.fecha DESC LIMIT 500
    ");
    $st->execute($params);
    $detalle = $st->fetchAll();
    ?>
    <?php if (!$detalle): ?>
      <div class="text-center text-muted py-5">Sin atenciones en el período.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Fecha</th><th>Barbero</th><th>Servicios</th><th>Método</th><th class="text-end">Total</th><th class="text-end">Comisión</th></tr>
          </thead>
          <tbody>
            <?php foreach ($detalle as $a): ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d/m H:i', strtotime($a['fecha']))) ?></td>
                <td class="fw-semibold small"><?= e($a['barbero']) ?></td>
                <td class="small"><?= e($a['servicios'] ?: '—') ?></td>
                <td class="small"><?= e($a['metodo']) ?></td>
                <td class="text-end fw-semibold"><?= money($a['total_cobrado']) ?></td>
                <td class="text-end text-gold"><?= money($a['comision']) ?></td>
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
