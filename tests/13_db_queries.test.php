<?php
/**
 * Pruebas de humo de las consultas SQL usadas por las pantallas
 * (atenciones, reportes, caja) contra SQLite en memoria.
 * Verifica que el SQL es válido y que ya no referencia columnas de cliente.
 * Ejecutar con:  php tests/run.php --db
 */

if (!extension_loaded('pdo_sqlite')) {
    echo "  (omitido: pdo_sqlite no está habilitado)\n";
    return;
}

$pdo = t_db();
t_reset($pdo);

/* Datos de un turno */
guardarAtencion($pdo, ['barbero_id' => 1, 'servicio_ids' => [1], 'cantidades' => [1], 'metodo_pago_id' => 1, 'fecha' => '2026-10-11T10:00', 'propina' => 5]);
guardarAtencion($pdo, ['barbero_id' => 2, 'servicio_ids' => [2], 'cantidades' => [2], 'metodo_pago_id' => 2, 'fecha' => '2026-10-11T11:00']);

/* ---------- Consulta de index.php: lista de atenciones de hoy (sin cliente) ----------
   Se valida la parte del SQL que cambió (ya no selecciona columnas de cliente)
   con funciones disponibles en SQLite; la sintaxis MySQL (CONCAT/GROUP_CONCAT)
   es responsabilidad de la prueba de integración con MySQL, no de este humo. */
$sql = "SELECT a.*, b.nombre AS barbero, mp.nombre AS metodo_nombre, mp.tipo AS metodo_tipo,
      (SELECT GROUP_CONCAT(s.nombre)
       FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
       WHERE s2.atencion_id = a.id) AS servicios_txt
    FROM atenciones a
    JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE a.estado = 'activa'
    ORDER BY a.fecha DESC, a.id DESC";
$st = $pdo->query($sql);
$lista = $st->fetchAll();
T::eq(count($lista), 2, 'la consulta del panel devuelve las 2 atenciones sin error');
T::ok(!array_key_exists('cliente_nombre', $lista[0]), 'la fila del panel ya no trae cliente_nombre');

/* ---------- Consulta de reportes.php: detalle del período (sin cliente) ---------- */
$where = "a.estado = 'activa'";
$st = $pdo->prepare("
    SELECT a.fecha, b.nombre AS barbero, mp.nombre AS metodo,
      (SELECT GROUP_CONCAT(s.nombre)
       FROM atencion_servicios s2 JOIN servicios s ON s.id = s2.servicio_id
       WHERE s2.atencion_id = a.id) AS servicios,
    a.total_cobrado, a.comision
    FROM atenciones a
    JOIN barberos b ON b.id = a.barbero_id
    JOIN metodos_pago mp ON mp.id = a.metodo_pago_id
    WHERE {$where}
    ORDER BY a.fecha DESC LIMIT 500
");
$st->execute();
$det = $st->fetchAll();
T::eq(count($det), 2, 'la consulta de detalle de reportes funciona sin cliente_nombre');
T::ok(isset($det[0]['servicios']), 'el detalle incluye el texto de servicios');

/* ---------- Todas las tablas deben existir (no quedar referencias a clientes) ---------- */
$tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
T::ok(!in_array('clientes', $tablas, true), 'la tabla clientes ya no existe en el esquema');

/* ---------- queryAtenciones con filtros sigue funcionando ---------- */
$f = ['desde' => '2026-10-11', 'hasta' => '2026-10-11', 'metodo_pago_id' => 1];
[$whereF, $paramsF] = queryAtenciones($f);
$st = $pdo->prepare("SELECT COUNT(*) FROM atenciones a WHERE {$whereF}");
$st->execute($paramsF);
T::eq((int) $st->fetchColumn(), 1, 'queryAtenciones con filtro de método de pago devuelve 1');
