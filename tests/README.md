# Pruebas (tests) — Barbería Dembow

Suite de pruebas **sin dependencias externas** (no requiere Composer ni PHPUnit).
Usa el PHP de XAMPP. Ejecútala desde la raíz del proyecto.

## Comandos

```powershell
# Pruebas rápidas: ayudantes puros (no toca la base de datos)
php tests/run.php

# Todas las pruebas, incluida la lógica de negocio con SQLite en memoria
php tests/run.php --db

# Ver cada aserción que pasa
php tests/run.php --db --verbose

# Ejecutar solo algunos archivos (por coincidencia en el nombre)
php tests/run.php liquidacion
```

Códigos de salida: `0` = todo OK, `1` = hay fallos.

## Qué se prueba

| Archivo                      | Cubre                                                                                                                       | Requiere BD |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------- | ----------- |
| `01_helpers.test.php`        | `codigoAtencion`, `iniciales`, `hueNombre`, `money`, `quitarCeros`, `formatoBytes`, `calcComision`, `normalizarFechaHora`, `parsearNumero` | No          |
| `10_db_atenciones.test.php` | Crear/editar/anular atenciones y comisiones (% y fijo); ignora campos antiguos de propina/cortesía | Sí (`--db`) |
| `11_db_caja.test.php`        | Efectivo/digital del turno, efectivo esperado, comisión del turno, arqueo y mutex                                           | Sí (`--db`) |
| `15_caja_obligatoria.test.php` | RF-12: abrir caja obligatorio para iniciar el turno (bloqueo de registro/venta, UI bloqueada y estado `turnoIniciado`)      | No          |
| `16_otro_valor.test.php`   | RF-07: servicio especial «Otro valor» — monto libre con motivo obligatorio, validaciones, formato, comisión fija y edición     | No          |
| `12_db_liquidacion.test.php` | Liquidación por barbero (comisiones − adelantos − descuentos), reportes y horas pico | Sí (`--db`) |
| `13_db_queries.test.php` | Humo de las consultas de las pantallas (panel, reportes) tras quitar clientes | Sí (`--db`) |
| `14_db_roles.test.php` | El rol `cajero` ya no existe y el administrador abre/cierra la caja | Sí (`--db`) |

## Cómo funciona

- `bootstrap_tests.php` carga los `inc/*.php` reales, define un mini framework de
  aserciones (`T::ok`, `T::eq`, `T::group`) y crea una base **SQLite en memoria**
  con el esquema mínimo y datos de ejemplo.
- La lógica de negocio (`guardarAtencion`, `anularAtencion`, `cajaActual`,
  `liquidacion`, `registrarAdelanto`, …) se prueba **contra la misma
  implementación** que usa la aplicación; `condicionRangoFecha()` y las ramas
  `pdo_es_sqlite()` hacen el SQL portable entre MySQL y SQLite.
- Por eso `php tests/run.php --db` sirve de _smoke test_ de que las acciones
  (los "botones") siguen funcionando tras cualquier cambio.

## Nota sobre los "botones"

Los botones de la interfaz son formularios que llaman a las funciones de
`inc/acciones.php` (registrar, editar, anular, abrir/cerrar caja, adelantos,
vender/reponer). Las pruebas `*_db_*` ejercen esas mismas funciones, así que
verifican el comportamiento real que disparan los botones. Para la parte
visual/interactiva del navegador puedes complementar con la habilidad
`browser-automation`.
