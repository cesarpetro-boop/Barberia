<?php
/**
 * Ejecutor de pruebas.
 *
 *   php tests/run.php            → pruebas rápidas (sin base de datos)
 *   php tests/run.php --db       → incluye integración con SQLite en memoria
 *   php tests/run.php <filtro>   → solo archivos cuyo nombre contenga el filtro
 *   php tests/run.php --verbose  → muestra cada aserción
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap_tests.php';

$args = array_slice($argv, 1);
$withDb = in_array('--db', $args, true);
$verbose = in_array('--verbose', $args, true);
$filtros = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));

T::$verbose = $verbose;

$archivos = glob(__DIR__ . '/*.test.php') ?: [];
sort($archivos);

$ejecutados = 0;
foreach ($archivos as $file) {
    $nombre = basename($file);
    $esDb = str_contains($nombre, 'db_');

    if ($esDb && !$withDb)
        continue;
    if ($filtros && !array_filter($filtros, fn($f) => str_contains($nombre, $f)))
        continue;

    t_run_file($file);
    $ejecutados++;
}

echo "\n" . str_repeat('─', 56) . "\n";
if ($ejecutados === 0) {
    echo "No se encontraron archivos de prueba para ejecutar.\n";
    exit(1);
}
$total = T::$run;
$failed = T::$failed;
$ok = $total - $failed;

if ($failed === 0) {
    echo "\033[32m✔ {$ok}/{$total} aserciones OK\033[0m — {$ejecutados} archivo(s) de prueba\n";
    exit(0);
}
echo "\033[31m✗ {$failed} de {$total} aserciones fallaron\033[0m ({$ok} OK)\n";
exit(1);
