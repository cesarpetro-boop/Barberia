<?php
/**
 * Lógica de negocio v2:
 *  atenciones (RF-05..RF-10), caja (RF-12), liquidación (RF-13/14),
 *  resúmenes para reportes (RF-15..RF-19).
 */

/* ============================================================
   ATENCIONES
   ============================================================ */

/** Guarda (crea o edita) una atención. RF-05..RF-10 */
function guardarAtencion(PDO $pdo, array $d, $editarId = null) {
    $usuario = usuarioActual();
    $uid = $usuario ? (int)$usuario['id'] : null;

    $barbero_id = (int)($d['barbero_id'] ?? 0);
    // Rol barbero: solo puede registrar atenciones a su nombre, así su historial
    // siempre muestra todo lo que cobró (nada queda "perdido" en otro barbero).
    if (esRol('barbero')) {
        $barbero_id = (int)($_SESSION['user_barbero_id'] ?? 0);
    }
    // Normaliza la fecha (viene de datetime-local: "2026-10-11T14:30").
    $fecha = normalizarFechaHora($d['fecha'] ?? '', ahora());
    // Fuente única de tiempo: si el usuario NO tocó la fecha propuesta por el
    // servidor (fecha_original), se registra con la hora real del momento.
    // Así la atención cae dentro del turno de caja aunque el reloj del
    // navegador o el llenado del form tarden unos minutos.
    $fechaOriginal = trim($d['fecha_original'] ?? '');
    if (!$editarId && $fechaOriginal !== ''
        && $fecha === normalizarFechaHora($fechaOriginal, '')) {
        $fecha = ahora();
    }
    $metodo_pago_id = (int)($d['metodo_pago_id'] ?? 1);
    $notas = trim($d['notas'] ?? '') ?: null;
    $servicio_ids = is_array($d['servicio_ids'] ?? null) ? $d['servicio_ids'] : [];
    $cantidades = is_array($d['cantidades'] ?? null) ? $d['cantidades'] : [];
    // Montos y motivos alineados por índice: solo se honran en el servicio especial "Otro valor".
    $preciosLibres = is_array($d['precios'] ?? null) ? $d['precios'] : [];
    $motivosLibres = is_array($d['motivos'] ?? null) ? $d['motivos'] : [];

    // Validaciones
    if ($barbero_id <= 0) return ['ok' => false, 'error' => 'Selecciona un barbero.'];
    $st = $pdo->prepare("SELECT * FROM barberos WHERE id = ? AND activo = 1");
    $st->execute([$barbero_id]);
    $barbero = $st->fetch();
    if (!$barbero) return ['ok' => false, 'error' => 'Barbero no válido o inactivo.'];
    if (empty($servicio_ids)) return ['ok' => false, 'error' => 'Selecciona al menos un servicio.'];
    // Monto de servicios con precio snapshot (RF-03: los cambios de precio no alteran el pasado)
    $montoServicios = 0.0;
    $lineas = [];
    $stS = $pdo->prepare("SELECT nombre, precio, es_otro_valor FROM servicios WHERE id = ? AND activo = 1");
    foreach ($servicio_ids as $i => $sid) {
        $sid = (int)$sid;
        $cant = max(1, (int)($cantidades[$i] ?? 1));
        $stS->execute([$sid]);
        $srv = $stS->fetch();
        if (!$srv) continue;
        $precio = (float)$srv['precio'];
        $nota = null;
        if (!empty($srv['es_otro_valor'])) {
            // RF-07 · "Otro valor": el monto lo coloca el barbero y el motivo es obligatorio.
            $precio = round(max(0, parsearNumero($preciosLibres[$i] ?? 0)), 2);
            $nota = trim((string)($motivosLibres[$i] ?? ''));
            if ($precio <= 0) {
                return ['ok' => false, 'error' => 'En "Otro valor" el monto debe ser mayor que cero.'];
            }
            if ($nota === '') {
                return ['ok' => false, 'error' => 'En "Otro valor" debes indicar el motivo del ingreso.'];
            }
            $nota = mb_substr($nota, 0, 255);
            $cant = 1; // Es un cobro puntual: no se multiplica.
        }
        $lineas[] = [
            'servicio_id' => $sid,
            'cantidad' => $cant,
            'precio_unit' => $precio,
            'nombre' => $srv['nombre'],
            'nota' => $nota,
        ];
        $montoServicios += $precio * $cant;
    }
    if (empty($lineas)) return ['ok' => false, 'error' => 'Servicio no válido.'];
    $montoServicios = round($montoServicios, 2);

    $montoFinal = $montoServicios;
    $totalCobrado = $montoFinal;

    // Comisión según esquema del barbero (RF-13)
    // Comisión según esquema del barbero (RF-13). Las líneas de "Otro valor" son
    // ingresos libres, no servicios atendidos: no suman tarifa fija extra.
    $numServicios = max(1, count(array_filter($lineas, fn($l) => $l['nota'] === null)));
    $comision = calcComision($barbero, $montoFinal, $numServicios);

    if ($editarId) {
        // ---------- EDITAR (RF-09) ----------
        $codAct = $pdo->query("SELECT codigo FROM atenciones WHERE id = " . (int)$editarId)->fetchColumn() ?: codigoAtencion($editarId);
        $pdo->prepare("UPDATE atenciones SET
                fecha = ?, barbero_id = ?, metodo_pago_id = ?, monto_servicios = ?, descuento = 0, motivo_descuento = NULL,
                monto_final = ?, propina = 0, total_cobrado = ?, comision = ?, notas = ?, editado_por = ?, editado_en = ?
            WHERE id = ? AND estado = 'activa'")
            ->execute([$fecha, $barbero_id, $metodo_pago_id, $montoServicios, $montoFinal, $totalCobrado, $comision,
                $notas, $uid, ahora(), $editarId]);
        $pdo->prepare("DELETE FROM atencion_servicios WHERE atencion_id = ?")->execute([$editarId]);
        $insDet = $pdo->prepare("INSERT INTO atencion_servicios (atencion_id, servicio_id, cantidad, precio_unit, nota) VALUES (?,?,?,?,?)");
        foreach ($lineas as $l) {
            $insDet->execute([$editarId, $l['servicio_id'], $l['cantidad'], $l['precio_unit'], $l['nota']]);
        }
        audit($pdo, 'editar', 'atenciones', $editarId, 'Editó la atención ' . $codAct . ' (' . money($totalCobrado) . ')', json_encode($lineas, JSON_UNESCAPED_UNICODE));
        return ['ok' => true, 'id' => $editarId, 'mensaje' => '✔ Atención ' . $codAct . ' editada'];
    }

    // ---------- CREAR ----------
    $pdo->prepare("INSERT INTO atenciones
        (codigo, fecha, barbero_id, metodo_pago_id, monto_servicios, descuento, motivo_descuento,
         monto_final, propina, total_cobrado, comision, notas, creado_por)
        VALUES ('TEMP', ?,?,?,?,0,NULL,?,0,?,?,?,?)")
        ->execute([$fecha, $barbero_id, $metodo_pago_id,
            $montoServicios, $montoFinal, $totalCobrado, $comision, $notas, $uid]);
    $id = (int)$pdo->lastInsertId();
    $cod = codigoAtencion($id);
    $pdo->prepare("UPDATE atenciones SET codigo = ? WHERE id = ?")->execute([$cod, $id]);
    $insDet = $pdo->prepare("INSERT INTO atencion_servicios (atencion_id, servicio_id, cantidad, precio_unit, nota) VALUES (?,?,?,?,?)");
    foreach ($lineas as $l) {
        $insDet->execute([$id, $l['servicio_id'], $l['cantidad'], $l['precio_unit'], $l['nota']]);
    }
    audit($pdo, 'crear', 'atenciones', $id, 'Registró la atención ' . $cod . ' (' . money($totalCobrado) . ')', json_encode($lineas, JSON_UNESCAPED_UNICODE));
    return [
        'ok' => true,
        'id' => $id,
        'mensaje' => '✔ Atención ' . $cod . ' registrada — comisión ' . money($comision),
    ];
}

/** Anula una atención (RF-09). */
function anularAtencion(PDO $pdo, $id, $motivo) {
    $usuario = usuarioActual();
    $uid = $usuario ? (int)$usuario['id'] : null;
    $id = (int)$id;
    $st = $pdo->prepare("SELECT * FROM atenciones WHERE id = ? AND estado = 'activa'");
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) return ['ok' => false, 'error' => 'Atención no encontrada o ya anulada.'];
    $motivo = trim((string)$motivo);
    if ($motivo === '') return ['ok' => false, 'error' => 'El motivo de anulación es obligatorio.'];
    $pdo->prepare("UPDATE atenciones SET estado = 'anulada', anulado_por = ?, motivo_anulacion = ? WHERE id = ?")
        ->execute([$uid, mb_substr($motivo, 0, 255), $id]);
    audit($pdo, 'anular', 'atenciones', $id, 'Anuló la atención ' . $a['codigo'] . ' (' . money($a['total_cobrado']) . '): ' . $motivo);
    return ['ok' => true, 'mensaje' => 'Atención ' . $a['codigo'] . ' anulada'];
}

/** Obtiene una atención con sus líneas de servicio. */
function obtenerAtencion(PDO $pdo, $id): ?array {
    $st = $pdo->prepare("SELECT a.*, b.nombre AS barbero_nombre, b.tipo_comision, b.comision_valor, mp.nombre AS metodo_nombre
        FROM atenciones a
        JOIN barberos b ON b.id = a.barbero_id
        JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE a.id = ?");
    $st->execute([(int)$id]);
    $a = $st->fetch();
    if (!$a) return null;
    $st = $pdo->prepare("SELECT s2.*, s.nombre AS servicio_nombre, s.es_otro_valor
        FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
        WHERE s2.atencion_id = ? ORDER BY s2.id");
    $st->execute([(int)$id]);
    $a['lineas'] = $st->fetchAll();
    return $a;
}

/** Procesa formularios de atenciones (guardar / editar / anular). */
function procesar_atenciones(PDO $pdo, string $redirect): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $accion = $_POST['accion'] ?? '';
    if (!in_array($accion, ['guardar_atencion', 'editar_atencion', 'anular_atencion'], true)) return;
    csrf_check();

    if ($accion === 'anular_atencion') {
        // RNF-09: solo el administrador puede anular registros
        if (!esRol('admin')) {
            audit($pdo, 'intento_anular', 'atenciones', (int)($_POST['id'] ?? 0), 'Intento de anulación sin permiso (' . rolActual() . ')');
            flash('Solo el administrador puede anular registros.', 'danger');
            redirect($redirect);
        }
        $r = anularAtencion($pdo, $_POST['id'] ?? 0, $_POST['motivo'] ?? '');
        flash($r['ok'] ? $r['mensaje'] : $r['error'], $r['ok'] ? 'success' : 'danger');
        redirect($redirect);
    }

    // RF-12: abrir la caja es obligatorio para empezar el turno. Solo se bloquea
    // el registro NUEVO; editar una atención del historial sigue permitido.
    if ($accion === 'guardar_atencion' && !turnoIniciado($pdo)) {
        audit($pdo, 'intento_atencion_sin_caja', 'atenciones', 0,
            'Intentó registrar una atención con la caja cerrada (' . rolActual() . ')');
        flash('La caja está cerrada: abre la caja para empezar el turno antes de registrar atenciones.', 'warning');
        redirect($redirect);
    }

    $datos = [
        'barbero_id' => $_POST['barbero_id'] ?? 0,
        'fecha' => $_POST['fecha'] ?? '',
        'fecha_original' => $_POST['fecha_original'] ?? '',
        'metodo_pago_id' => $_POST['metodo_pago_id'] ?? 1,
        'notas' => $_POST['notas'] ?? '',
        'servicio_ids' => $_POST['servicio_ids'] ?? [],
        'cantidades' => $_POST['cantidades'] ?? [],
        // Monto libre y motivo: solo se aplican al servicio especial "Otro valor".
        'precios' => $_POST['precios'] ?? [],
        'motivos' => $_POST['motivos'] ?? [],
    ];

    if ($accion === 'guardar_atencion') {
        $r = guardarAtencion($pdo, $datos);
    } else {
        $r = guardarAtencion($pdo, $datos, (int)($_POST['id'] ?? 0));
    }
    flash($r['ok'] ? $r['mensaje'] : $r['error'], $r['ok'] ? 'success' : 'danger');
    redirect($redirect);
}

/** Query de atenciones con filtros (RF-15/RF-18). Respeta el rol barbero. */
function queryAtenciones(array $f): array {
    $where = "a.estado = 'activa'";
    $params = [];
    if (!empty($f['desde'])) { $where .= " AND a.fecha >= ?"; $params[] = $f['desde']; }
    // DATE(...) es portable (MySQL y SQLite) y equivale a "hasta el final del día".
    if (!empty($f['hasta'])) { $where .= " AND DATE(a.fecha) <= ?"; $params[] = $f['hasta']; }
    if (!empty($f['barbero_id'])) { $where .= " AND a.barbero_id = ?"; $params[] = (int)$f['barbero_id']; }
    if (!empty($f['servicio_id'])) {
        $where .= " AND EXISTS (SELECT 1 FROM atencion_servicios s2 WHERE s2.atencion_id = a.id AND s2.servicio_id = ?)";
        $params[] = (int)$f['servicio_id'];
    }
    if (!empty($f['metodo_pago_id'])) { $where .= " AND a.metodo_pago_id = ?"; $params[] = (int)$f['metodo_pago_id']; }
    // Rol barbero: solo ve sus propias atenciones
    $miBarbero = barberoSegunRol();
    if ($miBarbero > 0) {
        $where .= " AND a.barbero_id = ?";
        $params[] = $miBarbero;
    }
    return [$where, $params];
}

/* ============================================================
   CAJA (RF-12)
   ============================================================ */

/** Calcula los totales de un turno desde una fecha/hora. */
function calcularTurno(PDO $pdo, string $desde): array {
    $st = $pdo->prepare("SELECT
            COALESCE(SUM(a.monto_final),0) monto,
            COALESCE(SUM(a.comision),0) comision,
            COUNT(*) atenciones,
            COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN a.total_cobrado ELSE 0 END),0) efectivo,
            COALESCE(SUM(CASE WHEN mp.tipo = 'digital' THEN a.total_cobrado ELSE 0 END),0) digital
        FROM atenciones a
        JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE a.fecha >= ? AND a.estado = 'activa'");
    $st->execute([$desde]);
    $r = $st->fetch();

    $st = $pdo->prepare("SELECT
            COALESCE(SUM(vp.total),0) total,
            COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN vp.total ELSE 0 END),0) efectivo,
            COALESCE(SUM(CASE WHEN mp.tipo = 'digital' THEN vp.total ELSE 0 END),0) digital
        FROM ventas_productos vp
        JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
        WHERE vp.fecha >= ?");
    $st->execute([$desde]);
    $p = $st->fetch();

    $efectivoTurno = (float)$r['efectivo'] + (float)$p['efectivo'];
    $digitalTurno = (float)$r['digital'] + (float)$p['digital'];

    return [
        'atenciones' => (int)$r['atenciones'],
        'monto' => (float)$r['monto'],
        'comision' => (float)$r['comision'],
        'ventas_productos' => (float)$p['total'],
        'efectivo_turno' => $efectivoTurno,
        'digital_turno' => $digitalTurno,
        'ingresos_turno' => $efectivoTurno + $digitalTurno,
    ];
}

