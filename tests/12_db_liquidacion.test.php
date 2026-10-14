<?php
/**
 * Pruebas de liquidación de barberos y reportes (SQLite en memoria).
 * Ejecutar con:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
    echo "  (omitido: pdo_sqlite no está habilitado)\n";
    return;
}

$pdo = t_db();
t_reset($pdo);

/* Dos atenciones para Ana (40%) en el mismo día */
guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1], 'propina' => 20, 'fecha' => '2026-10-11T10:00']);
guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [2], 'cantidades' => [1], 'propina' => 0, 'fecha' => '2026-10-11T11:00']);
/* Una atención para Beto (fijo 50) */
guardarAtencion($pdo, ['barbero_id' => 2, 'servicio_ids' => [1], 'cantidades' => [1], 'fecha' => '2026-10-11T12:00']);

/* Adelanto y descuento a Ana */
$r = registrarAdelanto($pdo, ['barbero_id' => 1, 'monto' => 30, 'tipo' => 'adelanto', 'fecha' => '2026-10-11', 'motivo' => 'anticipo']);
T::ok($r['ok'] ?? false, 'registrarAdelanto registra un adelanto');
$r = registrarAdelanto($pdo, ['barbero_id' => 1, 'monto' => 10, 'tipo' => 'descuento', 'fecha' => '2026-10-11']);
T::ok($r['ok'] ?? false, 'registrarAdelanto registra un descuento');
T::eq(registrarAdelanto($pdo, ['barbero_id' => 1, 'monto' => 0])['ok'], false, 'adelanto con monto 0 devuelve error');
T::eq(registrarAdelanto($pdo, ['barbero_id' => 0, 'monto' => 50])['ok'], false, 'adelanto sin barbero devuelve error');

$filas = liquidacion($pdo, '2026-10-11', '2026-10-11');
$ana = array_values(array_filter($filas, fn($f) => (int) $f['id'] === 1))[0] ?? null;
$beto = array_values(array_filter($filas, fn($f) => (int) $f['id'] === 2))[0] ?? null;

T::eq($ana['atenciones'] ?? 0, 2, 'Ana tiene 2 atenciones');
T::eq($ana['comision'] ?? 0, 60.0, 'comisión de Ana = 40 + 20 = 60');
T::eq($ana['propinas'] ?? 0, 0.0, 'los importes de propina ya no se aplican');
T::eq($ana['subtotal'] ?? 0, 60.0, 'subtotal de Ana = comisión de 60 sin propinas');
T::eq($ana['adelantos'] ?? 0, 30.0, 'adelantos de Ana = 30');
T::eq($ana['descuentos'] ?? 0, 10.0, 'descuentos de Ana = 10');
T::eq($ana['pago_neto'] ?? 0, 20.0, 'pago neto de Ana = 60 − 30 − 10 = 20');

T::eq($beto['comision'] ?? 0, 50.0, 'comisión de Beto (fijo) = 50');
T::eq($beto['pago_neto'] ?? 0, 50.0, 'pago neto de Beto = 50 (sin adelantos)');

/* Un período sin atenciones no devuelve filas */
T::eq(count(liquidacion($pdo, '2020-01-01', '2020-01-02')), 0, 'período sin movimientos = 0 filas');

/* El adelanto fuera del período no se descuenta */
$filas = liquidacion($pdo, '2026-10-12', '2026-10-12');
T::eq(count($filas), 0, 'día siguiente sin atenciones');

/* ---------- resumenPeriodo ---------- */
$res = resumenPeriodo($pdo, '2026-10-11', '2026-10-11');
T::eq((int) $res['atenciones'], 3, 'resumen del período: 3 atenciones');
T::eq($res['ingresos'], 250.0, 'resumen del período: ingresos = 100 + 50 + 100 = 250');
T::eq($res['comisiones'], 110.0, 'resumen del período: comisiones = 60 + 50 = 110');
T::ok(!array_key_exists('propinas', $res), 'el resumen no expone propinas de atenciones');

/* ---------- horasPico ---------- */
$horas = horasPico($pdo, '2026-10-11', '2026-10-11');
T::eq($horas[10] ?? 0, 1, 'horas pico: 1 atención a las 10h');
T::eq($horas[12] ?? 0, 1, 'horas pico: 1 atención a las 12h');

/* ---------- resumen por método de pago ---------- */
$met = resumenPago($pdo, '2026-10-11', '2026-10-11');
T::eq(count($met), 1, 'todos los pagos del período son efectivo (1 método)');
T::eq($met[0]['monto'], 250.0, 'efectivo total del período suma solo servicios cobrados');

/* ---------- servicios más solicitados ---------- */
$top = serviciosMasSolicitados($pdo, '2026-10-11', '2026-10-11');
T::eq(count($top), 2, 'hay 2 servicios distintos solicitados');
$corte = array_values(array_filter($top, fn($s) => (int) $s['id'] === 1))[0] ?? null;
T::eq($corte['unidades'] ?? 0, 2, 'se hicieron 2 cortes en total');
