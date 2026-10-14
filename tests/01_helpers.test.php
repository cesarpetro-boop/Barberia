<?php
/**
 * Pruebas unitarias de los ayudantes puros de inc/functions.php.
 * No requieren base de datos.
 */

/* ---------- codigoAtencion ---------- */
T::eq(codigoAtencion(1), 'A-000001', 'codigoAtencion rellena a 6 dígitos');
T::eq(codigoAtencion(123456), 'A-123456', 'codigoAtencion admite 6 dígitos');
T::eq(codigoAtencion(9999999), 'A-9999999', 'codigoAtencion no trunca ids largos');

/* ---------- iniciales ---------- */
T::eq(iniciales('Carlos Ramírez'), 'CR', 'iniciales toma nombre y apellido');
T::eq(iniciales('  ana   maría  lópez '), 'AM', 'iniciales ignora espacios extra');
T::eq(iniciales('Príncipe'), 'P', 'iniciales con un solo nombre');
T::eq(iniciales(''), '?', 'iniciales vacío devuelve "?"');

/* ---------- hueNombre ---------- */
T::eq(hueNombre('Carlos'), hueNombre('Carlos'), 'hueNombre es determinista');
T::ok(hueNombre('Carlos') >= 0 && hueNombre('Carlos') < 360, 'hueNombre está en el rango 0..359');
T::ok(hueNombre('Carlos') !== hueNombre('Miguel'), 'hueNombre diferencia nombres distintos');

/* ---------- money ---------- */
T::eq(money(1234.5), '$ 1,234.5', 'money formatea con separador de miles');
T::eq(money(17000), '$ 17,000', 'money elimina los dos ceros decimales sobrantes');
T::eq(money(0), '$ 0', 'money formatea el cero');
T::eq(quitarCeros('17,000.00'), '17,000', 'quitarCeros quita .00');
T::eq(quitarCeros('1,234.50'), '1,234.5', 'quitarCeros quita solo el cero sobrante');
T::eq(quitarCeros('17,000'), '17,000', 'quitarCeros no toca cifras sin decimales');

/* ---------- formatoBytes ---------- */
T::eq(formatoBytes(0), '0 B', 'formatoBytes de 0');
T::eq(formatoBytes(900), '900 B', 'formatoBytes de bytes');
T::eq(formatoBytes(1024), '1 KB', 'formatoBytes convierte a KB');
T::eq(formatoBytes(1048576), '1 MB', 'formatoBytes convierte a MB');

/* ---------- calcComision: esquema porcentaje ---------- */
$pct = ['tipo_comision' => 'porcentaje', 'comision_valor' => 40];
T::eq(calcComision($pct, 100, 1), 40.0, 'comisión 40% de 100 = 40');
T::eq(calcComision($pct, 250, 3), 100.0, 'comisión porcentaje ignora el nº de servicios');
T::eq(calcComision($pct, 0, 1), 0.0, 'comisión de monto 0 = 0');
$pct130 = ['tipo_comision' => 'porcentaje', 'comision_valor' => 130];
T::eq(calcComision($pct130, 100, 1), 100.0, 'comisión porcentaje se limita al 100%');
$neg = ['tipo_comision' => 'porcentaje', 'comision_valor' => -5];
T::eq(calcComision($neg, 100, 1), 0.0, 'comisión porcentaje negativa se acota a 0');

/* ---------- calcComision: esquema tarifa fija ---------- */
$fijo = ['tipo_comision' => 'fijo', 'comision_valor' => 50];
T::eq(calcComision($fijo, 1000, 1), 50.0, 'tarifa fija por 1 servicio');
T::eq(calcComision($fijo, 1000, 3), 150.0, 'tarifa fija × nº de servicios');
T::eq(calcComision($fijo, 0, 0), 50.0, 'tarifa fija con 0 líneas cuenta como mínimo 1');

/* ---------- normalizarFechaHora ---------- */
T::eq(normalizarFechaHora('2026-10-11T14:30'), '2026-10-11 14:30', 'normaliza datetime-local con T');
T::eq(normalizarFechaHora('2026-10-11 14:30'), '2026-10-11 14:30', 'normaliza datetime con espacio');
T::eq(normalizarFechaHora('2026-10-11T14:30:45'), '2026-10-11 14:30', 'normaliza datetime con segundos');
T::eq(normalizarFechaHora('', '1970-01-01 00:00'), '1970-01-01 00:00', 'fecha vacía usa el valor por defecto indicado');
T::eq(normalizarFechaHora('texto inválido', '2000-01-01 08:00'), '2000-01-01 08:00', 'fecha inválida usa el valor por defecto');

/* ---------- parsearNumero ---------- */
T::eq(parsearNumero('1234.56'), 1234.56, 'parsearNumero decimal con punto');
T::eq(parsearNumero('1.234,56'), 1234.56, 'parsearNumero formato europeo');
T::eq(parsearNumero('$ 1,234.56'), 1234.56, 'parsearNumero ignora el símbolo $');
T::eq(parsearNumero('abc'), 0.0, 'parsearNumero de texto no numérico = 0');
T::eq(parsearNumero(50), 50.0, 'parsearNumero de int');
T::eq(parsearNumero(''), 0.0, 'parsearNumero de cadena vacía = 0');
