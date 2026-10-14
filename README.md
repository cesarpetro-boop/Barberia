# 💈 Barbería Dembow — Control de barbería v2

Sistema en **PHP puro + Bootstrap 5 + JavaScript + MySQL** para controlar
una barbería: registra **atenciones por barbero** (varios servicios a la
vez), calcula **comisiones automáticas**, cierra
**caja con arqueo de efectivo y pagos digitales**, liquida barberos con
**adelantos/descuentos** y controla el **desabasto de productos**.

## Requisitos

- XAMPP (o cualquier servidor con **PHP 7.4+** y **MySQL/MariaDB**)
- Extensión PHP `pdo_mysql` (incluida por defecto en XAMPP)

## Instalación (30 segundos)

1. Copia la carpeta `Lavadero` en `C:\xampp\htdocs\Lavadero`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Abre: **http://localhost/Lavadero/**

> La base de datos (`barberia_control`), las tablas, el usuario inicial
> y los datos de ejemplo (3 barberos, 6 servicios, 6 productos, 3 métodos
> de pago) se crean **automáticamente** en la primera visita. No hay que
> importar nada. Si ya tenías datos de la v1, se migran solos.

## Acceso

La primera vez inicia sesión con:

- **Usuario:** `admin`
- **Contraseña:** `admin123`

Hay dos actores (RF-01..RF-19):

| Rol | Puede hacer |
|---|---|
| **Administrador** | **Acceso total**: registra y edita atenciones y cobros, **abre y cierra la caja** (con arqueo), maneja inventario (vender/reponer), registra adelantos y descuentos, y configura barberos, servicios, métodos de pago, usuarios, catálogos, auditoría, liquidaciones, reportes y respaldos. Único que puede **anular** registros (RNF-09). |
| **Barbero** | Ve el panel, **sus propias atenciones** y **su liquidación**. |

Usuario de demostración (cámbialo o elimínalo en Usuarios): `barbero` / `barbero123`.

## Flujo de trabajo

Registrar un corte son **3 toques** (RNF-01):

1. **Toca la tarjeta del barbero** (verás su esquema de comisión: % o tarifa fija).
2. **Toca el chip del servicio** — puedes elegir **varios** y ajustar la **cantidad** de cada uno.
3. **Toca «Registrar atención»** — fecha/hora y pago en **efectivo** ya vienen por defecto.

Los medios de pago disponibles están en la misma pantalla, sin pasos extra.

> **RF-12 · Abrir caja obligatorio para empezar el turno:** hasta que no abras la
> caja del día (con su base de efectivo) no se pueden **registrar atenciones** ni
> **vender productos**: el formulario queda sustituido por un aviso con el botón
> «Abrir caja» y el servidor rechaza el envío. Editar o anular registros del
> historial y registrar adelantos **no** exigen caja abierta (no inician turno).

> **RF-07 · Otro valor (monto a convenir):** en el paso de servicios hay un chip
> especial **«Otro valor»** para clientes que no llegan con el dinero completo.
> Al elegirlo aparecen dos campos: **monto** (lo que pagan en el momento) y
> **motivo del ingreso** (obligatorio). Ese monto suma al total de la atención y
> el motivo queda visible en el historial, el dashboard, la caja y los reportes.
> No necesita un servicio del catálogo: usa un servicio reservado que no se puede
> eliminar ni desactivar. **Solo se honra en ese servicio especial** — enviar un
> precio libre para un servicio normal se ignora, así que nadie puede alterar el
> precio snapshot (RF-03).

La **comisión, el total y el total cobrado se calculan en vivo** mientras
eliges. La atención aparece al instante en "Atenciones de hoy".

## Módulos

| Módulo | Qué hace |
|---|---|
| **Panel** | KPIs del día (atenciones, ingresos, comisiones, ganancia neta) con tendencia vs ayer, registro rápido, atenciones de hoy (editar/anular), ranking de barberos, gráfico de ingresos de 7 días, participación del día y alerta de desabasto. |
| **Registrar** | Formulario paso a paso para crear atenciones (servicios múltiples, descuento, propina, pago). Al editar desde el historial regresa aquí con los datos cargados. |
| **Historial** | Pantalla propia con filtros por **fecha, barbero, servicio y método de pago**. **Editar** y **anular** (con motivo obligatorio, queda constancia en la auditoría). |
| **Caja** | Apertura con base de efectivo. **Abrir la caja es obligatorio para iniciar el turno**: sin caja abierta no se registran atenciones ni ventas de productos (RF-12). Cierre con **arqueo de efectivo** (diferencia exacto/sobrante/faltante) y **declaración de pagos digitales**. Detalle del turno e historial de cajas. |
| **Liquidación** | Comisiones por barbero en el período (**hoy, semana, quincena, mes o rango**), menos **adelantos y descuentos** = pago neto. Registro de adelantos/descuentos con motivo. |
| **Reportes** | Diario/semanal/mensual, **comparativo vs período anterior**, ranking de productividad, ingresos por servicio, por método de pago, **servicios más solicitados**, **horas pico**, detalle de atenciones, **exportar CSV (Excel)** e **imprimir/PDF**. |
| **Barberos** | CRUD con **documento, teléfono, fecha de ingreso** y comisión **porcentaje o tarifa fija por servicio**. |
| **Servicios** | Catálogo con precio y duración. **Historial de cambios de precio** (los precios pasados no se alteran). Incluye el servicio reservado **«Otro valor»** (monto libre con motivo, RF-07) que no se puede eliminar. |
| **Métodos de pago** | CRUD de medios: **efectivo** (entra a caja) y **digitales** (transferencia, tarjeta…). |
| **Productos** | Inventario con stock, stock mínimo (alerta de **desabasto**), costo y precio. **Vender** (descuenta stock, elige método de pago) y **Reponer**. |
| **Respaldos** | Copia de seguridad **automática diaria** (al entrar el admin) + manual, descarga y restauración. |
| **Privacidad** | Política de tratamiento de datos personales (**Ley 1581 de 2012**), pública desde el login. |
| **Auditoría** | Constancia de **quién hizo qué, cuándo y por qué** (crear, editar, anular, caja, adelantos, login). |
| **Usuarios** | CRUD de usuarios con rol y vinculación a barbero. |