/** Caja abierta actual con cálculos del turno. */
function cajaActual(PDO $pdo): ?array {
    $caja = $pdo->query("SELECT * FROM cajas WHERE fecha_cierre IS NULL ORDER BY id DESC LIMIT 1")->fetch();
    if (!$caja) return null;
    $calc = calcularTurno($pdo, $caja['fecha_apertura']);
    foreach ($calc as $k => $v) $caja[$k] = $v;
    $caja['efectivo_esperado'] = round((float)$caja['monto_apertura'] + $calc['efectivo_turno'], 2);
    $caja['digital_esperado'] = $calc['digital_turno'];
    return $caja;
}

/**
 * RF-12 · Abrir caja obligatorio para empezar el turno.
 * true solo con caja abierta: registrar una atención nueva o vender un producto
 * entra al turno de caja, así que con la caja cerrada no se aceptan.
 * (Editar/anular del historial y los adelantos no inician turno: quedan libres.)
 */
function turnoIniciado(PDO $pdo): bool {
    return cajaActual($pdo) !== null;
}

/** Abre una caja con mutex y auditoría en una sola transacción. */
function abrirCaja(PDO $pdo, float $base): ?int {
    $pdo->beginTransaction();
    try {
        if (pdo_es_sqlite($pdo)) {
            $pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1");
        } else {
            $pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1 FOR UPDATE");
        }
        $open = (int)$pdo->query("SELECT COUNT(*) FROM cajas WHERE fecha_cierre IS NULL")->fetchColumn();
        if ($open) {
            $pdo->rollBack();
            return null;
        }

        $pdo->prepare("INSERT INTO cajas (fecha_apertura, monto_apertura) VALUES (?, ?)")->execute([ahora(), $base]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE mutex_caja SET caja_id = ? WHERE id = 1")->execute([$id]);
        audit($pdo, 'abrir_caja', 'cajas', $id, 'Abrió la caja con base ' . money($base));
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Cierra la caja abierta y registra el cierre de forma atómica. */
function cerrarCaja(PDO $pdo, float $contado, float $digitalDeclarado, ?string $notas): ?array {
    $pdo->beginTransaction();
    try {
        $sql = "SELECT * FROM cajas WHERE fecha_cierre IS NULL ORDER BY id DESC LIMIT 1";
        if (!pdo_es_sqlite($pdo)) $sql .= " FOR UPDATE";
        $caja = $pdo->query($sql)->fetch();
        if (!$caja) {
            $pdo->rollBack();
            return null;
        }

        $calc = calcularTurno($pdo, $caja['fecha_apertura']);
        $esperado = round((float)$caja['monto_apertura'] + $calc['efectivo_turno'], 2);
        $diferencia = round($contado - $esperado, 2);
        $digitalDif = round($digitalDeclarado - $calc['digital_turno'], 2);

        $st = $pdo->prepare("UPDATE cajas SET fecha_cierre = ?, ingresos_turno = ?, efectivo_turno = ?, digital_turno = ?,
            monto_cierre = ?, monto_esperado = ?, diferencia = ?, digital_declarado = ?, digital_diferencia = ?, notas = ?
            WHERE id = ? AND fecha_cierre IS NULL");
        $st->execute([ahora(), $calc['ingresos_turno'], $calc['efectivo_turno'], $calc['digital_turno'],
            $contado, $esperado, $diferencia, $digitalDeclarado, $digitalDif, $notas, $caja['id']]);
        if ($st->rowCount() !== 1) {
            throw new RuntimeException('La caja ya fue cerrada por otra solicitud.');
        }

        $pdo->prepare("UPDATE mutex_caja SET caja_id = NULL WHERE id = 1")->execute();
        audit($pdo, 'cerrar_caja', 'cajas', $caja['id'],
            'Cerró la caja — efectivo ' . money($contado) . ' (dif. ' . money($diferencia) . '), digital ' . money($digitalDeclarado) . ' (dif. ' . money($digitalDif) . ')');
        $pdo->commit();

        return [
            'id' => (int)$caja['id'],
            'esperado' => $esperado,
            'diferencia' => $diferencia,
            'digital_diferencia' => $digitalDif,
            'digital_turno' => $calc['digital_turno'],
            'digital_declarado' => $digitalDeclarado,
            'ingresos_turno' => $calc['ingresos_turno'],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Procesa apertura y cierre de caja (RF-12). */
function procesar_caja(PDO $pdo, string $redirect): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $accion = $_POST['accion'] ?? '';
    if ($accion !== 'abrir_caja' && $accion !== 'cerrar_caja') return;
    csrf_check();

    // El arqueo/cierre es una operación de administración (RF-12):
    // el barbero solo ABRE caja para iniciar su turno.
    if ($accion === 'cerrar_caja' && esRol('barbero')) {
        flash('Solo el administrador puede cerrar la caja.', 'warning');
        redirect($redirect);
    }

    if ($accion === 'abrir_caja') {
        $base = round((float)($_POST['monto_apertura'] ?? 0), 2);
        if ($base < 0) { flash('El monto base no puede ser negativo.', 'danger'); redirect($redirect); }
        try {
            $id = abrirCaja($pdo, $base);
        } catch (Throwable $e) {
            error_log('No se pudo abrir la caja: ' . $e->getMessage());
            flash('No se pudo abrir la caja. No se guardaron cambios.', 'danger');
            redirect($redirect);
        }
        if ($id === null) {
            flash('Ya hay una caja abierta. Ciérrala antes de abrir otra.', 'warning');
            redirect($redirect);
        }
        flash('✔ Caja abierta — base de efectivo: ' . money($base));
        redirect($redirect);
    }

    // Cerrar caja: arqueo de efectivo + declaración de digitales
    $contado = round((float)($_POST['monto_cierre'] ?? 0), 2);
    $digitalDeclarado = round((float)($_POST['digital_declarado'] ?? 0), 2);
    $notas = trim($_POST['notas'] ?? '') ?: null;

    try {
        $cierre = cerrarCaja($pdo, $contado, $digitalDeclarado, $notas);
    } catch (Throwable $e) {
        error_log('No se pudo cerrar la caja: ' . $e->getMessage());
        flash('No se pudo cerrar la caja. No se guardaron cambios.', 'danger');
        redirect($redirect);
    }
    if (!$cierre) { flash('No hay una caja abierta.', 'danger'); redirect($redirect); }

    $msgs = [];
    $msgs[] = abs($cierre['diferencia']) < 0.005 ? 'efectivo exacto ' . money($cierre['esperado'])
        : ($cierre['diferencia'] > 0 ? 'efectivo sobrante ' . money($cierre['diferencia']) : 'efectivo faltante ' . money(abs($cierre['diferencia'])));
    $msgs[] = 'digital ' . money($cierre['digital_declarado']) . ($cierre['digital_diferencia'] != 0 ? ' (dif. ' . money($cierre['digital_diferencia']) . ')' : '');
    $msgs[] = 'ingresos del turno ' . money($cierre['ingresos_turno']);
    flash('✔ Caja cerrada — ' . implode(' · ', $msgs), abs($cierre['diferencia']) < 0.005 ? 'success' : 'warning');
    redirect($redirect);
}

/* ============================================================
   ADELANTOS / DESCUENTOS A BARBEROS (RF-14)
   ============================================================ */

function registrarAdelanto(PDO $pdo, array $d): array {
    $usuario = usuarioActual();
    $barbero_id = (int)($d['barbero_id'] ?? 0);
    // Rol barbero: solo sobre sí mismo (su liquidación es la única que ve).
    if (esRol('barbero')) {
        $barbero_id = (int)($_SESSION['user_barbero_id'] ?? 0);
    }
    $monto = round((float)($d['monto'] ?? 0), 2);
    $tipo = in_array($d['tipo'] ?? '', ['adelanto', 'descuento'], true) ? $d['tipo'] : 'adelanto';
    $fecha = normalizarFechaHora($d['fecha'] ?? '', date('Y-m-d H:i'));
    $motivo = trim($d['motivo'] ?? '') ?: null;

    if ($barbero_id <= 0) return ['ok' => false, 'error' => 'Selecciona un barbero.'];
    if ($monto <= 0) return ['ok' => false, 'error' => 'El monto debe ser mayor a cero.'];

    $pdo->prepare("INSERT INTO adelantos (barbero_id, fecha, monto, tipo, motivo, creado_por) VALUES (?,?,?,?,?,?)")
        ->execute([$barbero_id, $fecha, $monto, $tipo, $motivo, $usuario ? (int)$usuario['id'] : null]);
    $id = (int)$pdo->lastInsertId();
    $b = $pdo->query("SELECT nombre FROM barberos WHERE id = $barbero_id")->fetch();
    audit($pdo, $tipo, 'adelantos', $id, ucfirst($tipo) . ' de ' . money($monto) . ' para ' . ($b['nombre'] ?? '?') . ($motivo ? ': ' . $motivo : ''));
    return ['ok' => true, 'mensaje' => '✔ ' . ucfirst($tipo) . ' registrado: ' . money($monto)];
}

function procesar_adelantos(PDO $pdo, string $redirect): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (($_POST['accion'] ?? '') !== 'registrar_adelanto') return;
    csrf_check();
    $r = registrarAdelanto($pdo, $_POST);
    flash($r['ok'] ? $r['mensaje'] : $r['error'], $r['ok'] ? 'success' : 'danger');
    redirect($redirect);
}

/* ============================================================
   RESÚMENES PARA REPORTES (RF-15..RF-19)
   ============================================================ */

/**
 * Condición de rango de fechas para un período [desde, hasta] inclusivo.
 * Usa la función de fecha propia de cada motor (MySQL vs SQLite).
 */
function condicionRangoFecha(PDO $pdo, string $col): string {
    return pdo_es_sqlite($pdo)
        ? "$col >= ? AND $col <= date(?, '+1 day')"
        : "$col >= ? AND $col < DATE_ADD(?, INTERVAL 1 DAY)";
}

/** Resumen general de un período. */
function resumenPeriodo(PDO $pdo, $desde, $hasta): array {
    $st = $pdo->prepare("SELECT
            COUNT(*) atenciones,
            COALESCE(SUM(monto_final),0) ingresos,
            COALESCE(SUM(total_cobrado),0) cobrado,
            COALESCE(SUM(comision),0) comisiones
        FROM atenciones WHERE " . condicionRangoFecha($pdo, 'fecha') . " AND estado = 'activa'");
    $st->execute([$desde, $hasta]);
    $r = $st->fetch();
    $r['ganancia'] = (float)$r['ingresos'] - (float)$r['comisiones'];
    return $r;
}

/** Resumen por medio de pago (RF-16). */
function resumenPago(PDO $pdo, $desde, $hasta): array {
    $st = $pdo->prepare("SELECT mp.id, mp.nombre, mp.tipo, COALESCE(SUM(a.total_cobrado),0) monto, COUNT(*) atenciones
        FROM atenciones a JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE " . condicionRangoFecha($pdo, 'a.fecha') . " AND a.estado = 'activa'
        GROUP BY a.metodo_pago_id, mp.nombre, mp.tipo ORDER BY monto DESC");
    $st->execute([$desde, $hasta]);
    return $st->fetchAll();
}

/** Liquidación de barberos en un período (RF-13/14). */
function liquidacion(PDO $pdo, $desde, $hasta): array {
    $st = $pdo->prepare("SELECT b.id, b.nombre, b.tipo_comision, b.comision_valor,
            COUNT(*) atenciones,
            COALESCE(SUM(a.monto_final),0) facturado,
            COALESCE(SUM(a.comision),0) comision
        FROM atenciones a JOIN barberos b ON b.id = a.barbero_id
        WHERE " . condicionRangoFecha($pdo, 'a.fecha') . " AND a.estado = 'activa'
        GROUP BY a.barbero_id, b.nombre, b.tipo_comision, b.comision_valor
        ORDER BY facturado DESC");
    $st->execute([$desde, $hasta]);
    $filas = $st->fetchAll();

    // date(fecha) permite comparar contra la fecha simple aunque la columna
    // guarde hora (necesario porque adelantos.fecha se normaliza a datetime).
    $st = $pdo->prepare("SELECT barbero_id, tipo, COALESCE(SUM(monto),0) monto
        FROM adelantos WHERE date(fecha) >= date(?) AND date(fecha) <= date(?) GROUP BY barbero_id, tipo");
    $st->execute([$desde, $hasta]);
    $adv = [];
    foreach ($st as $r) {
        $adv[$r['barbero_id']][$r['tipo']] = (float)$r['monto'];
    }

    foreach ($filas as &$f) {
        $id = (int)$f['id'];
        $f['adelantos'] = $adv[$id]['adelanto'] ?? 0;
        $f['descuentos'] = $adv[$id]['descuento'] ?? 0;
        $f['subtotal'] = (float)$f['comision'];
        $f['pago_neto'] = round((float)$f['subtotal'] - (float)$f['adelantos'] - (float)$f['descuentos'], 2);
    }
    return $filas;
}

/** Servicios más solicitados (RF-19). */
function serviciosMasSolicitados(PDO $pdo, $desde, $hasta): array {
    $st = $pdo->prepare("SELECT s.id, s.nombre, COALESCE(SUM(s2.cantidad),0) unidades,
            COALESCE(SUM(s2.cantidad * s2.precio_unit),0) ingresos, COUNT(DISTINCT s2.atencion_id) atenciones
        FROM atencion_servicios s2
        JOIN servicios s ON s.id = s2.servicio_id
        JOIN atenciones a ON a.id = s2.atencion_id
        WHERE " . condicionRangoFecha($pdo, 'a.fecha') . " AND a.estado = 'activa'
        GROUP BY s2.servicio_id, s.nombre
        ORDER BY unidades DESC");
    $st->execute([$desde, $hasta]);
    return $st->fetchAll();
}

/** Horas pico (RF-19): atenciones por hora del día. */
function horasPico(PDO $pdo, $desde, $hasta): array {
    $horaExpr = pdo_es_sqlite($pdo)
        ? "CAST(strftime('%H', fecha) AS INTEGER)"
        : "HOUR(fecha)";
    $st = $pdo->prepare("SELECT $horaExpr hora, COUNT(*) c
        FROM atenciones
        WHERE " . condicionRangoFecha($pdo, 'fecha') . " AND estado = 'activa'
        GROUP BY $horaExpr ORDER BY hora");
    $st->execute([$desde, $hasta]);
    $porHora = [];
    foreach ($st as $r) $porHora[(int)$r['hora']] = (int)$r['c'];
    return $porHora;
}

/** Resumen por medio de pago desde una fecha/hora (turno de caja). */
function resumenPagoDesde(PDO $pdo, string $desde): array {
    $st = $pdo->prepare("SELECT mp.tipo, COALESCE(SUM(a.total_cobrado),0) monto
        FROM atenciones a JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
        WHERE a.fecha >= ? AND a.estado = 'activa' GROUP BY mp.tipo");
    $st->execute([$desde]);
    $out = ['efectivo' => 0.0, 'digital' => 0.0];
    foreach ($st as $r) {
        $out[$r['tipo']] = (float)$r['monto'];
    }
    // Productos
    $st = $pdo->prepare("SELECT mp.tipo, COALESCE(SUM(vp.total),0) monto
        FROM ventas_productos vp JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
        WHERE vp.fecha >= ? GROUP BY mp.tipo");
    $st->execute([$desde]);
    foreach ($st as $r) {
        $out[$r['tipo']] += (float)$r['monto'];
    }
    return $out;
}
