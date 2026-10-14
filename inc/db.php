<?php
/**
 * Conexión a MySQL + esquema v2 + migración desde v1.
 * La base de datos, tablas y datos iniciales se crean solos.
 */
require_once __DIR__ . '/config.php';

/** ¿La conexión actual es SQLite? (se usa en pruebas con SQLite en memoria). */
function pdo_es_sqlite(PDO $pdo): bool {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

function pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            try {
                $admin = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
                $admin->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
            } catch (PDOException $e2) {
                exit('No se pudo conectar a MySQL: ' . $e2->getMessage());
            }
        }
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo) {
    $pdo->exec("SET NAMES utf8mb4");

    $statements = [
        // ---------- USUARIOS ----------
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            usuario VARCHAR(50) NOT NULL,
            contrasena VARCHAR(255) NOT NULL,
            rol ENUM('admin','barbero') NOT NULL DEFAULT 'admin',
            barbero_id INT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_acceso DATETIME NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usuario (usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- BARBEROS (RF-01) ----------
        "CREATE TABLE IF NOT EXISTS barberos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            documento VARCHAR(30) NULL,
            telefono VARCHAR(60) NULL,
            fecha_ingreso DATE NULL,
            tipo_comision ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
            comision_valor DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '% sobre venta o tarifa fija por servicio',
            porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'v1 (migración)',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- SERVICIOS (RF-02) ----------
        "CREATE TABLE IF NOT EXISTS servicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            duracion INT NULL COMMENT 'minutos',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            es_otro_valor TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = precio libre colocado por el barbero (RF-07)',
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- HISTORIAL DE PRECIOS (RF-03) ----------
        "CREATE TABLE IF NOT EXISTS servicios_precios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            servicio_id INT NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            cambiado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sp_servicio (servicio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- MÉTODOS DE PAGO (RF-04) ----------
        "CREATE TABLE IF NOT EXISTS metodos_pago (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            tipo ENUM('efectivo','digital') NOT NULL DEFAULT 'efectivo' COMMENT 'efectivo entra a la caja; digital no',
            activo TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- ATENCIONES (RF-05..RF-09) ----------
        "CREATE TABLE IF NOT EXISTS atenciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(12) NOT NULL,
            fecha DATETIME NOT NULL,
            barbero_id INT NOT NULL,
            metodo_pago_id INT NOT NULL,
            monto_servicios DECIMAL(10,2) NOT NULL DEFAULT 0,
            descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
            motivo_descuento VARCHAR(255) NULL,
            monto_final DECIMAL(10,2) NOT NULL DEFAULT 0,
            propina DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_cobrado DECIMAL(10,2) NOT NULL DEFAULT 0,
            comision DECIMAL(10,2) NOT NULL DEFAULT 0,
            notas VARCHAR(255) NULL,
            estado ENUM('activa','anulada') NOT NULL DEFAULT 'activa',
            anulado_por INT NULL,
            motivo_anulacion VARCHAR(255) NULL,
            creado_por INT NULL,
            editado_por INT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            editado_en DATETIME NULL,
            KEY idx_at_fecha (fecha),
            KEY idx_at_barbero (barbero_id),
            KEY idx_at_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- DETALLE DE SERVICIOS (RF-06) ----------
        "CREATE TABLE IF NOT EXISTS atencion_servicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            atencion_id INT NOT NULL,
            servicio_id INT NOT NULL,
            cantidad INT NOT NULL DEFAULT 1,
            precio_unit DECIMAL(10,2) NOT NULL COMMENT 'precio snapshot',
            nota VARCHAR(255) NULL COMMENT 'motivo del ingreso (solo en el servicio Otro valor)',
            KEY idx_as_atencion (atencion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- ADELANTOS / DESCUENTOS (RF-14) ----------
        "CREATE TABLE IF NOT EXISTS adelantos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barbero_id INT NOT NULL,
            fecha DATE NOT NULL,
            monto DECIMAL(10,2) NOT NULL,
            tipo ENUM('adelanto','descuento') NOT NULL DEFAULT 'adelanto',
            motivo VARCHAR(255) NULL,
            creado_por INT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- PRODUCTOS ----------
        "CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            stock DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock_min DECIMAL(10,2) NOT NULL DEFAULT 0,
            costo DECIMAL(10,2) NOT NULL DEFAULT 0,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- VENTAS DE PRODUCTOS ----------
        "CREATE TABLE IF NOT EXISTS ventas_productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATETIME NOT NULL,
            producto_id INT NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL,
            precio_unit DECIMAL(10,2) NOT NULL,
            costo_unit DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            metodo_pago VARCHAR(50) NOT NULL DEFAULT 'Efectivo',
            metodo_pago_id INT NULL,
            KEY idx_vp_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- CAJAS (RF-12) ----------
        "CREATE TABLE IF NOT EXISTS cajas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha_apertura DATETIME NOT NULL,
            fecha_cierre DATETIME NULL,
            monto_apertura DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'base de efectivo',
            ingresos_turno DECIMAL(10,2) NULL,
            efectivo_turno DECIMAL(10,2) NULL,
            digital_turno DECIMAL(10,2) NULL,
            monto_cierre DECIMAL(10,2) NULL COMMENT 'efectivo contado',
            monto_esperado DECIMAL(10,2) NULL COMMENT 'base + efectivo del turno',
            diferencia DECIMAL(10,2) NULL,
            digital_declarado DECIMAL(10,2) NULL,
            digital_diferencia DECIMAL(10,2) NULL,
            notas VARCHAR(255) NULL,
            KEY idx_caja_apertura (fecha_apertura)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ---------- AUDITORÍA (RF-09) ----------
        "CREATE TABLE IF NOT EXISTS auditoria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            usuario_nombre VARCHAR(100) NULL,
            accion VARCHAR(30) NOT NULL,
            tabla VARCHAR(40) NULL,
            registro_id INT NULL,
            descripcion VARCHAR(255) NULL,
            detalles TEXT NULL,
            ip VARCHAR(45) NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_aud_tabla (tabla, registro_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    // ================= MIGRACIÓN =================
    if (!$pdo->query("SELECT COUNT(*) FROM meta WHERE k = 'v2'")->fetchColumn()) {
        migrate_v1_to_v2($pdo);
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('v2', '1')");
    }

    // Datos iniciales solo la primera vez
    if (!$pdo->query("SELECT COUNT(*) FROM meta WHERE k = 'seeded'")->fetchColumn()) {
        seed_inicial($pdo);
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('seeded', '1')");
    }

    // ================= v2.1: SEDES (RNF-14) + MUTEX CAJA (RNF-05) =================
    if (!$pdo->query("SELECT COUNT(*) FROM meta WHERE k = 'v21'")->fetchColumn()) {
        // Sedes: esquema listo para crecer a varias sedes sin rediseñar
        $pdo->exec("CREATE TABLE IF NOT EXISTS sedes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            direccion VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO sedes (id, nombre, direccion) VALUES (1, 'Principal', NULL)");

        // Columna sede_id donde no exista
        $tablas = ['atenciones', 'cajas', 'ventas_productos'];
        foreach ($tablas as $tabla) {
            $existe = (int)$pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($tabla) . " AND COLUMN_NAME = 'sede_id'"
            )->fetchColumn();
            if (!$existe) {
                $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN sede_id INT NOT NULL DEFAULT 1");
            }
        }

        // Mutex de caja: bloqueo de fila para evitar dos cajas abiertas en carrera (RNF-05)
        $pdo->exec("CREATE TABLE IF NOT EXISTS mutex_caja (
            id TINYINT NOT NULL DEFAULT 1,
            caja_id INT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO mutex_caja (id, caja_id) VALUES (1, NULL)");

        $pdo->exec("INSERT INTO meta (k, v) VALUES ('v21', '1')");
    }

    // ================= v2.2: se elimina el rol 'cajero' =================
    // El administrador asume todas las funciones; los usuarios de caja pasan a admin.
    if (!$pdo->query("SELECT COUNT(*) FROM meta WHERE k = 'v22'")->fetchColumn()) {
        try {
            $pdo->exec("UPDATE usuarios SET rol = 'admin' WHERE rol = 'cajero'");
            $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin','barbero') NOT NULL DEFAULT 'admin'");
        } catch (Exception $ex) {
            // Si el ALTER no funciona (p. ej. SQLite en pruebas) no bloquea la app.
        }
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('v22', '1')");
    }

    // ================= v2.3: servicio "Otro valor" (RF-07) =================
    // Permite cobrar un monto libre colocado por el barbero con su motivo,
    // para clientes que no llegan con el dinero completo.
    if (!$pdo->query("SELECT COUNT(*) FROM meta WHERE k = 'v23'")->fetchColumn()) {
        $cols = $pdo->query("SHOW COLUMNS FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('es_otro_valor', $cols, true)) {
            $pdo->exec("ALTER TABLE servicios ADD COLUMN es_otro_valor TINYINT(1) NOT NULL DEFAULT 0 AFTER activo");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM atencion_servicios")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('nota', $cols, true)) {
            $pdo->exec("ALTER TABLE atencion_servicios ADD COLUMN nota VARCHAR(255) NULL AFTER precio_unit");
        }
        // Servicio especial: siempre activo, precio de referencia 0 (el monto lo pone el barbero).
        if (!$pdo->query("SELECT COUNT(*) FROM servicios WHERE es_otro_valor = 1")->fetchColumn()) {
            $pdo->exec("INSERT INTO servicios (nombre, precio, duracion, activo, es_otro_valor)
                        VALUES ('Otro valor', 0.00, NULL, 1, 1)");
            $pdo->exec("INSERT INTO servicios_precios (servicio_id, precio)
                        SELECT id, precio FROM servicios WHERE es_otro_valor = 1");
        }
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('v23', '1')");
    }
}

/** Migra la estructura v1 (cortes) a v2 (atenciones) preservando los datos. */
function migrate_v1_to_v2(PDO $pdo) {
    // 1. Columnas nuevas de barberos
    $cols = $pdo->query("SHOW COLUMNS FROM barberos")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('documento', $cols, true)) {
        $pdo->exec("ALTER TABLE barberos ADD COLUMN documento VARCHAR(30) NULL AFTER nombre");
    }
    if (!in_array('fecha_ingreso', $cols, true)) {
        $pdo->exec("ALTER TABLE barberos ADD COLUMN fecha_ingreso DATE NULL AFTER telefono");
    }
    if (!in_array('tipo_comision', $cols, true)) {
        $pdo->exec("ALTER TABLE barberos ADD COLUMN tipo_comision ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje' AFTER fecha_ingreso");
    }
    if (!in_array('comision_valor', $cols, true)) {
        $pdo->exec("ALTER TABLE barberos ADD COLUMN comision_valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tipo_comision");
    }
    $pdo->exec("UPDATE barberos SET comision_valor = porcentaje, tipo_comision = 'porcentaje'");

    // 2. Métodos de pago
    $pdo->exec("INSERT IGNORE INTO metodos_pago (id, nombre, tipo) VALUES
        (1, 'Efectivo', 'efectivo'),
        (2, 'Transferencia', 'digital'),
        (3, 'Tarjeta', 'digital')");

    // 3. Historial inicial de precios
    $pdo->exec("INSERT INTO servicios_precios (servicio_id, precio)
        SELECT s.id, s.precio FROM servicios s
        WHERE NOT EXISTS (SELECT 1 FROM servicios_precios sp WHERE sp.servicio_id = s.id)");

    // 4. Usuario administrador inicial
    $pdo->exec("INSERT IGNORE INTO usuarios (nombre, usuario, contrasena, rol, activo) VALUES
        ('Administrador', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 1)");

    // 5. Nuevas columnas de cajas
    $cols = $pdo->query("SHOW COLUMNS FROM cajas")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('efectivo_turno', $cols, true)) {
        $pdo->exec("ALTER TABLE cajas ADD COLUMN efectivo_turno DECIMAL(10,2) NULL AFTER ingresos_turno");
    }
    if (!in_array('digital_turno', $cols, true)) {
        $pdo->exec("ALTER TABLE cajas ADD COLUMN digital_turno DECIMAL(10,2) NULL AFTER efectivo_turno");
    }
    if (!in_array('digital_declarado', $cols, true)) {
        $pdo->exec("ALTER TABLE cajas ADD COLUMN digital_declarado DECIMAL(10,2) NULL AFTER diferencia");
    }
    if (!in_array('digital_diferencia', $cols, true)) {
        $pdo->exec("ALTER TABLE cajas ADD COLUMN digital_diferencia DECIMAL(10,2) NULL AFTER digital_declarado");
    }

    // 6. metodo_pago_id en ventas de productos
    $cols = $pdo->query("SHOW COLUMNS FROM ventas_productos")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('metodo_pago_id', $cols, true)) {
        $pdo->exec("ALTER TABLE ventas_productos ADD COLUMN metodo_pago_id INT NULL AFTER metodo_pago");
    }
    $pdo->exec("UPDATE ventas_productos vp
        JOIN metodos_pago mp ON LOWER(mp.nombre) = LOWER(vp.metodo_pago)
        SET vp.metodo_pago_id = mp.id
        WHERE vp.metodo_pago_id IS NULL");
    $pdo->exec("UPDATE ventas_productos SET metodo_pago_id = 1 WHERE metodo_pago_id IS NULL");

    // 7. Migrar cortes → atenciones
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('cortes', $tablas) && !$pdo->query("SELECT COUNT(*) FROM atenciones")->fetchColumn()) {
        $cortes = $pdo->query("SELECT * FROM cortes")->fetchAll();
        $insAten = $pdo->prepare("INSERT INTO atenciones
            (codigo, fecha, barbero_id, metodo_pago_id, monto_servicios, monto_final, total_cobrado, comision, creado_por)
            VALUES (?,?,?,?,?,?,?,?,1)");
        $insDet = $pdo->prepare("INSERT INTO atencion_servicios (atencion_id, servicio_id, cantidad, precio_unit) VALUES (?,?,1,?)");
        foreach ($cortes as $c) {
            $mp = $pdo->prepare("SELECT id FROM metodos_pago WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
            $mp->execute([(string)$c['metodo_pago']]);
            $mpId = $mp->fetchColumn() ?: 1;
            $insAten->execute([
                codigoAtencion($c['id']),
                $c['fecha'],
                (int)$c['barbero_id'],
                $mpId,
                (float)$c['monto'],
                (float)$c['monto'],
                (float)$c['monto'],
                (float)$c['comision'],
            ]);
            $aid = (int)$pdo->lastInsertId();
            if ($c['servicio_id']) {
                $insDet->execute([$aid, (int)$c['servicio_id'], (float)$c['monto']]);
            }
        }
        $pdo->exec("DROP TABLE IF EXISTS cortes");
    }
}

/** Datos de ejemplo para instalación nueva. */
function seed_inicial(PDO $pdo) {
    $pdo->exec("INSERT INTO barberos (nombre, documento, telefono, fecha_ingreso, tipo_comision, comision_valor) VALUES
        ('Carlos Ramírez', '11.222.333', '555-0101', '2024-01-15', 'porcentaje', 40.00),
        ('Miguel Ángel',  '44.555.666', '555-0102', '2024-03-01', 'porcentaje', 35.00),
        ('Andrés López',   '777.888.999', '555-0103', '2025-06-10', 'porcentaje', 30.00)");

    $pdo->exec("INSERT INTO servicios (nombre, precio, duracion, es_otro_valor) VALUES
        ('Corte clásico', 120.00, 25, 0),
        ('Corte moderno', 160.00, 30, 0),
        ('Corte + barba', 220.00, 45, 0),
        ('Arreglo de barba', 90.00, 20, 0),
        ('Fade / diseño', 180.00, 35, 0),
        ('Corte infantil', 90.00, 20, 0),
        ('Cejas', 40.00, 10, 0),
        ('Tinte', 250.00, 50, 0),
        ('Otro valor', 0.00, NULL, 1)");

    $pdo->exec("INSERT INTO servicios_precios (servicio_id, precio) SELECT id, precio FROM servicios");

    $pdo->exec("INSERT INTO productos (nombre, stock, stock_min, costo, precio) VALUES
        ('Pomada mate',        20,  5, 35.00,  80.00),
        ('Gel de fijación',    15,  5, 25.00,  60.00),
        ('Shampoo barbarejo',  10,  4, 40.00,  95.00),
        ('Aceite de barba',     8,  3, 55.00, 130.00),
        ('Navajas descartables',30, 10,  8.00,  25.00),
        ('Toallas desechables',60, 20,  2.00,   8.00)");
}
