<?php
/**
 * RF-12 · Abrir caja obligatorio para empezar el turno.
 * Sin caja abierta no se registra una atención nueva ni se vende un producto;
 * editar/anular del historial y los adelantos quedan libres.
 * Ejecutar con:  php tests/run.php [--db]
 */

$raiz = dirname(__DIR__);

/* ---------- El guard vive en la capa que procesa los formularios ---------- */
$acciones = file_get_contents($raiz . '/inc/acciones.php');
T::ok(str_contains($acciones, 'function turnoIniciado'), 'existe turnoIniciado() como estado del turno');

// procesar_atenciones exige caja abierta solo para guardar_atencion (registro nuevo)
T::ok(
    str_contains($acciones, "'guardar_atencion' && !turnoIniciado(\$pdo)"),
    'procesar_atenciones bloquea guardar_atencion cuando la caja está cerrada'
);

// La edición/anulación del historial no exige caja abierta (solo el registro nuevo)
T::ok(
    !str_contains($acciones, "editar_atencion' && !turnoIniciado"),
    'editar_atencion no exige caja abierta (la edición del historial queda libre)'
);
T::ok(
    !str_contains($acciones, "anular_atencion' && !turnoIniciado"),
    'anular_atencion no exige caja abierta'
);

// productos: la venta de producto exige caja abierta
$prod = file_get_contents($raiz . '/productos.php');
$vender = strpos($prod, "} elseif (\$accion === 'vender') {");
T::ok($vender !== false, 'productos.php conserva la acción vender');
T::ok(
    $vender !== false && str_contains(substr($prod, $vender, 300), 'turnoIniciado($pdo)'),
    'la acción vender exige turno iniciado (caja abierta)'
);

/* ---------- La UI avisa y deshabilita ---------- */
$form = file_get_contents($raiz . '/inc/form_atencion.php');
T::ok(
    str_contains($form, "if (!\$esEditar && empty(\$caja)): ?>"),
    'el formulario de atención se reemplaza por el aviso cuando no hay caja'
);
T::ok(str_contains($form, 'Abre la caja para empezar el turno'), 'el aviso invita a abrir la caja');
T::ok(str_contains($form, 'modalAbrirCaja'), 'el aviso abre el modal de apertura de caja');

T::ok(str_contains($prod, "<?= \$caja ? '' : 'disabled' ?>"), 'el botón Vender queda deshabilitado con la caja cerrada');
T::ok(str_contains($prod, "target=\"#modalAbrirCaja\""), 'productos.php ofrece abrir la caja desde su franja');

/* ---------- El estado del turno se deriva de la caja ---------- */
if (extension_loaded('pdo_sqlite')) {
    $pdo = t_db();
    t_reset($pdo);

    T::eq(turnoIniciado($pdo), false, 'sin caja abierta el turno no está iniciado');
    $cajaId = abrirCaja($pdo, 0.0);
    T::eq(turnoIniciado($pdo), true, 'al abrir caja el turno queda iniciado');
    cerrarCaja($pdo, 0.0, 0.0, null);
    T::eq(turnoIniciado($pdo), false, 'al cerrar caja el turno deja de estar iniciado');
}
