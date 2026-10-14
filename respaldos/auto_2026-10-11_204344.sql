-- ============================================================
-- Respaldo de Barbería Lavadero v2.0
-- Base de datos: barberia_control
-- Generado: 2026-10-11 20:43:44
-- Usuario: Administrador (admin)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Estructura de `adelantos`
DROP TABLE IF EXISTS `adelantos`;
CREATE TABLE `adelantos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barbero_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo` enum('adelanto','descuento') NOT NULL DEFAULT 'adelanto',
  `motivo` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 0 registro(s)

-- ------------------------------------------------------------
-- Estructura de `atencion_servicios`
DROP TABLE IF EXISTS `atencion_servicios`;
CREATE TABLE `atencion_servicios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atencion_id` int(11) NOT NULL,
  `servicio_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unit` decimal(10,2) NOT NULL COMMENT 'precio snapshot',
  PRIMARY KEY (`id`),
  KEY `idx_as_atencion` (`atencion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`) VALUES (1,1,1,1,120.00);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`) VALUES (2,2,2,1,200.00);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`) VALUES (3,3,3,1,220.00);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`) VALUES (10,4,1,1,120.00);
-- 4 registro(s)

-- ------------------------------------------------------------
-- Estructura de `atenciones`
DROP TABLE IF EXISTS `atenciones`;
CREATE TABLE `atenciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(12) NOT NULL,
  `fecha` datetime NOT NULL,
  `barbero_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `cliente_nombre` varchar(100) DEFAULT NULL,
  `cliente_telefono` varchar(40) DEFAULT NULL,
  `metodo_pago_id` int(11) NOT NULL,
  `monto_servicios` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `motivo_descuento` varchar(255) DEFAULT NULL,
  `monto_final` decimal(10,2) NOT NULL DEFAULT 0.00,
  `propina` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cobrado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comision` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas` varchar(255) DEFAULT NULL,
  `estado` enum('activa','anulada') NOT NULL DEFAULT 'activa',
  `anulado_por` int(11) DEFAULT NULL,
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `editado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `editado_en` datetime DEFAULT NULL,
  `sede_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_at_fecha` (`fecha`),
  KEY `idx_at_barbero` (`barbero_id`),
  KEY `idx_at_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (1,'A-000006','2026-10-10 21:45:00',1,3,'Pedro',NULL,1,120.00,0.00,NULL,120.00,0.00,120.00,48.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (2,'A-000007','2026-10-10 21:45:00',1,1,'Ana',NULL,2,200.00,0.00,NULL,200.00,0.00,200.00,80.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (3,'A-000008','2026-10-11 10:12:00',1,NULL,NULL,NULL,1,220.00,0.00,NULL,220.00,0.00,220.00,88.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (4,'A-000009','2026-10-11 17:17:00',1,2,'Luis',NULL,1,120.00,0.00,NULL,120.00,0.00,120.00,48.00,NULL,'activa',NULL,NULL,1,1,'2026-10-11 11:16:02','2026-10-11 18:57:00',1);
-- 4 registro(s)

-- ------------------------------------------------------------
-- Estructura de `auditoria`
DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `accion` varchar(30) NOT NULL,
  `tabla` varchar(40) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aud_tabla` (`tabla`,`registro_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (33,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 12:08:16');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (34,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 13:43:44');
-- 2 registro(s)

-- ------------------------------------------------------------
-- Estructura de `barberos`
DROP TABLE IF EXISTS `barberos`;
CREATE TABLE `barberos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `telefono` varchar(60) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `tipo_comision` enum('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
  `comision_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (1,'Carlos Ramírez',NULL,40.00,'',NULL,'porcentaje',40.00,1,'2026-10-10 07:42:27');
INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (2,'Miguel Ángel',NULL,35.00,'',NULL,'porcentaje',35.00,1,'2026-10-10 07:42:27');
INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (3,'Andrés López',NULL,30.00,'',NULL,'porcentaje',30.00,1,'2026-10-10 07:42:27');
-- 3 registro(s)

-- ------------------------------------------------------------
-- Estructura de `cajas`
DROP TABLE IF EXISTS `cajas`;
CREATE TABLE `cajas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_apertura` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'base de efectivo al abrir',
  `ingresos_turno` decimal(10,2) DEFAULT NULL COMMENT 'ingresos totales del turno',
  `efectivo_turno` decimal(10,2) DEFAULT NULL,
  `digital_turno` decimal(10,2) DEFAULT NULL,
  `monto_cierre` decimal(10,2) DEFAULT NULL COMMENT 'efectivo contado al cerrar (arqueo)',
  `monto_esperado` decimal(10,2) DEFAULT NULL COMMENT 'base + efectivo del turno',
  `diferencia` decimal(10,2) DEFAULT NULL COMMENT 'contado - esperado',
  `digital_declarado` decimal(10,2) DEFAULT NULL,
  `digital_diferencia` decimal(10,2) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `sede_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_apertura` (`fecha_apertura`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 0 registro(s)

-- ------------------------------------------------------------
-- Estructura de `clientes`
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `visitas` int(11) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cliente_telefono` (`telefono`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `clientes` (`id`,`nombre`,`telefono`,`visitas`,`creado_en`) VALUES (1,'Ana',NULL,1,'2026-10-11 11:16:02');
INSERT INTO `clientes` (`id`,`nombre`,`telefono`,`visitas`,`creado_en`) VALUES (2,'Luis',NULL,1,'2026-10-11 11:16:02');
INSERT INTO `clientes` (`id`,`nombre`,`telefono`,`visitas`,`creado_en`) VALUES (3,'Pedro',NULL,1,'2026-10-11 11:16:02');
-- 3 registro(s)

-- ------------------------------------------------------------
-- Estructura de `meta`
DROP TABLE IF EXISTS `meta`;
CREATE TABLE `meta` (
  `k` varchar(50) NOT NULL,
  `v` text DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `meta` (`k`,`v`) VALUES ('seeded',1);
INSERT INTO `meta` (`k`,`v`) VALUES ('v2',1);
INSERT INTO `meta` (`k`,`v`) VALUES ('v21',1);
-- 3 registro(s)

-- ------------------------------------------------------------
-- Estructura de `metodos_pago`
DROP TABLE IF EXISTS `metodos_pago`;
CREATE TABLE `metodos_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('efectivo','digital') NOT NULL DEFAULT 'efectivo' COMMENT 'efectivo entra a la caja; digital no',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `metodos_pago` (`id`,`nombre`,`tipo`,`activo`) VALUES (1,'Efectivo','efectivo',1);
INSERT INTO `metodos_pago` (`id`,`nombre`,`tipo`,`activo`) VALUES (2,'Transferencia','digital',1);
INSERT INTO `metodos_pago` (`id`,`nombre`,`tipo`,`activo`) VALUES (3,'Tarjeta','digital',1);
-- 3 registro(s)

-- ------------------------------------------------------------
-- Estructura de `mutex_caja`
DROP TABLE IF EXISTS `mutex_caja`;
CREATE TABLE `mutex_caja` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `caja_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mutex_caja` (`id`,`caja_id`) VALUES (1,NULL);
-- 1 registro(s)

-- ------------------------------------------------------------
-- Estructura de `productos`
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_min` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'stock mínimo antes de alerta',
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (1,'Pomada mate',18.00,5.00,35.00,80.00,1,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (2,'Gel de fijación',15.00,5.00,25.00,60.00,1,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (3,'Shampoo barbarejo',10.00,4.00,40.00,95.00,1,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (4,'Aceite de barba',8.00,3.00,55.00,130.00,1,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (5,'Navajas descartables',30.00,10.00,8.00,25.00,1,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (6,'Toallas desechables',60.00,20.00,2.00,8.00,1,'2026-10-10 07:42:27');
-- 6 registro(s)

-- ------------------------------------------------------------
-- Estructura de `sedes`
DROP TABLE IF EXISTS `sedes`;
CREATE TABLE `sedes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sedes` (`id`,`nombre`,`direccion`,`activo`,`creado_en`) VALUES (1,'Principal',NULL,1,'2026-10-11 13:33:26');
-- 1 registro(s)

-- ------------------------------------------------------------
-- Estructura de `servicios`
DROP TABLE IF EXISTS `servicios`;
CREATE TABLE `servicios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `duracion` int(11) DEFAULT NULL COMMENT 'minutos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (1,'Corte clásico',120.00,25,1,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (2,'Corte moderno',160.00,30,1,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (3,'Corte + barba',220.00,45,1,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (4,'Arreglo de barba',90.00,20,1,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (5,'Fade / diseño',180.00,35,1,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`creado_en`) VALUES (6,'Corte infantil',90.00,20,1,'2026-10-10 07:42:27');
-- 6 registro(s)

-- ------------------------------------------------------------
-- Estructura de `servicios_precios`
DROP TABLE IF EXISTS `servicios_precios`;
CREATE TABLE `servicios_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `servicio_id` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `cambiado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sp_servicio` (`servicio_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (1,1,120.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (2,2,160.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (3,3,220.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (4,4,90.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (5,5,180.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (6,6,90.00,'2026-10-11 11:12:40');
-- 6 registro(s)

-- ------------------------------------------------------------
-- Estructura de `usuarios`
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('admin','cajero','barbero') NOT NULL DEFAULT 'cajero',
  `barbero_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`id`,`nombre`,`usuario`,`contrasena`,`rol`,`barbero_id`,`activo`,`ultimo_acceso`,`creado_en`) VALUES (1,'Administrador','admin','$2y$10$7o05eehxtdZ1NXa0chZ27OfaKGajdzzrNm3TQCs/m7HZxllI7K8Bu','admin',NULL,1,'2026-10-11 20:43:00','2026-10-11 11:12:40');
-- 1 registro(s)

-- ------------------------------------------------------------
-- Estructura de `ventas_productos`
DROP TABLE IF EXISTS `ventas_productos`;
CREATE TABLE `ventas_productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unit` decimal(10,2) NOT NULL,
  `costo_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia') NOT NULL DEFAULT 'efectivo',
  `metodo_pago_id` int(11) DEFAULT NULL,
  `sede_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `fk_ventas_producto` (`producto_id`),
  CONSTRAINT `fk_ventas_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ventas_productos` (`id`,`fecha`,`producto_id`,`cantidad`,`precio_unit`,`costo_unit`,`total`,`metodo_pago`,`metodo_pago_id`,`sede_id`) VALUES (2,'2026-10-10 21:45:00',1,2.00,80.00,35.00,160.00,'efectivo',1,1);
-- 1 registro(s)

SET FOREIGN_KEY_CHECKS = 1;