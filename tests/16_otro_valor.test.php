<?php
/**
 * Pruebas del servicio especial "Otro valor" (RF-07): monto libre colocado
 * por el barbero con motivo obligatorio, para clientes sin el dinero completo.
 * Ejecutar con:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
   echo "  (omitido: pdo_sqlite no está habilitado)\n";
   return;
}

$pdo = t_db();
t_reset($pdo);

// Servicios fijos del fixture: 1=Corte 100, 2=Barba 50, 3=Inactivo, 4=Otro valor
const OTRO = 4;

T::group('Otro valor · esquema y catálogo');
$filas = $pdo->query("SELECT * FROM servicios WHERE es_otro_valor = 1")->fetchAll();
T::eq(count($filas), 1, 'existe exactamente un servicio especial "Otro valor"');
T::eq($filas[0]['nombre'] ?? '', 'Otro valor', 'se llama "Otro valor"');
T::eq((int)($filas[0]['activo'] ?? 0), 1, 'el servicio especial nace activo');
T::ok(esOtroValor($pdo, OTRO), 'esOtroValor reconoce el servicio especial');
T::ok(!esOtroValor($pdo, 1), 'esOtroValor devuelve false para un servicio normal');
T::ok(!esOtroValor($pdo, 999), 'esOtroValor devuelve false para un id inexistente');

T::group('Otro valor · registro con monto libre');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1,               // Ana 40%
   'fecha' => '2026-10-11T10:00',
   'metodo_pago_id' => 1,
   'servicio_ids' => [1, OTRO],     // Corte 100 + Otro valor a convenir
   'cantidades' => [1, 1],
   'precios' => ['', '8000'],
   'motivos' => ['', 'Abono del corte, debe el resto'],
]);
T::ok($r['ok'] ?? false, 'registra atención con "Otro valor" y devuelve ok');

$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_servicios'], 8100.0, 'subtotal = Corte 100 + Otro valor 8000 = 8100');
T::eq($a['monto_final'], 8100.0, 'total a cobrar incluye el monto libre');
T::eq($a['total_cobrado'], 8100.0, 'total cobrado incluye el monto libre');
T::eq($a['comision'], 3240.0, 'comisión 40% de 8100 = 3240 (el monto libre suma base)');

$lin = $pdo->query("SELECT * FROM atencion_servicios WHERE atencion_id = " . (int)$a['id'] . " AND servicio_id = " . OTRO)->fetch();
T::eq($lin['precio_unit'] ?? 0, 8000.0, 'la línea guarda el monto colocado por el barbero');
T::eq($lin['nota'] ?? '', 'Abono del corte, debe el resto', 'se guarda el motivo del ingreso');
T::eq((int)($lin['cantidad'] ?? 0), 1, 'el monto libre se registra con cantidad 1');

$linCorte = $pdo->query("SELECT * FROM atencion_servicios WHERE atencion_id = " . (int)$a['id'] . " AND servicio_id = 1")->fetch();
T::eq($linCorte['nota'] ?? null, null, 'los servicios normales quedan sin motivo');
T::eq($linCorte['precio_unit'] ?? 0, 100.0, 'los servicios normales conservan su precio snapshot');

T::group('Otro valor · validaciones');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['8000'], 'motivos' => [''],
]);
T::eq($r['ok'], false, 'sin motivo devuelve error');

$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['8000'], 'motivos' => ['   '],
]);
T::eq($r['ok'], false, 'motivo solo con espacios también devuelve error');

$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => [''], 'motivos' => ['Abono'],
]);
T::eq($r['ok'], false, 'sin monto devuelve error');

$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['0'], 'motivos' => ['Abono'],
]);
T::eq($r['ok'], false, 'monto 0 devuelve error');

$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['-500'], 'motivos' => ['Abono'],
]);
T::eq($r['ok'], false, 'monto negativo devuelve error');

T::group('Otro valor · solo se honra en el servicio especial');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1],
   'precios' => ['1'], 'motivos' => ['Tratar de bajar el precio normal'],
]);
T::ok($r['ok'] ?? false, 'un servicio normal acepta el envío sin romper');
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_servicios'], 100.0, 'el precio del servicio normal se ignora: sigue valiendo 100 (RF-03)');
$lin = $pdo->query("SELECT * FROM atencion_servicios WHERE atencion_id = " . (int)$a['id'])->fetch();
T::eq($lin['nota'] ?? null, null, 'el motivo enviado a un servicio normal no se guarda');

T::group('Otro valor · formato y decimales');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['8500.5'], 'motivos' => ['Pago parcial con decimales'],
]);
T::ok($r['ok'] ?? false, 'acepta montos con decimales');
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_final'], 8500.5, 'se conservan los decimales reales (8500.5)');

$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['8.500,50'], 'motivos' => ['Formato europeo'],
]);
T::ok($r['ok'] ?? false, 'acepta formato con separador de miles');
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_final'], 8500.5, 'parsea "8.500,50" como 8500.5');

T::group('Otro valor · la cantidad enviada se fuerza a 1');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [5],
   'precios' => ['3000'], 'motivos' => ['Intento de multiplicar'],
]);
$lin = $pdo->query("SELECT * FROM atencion_servicios ORDER BY id DESC LIMIT 1")->fetch();
T::eq((int)($lin['cantidad'] ?? 0), 1, 'la cantidad del monto libre queda en 1');
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['monto_final'], 3000.0, 'el total no se multiplica por la cantidad');

T::group('Otro valor · comisión con tarifa fija (Beto $50/servicio)');
t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 2,
   'servicio_ids' => [1, OTRO],
   'cantidades' => [1, 1],
   'precios' => ['', '8000'],
   'motivos' => ['', 'Abono'],
]);
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['comision'], 50.0, 'el monto libre NO suma tarifa fija extra: solo 1 servicio = 50');

t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 2,
   'servicio_ids' => [1, 2, OTRO],
   'cantidades' => [1, 1, 1],
   'precios' => ['', '', '8000'],
   'motivos' => ['', '', 'Abono'],
]);
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['comision'], 100.0, 'con 2 servicios reales la tarifa fija es 100, no 150');

t_reset($pdo);
$r = guardarAtencion($pdo, [
   'barbero_id' => 2,
   'servicio_ids' => [OTRO],
   'cantidades' => [1],
   'precios' => ['8000'],
   'motivos' => ['Solo monto libre'],
]);
$a = $pdo->query("SELECT * FROM atenciones ORDER BY id DESC LIMIT 1")->fetch();
T::eq($a['comision'], 50.0, 'una atención solo de monto libre paga 1 tarifa fija (mínimo 1)');

T::group('Otro valor · editar y obtener');
t_reset($pdo);
$idA = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['8000'], 'motivos' => ['Abono original'],
])['id'];

$det = obtenerAtencion($pdo, $idA);
$lin = $det['lineas'][0] ?? [];
T::eq($lin['es_otro_valor'] ?? 0, 1, 'obtenerAtencion expone es_otro_valor para precargar el form');
T::eq($lin['nota'] ?? '', 'Abono original', 'obtenerAtencion expone el motivo');

// Editar cambiando monto y motivo
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['12000'], 'motivos' => ['Motivo actualizado'],
], $idA);
T::ok($r['ok'] ?? false, 'editar con otro monto devuelve ok');
$a = $pdo->query("SELECT * FROM atenciones WHERE id = $idA")->fetch();
T::eq($a['monto_final'], 12000.0, 'al editar se actualiza el monto libre');
$lin = $pdo->query("SELECT * FROM atencion_servicios WHERE atencion_id = $idA")->fetch();
T::eq($lin['nota'] ?? '', 'Motivo actualizado', 'al editar se actualiza el motivo');

// Editar quitando el monto libre por completo
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1],
], $idA);
T::ok($r['ok'] ?? false, 'se puede editar quitando el monto libre');
$a = $pdo->query("SELECT * FROM atenciones WHERE id = $idA")->fetch();
T::eq($a['monto_final'], 100.0, 'tras quitarlo solo queda el servicio normal');
T::eq((int)$pdo->query("SELECT COUNT(*) FROM atencion_servicios WHERE atencion_id = $idA")->fetchColumn(), 1, 'quedó solo la línea del servicio normal');

// Editar quitando el único servicio normal, dejando solo el monto libre
t_reset($pdo);
$idA = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [1, OTRO], 'cantidades' => [1, 1],
   'precios' => ['', '5000'], 'motivos' => ['', 'Abono'],
])['id'];
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['5000'], 'motivos' => ['Abono'],
], $idA);
T::ok($r['ok'] ?? false, 'una atención puede quedar solo con el monto libre');
$a = $pdo->query("SELECT * FROM atenciones WHERE id = $idA")->fetch();
T::eq($a['monto_final'], 5000.0, 'el total es únicamente el monto libre');

T::group('Otro valor · motivo muy largo');
t_reset($pdo);
$largo = str_repeat('a', 400);
$r = guardarAtencion($pdo, [
   'barbero_id' => 1, 'servicio_ids' => [OTRO], 'cantidades' => [1],
   'precios' => ['1000'], 'motivos' => [$largo],
]);
T::ok($r['ok'] ?? false, 'acepta un motivo largo');
$lin = $pdo->query("SELECT * FROM atencion_servicios ORDER BY id DESC LIMIT 1")->fetch();
T::eq(mb_strlen($lin['nota'] ?? ''), 255, 'el motivo se trunca a 255 caracteres');