## Cómo se calcula el dinero

- **Subtotal** = Σ (precio × cantidad) de los servicios de la atención.
- **Total a cobrar** = subtotal de servicios.
- **Comisión** = total a cobrar × % del barbero, **o** tarifa fija × cantidad de servicios (según su esquema).
- **Total cobrado** = total a cobrar.
- **Ganancia neta de la casa** = (servicios + productos) − comisiones − costo de productos vendidos.
- **Efectivo esperado al cerrar caja** = base de apertura + todo lo cobrado en **efectivo** del turno.
- **Pago neto del barbero** = comisiones − adelantos − descuentos del período.

## Diseño

- Tema oscuro premium con acentos dorados y bola de barbero animada.
- Layout con **barra lateral** (se convierte en menú hamburguesa en móvil).
- Tarjetas de barbero, chips multi-servicio con stepper de cantidad, tarjetas de pago, vista previa en vivo.
- Totalmente **responsive** y apto para pantallas táctiles (tablet de mostrador).
- Tipografía Inter, micro-animaciones, gráficos SVG con tooltips.

## Configuración

Edita `inc/config.php` si usas otro usuario o contraseña de MySQL:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'barberia_control');
define('DB_USER', 'root');
define('DB_PASS', '');       // vacío por defecto en XAMPP
```

## Estructura de archivos

```
Lavadero/
├── index.php          Panel de control (dashboard)
├── atenciones.php     Registro de atenciones (paso a paso)
├── historial.php      Historial con filtros (editar/anular)
├── caja.php           Caja: apertura, arqueo, digitales, historial
├── liquidacion.php    Liquidación de barberos + adelantos (RF-13/14)
├── reportes.php       Reportes, comparativos, horas pico, CSV/PDF
├── barberos.php       Barberos (RF-01)
├── servicios.php      Catálogo + historial de precios (RF-02/03)
├── metodos.php        Métodos de pago (RF-04)
├── productos.php      Inventario / ventas / desabasto
├── auditoria.php      Auditoría de acciones (RF-09)
├── usuarios.php       Usuarios y roles
├── respaldos.php      Respaldos automáticos/manuales (RNF-11)
├── privacidad.php     Política de privacidad Ley 1581 (pública, RNF-15)
├── login.php / logout.php
├── inc/
│   ├── config.php     Configuración
│   ├── functions.php  Ayudantes (CSRF, flashes, comisiones, auditoría)
│   ├── auth.php       Autenticación y roles
│   ├── db.php         Conexión + esquema + migración v1→v2 + sedes (RNF-14)
│   ├── respaldos.php  Volcado/restauración SQL (RNF-11)
│   ├── bootstrap.php  Carga inicial
│   ├── acciones.php   Lógica de negocio (atenciones, caja, liquidación)
│   ├── form_atencion.php  Widget de registro paso a paso
│   ├── modales_caja.php   Modales abrir/cerrar caja
│   ├── modales_atencion.php  Modal de anulación con motivo
│   ├── header.php     Layout (sidebar + topbar por rol)
│   └── footer.php     Cierre de layout
└── assets/
    ├── css/style.css  Tema oscuro completo
    ├── js/main.js     Interacciones (multi-servicios, cálculo en vivo, arqueo, CSV)
    └── vendor/        Bootstrap, iconos, Inter y las tipografías del tema (Bebas Neue,
                       Montserrat, Outfit, Syne) 100% locales — cero CDNs (RNF-07)
