<?php
/**
 * Configuración de la aplicación
 */
define('APP_NAME', 'Barbería Dembow');
define('APP_VERSION', '2.0');

/**
 * Zona horaria única de la app (Colombia · Ley 1581).
 * TODAS las fechas se escriben con este reloj (ahora()): atenciones, cajas,
 * liquidaciones y auditoría. Sin esto, PHP usaría la timezone de php.ini y
 * quedaría desfasada del reloj del navegador → la caja filtraba
 * "fecha >= apertura" y descartaba todo el turno del día.
 */
define('APP_TIMEZONE', 'America/Bogota');
date_default_timezone_set(APP_TIMEZONE);

// Base de datos (XAMPP: usuario root sin contraseña por defecto)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'barberia_control');
define('DB_USER', 'root');
define('DB_PASS', '');

// Sesión
define('SESSION_NAME', 'BARBERIA_V2');
