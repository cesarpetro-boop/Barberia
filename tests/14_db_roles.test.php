<?php
/**
 * Verifica que el rol 'cajero' fue eliminado y que el administrador asume
 * su antiguo menú (ahora tiene acceso total). Requiere:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
    echo "  (omitido: pdo_sqlite no está habilitado)\n";
    return;
}

/* ---------- El código fuente ya no expone el rol cajero ---------- */
$raiz = dirname(__DIR__);

// acciones / lógica: no debe haber comprobaciones de rol 'cajero'
$fuentes = [
    $raiz . '/inc/header.php',
    $raiz . '/usuarios.php',
    $raiz . '/liquidacion.php',
    $raiz . '/inc/auth.php',
];
foreach ($fuentes as $f) {
    $txt = file_get_contents($f);
    $nombre = basename($f);
    T::ok(!preg_match("/['\"]cajero['\"]/", $txt), "{$nombre} ya no menciona el rol 'cajero'");
}

// El esquema nuevo debe declarar el ENUM con solo admin y barbero
$db = file_get_contents($raiz . '/inc/db.php');
T::ok(
    str_contains($db, "ENUM('admin','barbero')"),
    "El esquema nuevo define el rol ENUM('admin','barbero') sin cajero"
);
T::ok(
    !preg_match("/ENUM\('admin','cajero','barbero'\)/", $db),
    'El esquema nuevo ya no incluye cajero en el ENUM'
);

/* ---------- El administrador puede abrir y cerrar caja ---------- */
$pdo = t_db();
t_reset($pdo);

$cajaId = abrirCaja($pdo, 50.0);
T::ok($cajaId !== null, 'la acción real abre caja para el administrador');
$cajaId = (int)$cajaId;
$pdo->prepare("UPDATE cajas SET fecha_apertura = ? WHERE id = ?")->execute(['2026-10-11 09:00', $cajaId]);

T::ok(cajaActual($pdo) !== null, 'el administrador abre la caja correctamente');

// Movimiento y cierre
guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1], 'metodo_pago_id' => 1, 'fecha' => '2026-10-11T10:00']);
$caja = cajaActual($pdo);
T::eq($caja['efectivo_esperado'], 150.0, 'efectivo esperado = base 50 + 100 del turno');

$cierre = cerrarCaja($pdo, 150.0, 0.0, null);
T::ok($cierre !== null, 'la acción de cierre procesa una caja abierta');
T::eq($cierre['diferencia'], 0.0, 'el cierre conserva el cálculo de diferencia esperado');

T::eq(cajaActual($pdo), null, 'el administrador cierra la caja correctamente');
T::eq((int) $pdo->query("SELECT COUNT(*) FROM cajas WHERE fecha_cierre IS NOT NULL")->fetchColumn(), 1, 'quedó 1 caja cerrada en el historial');
T::eq($pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1")->fetchColumn(), null, 'el cierre libera el mutex de caja');
T::eq((int) $pdo->query("SELECT COUNT(*) FROM auditoria WHERE accion = 'cerrar_caja'")->fetchColumn(), 1, 'el cierre queda registrado en auditoría');
