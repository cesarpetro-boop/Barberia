<?php
/**
 * Pruebas de integración de la lógica de atenciones sobre SQLite en memoria.
 * Ejecutar con:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
   echo "  (omitido: pdo_sqlite no está habilitado)\n";
   return;
}

$pdo = t_db();
t_reset($pdo);

/* ============================================================
   guardarAtencion: creación
   ============================================================ */
$r = guardarAtencion($pdo, [
   'barbero_id' => 1,               // Ana 40%
   'fecha' => '2026-10-11T10:00',
   'metodo_pago_id' => 1,
   'servicio_ids' => [1, 2],        // Corte 100 + Barba 50
   'cantidades' => [1, 2],
   'propina' => 20,
   'descuento' => 30,
]);

T::ok($r['ok'] ?? false, 'guardarAtencion crea la atención y devuelve ok');

$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_servicios'], 200.0, 'subtotal = 100×1 + 50×2 = 200');
T::eq($a['descuento'], 0.0, 'se ignora el descuento/cortesía legado al registrar');
T::eq($a['propina'], 0.0, 'se ignora la propina legada al registrar');
T::eq($a['monto_final'], 200.0, 'total a cobrar es el subtotal íntegro');
T::eq($a['total_cobrado'], 200.0, 'total cobrado no suma propinas');
T::eq($a['comision'], 80.0, 'comisión 40% del subtotal íntegro = 80');
T::eq($a['codigo'], 'A-000001', 'código generado A-000001');
T::eq($a['estado'], 'activa', 'estado activa');
T::eq($a['fecha'], '2026-10-11 10:00', 'fecha normalizada desde datetime-local');

$lineas = $pdo->query("SELECT * FROM atencion_servicios WHERE atencion_id = " . (int) $a['id'])->fetchAll();
T::eq(count($lineas), 2, 'se guardaron 2 líneas de servicio');
$barba = array_values(array_filter($lineas, fn($l) => (int) $l['servicio_id'] === 2))[0] ?? null;
T::eq($barba['cantidad'] ?? 0, 2, 'la línea de barba tiene cantidad 2');
T::eq($barba['precio_unit'] ?? 0, 50.0, 'la línea guarda el precio unitario (snapshot)');

/* ============================================================
   Validaciones
   ============================================================ */
$r = guardarAtencion($pdo, ['barbero_id' => 0, 'servicio_ids' => [1]]);
T::eq($r['ok'], false, 'sin barbero devuelve error');

$r = guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => []]);
T::eq($r['ok'], false, 'sin servicios devuelve error');

$r = guardarAtencion($pdo, ['barbero_id' => 99, 'servicio_ids' => [1]]);
T::eq($r['ok'], false, 'barbero inexistente/inactivo devuelve error');

$r = guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [3]]);
T::eq($r['ok'], false, 'servicio inactivo no es válido');

$r = guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1], 'propina' => -5]);
T::ok($r['ok'] ?? false, 'los campos de propina eliminados ya no bloquean la atención');

/* ============================================================
   Los datos antiguos de cortesía ya no cambian el total
   ============================================================ */
$r = guardarAtencion($pdo, [
   'barbero_id' => 1,
   'servicio_ids' => [1],
   'cantidades' => [1],
   'descuento' => 999,
]);
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['descuento'], 0.0, 'no se guarda descuento/cortesía en atenciones nuevas');
T::eq($a['monto_final'], 100.0, 'el servicio se cobra por su precio íntegro');

/* ============================================================
   Comisión con esquema fijo
   ============================================================ */
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 2,               // Beto fijo $50/servicio
   'servicio_ids' => [1, 2],
   'cantidades' => [1, 1],
]);
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['comision'], 100.0, 'comisión fija = 50 × 2 servicios = 100');

/* ============================================================
   Editar atención
   ============================================================ */
t_reset($pdo);
$idA = guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1]])['id'];
$r = guardarAtencion($pdo, [
   'barbero_id' => 1,
   'servicio_ids' => [1, 2],
   'cantidades' => [1, 1],
   'propina' => 10,
], $idA);
T::ok($r['ok'] ?? false, 'editar atención devuelve ok');
$a = $pdo->query("SELECT * FROM atenciones WHERE id = $idA")->fetch();
T::eq($a['monto_servicios'], 150.0, 'tras editar, subtotal = 150');
T::eq($a['propina'], 0.0, 'al editar se elimina la propina antigua');
T::eq($a['total_cobrado'], 150.0, 'tras editar solo se cobra el precio del servicio');
T::eq($a['comision'], 60.0, 'tras editar, comisión 40% de 150 = 60');
T::eq((int) $pdo->query("SELECT COUNT(*) FROM atencion_servicios WHERE atencion_id = $idA")->fetchColumn(), 2, 'se reemplazaron las líneas de servicio');

/* ============================================================
   Anular atención
   ============================================================ */
t_reset($pdo);
$idA = guardarAtencion($pdo, [
   'barbero_id' => 1,
   'servicio_ids' => [1],
   'cantidades' => [1],
])['id'];

$r = anularAtencion($pdo, $idA, '');
T::eq($r['ok'], false, 'anular sin motivo devuelve error');

$r = anularAtencion($pdo, $idA, 'Cliente canceló');
T::ok($r['ok'] ?? false, 'anular con motivo devuelve ok');
$a = $pdo->query("SELECT * FROM atenciones WHERE id = $idA")->fetch();
T::eq($a['estado'], 'anulada', 'la atención queda anulada');
T::eq($a['motivo_anulacion'], 'Cliente canceló', 'se guarda el motivo de anulación');

$r = anularAtencion($pdo, $idA, 'otra vez');
T::eq($r['ok'], false, 'no se puede anular dos veces');

/* ============================================================
   obtenerAtencion
   ============================================================ */
t_reset($pdo);
$idA = guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1, 2], 'cantidades' => [1, 2]])['id'];
$det = obtenerAtencion($pdo, $idA);
T::ok($det !== null, 'obtenerAtencion devuelve la atención');
T::eq(count($det['lineas']), 2, 'obtenerAtencion incluye sus 2 líneas');
T::eq($det['barbero_nombre'], 'Ana 40%', 'obtenerAtencion trae el nombre del barbero');
T::eq(obtenerAtencion($pdo, 999999), null, 'obtenerAtencion de id inexistente = null');