```

## Requerimientos no funcionales (RNF)

| RNF | Cómo se cumple |
|---|---|
| **RNF-01** (3 toques / 10 s) | Barbero → servicio → Registrar; fecha y efectivo por defecto; comisión en vivo. |
| **RNF-02** (simple, botones grandes) | Tarjetas y chips táctiles, botones `btn-lg`, un solo formulario por pantalla. |
| **RNF-03** (responsive) | Sidebar → hamburguesa en móvil; grillas adaptativas; probado en escritorio y móvil. |
| **RNF-04** (< 2 s) | PHP + MySQL local, sin frameworks; páginas cargan en ~1–2 s. |
| **RNF-05** (uso simultáneo) | Sesiones independientes por usuario; apertura de caja con bloqueo de fila (`SELECT … FOR UPDATE` + `mutex_caja`) para que dos usuarios no abran dos cajas a la vez. |
| **RNF-06** (disponibilidad) | Corre en el PC local de la barbería (XAMPP); no depende de internet ni de terceros. |
| **RNF-07** (sin conexión) | **100% offline**: Bootstrap, iconos, Inter y las 4 tipografías del tema están en `assets/vendor/`; cero CDNs (Google Fonts eliminado de las 3 vistas y del CSS). Los estáticos se sirven con `?v=<fecha>` (`asset()`) para que el navegador nunca use CSS/JS viejos desde la caché. |
| **RNF-08** (login + roles) | `admin` (acceso total) / `barbero`; el barbero solo ve lo suyo (forzado en cada consulta). El admin **supera cualquier filtro de roles** en `requerir_login()`, y los privilegios se revalidan contra la BD en cada petición: desactivar un usuario o cambiarle el rol surte efecto inmediato. |
| **RNF-09** (solo admin anula, precios y reportes) | Anular y `reportes.php` exigen rol admin (servidor + UI); los intentos quedan en auditoría. Precios/catálogos ya eran solo-admin. |
| **RNF-10** (auditoría) | Tabla `auditoria`: usuario, IP, acción, motivo (crear/editar/anular/caja/adelantos/login/respaldos). |
| **RNF-11** (respaldos diarios) | **Automático**: un `.sql` por día al entrar el admin + **manual**, descarga, restauración y eliminación desde `respaldos.php`; carpeta bloqueada con `.htaccess`. |
| **RNF-12** (no se borra, se anula) | Atenciones → estado `anulada`; catálogos con historial se desactivan, no se eliminan. |
| **RNF-13** (crecer sin técnico) | CRUD de barberos, servicios, métodos de pago, productos y usuarios desde la UI. |
| **RNF-14** (más barberos/sedes) | Sin límite de barberos/usuarios; esquema con tabla `sedes` + `sede_id` listo para multisedes sin rediseñar. |
| **RNF-15** (Ley 1581) | `privacidad.php` pública, aviso en login y pie de página y **mínimos datos necesarios** (no se almacenan datos de clientes; solo servicios, barbero, monto y fecha). |
| **RNF-16** (económica) | PHP + MySQL + Bootstrap, todo libre y local; costo operativo $0. |

## Notas de integridad de datos

- **Fuente única de tiempo.** Toda la app usa el reloj del servidor
  (`date.timezone` fijado a `America/Bogota` en `inc/config.php`). Los
  formularios proponen la hora del servidor (no la del navegador) y, si no se
  toca la fecha, al guardar se usa `ahora()` del momento del registro. Así las
  atenciones y ventas caen dentro del turno de caja aunque los relojes del PC
  y del navegador difieran — antes, un desfase de horas hacía que la caja
  filtrara `fecha >= apertura` y **descartara todo el turno** (totales en $0).
- **Ventas con método de pago válido.** El registro de venta valida el método
  con `SELECT id, nombre, tipo` y cae a *Efectivo* si el id no existe. Una
  venta con `metodo_pago_id = 0` no empareja el `JOIN metodos_pago` de la caja
  y dejaba de sumar; las ventas afectadas se repararon.
- **La caja muestra el desglose** Base · Servicios · Productos · Ingresos ·
  Efectivo esperado · Digital, y cada fila del turno lleva su badge de pago
  (efectivo/verde · digital/azul: transferencia, tarjeta, Nequi…).
- **Formato de dinero sin ceros sobrantes.** Todas las cifras se pintan con
  `money()` (PHP) o `fmtMoney()` (JS), que quitan los ceros decimales inútiles:
  `$ 17,000.00` se muestra como `$ 17,000`. Si hay centavos reales se conservan
  (`$ 8,500.5`). Los totales solo se redondean a 2 decimales en el cálculo, no
  en la visualización.

## Notas de seguridad

- **Login** con contraseñas hasheadas (`password_hash`) y sesiones.
- Consultas con **sentencias preparadas** (PDO) en todo el sistema.
- **Tokens CSRF** en todos los formularios.
- Salidas escapadas con `htmlspecialchars` (anti-XSS).
- Cada acción queda registrada en la **auditoría** con usuario, IP y motivo.
- Eliminar barberos/servicios/productos/métodos con historial está bloqueado:
  desactívalos en su lugar.
- Un barbero (rol barbero) solo ve sus propias atenciones y su liquidación.

## Coexistencia con otros proyectos

Esta app usa su propia base de datos (`barberia_control`), por lo que no
interfiere con otros sistemas que compartan el servidor MySQL.
