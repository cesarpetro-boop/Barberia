<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login();

// El dashboard resume el negocio completo (todas las cajas, todos los barberos):
// es una pantalla de administración, no del turno del barbero.
if (esRol('barbero')) {
    redirect('atenciones.php');
}

require_once __DIR__ . '/inc/acciones.php';

$page = 'dashboard';
$title = 'Dashboard';
$subtitle = 'Resumen en vivo del día de hoy';

$pdo = pdo();
procesar_atenciones($pdo, 'index.php');
procesar_caja($pdo, 'index.php');
procesar_adelantos($pdo, 'index.php');

// ---------- Resumen de hoy ----------
$hoy = $pdo->query("
    SELECT COUNT(*) atenciones,
           COALESCE(SUM(monto_final),0) ingresos,
           COALESCE(SUM(comision),0) comisiones,
           COALESCE(SUM(total_cobrado),0) cobrado
    FROM atenciones WHERE DATE(fecha) = CURDATE() AND estado = 'activa'
")->fetch();

$prodHoy = $pdo->query("
    SELECT COUNT(*) ventas,
           COALESCE(SUM(total),0) ingresos,
           COALESCE(SUM(cantidad * costo_unit),0) costo
    FROM ventas_productos WHERE DATE(fecha) = CURDATE()
")->fetch();

// Ganancia de la casa: servicios + productos − comisiones − costo de productos.
$gananciaHoy = (float)$hoy['ingresos'] + (float)$prodHoy['ingresos']
    - (float)$hoy['comisiones'] - (float)$prodHoy['costo'];

// ---------- Tendencia vs ayer ----------
$ayer = $pdo->query("
    SELECT COALESCE(SUM(monto_final),0) s FROM atenciones
    WHERE DATE(fecha) = CURDATE() - INTERVAL 1 DAY AND estado = 'activa'
")->fetchColumn();
$ayerP = $pdo->query("SELECT COALESCE(SUM(total),0) FROM ventas_productos WHERE DATE(fecha) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();
$ingresosHoy = (float)$hoy['ingresos'] + (float)$prodHoy['ingresos'];
$ingresosAyer = (float)$ayer + (float)$ayerP;
$trend = $ingresosAyer > 0 ? round(($ingresosHoy - $ingresosAyer) / $ingresosAyer * 100, 1) : ($ingresosHoy > 0 ? 100 : 0);
$trendUp = $trend >= 0;

// ---------- Métricas adicionales ----------
// Ticket promedio de servicios del día
$ticketPromedio = (int)$hoy['atenciones'] > 0
    ? round((float)$hoy['ingresos'] / (int)$hoy['atenciones'], 0)
    : 0;
// Progreso del día vs promedio 7 días (excluyendo hoy para no distorsionar)
$promedioDiariosAyer = $promedioDiario7 ?? 0; // se calcula más adelante, se reusa
// Porcentaje del ingreso de hoy respecto al promedio semanal (se fijará post-cálculo de $days)

// ---------- Atenciones de hoy ----------
$listaHoy = $pdo->query("
    SELECT a.*, b.nombre AS barbero, mp.nombre AS metodo_nombre, mp.tipo AS metodo_tipo,
      (SELECT GROUP_CONCAT(CONCAT(s.nombre, IF(s2.cantidad > 1, CONCAT(' ×', s2.cantidad), ''), IF(s2.nota IS NOT NULL, CONCAT(' — ', s2.nota), '')) SEPARATOR ' · ')
       FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
       WHERE s2.atencion_id = a.id) AS servicios_txt
    FROM atenciones a
    JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE DATE(a.fecha) = CURDATE() AND a.estado = 'activa'
    ORDER BY a.fecha DESC, a.id DESC
")->fetchAll();
$totalCobradoHoy = array_sum(array_map(fn($a) => (float)$a['total_cobrado'], $listaHoy));
$totalComHoy = array_sum(array_map(fn($a) => (float)$a['comision'], $listaHoy));

// ---------- Ranking de barberos del día ----------
$topBarberos = $pdo->query("
    SELECT b.id, b.nombre, b.tipo_comision, b.comision_valor,
           COUNT(*) AS cortes,
           COALESCE(SUM(a.monto_final),0) AS ingresos,
           COALESCE(SUM(a.comision),0) AS comisiones
    FROM atenciones a JOIN barberos b ON b.id = a.barbero_id
    WHERE DATE(a.fecha) = CURDATE() AND a.estado = 'activa'
    GROUP BY a.barbero_id, b.nombre, b.tipo_comision, b.comision_valor
    ORDER BY ingresos DESC
")->fetchAll();

// ---------- Últimos 7 días ----------
$ingresosPorDia = [];
foreach ($pdo->query("SELECT DATE(fecha) d, COALESCE(SUM(monto_final),0) m FROM atenciones WHERE fecha >= CURDATE() - INTERVAL 6 DAY AND estado = 'activa' GROUP BY DATE(fecha)") as $r) {
    $ingresosPorDia[$r['d']] = (float)$r['m'];
}
foreach ($pdo->query("SELECT DATE(fecha) d, COALESCE(SUM(total),0) m FROM ventas_productos WHERE fecha >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(fecha)") as $r) {
    $ingresosPorDia[$r['d']] = ($ingresosPorDia[$r['d']] ?? 0) + (float)$r['m'];
}
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[] = ['d' => $d, 'm' => $ingresosPorDia[$d] ?? 0];
}

// ---------- Inventario ----------
$inv = $pdo->query("
    SELECT COUNT(*) AS productos,
           COALESCE(SUM(stock * costo), 0) AS valor,
           COALESCE(SUM(stock), 0) AS unidades
    FROM productos WHERE activo = 1
")->fetch();
$bajos = $pdo->query("SELECT * FROM productos WHERE stock <= stock_min AND activo = 1 ORDER BY stock ASC")->fetchAll();

// ---------- Datos para el formulario ----------
$barberos = $pdo->query("SELECT * FROM barberos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY es_otro_valor, nombre")->fetchAll();
$metodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();

// ---------- Caja ----------
$caja = cajaActual($pdo);

/* ============================================================
   GRÁFICO DE LÍNEAS — ingresos últimos 7 días + métricas
   ============================================================ */
$puntosDashboard = [];
$total7Dias = 0;
$max7Dia = $days[0];
foreach ($days as $i => $d) {
    $total7Dias += (float)$d['m'];
    if ((float)$d['m'] > (float)$max7Dia['m']) {
        $max7Dia = $d;
    }
    $esHoy = ($i === count($days) - 1);
    $puntosDashboard[] = [
        'label' => $esHoy ? 'Hoy' : date('D d', strtotime($d['d'])),
        'val' => (float)$d['m'],
    ];
}
$promedioDiario7 = $total7Dias / max(1, count($days));
// Progreso de hoy vs promedio (para la barra de progreso en KPI)
$progresoHoyPct = $promedioDiario7 > 0 ? min(150, round($ingresosHoy / $promedioDiario7 * 100)) : ($ingresosHoy > 0 ? 100 : 0);
$chartSvg = generar_grafico_lineas_svg($puntosDashboard, ['chartId' => 'dashIngresos', 'width' => 740, 'height' => 260]);

/* ============================================================
   DONA SVG — participación de barberos del día
   ============================================================ */
$participacion = [];
foreach ($topBarberos as $b) {
    $participacion[] = [
        'nombre' => $b['nombre'],
        'ingresos' => (float)$b['ingresos'],
        'comision' => (float)$b['comisiones'],
        'cortes' => (int)$b['cortes'],
        'hue' => hueNombre($b['nombre']),
        'share' => (float)$hoy['ingresos'] > 0 ? (float)$b['ingresos'] / (float)$hoy['ingresos'] : 0,
    ];
}

$R = 52;
$CIRC = 2 * M_PI * $R;
$off = 0;
$donutSegs = '';
foreach ($participacion as $p) {
    if ($p['share'] <= 0) continue;
    $len = $p['share'] * $CIRC;
    $donutSegs .= '<circle cx="90" cy="90" r="' . $R . '" fill="none" stroke="hsl(' . $p['hue'] . ',62%,52%)" stroke-width="24" stroke-dasharray="' . $len . ' ' . ($CIRC - $len) . '" stroke-dashoffset="' . (-$off) . '" transform="rotate(-90 90 90)" opacity="0.92"><title>' . e($p['nombre']) . ' — ' . money($p['ingresos']) . ' (' . round($p['share'] * 100) . '%)</title></circle>';
    $off += $len;
}
if ($donutSegs === '') {
    $donutSegs = '<circle cx="90" cy="90" r="' . $R . '" fill="none" stroke="#232a3d" stroke-width="24"/>';
}

$donutSvg = '<svg viewBox="0 0 180 180" class="donut-svg" role="img" aria-label="Participación por barbero">
  <circle cx="90" cy="90" r="' . ($R - 14) . '" fill="#141826"/>
  ' . $donutSegs . '
  <text x="90" y="84" text-anchor="middle" font-size="28" font-weight="900" fill="#ffffff">' . (int)$hoy['atenciones'] . '</text>
  <text x="90" y="106" text-anchor="middle" font-size="11" font-weight="700" fill="#cbd5e1" letter-spacing="0.08em">ATENCIONES</text>
</svg>';

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ SALUDO ============ -->
<div class="dash-greeting animate-in" style="--delay:0ms">
  <div class="greeting-left">
    <div class="greeting-emoji"><?= (int)$hoy['atenciones'] > 5 ? '🔥' : ((int)$hoy['atenciones'] > 0 ? '✂️' : '👋') ?></div>
    <div>
      <h2 class="greeting-title">
        <?= (int)$hoy['atenciones'] > 0 ? '¡Vamos con todo!' : '¡Listo para empezar!' ?>
      </h2>
      <p class="greeting-sub">
        <?= e(date('l d/m/Y')) ?>
        <?php if ((int)$hoy['atenciones'] > 0): ?>
          &nbsp;·&nbsp; <strong><?= (int)$hoy['atenciones'] ?></strong> atención<?= (int)$hoy['atenciones'] === 1 ? '' : 'es' ?> hoy
        <?php endif; ?>
        &nbsp;·&nbsp; <span id="reloj-live" class="reloj-live"></span>
      </p>
    </div>
  </div>
  <div class="greeting-actions">
    <a href="historial.php" class="btn-action-ghost"><i class="bi bi-clock-history"></i><span>Historial</span></a>
    <a href="reportes.php" class="btn-action-ghost"><i class="bi bi-graph-up-arrow"></i><span>Reportes</span></a>
    <a href="atenciones.php#registrar" class="btn-gold"><i class="bi bi-plus-lg me-1"></i>Nueva atención</a>
  </div>
</div>

<!-- ============ KPI DEL DIA ============ -->
<div class="row g-3 mb-4">

  <!-- KPI 1: Cortes -->
  <div class="col-6 col-xl-3">
    <div class="kpi kpi-animated animate-in" style="--delay:80ms">
      <span class="k-icon"><i class="bi bi-scissors"></i></span>
      <div class="k-label">Cortes del D&iacute;a</div>
      <div class="k-value" data-count-int="<?= (int)$hoy['atenciones'] ?>"><?= (int)$hoy['atenciones'] ?></div>
      <div class="k-sub">
        <?php if ((int)$prodHoy['ventas'] > 0): ?>
          <span class="kpi-tag kpi-tag-blue"><i class="bi bi-box-seam me-1"></i><?= (int)$prodHoy['ventas'] ?> prod.</span>
        <?php else: ?>
          <span class="text-muted">Solo servicios hoy</span>
        <?php endif; ?>
      </div>
      <div class="kpi-sparkline">
        <div class="kpi-bar-bg"><div class="kpi-bar-fill kpi-bar-gold" style="width:<?= min(100, (int)$hoy['atenciones'] * 10) ?>%"></div></div>
      </div>
    </div>
  </div>

  <!-- KPI 2: Ingresos -->
  <div class="col-6 col-xl-3">
    <div class="kpi kpi-animated animate-in" style="--delay:160ms">
      <span class="k-icon"><i class="bi bi-cash-stack"></i></span>
      <div class="k-label">Ingresos del D&iacute;a</div>
      <div class="k-value text-gold" data-count-money="<?= round($ingresosHoy) ?>"><?= money($ingresosHoy) ?></div>
      <div class="k-sub">
        <span class="trend <?= $trendUp ? 'up' : 'down' ?>"><?= $trendUp ? '&#9650; +' : '&#9660; -' ?><?= abs($trend) ?>%</span>
        vs ayer
      </div>
      <div class="kpi-sparkline">
        <div class="kpi-bar-bg">
          <div class="kpi-bar-fill kpi-bar-gold" style="width:<?= $progresoHoyPct ?>%"></div>
        </div>
        <span class="kpi-bar-label"><?= $progresoHoyPct ?>% del promedio sem.</span>
      </div>
    </div>
  </div>

  <!-- KPI 3: Ticket promedio -->
  <div class="col-6 col-xl-3">
    <div class="kpi kpi-animated k-blue animate-in" style="--delay:240ms">
      <span class="k-icon"><i class="bi bi-receipt"></i></span>
      <div class="k-label">Ticket Promedio</div>
      <div class="k-value" style="color:#93c5fd" data-count-money="<?= round($ticketPromedio) ?>"><?= money($ticketPromedio) ?></div>
      <div class="k-sub">
        <?php if ((int)$hoy['atenciones'] > 0): ?>
          por atenci&oacute;n &middot; com. <?= money($hoy['comisiones']) ?>
        <?php else: ?>
          Sin atenciones a&uacute;n
        <?php endif; ?>
      </div>
      <div class="kpi-sparkline">
        <div class="kpi-bar-bg"><div class="kpi-bar-fill kpi-bar-blue" style="width:<?= $promedioDiario7 > 0 ? min(100, round($ingresosHoy / $promedioDiario7 * 100)) : 0 ?>%"></div></div>
      </div>
    </div>
  </div>

  <!-- KPI 4: Ganancia -->
  <div class="col-6 col-xl-3">
    <div class="kpi kpi-animated k-green animate-in" style="--delay:320ms">
      <span class="k-icon"><i class="bi bi-piggy-bank"></i></span>
      <div class="k-label">Ganancia Neta</div>
      <div class="k-value" style="color:#34d399" data-count-money="<?= round($gananciaHoy) ?>"><?= money($gananciaHoy) ?></div>
      <div class="k-sub">
        <span class="kpi-tag kpi-tag-green">Libre</span>
        Ingresos &minus; com. &minus; costos
      </div>
      <div class="kpi-sparkline">
        <div class="kpi-bar-bg"><div class="kpi-bar-fill kpi-bar-green" style="width:<?= $ingresosHoy > 0 ? min(100, round($gananciaHoy / $ingresosHoy * 100)) : 0 ?>%"></div></div>
        <span class="kpi-bar-label"><?= $ingresosHoy > 0 ? round($gananciaHoy / $ingresosHoy * 100) : 0 ?>% de margen</span>
      </div>
    </div>
  </div>

</div>
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
      <a href="caja.php" class="btn btn-outline-warning me-2"><i class="bi bi-wallet2 me-1"></i>Ver caja</a>
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

<!-- ============ GRÁFICO DE LÍNEAS + MÉTRICAS ============ -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up me-2 text-gold"></i>Tendencia de ingresos · Últimos 7 días</span>
        <span class="badge bg-warning bg-opacity-25 text-gold border border-warning border-opacity-25">Gráfico de líneas</span>
      </div>
      <div class="card-body">
        <!-- Métricas clave resumen -->
        <div class="row g-2 mb-3 p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
          <div class="col-6 col-sm-3">
            <span class="d-block small" style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase">Total 7 días</span>
            <strong class="text-gold" style="font-size:17px"><?= money($total7Dias) ?></strong>
          </div>
          <div class="col-6 col-sm-3">
            <span class="d-block small" style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase">Promedio diario</span>
            <strong style="color:#ffffff;font-size:17px"><?= money($promedioDiario7) ?></strong>
          </div>
          <div class="col-6 col-sm-3">
            <span class="d-block small" style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase">Día pico</span>
            <strong style="color:#34d399;font-size:17px"><?= date('D d', strtotime($max7Dia['d'])) ?> (<?= money($max7Dia['m']) ?>)</strong>
          </div>
          <div class="col-6 col-sm-3">
            <span class="d-block small" style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase">Tendencia hoy</span>
            <span class="trend <?= $trendUp ? 'up' : 'down' ?>" style="font-size:12px"><?= $trendUp ? '▲ +' : '▼ -' ?><?= abs($trend) ?>%</span>
          </div>
        </div>

        <?= $chartSvg ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Participación del día</div>
      <div class="card-body">
        <div class="d-flex justify-content-center mb-3">
          <?= $donutSvg ?>
        </div>
        <?php if (!$participacion): ?>
          <p class="text-muted text-center mb-0 small">Sin atenciones hoy.<br>La participación se verá aquí.</p>
        <?php else: ?>
          <?php foreach ($participacion as $p): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="dot" style="background:hsl(<?= $p['hue'] ?>,62%,52%)"></span>
              <span class="flex-grow-1 small text-truncate" title="<?= e($p['nombre']) ?>"><?= e($p['nombre']) ?></span>
              <span class="text-muted small"><?= round($p['share'] * 100) ?>%</span>
              <span class="fw-semibold small"><?= money($p['ingresos']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ REGISTRO + ATENCIONES DE HOY ============ -->
<div class="row g-4 mb-4 align-items-start">
  <div class="col-xl-5">
    <?php $formId = 'corte-dash'; $redirectUrl = 'index.php'; ?>
    <?php require __DIR__ . '/inc/form_atencion.php'; ?>
  </div>

  <div class="col-xl-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-scissors me-2"></i>Atenciones de hoy</span>
        <span class="badge" style="background:rgba(212,175,55,.15);color:var(--gold2)"><?= count($listaHoy) ?></span>
      </div>
      <div class="today-list">
        <?php if (!$listaHoy): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-scissors fs-1 d-block mb-2 opacity-50"></i>
            Aún no hay atenciones hoy.<br>
            <small>Registra la primera con el formulario de la izquierda.</small>
          </div>
        <?php endif; ?>
        <?php foreach ($listaHoy as $a):
            $hue = hueNombre($a['barbero']);
        ?>
          <div class="today-row">
            <span class="avatar" style="width:38px;height:38px;font-size:13px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($a['barbero'])) ?></span>
            <span class="badge-pago <?= $a['metodo_tipo'] ?>" title="<?= e($a['metodo_nombre']) ?>">
              <i class="bi <?= $a['metodo_tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
            </span>
            <div class="t-info">
              <div class="t-serv"><?= e($a['servicios_txt'] ?: '—') ?> · <?= e($a['barbero']) ?></div>
              <div class="t-client"><?= e(date('H:i', strtotime($a['fecha']))) ?></div>
            </div>
            <span class="t-time"><?= e(date('H:i', strtotime($a['fecha']))) ?></span>
            <div>
              <div class="t-monto"><?= money($a['total_cobrado']) ?></div>
              <div class="t-com">com. <?= money($a['comision']) ?></div>
            </div>
            <div class="d-flex gap-1">
              <a href="atenciones.php?editar=<?= (int)$a['id'] ?>#registrar" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
              <?php if (esRol('admin')): ?>
              <button class="btn btn-sm btn-outline-danger" data-anular="<?= (int)$a['id'] ?>" title="Anular (solo admin)"><i class="bi bi-x-lg"></i></button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($listaHoy): ?>
        <div class="card-footer d-flex justify-content-between align-items-center" style="background:var(--card2);border-top:1px solid var(--border);border-radius:0 0 var(--radius) var(--radius)">
          <span class="text-muted small fw-semibold">TOTAL DEL DÍA</span>
          <div class="text-end">
            <span class="fw-bold me-3"><?= money($totalCobradoHoy) ?></span>
            <span class="text-gold fw-bold">com. <?= money($totalComHoy) ?></span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ RANKING + DESABASTO ============ -->
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-trophy me-2"></i>Ranking de barberos · hoy</div>
      <div class="card-body">
        <?php if (!$topBarberos): ?>
          <p class="text-muted mb-0">Sin atenciones registradas hoy — el ranking aparecerá aquí.</p>
        <?php endif; ?>
        <?php $i = 1; foreach ($topBarberos as $b):
            $share = (float)$hoy['ingresos'] > 0 ? (float)$b['ingresos'] / (float)$hoy['ingresos'] * 100 : 0;
            $hue = hueNombre($b['nombre']);
        ?>
          <div class="d-flex align-items-center gap-3 mb-4<?= $i === count($topBarberos) ? ' mb-0' : '' ?>">
            <span class="rank<?= $i === 1 ? ' r1' : '' ?>"><?= $i ?></span>
            <span class="avatar" style="width:40px;height:40px;font-size:14px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($b['nombre'])) ?></span>
            <div class="flex-grow-1" style="min-width:0">
              <div class="d-flex justify-content-between align-items-baseline mb-1">
                <strong class="small"><?= e($b['nombre']) ?></strong>
                <span class="text-muted" style="font-size:11.5px"><?= (int)$b['cortes'] ?> atención<?= (int)$b['cortes'] === 1 ? '' : 'es' ?> · <?= money($b['ingresos']) ?></span>
              </div>
              <div class="progress"><div class="progress-bar" style="width:<?= max(3, round($share)) ?>%"></div></div>
            </div>
            <div class="text-end" style="min-width:76px">
              <div class="text-gold fw-bold"><?= money($b['comisiones']) ?></div>
              <small class="text-muted">comisión</small>
            </div>
          </div>
        <?php $i++; endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-2"></i>Alerta de desabasto</span>
        <span class="badge" style="background:rgba(248,113,113,.15);color:#fca5a5"><?= count($bajos) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (!$bajos): ?>
          <div class="text-center py-4">
            <i class="bi bi-check-circle fs-2 d-block mb-2" style="color:#6ee7b7"></i>
            <span class="text-muted">Todo abastecido — ningún producto está bajo su mínimo.</span>
          </div>
        <?php else: ?>
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th><th class="text-end">Estado</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($bajos as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['nombre']) ?></td>
                  <td class="text-end fw-bold"><?= (float)$p['stock'] ?></td>
                  <td class="text-end text-muted"><?= (float)$p['stock_min'] ?></td>
                  <td class="text-end">
                    <?php if ((float)$p['stock'] <= 0): ?>
                      <span class="badge bg-danger">Agotado</span>
                    <?php else: ?>
                      <span class="badge" style="background:rgba(212,175,55,.15);color:var(--gold2)">Bajo</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a href="productos.php" class="btn btn-sm btn-outline-info"><i class="bi bi-boxes me-1"></i>Reponer</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>
<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
