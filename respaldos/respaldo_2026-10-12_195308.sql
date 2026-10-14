-- ============================================================
-- Respaldo de Barbería Dembow v2.0
-- Base de datos: barberia_control
-- Generado: 2026-10-12 19:53:08
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
  `nota` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_as_atencion` (`atencion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (1,1,1,1,120.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (2,2,2,1,200.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (3,3,3,1,220.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (10,4,1,1,120.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (15,9,4,1,90.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (16,10,3,1,220.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (17,11,3,1,17000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (18,12,1,1,14000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (19,13,4,1,5000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (20,14,1,1,14000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (22,16,3,1,17000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (24,18,4,1,5000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (25,18,6,1,3000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (26,19,3,2,17000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (31,23,7,1,13000.00,'Abono');
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (32,24,4,1,5000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (33,25,3,5,17000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (34,25,6,1,3000.00,NULL);
INSERT INTO `atencion_servicios` (`id`,`atencion_id`,`servicio_id`,`cantidad`,`precio_unit`,`nota`) VALUES (35,26,1,1,14000.00,NULL);
-- 19 registro(s)

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (1,'A-000006','2026-10-10 21:45:00',1,3,'Pedro',NULL,1,120.00,0.00,NULL,120.00,0.00,120.00,48.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (2,'A-000007','2026-10-10 21:45:00',1,1,'Ana',NULL,2,200.00,0.00,NULL,200.00,0.00,200.00,80.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (3,'A-000008','2026-10-11 10:12:00',1,NULL,NULL,NULL,1,220.00,0.00,NULL,220.00,0.00,220.00,88.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 11:16:02',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (4,'A-000009','2026-10-11 17:17:00',1,2,'Luis',NULL,1,120.00,0.00,NULL,120.00,0.00,120.00,48.00,NULL,'activa',NULL,NULL,1,1,'2026-10-11 11:16:02','2026-10-11 18:57:00',1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (9,'A-000009','2026-10-11 19:56:00',3,NULL,NULL,NULL,2,90.00,0.00,NULL,90.00,0.00,90.00,27.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-11 19:56:39',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (10,'A-000010','2026-10-12 10:05:00',3,NULL,NULL,NULL,1,220.00,0.00,NULL,220.00,0.00,220.00,66.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:05:23',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (11,'A-000011','2026-10-12 10:13:00',1,NULL,NULL,NULL,1,17000.00,0.00,NULL,17000.00,0.00,17000.00,6800.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:14:21',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (12,'A-000012','2026-10-12 10:14:00',1,NULL,NULL,NULL,1,14000.00,0.00,NULL,14000.00,0.00,14000.00,5600.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:15:04',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (13,'A-000013','2026-10-12 10:17:00',1,NULL,NULL,NULL,2,5000.00,0.00,NULL,5000.00,0.00,5000.00,2500.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:17:26',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (14,'A-000014','2026-10-12 10:18:00',2,NULL,NULL,NULL,2,14000.00,0.00,NULL,14000.00,0.00,14000.00,7000.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:18:52',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (16,'A-000016','2026-10-12 10:44:00',1,NULL,NULL,NULL,1,17000.00,0.00,NULL,17000.00,0.00,17000.00,8500.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 10:44:18',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (18,'A-000018','2026-10-12 15:39:00',2,NULL,NULL,NULL,1,8000.00,0.00,NULL,8000.00,0.00,8000.00,4000.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 15:39:45',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (19,'A-000019','2026-10-12 15:40:00',3,NULL,NULL,NULL,1,34000.00,0.00,NULL,34000.00,0.00,34000.00,17000.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 15:40:12',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (23,'A-000023','2026-10-12 16:34:00',1,NULL,NULL,NULL,1,13000.00,0.00,NULL,13000.00,0.00,13000.00,6500.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 16:34:43',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (24,'A-000024','2026-10-12 18:13:00',3,NULL,NULL,NULL,1,5000.00,0.00,NULL,5000.00,0.00,5000.00,2500.00,NULL,'activa',NULL,NULL,4,NULL,'2026-10-12 18:13:18',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (25,'A-000025','2026-10-12 18:22:00',3,NULL,NULL,NULL,1,88000.00,0.00,NULL,88000.00,0.00,88000.00,44000.00,NULL,'activa',NULL,NULL,4,NULL,'2026-10-12 18:22:28',NULL,1);
INSERT INTO `atenciones` (`id`,`codigo`,`fecha`,`barbero_id`,`cliente_id`,`cliente_nombre`,`cliente_telefono`,`metodo_pago_id`,`monto_servicios`,`descuento`,`motivo_descuento`,`monto_final`,`propina`,`total_cobrado`,`comision`,`notas`,`estado`,`anulado_por`,`motivo_anulacion`,`creado_por`,`editado_por`,`creado_en`,`editado_en`,`sede_id`) VALUES (26,'A-000026','2026-10-12 19:07:00',2,NULL,NULL,NULL,2,14000.00,0.00,NULL,14000.00,0.00,14000.00,7000.00,NULL,'activa',NULL,NULL,1,NULL,'2026-10-12 19:07:47',NULL,1);
-- 17 registro(s)

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
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (33,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 12:08:16');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (34,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 13:43:44');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (35,1,'Administrador','restaurar_respaldo','respaldos',NULL,'Restauró respaldo auto_2026-10-11_204344.sql (79 sentencias)',NULL,'127.0.0.1','2026-10-11 14:15:00');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (36,1,'Administrador','eliminar_respaldo','respaldos',NULL,'Eliminó respaldo respaldo_2026-10-11_211459.sql',NULL,'127.0.0.1','2026-10-11 14:15:00');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (37,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 14:15:36');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (38,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-11 14:15:44');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (39,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 14:16:34');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (40,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 14:24:17');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (41,1,'Administrador','anonimizar','clientes',2,'Anonimizó datos del cliente (derecho al olvido, Ley 1581)',NULL,'127.0.0.1','2026-10-11 15:00:33');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (42,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-11 19:15:03');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (43,1,'Administrador','respaldar','respaldos',NULL,'Generó respaldo auto_2026-10-12_021503.sql (16 tablas)',NULL,'127.0.0.1','2026-10-11 19:15:03');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (44,1,'Administrador','crear','atenciones',9,'Registró la atención A-000009 ($ 90.00)','[{\"servicio_id\":4,\"cantidad\":1,\"precio_unit\":90,\"nombre\":\"Arreglo de barba\"}]','127.0.0.1','2026-10-11 19:56:40');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (46,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:08:02');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (47,1,'Administrador','abrir_caja','cajas',10,'Abrió la caja con base $ 50.00',NULL,'127.0.0.1','2026-10-12 09:08:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (48,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:08:33');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (49,1,'Administrador','cerrar_caja','cajas',10,'Cerró la caja — efectivo $ 50.00 (dif. $ 0.00), digital $ 0.00 (dif. $ 0.00)',NULL,'127.0.0.1','2026-10-12 09:08:35');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (50,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:08:49');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (51,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:09:53');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (52,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:11:13');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (53,NULL,'sistema','revertir_prueba','barberos',1,'Restauró teléfono de prueba (555-0101) modificado durante diagnóstico',NULL,NULL,'2026-10-12 09:11:35');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (54,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:20:01');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (55,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:21:02');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (56,1,'Administrador','abrir_caja','cajas',11,'Abrió la caja con base $ 25.00',NULL,'127.0.0.1','2026-10-12 09:21:22');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (57,1,'Administrador','cerrar_caja','cajas',11,'Cerró la caja — efectivo $ 25.00 (dif. $ 0.00), digital $ 0.00 (dif. $ 0.00)',NULL,'127.0.0.1','2026-10-12 09:21:38');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (58,NULL,'sistema','revertir_prueba','productos',4,'Restauró stock mínimo de prueba (3) modificado durante verificación',NULL,NULL,'2026-10-12 09:22:27');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (59,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:22:33');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (60,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 09:27:20');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (61,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:27:29');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (62,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:52:27');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (63,1,'Administrador','abrir_caja','cajas',12,'Abrió la caja con base $ 10.00',NULL,'127.0.0.1','2026-10-12 09:52:47');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (64,1,'Administrador','cerrar_caja','cajas',12,'Cerró la caja — efectivo $ 10.00 (dif. $ 0.00), digital $ 0.00 (dif. $ 0.00)',NULL,'127.0.0.1','2026-10-12 09:53:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (65,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:54:13');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (66,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:55:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (67,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 09:56:43');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (68,1,'Administrador','abrir_caja','cajas',13,'Abrió la caja con base $ 0.00',NULL,'127.0.0.1','2026-10-12 10:04:58');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (69,1,'Administrador','crear','atenciones',10,'Registró la atención A-000010 ($ 220.00)','[{\"servicio_id\":3,\"cantidad\":1,\"precio_unit\":220,\"nombre\":\"Corte + barba\"}]','127.0.0.1','2026-10-12 10:05:23');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (70,1,'Administrador','eliminar','productos',4,'Eliminó el producto #4',NULL,'127.0.0.1','2026-10-12 10:09:22');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (71,1,'Administrador','eliminar','productos',2,'Eliminó el producto #2',NULL,'127.0.0.1','2026-10-12 10:09:25');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (72,1,'Administrador','eliminar','productos',5,'Eliminó el producto #5',NULL,'127.0.0.1','2026-10-12 10:09:27');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (73,1,'Administrador','eliminar','productos',3,'Eliminó el producto #3',NULL,'127.0.0.1','2026-10-12 10:09:42');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (74,1,'Administrador','eliminar','productos',6,'Eliminó el producto #6',NULL,'127.0.0.1','2026-10-12 10:09:45');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (75,1,'Administrador','crear','atenciones',11,'Registró la atención A-000011 ($ 17,000.00)','[{\"servicio_id\":3,\"cantidad\":1,\"precio_unit\":17000,\"nombre\":\"Corte + barba\"}]','127.0.0.1','2026-10-12 10:14:21');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (76,1,'Administrador','crear','atenciones',12,'Registró la atención A-000012 ($ 14,000.00)','[{\"servicio_id\":1,\"cantidad\":1,\"precio_unit\":14000,\"nombre\":\"Corte clásico\"}]','127.0.0.1','2026-10-12 10:15:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (77,1,'Administrador','crear','atenciones',13,'Registró la atención A-000013 ($ 5,000.00)','[{\"servicio_id\":4,\"cantidad\":1,\"precio_unit\":5000,\"nombre\":\"Arreglo de barba\"}]','127.0.0.1','2026-10-12 10:17:26');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (78,1,'Administrador','crear','atenciones',14,'Registró la atención A-000014 ($ 14,000.00)','[{\"servicio_id\":1,\"cantidad\":1,\"precio_unit\":14000,\"nombre\":\"Corte clásico\"}]','127.0.0.1','2026-10-12 10:18:52');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (79,NULL,'sistema','corregir_apertura','cajas',13,'Ajustó fecha de apertura desfasada 2026-10-12 17:04:00 → 2026-10-12 10:04:00 (timezone de PHP corregida a America/Bogota)',NULL,NULL,'2026-10-12 10:27:24');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (80,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:28:27');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (81,1,'Administrador','crear','atenciones',15,'Registró la atención A-000015 ($ 5,000.00)','[{\"servicio_id\":4,\"cantidad\":1,\"precio_unit\":5000,\"nombre\":\"Arreglo de barba\"}]','127.0.0.1','2026-10-12 10:28:29');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (82,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:30:08');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (83,NULL,'sistema','reparar_venta','ventas_productos',3,'Restauró metodo_pago_id = 1 (estaba en 0 por SELECT sin columna id)',NULL,NULL,'2026-10-12 10:34:28');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (84,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:35:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (85,NULL,'sistema','limpiar_pruebas',NULL,NULL,'Eliminó datos de prueba automatizada: atención A-000015, ventas de Agua y Coronita (stock restaurado)',NULL,NULL,'2026-10-12 10:35:40');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (86,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:36:12');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (87,1,'Administrador','cerrar_caja','cajas',13,'Cerró la caja — efectivo $ 31,220.00 (dif. $ 0.00), digital $ 19,000.00 (dif. $ 0.00)',NULL,'127.0.0.1','2026-10-12 10:44:05');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (88,1,'Administrador','crear','atenciones',16,'Registró la atención A-000016 ($ 17,000.00)','[{\"servicio_id\":3,\"cantidad\":1,\"precio_unit\":17000,\"nombre\":\"Corte + barba\"}]','127.0.0.1','2026-10-12 10:44:18');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (89,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:57:22');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (90,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 10:57:28');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (91,4,'Barbero de Prueba','login','usuarios',4,'Inició sesión',NULL,'127.0.0.1','2026-10-12 10:57:30');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (92,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 12:41:58');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (93,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 13:56:06');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (104,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 14:10:32');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (105,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 14:11:45');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (106,1,'Administrador','abrir_caja','cajas',16,'Abrió la caja con base $ 0',NULL,'127.0.0.1','2026-10-12 15:39:30');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (107,1,'Administrador','crear','atenciones',18,'Registró la atención A-000018 ($ 8,000)','[{\"servicio_id\":4,\"cantidad\":1,\"precio_unit\":5000,\"nombre\":\"Arreglo de barba\"},{\"servicio_id\":6,\"cantidad\":1,\"precio_unit\":3000,\"nombre\":\"Cejas\"}]','127.0.0.1','2026-10-12 15:39:45');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (108,1,'Administrador','crear','atenciones',19,'Registró la atención A-000019 ($ 34,000)','[{\"servicio_id\":3,\"cantidad\":2,\"precio_unit\":17000,\"nombre\":\"Corte + barba\"}]','127.0.0.1','2026-10-12 15:40:13');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (109,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:21:43');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (112,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 16:25:35');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (113,4,'Barbero de Prueba','login','usuarios',4,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:25:37');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (115,4,'Barbero de Prueba','logout','usuarios',4,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 16:26:14');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (116,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:26:16');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (117,1,'Administrador','crear','atenciones',23,'Registró la atención A-000023 ($ 13,000)','[{\"servicio_id\":7,\"cantidad\":1,\"precio_unit\":13000,\"nombre\":\"Otro valor\",\"nota\":\"Abono\"}]','127.0.0.1','2026-10-12 16:34:43');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (118,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:36:21');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (119,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:40:50');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (120,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 16:50:23');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (121,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 16:50:55');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (122,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 17:00:15');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (123,4,'Harold','login','usuarios',4,'Inició sesión',NULL,'127.0.0.1','2026-10-12 17:00:24');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (124,5,'QA Barbero','login','usuarios',5,'Inició sesión',NULL,'127.0.0.1','2026-10-12 17:51:59');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (125,5,'QA Barbero','logout','usuarios',5,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 17:55:04');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (126,5,'QA Barbero','login','usuarios',5,'Inició sesión',NULL,'127.0.0.1','2026-10-12 17:55:08');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (127,5,'QA Barbero','logout','usuarios',5,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 17:55:12');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (128,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 17:55:15');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (129,4,'Harold','crear','atenciones',24,'Registró la atención A-000024 ($ 5,000)','[{\"servicio_id\":4,\"cantidad\":1,\"precio_unit\":5000,\"nombre\":\"Arreglo de barba\",\"nota\":null}]','127.0.0.1','2026-10-12 18:13:19');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (130,4,'Harold','logout','usuarios',4,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 18:13:40');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (131,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 18:13:45');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (132,6,'QA Barbero','login','usuarios',6,'Inició sesión',NULL,'127.0.0.1','2026-10-12 18:20:08');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (133,6,'QA Barbero','logout','usuarios',6,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 18:20:13');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (134,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 18:20:15');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (135,1,'Administrador','logout','usuarios',1,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 18:20:45');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (136,4,'Harold','login','usuarios',4,'Inició sesión',NULL,'127.0.0.1','2026-10-12 18:20:56');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (137,4,'Harold','crear','atenciones',25,'Registró la atención A-000025 ($ 88,000)','[{\"servicio_id\":3,\"cantidad\":5,\"precio_unit\":17000,\"nombre\":\"Corte + barba\",\"nota\":null},{\"servicio_id\":6,\"cantidad\":1,\"precio_unit\":3000,\"nombre\":\"Cejas\",\"nota\":null}]','127.0.0.1','2026-10-12 18:22:28');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (138,4,'Harold','logout','usuarios',4,'Cerró sesión',NULL,'127.0.0.1','2026-10-12 18:23:48');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (139,1,'Administrador','login','usuarios',1,'Inició sesión',NULL,'127.0.0.1','2026-10-12 18:23:53');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (140,1,'Administrador','crear','atenciones',26,'Registró la atención A-000026 ($ 14,000)','[{\"servicio_id\":1,\"cantidad\":1,\"precio_unit\":14000,\"nombre\":\"Corte clásico\",\"nota\":null}]','127.0.0.1','2026-10-12 19:07:47');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (141,1,'Administrador','cerrar_caja','cajas',16,'Cerró la caja — efectivo $ 148,000 (dif. $ 0), digital $ 14,000 (dif. $ 0)',NULL,'127.0.0.1','2026-10-12 19:07:57');
INSERT INTO `auditoria` (`id`,`usuario_id`,`usuario_nombre`,`accion`,`tabla`,`registro_id`,`descripcion`,`detalles`,`ip`,`creado_en`) VALUES (142,1,'Administrador','abrir_caja','cajas',17,'Abrió la caja con base $ 0',NULL,'127.0.0.1','2026-10-12 19:27:14');
-- 96 registro(s)

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

INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (1,'Camilo',NULL,40.00,'555-0101',NULL,'porcentaje',50.00,1,'2026-10-10 07:42:27');
INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (2,'Daniel',NULL,35.00,NULL,NULL,'porcentaje',50.00,1,'2026-10-10 07:42:27');
INSERT INTO `barberos` (`id`,`nombre`,`documento`,`porcentaje`,`telefono`,`fecha_ingreso`,`tipo_comision`,`comision_valor`,`activo`,`creado_en`) VALUES (3,'Harold',NULL,30.00,NULL,NULL,'porcentaje',50.00,1,'2026-10-10 07:42:27');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (10,'2026-10-12 16:08:00','2026-10-12 16:08:00',50.00,0.00,0.00,0.00,50.00,50.00,0.00,0.00,0.00,NULL,1);
INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (11,'2026-10-12 16:21:00','2026-10-12 16:21:00',25.00,0.00,0.00,0.00,25.00,25.00,0.00,0.00,0.00,NULL,1);
INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (12,'2026-10-12 16:52:00','2026-10-12 16:53:00',10.00,0.00,0.00,0.00,10.00,10.00,0.00,0.00,0.00,NULL,1);
INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (13,'2026-10-12 10:04:00','2026-10-12 10:44:00',0.00,50220.00,31220.00,19000.00,31220.00,31220.00,0.00,19000.00,0.00,NULL,1);
INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (16,'2026-10-12 15:39:00','2026-10-12 19:07:00',0.00,162000.00,148000.00,14000.00,148000.00,148000.00,0.00,14000.00,0.00,NULL,1);
INSERT INTO `cajas` (`id`,`fecha_apertura`,`fecha_cierre`,`monto_apertura`,`ingresos_turno`,`efectivo_turno`,`digital_turno`,`monto_cierre`,`monto_esperado`,`diferencia`,`digital_declarado`,`digital_diferencia`,`notas`,`sede_id`) VALUES (17,'2026-10-12 19:27:00',NULL,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1);
-- 6 registro(s)

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
INSERT INTO `clientes` (`id`,`nombre`,`telefono`,`visitas`,`creado_en`) VALUES (2,'Cliente #2',NULL,1,'2026-10-11 11:16:02');
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
INSERT INTO `meta` (`k`,`v`) VALUES ('v22',1);
INSERT INTO `meta` (`k`,`v`) VALUES ('v23',1);
-- 5 registro(s)

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
INSERT INTO `metodos_pago` (`id`,`nombre`,`tipo`,`activo`) VALUES (2,'Transferencia Nequi','digital',1);
-- 2 registro(s)

-- ------------------------------------------------------------
-- Estructura de `mutex_caja`
DROP TABLE IF EXISTS `mutex_caja`;
CREATE TABLE `mutex_caja` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `caja_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mutex_caja` (`id`,`caja_id`) VALUES (1,17);
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (1,'Pomada mate',18.00,5.00,35.00,80.00,0,'2026-10-10 07:42:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (8,'Agua',1.00,0.00,0.00,2000.00,1,'2026-10-12 10:10:31');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (9,'Coronita',12.00,0.00,0.00,4000.00,1,'2026-10-12 10:10:45');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (10,'Jugo de Corozo',20.00,0.00,0.00,3000.00,1,'2026-10-12 10:11:27');
INSERT INTO `productos` (`id`,`nombre`,`stock`,`stock_min`,`costo`,`precio`,`activo`,`creado_en`) VALUES (11,'Costeñita',30.00,0.00,0.00,3000.00,1,'2026-10-12 10:11:56');
-- 5 registro(s)

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
  `es_otro_valor` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (1,'Corte clásico',14000.00,25,1,0,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (2,'Corte moderno',160.00,30,1,0,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (3,'Corte + barba',17000.00,45,1,0,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (4,'Arreglo de barba',5000.00,20,1,0,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (6,'Cejas',3000.00,20,1,0,'2026-10-10 07:42:27');
INSERT INTO `servicios` (`id`,`nombre`,`precio`,`duracion`,`activo`,`es_otro_valor`,`creado_en`) VALUES (7,'Otro valor',0.00,NULL,1,1,'2026-10-12 16:20:26');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (1,1,120.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (2,2,160.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (3,3,220.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (4,4,90.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (6,6,90.00,'2026-10-11 11:12:40');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (9,4,5000.00,'2026-10-12 10:06:54');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (10,3,17000.00,'2026-10-12 10:07:08');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (11,1,14000.00,'2026-10-12 10:07:16');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (12,6,3000.00,'2026-10-12 10:08:15');
INSERT INTO `servicios_precios` (`id`,`servicio_id`,`precio`,`cambiado_en`) VALUES (13,7,0.00,'2026-10-12 16:20:26');
-- 10 registro(s)

-- ------------------------------------------------------------
-- Estructura de `usuarios`
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('admin','barbero') NOT NULL DEFAULT 'admin',
  `barbero_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`id`,`nombre`,`usuario`,`contrasena`,`rol`,`barbero_id`,`activo`,`ultimo_acceso`,`creado_en`) VALUES (1,'Administrador','admin','$2y$10$7o05eehxtdZ1NXa0chZ27OfaKGajdzzrNm3TQCs/m7HZxllI7K8Bu','admin',NULL,1,'2026-10-12 18:23:00','2026-10-11 11:12:40');
INSERT INTO `usuarios` (`id`,`nombre`,`usuario`,`contrasena`,`rol`,`barbero_id`,`activo`,`ultimo_acceso`,`creado_en`) VALUES (4,'Harold','Harold','$2y$10$AFjwyaXZ1CDTaKkHjQCekOJyE.PxV7VzZmuG6PIKsWGG/08CFgFBC','barbero',3,1,'2026-10-12 18:20:00','2026-10-11 14:15:20');
-- 2 registro(s)

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ventas_productos` (`id`,`fecha`,`producto_id`,`cantidad`,`precio_unit`,`costo_unit`,`total`,`metodo_pago`,`metodo_pago_id`,`sede_id`) VALUES (2,'2026-10-10 21:45:00',1,2.00,80.00,35.00,160.00,'efectivo',1,1);
-- 1 registro(s)

SET FOREIGN_KEY_CHECKS = 1;