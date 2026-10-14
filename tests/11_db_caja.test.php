<?php
/**
 * Pruebas de la caja: arqueo, efectivo/digital y pago a barberos.
 * Ejecutar con:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
    echo "  (omitido: pdo_sqlite no está habilitado)\n";
    return;
}

$pdo = t_db();
t_reset($pdo);

/* Sin caja abierta */
T::eq(cajaActual($pdo), null, 'cajaActual devuelve null cuando no hay caja abierta');
T::eq(turnoIniciado($pdo), false, 'RF-12: sin caja abierta el turno NO está iniciado');

/* Simula abrir caja (mutex + inserción) */
$pdo->beginTransaction();
if (pdo_es_sqlite($pdo)) {
    $pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1");
} else {
    $pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1 FOR UPDATE");
}
$pdo->prepare("INSERT INTO cajas (fecha_apertura, monto_apertura) VALUES (?, ?)")
    ->execute(['2026-10-11 09:00', 100.0]);
$cajaId = (int) $pdo->lastInsertId();
$pdo->prepare("UPDATE mutex_caja SET caja_id = ? WHERE id = 1")->execute([$cajaId]);
$pdo->commit();

$caja = cajaActual($pdo);
T::ok($caja !== null, 'cajaActual devuelve la caja abierta');
T::eq($caja['monto_apertura'], 100.0, 'base de apertura guardada');
T::eq(turnoIniciado($pdo), true, 'RF-12: con caja abierta el turno queda iniciado');

/* Movimientos del turno: 1 atención efectivo y 1 digital */
guardarAtencion($pdo, [
    'barbero_id' => 1,
    'servicio_ids' => [1],
    'cantidades' => [1],      // 100
    'metodo_pago_id' => 1,
    'fecha' => '2026-10-11T10:00',
    'propina' => 0,
]);
guardarAtencion($pdo, [
    'barbero_id' => 1,
    'servicio_ids' => [2],
    'cantidades' => [1],      // 50
    'metodo_pago_id' => 2,
    'fecha' => '2026-10-11T10:30',
    'propina' => 0,
]);

$caja = cajaActual($pdo);
T::eq($caja['efectivo_turno'], 100.0, 'efectivo del turno = 100 (solo la atención en efectivo)');
T::eq($caja['digital_turno'], 50.0, 'digital del turno = 50 (transferencia)');
T::eq($caja['ingresos_turno'], 150.0, 'ingresos del turno = 150');
T::eq($caja['efectivo_esperado'], 200.0, 'efectivo esperado = base 100 + efectivo 100');
T::eq($caja['digital_esperado'], 50.0, 'digital esperado = 50');
T::eq((int) $caja['atenciones'], 2, 'se contaron 2 atenciones en el turno');
T::eq($caja['comision'], 60.0, 'pago a barberos (comisiones) = 40 + 20 = 60');

/* Una atención de otro día no debe entrar al turno */
guardarAtencion($pdo, [
    'barbero_id' => 1,
    'servicio_ids' => [1],
    'cantidades' => [1],
    'metodo_pago_id' => 1,
    'fecha' => '2026-10-01T10:00',
]);
$caja = cajaActual($pdo);
T::eq($caja['efectivo_turno'], 100.0, 'una atención anterior al turno no suma al efectivo');

/* Cerrar caja con sobrante */
$esperado = 200.0;
$contado = 210.0;
$dif = round($contado - $esperado, 2);
$pdo->prepare("UPDATE cajas SET fecha_cierre=?, efectivo_turno=?, digital_turno=?, monto_cierre=?, monto_esperado=?, diferencia=?, digital_declarado=? WHERE id=?")
    ->execute(['2026-10-11 20:00', 100.0, 50.0, $contado, $esperado, $dif, 50.0, $cajaId]);
$pdo->prepare("UPDATE mutex_caja SET caja_id = NULL WHERE id = 1")->execute();

$cerrada = $pdo->query("SELECT * FROM cajas WHERE id = $cajaId")->fetch();
T::eq($cerrada['diferencia'], 10.0, 'diferencia de caja = sobrante de 10');
T::eq(cajaActual($pdo), null, 'tras cerrar ya no hay caja abierta');
T::eq(turnoIniciado($pdo), false, 'RF-12: al cerrar la caja el turno deja de estar iniciado');
T::eq((int) $pdo->query("SELECT caja_id FROM mutex_caja WHERE id = 1")->fetchColumn(), 0, 'el mutex de caja se liberó');
