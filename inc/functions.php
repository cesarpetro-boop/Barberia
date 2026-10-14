<?php
/**
 * Funciones auxiliares
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/** Escapa texto para salida HTML */
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Elimina los ceros sobrantes de una cifra con decimales (17,000.00 → 17,000 · 1,234.50 → 1,234.5).
 * Si no tiene punto decimal no hace nada (17000 se conserva entero).
 */
function quitarCeros($s) {
    $s = (string)$s;
    if (strpos($s, '.') === false) return $s;
    $s = rtrim($s, '0');
    return rtrim($s, '.');
}

/** Formatea dinero (sin los dos ceros decimales sobrantes) */
function money($n) {
    return '$ ' . quitarCeros(number_format((float)$n, 2));
}

/** Fecha/hora actual */
function ahora() {
    return date('Y-m-d H:i');
}

/** Mensaje flash */
function flash($msg, $type = 'success') {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

/** Muestra y limpia los flashes */
function render_flash() {
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
           . e($f['msg'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
    unset($_SESSION['flash']);
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Enlace a un estático con versión por fecha de modificación.
 * Evita que el navegador sirva CSS/JS viejos desde la caché (Ctrl+F5 manual).
 */
function asset(string $ruta): string {
    $abs = dirname(__DIR__) . '/' . str_replace('\\', '/', $ruta);
    $v = @filemtime($abs) ?: 1;
    return e($ruta . '?v=' . $v);
}

/** ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_input() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check() {
    $ok = !empty($_POST['csrf']) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
}

/** Iniciales para avatares */
function iniciales($nombre) {
    $parts = preg_split('/\s+/', trim((string)$nombre));
    $ini = '';
    foreach ($parts as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'));
        if (mb_strlen($ini) >= 2) break;
    }
    return $ini ?: '?';
}

/** Tono de color (0-359) derivado del nombre */
function hueNombre($nombre) {
    $n = (string)$nombre;
    $h = 0;
    for ($i = 0; $i < strlen($n); $i++) {
        $h = ($h * 31 + ord($n[$i])) % 360;
    }
    return $h;
}

/** Código de atención: A-000123 */
function codigoAtencion($id) {
    return 'A-' . str_pad((int)$id, 6, '0', STR_PAD_LEFT);
}

/** Formatea bytes legibles */
function formatoBytes(int $n): string {
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = $n;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }
    return round($v, 1) . ' ' . $u[$i];
}

/**
 * Comisión de una atención según el esquema del barbero.
 * - porcentaje: % del monto final cobrado
 * - fijo: tarifa fija por cada servicio de la atención
 */
function calcComision($barbero, $montoFinal, $numLineas) {
    $valor = max(0.0, (float)($barbero['comision_valor'] ?? 0));
    $monto = max(0.0, (float)$montoFinal);
    if (($barbero['tipo_comision'] ?? 'porcentaje') === 'fijo') {
        // La tarifa fija se cobra por cada servicio distinto de la atención.
        return round($valor * max(1, (int)$numLineas), 2);
    }
    // Esquema porcentaje: se limita a 0–100% para evitar comisiones absurdas por datos corruptos.
    $pct = min(100.0, $valor);
    return round($monto * $pct / 100, 2);
}

/**
 * Normaliza una fecha/hora recibida de un <input type="datetime-local">.
 * Acepta "Y-m-d\TH:i" o "Y-m-d H:i" (y variantes con segundos) y devuelve
 * "Y-m-d H:i" listo para almacenar. Si viene vacía o inválida usa $defecto.
 */
function normalizarFechaHora($valor, ?string $defecto = null): string {
    $defecto = $defecto ?? ahora();
    $valor = trim((string)$valor);
    if ($valor === '') return $defecto;
    $ts = strtotime($valor);
    if ($ts === false) return $defecto;
    return date('Y-m-d H:i', $ts);
}

/**
 * Parsea un número escrito "a la europea"/con símbolos ($ 1.234,56 / 1,234.56)
 * a float. Devuelve 0.0 si no se puede interpretar.
 */
function parsearNumero($valor): float {
    if (is_int($valor) || is_float($valor)) return (float)$valor;
    $s = trim((string)$valor);
    if ($s === '') return 0.0;
    $s = str_replace(['$', ' ', '\u{00A0}'], '', $s);
    $tieneComa = strpos($s, ',') !== false;
    $tienePunto = strpos($s, '.') !== false;
    if ($tieneComa && $tienePunto) {
        // El último separador es el decimal.
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    } elseif ($tieneComa) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

/**
 * Indica si un servicio es el especial "Otro valor" (RF-07): monto libre
 * colocado por el barbero, con motivo obligatorio.
 */
function esOtroValor(PDO $pdo, $servicioId): bool {
    $st = $pdo->prepare("SELECT es_otro_valor FROM servicios WHERE id = ?");
    $st->execute([(int)$servicioId]);
    $v = $st->fetchColumn();
    return $v !== false && (int)$v === 1;
}

/** Registra en la auditoría (RF-09) */
function audit(PDO $pdo, $accion, $tabla, $registro_id, $descripcion, $detalles = null) {
    $u = usuarioActual();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $st = $pdo->prepare("INSERT INTO auditoria (usuario_id, usuario_nombre, accion, tabla, registro_id, descripcion, detalles, ip) VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([
        $u ? (int)$u['id'] : null,
        $u ? $u['nombre'] : 'sistema',
        $accion,
        $tabla,
        $registro_id ? (int)$registro_id : null,
        mb_substr((string)$descripcion, 0, 255),
        $detalles,
        $ip,
    ]);
}

/**
 * Genera un gráfico de líneas y área suave en SVG de alto impacto visual
 * @param array $puntos Array con elementos ['label' => string, 'val' => float]
 * @param array $opts Opciones: ['chartId' => string, 'height' => int, 'width' => int]
 * @return string SVG renderizado
 */
function generar_grafico_lineas_svg(array $puntos, array $opts = []): string {
    if (empty($puntos)) {
        return '<div class="text-center text-muted py-5"><i class="bi bi-graph-up fs-2 d-block mb-2"></i>Sin datos para graficar en este rango</div>';
    }

    $W = $opts['width'] ?? 760;
    $H = $opts['height'] ?? 260;
    $chartId = $opts['chartId'] ?? ('lineChart_' . bin2hex(random_bytes(4)));

    $vals = array_map(fn($p) => (float)$p['val'], $puntos);
    $maxVal = max($vals) ?: 1;
    // Margen superior del 18% para que los números no choquen con el borde
    $maxScale = $maxVal * 1.18;

    $padL = 60;
    $padR = 30;
    $padT = 36;
    $padB = 40;
    $chartW = $W - $padL - $padR;
    $chartH = $H - $padT - $padB;
    $n = count($puntos);

    // 1. Grid horizontal con líneas punteadas y valores monetarios
    $svgGrid = '';
    for ($g = 0; $g <= 4; $g++) {
        $y = round($padT + $chartH * (1 - $g / 4), 1);
        $valGrid = $maxVal * ($g / 4);
        $svgGrid .= '<line x1="' . $padL . '" y1="' . $y . '" x2="' . ($W - $padR) . '" y2="' . $y . '" stroke="rgba(255,255,255,0.08)" stroke-width="1"' . ($g === 0 ? '' : ' stroke-dasharray="4 6"') . '/>';
        $svgGrid .= '<text x="' . ($padL - 12) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="11" font-weight="600" fill="#94a3b8">$' . number_format($valGrid, 0) . '</text>';
    }

    // 2. Calcular coordenadas (X, Y)
    $coords = [];
    foreach ($puntos as $i => $p) {
        $x = $n > 1 ? ($padL + ($i / ($n - 1)) * $chartW) : ($padL + $chartW / 2);
        $y = $padT + $chartH - (($p['val'] / $maxScale) * $chartH);
        $coords[] = [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'val' => (float)$p['val'],
            'label' => (string)$p['label'],
            'esMax' => ($p['val'] == $maxVal && $p['val'] > 0),
        ];
    }

    // 3. Generar spline cúbico suave
    $linePath = 'M ' . $coords[0]['x'] . ' ' . $coords[0]['y'];
    for ($i = 1; $i < $n; $i++) {
        $p0 = $coords[$i - 1];
        $p1 = $coords[$i];
        $cx1 = round($p0['x'] + ($p1['x'] - $p0['x']) * 0.5, 1);
        $cy1 = $p0['y'];
        $cx2 = round($p0['x'] + ($p1['x'] - $p0['x']) * 0.5, 1);
        $cy2 = $p1['y'];
        $linePath .= " C $cx1 $cy1, $cx2 $cy2, {$p1['x']} {$p1['y']}";
    }

    $yBottom = round($padT + $chartH, 1);
    $areaPath = $linePath . ' L ' . $coords[$n - 1]['x'] . ' ' . $yBottom . ' L ' . $coords[0]['x'] . ' ' . $yBottom . ' Z';

    // 4. Puntos, etiquetas de montos y fechas
    $svgPoints = '';
    $saltoLabel = $n > 14 ? (int)ceil($n / 8) : 1;

    foreach ($coords as $i => $c) {
        $esUltimo = ($i === $n - 1);
        $puntoColor = $c['esMax'] ? '#ffd54f' : ($esUltimo ? '#fbbf24' : '#f59e0b');
        $radio = $c['esMax'] ? 6.5 : ($esUltimo ? 6 : 4.5);

        // Marcador circular
        $svgPoints .= '<circle cx="' . $c['x'] . '" cy="' . $c['y'] . '" r="' . $radio . '" fill="' . $puntoColor . '" stroke="#07080d" stroke-width="2.5" filter="url(#glowP_' . $chartId . ')">';
        $svgPoints .= '<title>' . e($c['label']) . ': ' . money($c['val']) . '</title></circle>';

        // Etiqueta superior con monto
        if ($n <= 10 || $c['esMax'] || $esUltimo || ($i % $saltoLabel === 0 && $c['val'] > 0)) {
            $svgPoints .= '<text x="' . $c['x'] . '" y="' . ($c['y'] - 11) . '" text-anchor="middle" font-size="11.5" font-weight="800" fill="' . ($c['esMax'] ? '#fbbf24' : '#ffffff') . '">$' . number_format($c['val'], 0) . '</text>';
        }

        // Etiqueta inferior de fecha
        if ($i % $saltoLabel === 0 || $esUltimo) {
            $svgPoints .= '<text x="' . $c['x'] . '" y="' . ($H - 12) . '" text-anchor="middle" font-size="11.5" font-weight="' . ($esUltimo ? '800' : '600') . '" fill="' . ($esUltimo ? '#fbbf24' : '#cbd5e1') . '">' . e($c['label']) . '</text>';
        }
    }

    return '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="w-100 chart-svg" role="img" aria-label="Evolución de ingresos">
      <defs>
        <linearGradient id="areaG_' . $chartId . '" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.45"/>
          <stop offset="60%" stop-color="#f59e0b" stop-opacity="0.12"/>
          <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0"/>
        </linearGradient>
        <linearGradient id="lineG_' . $chartId . '" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#f59e0b"/>
          <stop offset="50%" stop-color="#fbbf24"/>
          <stop offset="100%" stop-color="#ffd54f"/>
        </linearGradient>
        <filter id="glowP_' . $chartId . '" x="-50%" y="-50%" width="200%" height="200%">
          <feDropShadow dx="0" dy="1" stdDeviation="3" flood-color="#f59e0b" flood-opacity="0.6"/>
        </filter>
        <filter id="glowL_' . $chartId . '" x="-10%" y="-10%" width="120%" height="120%">
          <feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#f59e0b" flood-opacity="0.35"/>
        </filter>
      </defs>
      ' . $svgGrid . '
      <path d="' . $areaPath . '" fill="url(#areaG_' . $chartId . ')"/>
      <path d="' . $linePath . '" fill="none" stroke="url(#lineG_' . $chartId . ')" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" filter="url(#glowL_' . $chartId . ')"/>
      ' . $svgPoints . '
    </svg>';
}
