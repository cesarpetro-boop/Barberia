<?php
/**
 * Arranque del sistema de pruebas (sin dependencias externas).
 *
 * Uso:
 *   php tests/run.php                  -> ejecuta todas las pruebas sin BD
 *   php tests/run.php --db             -> añade las pruebas de integración con SQLite en memoria
 *   php tests/run.php helpers          -> ejecuta solo los archivos que contengan "helpers"
 *   php tests/run.php --verbose        -> muestra también las aserciones que pasan
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/acciones.php';

/* ============================================================
   Mini framework de pruebas
   ============================================================ */

final class T
{
    public static int $run = 0;
    public static int $failed = 0;
    public static string $current = '';
    public static bool $verbose = false;

    public static function group(string $name): void
    {
        self::$current = $name;
        echo "\n\033[1m▶ {$name}\033[0m\n";
    }

    public static function ok(bool $cond, string $msg): void
    {
        self::$run++;
        if ($cond) {
            if (self::$verbose) {
                echo "  \033[32m✓\033[0m {$msg}\n";
            }
            return;
        }
        self::$failed++;
        echo "  \033[31m✗ FALLO:\033[0m {$msg}\n";
    }

    public static function eq(mixed $a, mixed $b, string $msg): void
    {
        $cond = (is_float($a) || is_float($b))
            ? abs((float) $a - (float) $b) < 0.005
            : $a === $b;
        self::ok($cond, $msg . '  (esperado=' . self::fmt($b) . ', obtenido=' . self::fmt($a) . ')');
    }

    private static function fmt(mixed $v): string
    {
        if (is_bool($v))
            return $v ? 'true' : 'false';
        if ($v === null)
            return 'null';
        if (is_array($v))
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        return is_scalar($v) ? (string) $v : gettype($v);
    }
}

/** Ejecuta un archivo de pruebas y devuelve los totales. */
function t_run_file(string $file): void
{
    $name = str_replace('.test.php', '', basename($file));
    T::group($name);
    try {
        require $file;
    } catch (Throwable $e) {
        T::$failed++;
        echo "  \033[31m✗ EXCEPCIÓN:\033[0m " . get_class($e) . ': ' . $e->getMessage()
            . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

/* ============================================================
   Base de datos de prueba: SQLite en memoria + datos de ejemplo
   ============================================================ */

/** Crea una conexión SQLite en memoria con el esquema mínimo y datos de ejemplo. */
function t_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO)
        return $pdo;

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('La extensión pdo_sqlite no está habilitada.');
    }

    $p = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $schema = <<<SQL
CREATE TABLE barberos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo_comision TEXT NOT NULL DEFAULT 'porcentaje',
    comision_valor REAL NOT NULL DEFAULT 0,
    activo INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE servicios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    precio REAL NOT NULL,
    activo INTEGER NOT NULL DEFAULT 1,
    es_otro_valor INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE metodos_pago (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL DEFAULT 'efectivo',
    activo INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE atenciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo TEXT NOT NULL DEFAULT 'TEMP',
    fecha TEXT NOT NULL,
    barbero_id INTEGER NOT NULL,
    metodo_pago_id INTEGER NOT NULL DEFAULT 1,
    monto_servicios REAL NOT NULL DEFAULT 0,
    descuento REAL NOT NULL DEFAULT 0,
    motivo_descuento TEXT NULL,
    monto_final REAL NOT NULL DEFAULT 0,
    propina REAL NOT NULL DEFAULT 0,
    total_cobrado REAL NOT NULL DEFAULT 0,
    comision REAL NOT NULL DEFAULT 0,
    notas TEXT NULL,
    estado TEXT NOT NULL DEFAULT 'activa',
    anulado_por INTEGER NULL,
    motivo_anulacion TEXT NULL,
    creado_por INTEGER NULL,
    editado_por INTEGER NULL,
    editado_en TEXT NULL
);
CREATE TABLE atencion_servicios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    atencion_id INTEGER NOT NULL,
    servicio_id INTEGER NOT NULL,
    cantidad INTEGER NOT NULL DEFAULT 1,
    precio_unit REAL NOT NULL,
    nota TEXT NULL
);
CREATE TABLE adelantos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    barbero_id INTEGER NOT NULL,
    fecha TEXT NOT NULL,
    monto REAL NOT NULL,
    tipo TEXT NOT NULL DEFAULT 'adelanto',
    motivo TEXT NULL,
    creado_por INTEGER NULL
);
CREATE TABLE auditoria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NULL,
    usuario_nombre TEXT NULL,
    accion TEXT NOT NULL,
    tabla TEXT NULL,
    registro_id INTEGER NULL,
    descripcion TEXT NULL,
    detalles TEXT NULL,
    ip TEXT NULL
);
CREATE TABLE cajas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha_apertura TEXT NOT NULL,
    fecha_cierre TEXT NULL,
    monto_apertura REAL NOT NULL DEFAULT 0,
    ingresos_turno REAL NULL,
    efectivo_turno REAL NULL,
    digital_turno REAL NULL,
    monto_cierre REAL NULL,
    monto_esperado REAL NULL,
    diferencia REAL NULL,
    digital_declarado REAL NULL,
    digital_diferencia REAL NULL,
    notas TEXT NULL
);
CREATE TABLE ventas_productos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha TEXT NOT NULL,
    producto_id INTEGER NOT NULL,
    cantidad REAL NOT NULL,
    precio_unit REAL NOT NULL,
    costo_unit REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL,
    metodo_pago TEXT NOT NULL DEFAULT 'Efectivo',
    metodo_pago_id INTEGER NULL
);
CREATE TABLE mutex_caja (id INTEGER PRIMARY KEY, caja_id INTEGER NULL);
INSERT INTO mutex_caja (id, caja_id) VALUES (1, NULL);
SQL;

    foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
        $p->exec($stmt);
    }

    // Datos de ejemplo
    $p->exec("INSERT INTO barberos (nombre, tipo_comision, comision_valor, activo) VALUES
        ('Ana 40%', 'porcentaje', 40, 1),
        ('Beto fijo', 'fijo', 50, 1),
        ('Inactivo', 'porcentaje', 10, 0)");
    $p->exec("INSERT INTO servicios (nombre, precio, activo, es_otro_valor) VALUES
        ('Corte', 100, 1, 0),
        ('Barba', 50, 1, 0),
        ('Inactivo', 999, 0, 0),
        ('Otro valor', 0, 1, 1)");
    $p->exec("INSERT INTO metodos_pago (nombre, tipo, activo) VALUES
        ('Efectivo', 'efectivo', 1),
        ('Transferencia', 'digital', 1)");

    $pdo = $p;
    return $pdo;
}

/** Borra las filas variables entre pruebas (deja catálogos y datos fijos). */
function t_reset(PDO $pdo): void
{
    foreach (['auditoria', 'adelantos', 'atencion_servicios', 'atenciones', 'cajas', 'ventas_productos'] as $t) {
        $pdo->exec("DELETE FROM $t");
    }
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('atenciones','adelantos','cajas','auditoria','atencion_servicios','ventas_productos')");
    $pdo->exec("UPDATE mutex_caja SET caja_id = NULL WHERE id = 1");
}
