<?php
/**
 * Respaldos automáticos y manuales (RNF-11).
 * Volcado SQL completo de la base de datos, un archivo por día.
 */

/** Carpeta de respaldos (bloqueada al público vía .htaccess). */
function dir_respaldos(): string {
    $d = __DIR__ . '/../respaldos';
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
        file_put_contents($d . '/.htaccess', "Require all denied\n");
        file_put_contents($d . '/index.html', '');
    }
    return $d;
}

/** Lista los respaldos existentes, del más reciente al más antiguo. */
function respaldos_listar(): array {
    $d = dir_respaldos();
    $out = [];
    foreach (glob($d . '/*.sql') ?: [] as $f) {
        $out[] = [
            'nombre' => basename($f),
            'archivo' => $f,
            'tam' => (int)filesize($f),
            'fecha' => (int)filemtime($f),
        ];
    }
    usort($out, fn($a, $b) => $b['fecha'] <=> $a['fecha']);
    return $out;
}

/** ¿Ya existe un respaldo automático de hoy? */
function respaldoHoyExistente(): bool {
    $hoy = date('Y-m-d');
    foreach (respaldos_listar() as $r) {
        if (str_starts_with($r['nombre'], 'auto_' . $hoy)) return true;
    }
    return false;
}

/**
 * Respaldo diario automático: se ejecuta al iniciar sesión como admin.
 * Genera a lo sumo un archivo por día.
 */
function respaldoDiarioAutomatico(PDO $pdo): void {
    if (respaldoHoyExistente()) return;
    hacerRespaldo($pdo, 'auto_' . date('Y-m-d_His') . '.sql');
}

/** Escribe un volcado SQL completo de la base de datos. Devuelve la ruta. */
function hacerRespaldo(PDO $pdo, ?string $nombre = null): string {
    $d = dir_respaldos();
    $nombre = $nombre ?: 'respaldo_' . date('Y-m-d_His') . '.sql';
    $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nombre));
    $path = $d . '/' . $nombre;

    $u = usuarioActual();
    $L = [];
    $L[] = '-- ============================================================';
    $L[] = '-- Respaldo de ' . APP_NAME . ' v' . APP_VERSION;
    $L[] = '-- Base de datos: ' . DB_NAME;
    $L[] = '-- Generado: ' . date('Y-m-d H:i:s');
    $L[] = '-- Usuario: ' . ($u ? $u['nombre'] . ' (' . $u['rol'] . ')' : 'sistema');
    $L[] = '-- ============================================================';
    $L[] = '';
    $L[] = 'SET NAMES utf8mb4;';
    $L[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $L[] = '';

    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablas as $t) {
        $L[] = '-- ------------------------------------------------------------';
        $L[] = "-- Estructura de `$t`";
        $L[] = 'DROP TABLE IF EXISTS `' . $t . '`;';
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
        $L[] = $create['Create Table'] . ';';
        $L[] = '';

        $sel = $pdo->query("SELECT * FROM `$t`");
        $n = 0;
        while ($fila = $sel->fetch(PDO::FETCH_ASSOC)) {
            $cols = '`' . implode('`,`', array_keys($fila)) . '`';
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                if (is_numeric($v)) return $v;
                return $pdo->quote((string)$v);
            }, array_values($fila));
            $L[] = "INSERT INTO `$t` ($cols) VALUES (" . implode(',', $vals) . ');';
            $n++;
        }
        $L[] = '-- ' . $n . ' registro(s)';
        $L[] = '';
    }
    $L[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    file_put_contents($path, implode("\n", $L));
    audit($pdo, 'respaldar', 'respaldos', null, 'Generó respaldo ' . $nombre . ' (' . count($tablas) . ' tablas)');
    return $path;
}

/** Divide un script SQL en sentencias respetando comillas. */
function split_sql(string $sql): array {
    $stmts = [];
    $cur = '';
    $len = strlen($sql);
    $enComilla = false;
    $comilla = '';
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($enComilla) {
            $cur .= $c;
            if ($c === '\\' && $i + 1 < $len) {
                $cur .= $sql[++$i];
                continue;
            }
            if ($c === $comilla) $enComilla = false;
            continue;
        }
        if ($c === "'" || $c === '"') {
            $enComilla = true;
            $comilla = $c;
            $cur .= $c;
            continue;
        }
        if ($c === ';') {
            if (trim($cur) !== '') $stmts[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') $stmts[] = trim($cur);
    return $stmts;
}

/** Restaura un respaldo .sql. Devuelve cantidad de sentencias ejecutadas. */
function restaurarRespaldo(PDO $pdo, string $path): int {
    if (!is_file($path)) throw new RuntimeException('Archivo no encontrado.');
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('No se pudo leer el archivo.');
    // Depura comentarios de línea completa ANTES de dividir: el generador
    // emite "-- ..." pegado al DROP/CREATE y si no se quitan se pierde la sentencia.
    $lineas = explode("\n", $sql);
    $limpias = [];
    foreach ($lineas as $ln) {
        $t = ltrim($ln);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) continue;
        $limpias[] = $ln;
    }
    $stmts = split_sql(implode("\n", $limpias));
    // Sin transacción: en MySQL el DDL (DROP/CREATE) hace commit implícito
    // y no se puede envolver. Se ejecuta sentencia por sentencia.
    $ejecutadas = 0;
    try {
        foreach ($stmts as $s) {
            if (trim($s) === '') continue;
            $pdo->exec($s);
            $ejecutadas++;
        }
    } catch (Exception $ex) {
        throw new RuntimeException('Restauración incompleta (' . $ejecutadas . ' sentencias aplicadas): ' . $ex->getMessage(), 0, $ex);
    }
    return $ejecutadas;
}

/** Descarga un respaldo por HTTP. */
function descargarRespaldo(string $path): void {
    if (!is_file($path)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
