<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/acciones.php';
requerir_login('admin');
$page = 'productos';
$title = 'Productos · Inventario';
$subtitle = 'Stock, ventas y control de desabasto';

$pdo = pdo();
procesar_caja($pdo, 'productos.php');

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $stock = round((float)($_POST['stock'] ?? 0), 2);
        $stock_min = round((float)($_POST['stock_min'] ?? 0), 2);
        $costo = round((float)($_POST['costo'] ?? 0), 2);
        $precio = round((float)($_POST['precio'] ?? 0), 2);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            flash('El nombre es obligatorio.', 'danger');
        } elseif ($precio < 0 || $costo < 0 || $stock < 0) {
            flash('Los valores no pueden ser negativos.', 'danger');
        } else {
            if ($id) {
                $pdo->prepare("UPDATE productos SET nombre=?, stock=?, stock_min=?, costo=?, precio=?, activo=? WHERE id=?")
                    ->execute([$nombre, $stock, $stock_min, $costo, $precio, $activo, $id]);
                flash('Producto actualizado.');
            } else {
                $pdo->prepare("INSERT INTO productos (nombre, stock, stock_min, costo, precio, activo) VALUES (?,?,?,?,?,?)")
                    ->execute([$nombre, $stock, $stock_min, $costo, $precio, $activo]);
                flash('Producto agregado.');
            }
            redirect('productos.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $c = $pdo->prepare("SELECT COUNT(*) FROM ventas_productos WHERE producto_id = ?");
        $c->execute([$id]);
        if ($c->fetchColumn() > 0) {
            flash('No se puede eliminar: tiene ventas registradas. Desactívalo en su lugar.', 'danger');
        } else {
            $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
            audit($pdo, 'eliminar', 'productos', $id, 'Eliminó el producto #' . $id);
            flash('Producto eliminado.');
        }
        redirect('productos.php');
    } elseif ($accion === 'vender') {
        // RF-12: la venta entra al turno de caja → exige caja abierta.
        if (!turnoIniciado($pdo)) {
            audit($pdo, 'intento_venta_sin_caja', 'productos', 0,
                'Intentó vender un producto con la caja cerrada (' . rolActual() . ')');
            flash('La caja está cerrada: abre la caja para empezar el turno antes de vender productos.', 'warning');
            redirect('productos.php');
        }
        $producto_id = (int)($_POST['producto_id'] ?? 0);
        $cantidad = round(parsearNumero($_POST['cantidad'] ?? 0), 2);
        $fecha = normalizarFechaHora($_POST['fecha'] ?? '', date('Y-m-d H:i'));
        // Fuente única de tiempo: si no se tocó la fecha propuesta por el
        // servidor, se registra con la hora real del momento (entra al turno).
        $fechaOriginal = trim($_POST['fecha_original'] ?? '');
        if ($fechaOriginal !== '' && $fecha === normalizarFechaHora($fechaOriginal, '')) {
            $fecha = date('Y-m-d H:i');
        }

        $st = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
        $st->execute([$producto_id]);
        $prod = $st->fetch();

        if (!$prod) {
            flash('Producto no válido.', 'danger');
        } elseif ($cantidad <= 0) {
            flash('La cantidad debe ser mayor a cero.', 'danger');
        } elseif ($cantidad > (float)$prod['stock']) {
            flash('Stock insuficiente. Disponible: ' . (float)$prod['stock'], 'danger');
        } else {
            // Método de pago (tabla metodos_pago)
            // IMPORTANTE: el SELECT debe incluir id — sin él, $mp['id'] no existe,
            // la venta se guardaba con metodo_pago_id = 0 y el JOIN de la caja la
            // descartaba (las ventas dejaban de sumar al turno).
            $mpId = (int)($_POST['metodo_pago_id'] ?? 1);
            $stMp = $pdo->prepare("SELECT id, nombre, tipo FROM metodos_pago WHERE id = ? AND activo = 1");
            $stMp->execute([$mpId]);
            $mp = $stMp->fetch();
            if (!$mp) {   // método inválido/inactivo: efectivo por defecto
                $stMp->execute([1]);
                $mp = $stMp->fetch();
            }
            $metodo = $mp['nombre'] ?? 'Efectivo';
            $mpId = (int)($mp['id'] ?? 1);
            $total = round($cantidad * (float)$prod['precio'], 2);
            $ins = $pdo->prepare("INSERT INTO ventas_productos (fecha, producto_id, cantidad, precio_unit, costo_unit, total, metodo_pago, metodo_pago_id) VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([$fecha, $producto_id, $cantidad, $prod['precio'], $prod['costo'], $total, $metodo, $mpId]);
            $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?")
                ->execute([$cantidad, $producto_id]);
            flash('Venta registrada — total: ' . money($total) . ' (' . $metodo . '). Stock actual: ' . ((float)$prod['stock'] - $cantidad));
            redirect('productos.php');
        }
    } elseif ($accion === 'reponer') {
        $producto_id = (int)($_POST['producto_id'] ?? 0);
        $cantidad = round(parsearNumero($_POST['cantidad'] ?? 0), 2);
        if ($cantidad <= 0) {
            flash('La cantidad a reponer debe ser mayor a cero.', 'danger');
        } else {
            $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?")
                ->execute([$cantidad, $producto_id]);
            flash('Stock repuesto: +' . $cantidad);
            redirect('productos.php');
        }
    }
}

// ---------- Listado ----------
$productos = $pdo->query("SELECT * FROM productos ORDER BY nombre")->fetchAll();
$metodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY tipo, nombre")->fetchAll();
$vendidos = $pdo->query("SELECT producto_id, COALESCE(SUM(cantidad),0) c FROM ventas_productos GROUP BY producto_id")
    ->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
$ultimasVentas = $pdo->query("
    SELECT vp.*, p.nombre AS producto, mp.nombre AS metodo, mp.tipo AS metodo_tipo FROM ventas_productos vp
    JOIN productos p ON p.id = vp.producto_id
    JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
    ORDER BY vp.id DESC LIMIT 8
")->fetchAll();

function estadoStock($p) {
    $stock = (float)$p['stock'];
    $min = (float)$p['stock_min'];
    if ($stock <= 0) return '<span class="badge bg-danger">Agotado</span>';
    if ($stock <= $min) return '<span class="badge bg-warning text-dark">Bajo stock</span>';
    return '<span class="badge bg-success">OK</span>';
}

// ---------- Caja (RF-12: la venta exige turno abierto) ----------
$caja = cajaActual($pdo);

require_once __DIR__ . '/inc/header.php';
?>

<!-- ============ CAJA ============ -->
<?php if ($caja): ?>
<div class="card caja-strip mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
    <span class="caja-dot" title="Caja abierta"></span>
    <div>
      <div class="fw-bold">Caja abierta</div>
      <small class="text-muted">desde las <?= e(date('H:i', strtotime($caja['fecha_apertura']))) ?> · puedes vender</small>
    </div>
    <div class="caja-stat">Base<b><?= money($caja['monto_apertura']) ?></b></div>
    <div class="caja-stat">Ingresos turno<b><?= money($caja['ingresos_turno']) ?></b></div>
    <div class="ms-auto">
      <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCerrarCaja">
        <i class="bi bi-cash-coin me-1"></i>Cerrar caja
      </button>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card caja-strip cerrada mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
    <i class="bi bi-wallet2 fs-4 text-muted"></i>
    <div>
      <div class="fw-bold">Caja cerrada · turno no iniciado</div>
      <small class="text-muted">Abre la caja para poder vender productos (las ventas entran al turno).</small>
    </div>
    <div class="ms-auto">
      <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
        <i class="bi bi-unlock me-1"></i>Abrir caja
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" id="btnNuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</button>
</div>

<?php
$bajos = array_filter($productos, fn($p) => (float)$p['stock'] <= (float)$p['stock_min'] && $p['activo']);
if ($bajos): ?>
<div class="alert alert-warning d-flex align-items-center" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
  <div><strong>⚠ Desabasto:</strong>
    <?php
    echo e(implode(' · ', array_map(fn($p) => $p['nombre'] . ' (' . (float)$p['stock'] . ')', $bajos)));
    ?>
    — usa "Reponer" para surtir.
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Inventario</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th><th class="text-end">Costo</th><th class="text-end">Precio</th><th class="text-end">Vendidos</th><th>Estado</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php if (!$productos): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Sin productos registrados</td></tr>
          <?php endif; ?>
          <?php foreach ($productos as $p): ?>
          <tr>
            <td class="fw-semibold"><?= e($p['nombre']) ?></td>
            <td class="text-end"><?= (float)$p['stock'] ?></td>
            <td class="text-end text-muted"><?= (float)$p['stock_min'] ?></td>
            <td class="text-end"><?= money($p['costo']) ?></td>
            <td class="text-end text-gold"><?= money($p['precio']) ?></td>
            <td class="text-end"><?= (float)($vendidos[$p['id']]['c'] ?? 0) ?></td>
            <td><?= estadoStock($p) ?></td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-success" data-vender
                      data-id="<?= (int)$p['id'] ?>" data-nombre="<?= e($p['nombre']) ?>"
                      data-stock="<?= (float)$p['stock'] ?>" data-precio="<?= (float)$p['precio'] ?>"
                      <?= $caja ? '' : 'disabled' ?>
                      title="<?= $caja ? 'Vender' : 'Abre la caja para poder vender' ?>">
                <i class="bi bi-cart"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-info" data-reponer
                      data-id="<?= (int)$p['id'] ?>" data-nombre="<?= e($p['nombre']) ?>"
                      data-stock="<?= (float)$p['stock'] ?>"
                      title="Reponer stock">
                <i class="bi bi-boxes"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-light" data-editar
                      data-id="<?= (int)$p['id'] ?>"
                      data-nombre="<?= e($p['nombre']) ?>"
                      data-stock="<?= (float)$p['stock'] ?>"
                      data-stockmin="<?= (float)$p['stock_min'] ?>"
                      data-costo="<?= (float)$p['costo'] ?>"
                      data-precio="<?= (float)$p['precio'] ?>"
                      data-activo="<?= (int)$p['activo'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?');">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Últimas ventas -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-cart me-1"></i>Últimas ventas</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Fecha</th><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Precio unit.</th><th>Pago</th><th class="text-end">Total</th></tr></thead>
      <tbody>
        <?php if (!$ultimasVentas): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Sin ventas aún</td></tr>
        <?php endif; ?>
        <?php foreach ($ultimasVentas as $v): ?>
        <tr>
          <td><?= e(date('d/m/Y H:i', strtotime($v['fecha']))) ?></td>
          <td><?= e($v['producto']) ?></td>
          <td class="text-end"><?= (float)$v['cantidad'] ?></td>
          <td class="text-end"><?= money($v['precio_unit']) ?></td>
          <td>
            <span class="badge-pago <?= $v['metodo_tipo'] ?>" title="<?= e($v['metodo']) ?>">
              <i class="bi <?= $v['metodo_tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
            </span>
            <span class="small text-muted"><?= e($v['metodo']) ?></span>
          </td>
          <td class="text-end text-gold"><?= money($v['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="p-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProductoLabel">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Nombre *</label>
            <input name="nombre" id="p-nombre" class="form-control" required maxlength="100">
          </div>
          <div class="col-6">
            <label class="form-label">Stock actual *</label>
            <input type="number" name="stock" id="p-stock" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-6">
            <label class="form-label">Stock mínimo</label>
            <input type="number" name="stock_min" id="p-stockmin" class="form-control" step="0.01" min="0">
            <div class="form-text">Alerta de desabasto cuando el stock baje de este valor.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Costo unitario</label>
            <input type="number" name="costo" id="p-costo" class="form-control" step="0.01" min="0">
          </div>
          <div class="col-6">
            <label class="form-label">Precio de venta *</label>
            <input type="number" name="precio" id="p-precio" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="p-activo" value="1" checked>
              <label class="form-check-label" for="p-activo">Activo</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal vender -->
<div class="modal fade" id="modalVenta" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="vender">
        <input type="hidden" name="producto_id" id="v-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Vender: <span id="v-nombre"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-6">
            <label class="form-label">Stock disponible</label>
            <input type="text" id="v-stock" class="form-control" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">Precio unitario</label>
            <input type="text" id="v-precio" class="form-control" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">Cantidad *</label>
            <input type="number" name="cantidad" id="v-cantidad" class="form-control" step="0.01" min="0.01" required>
          </div>
          <div class="col-6">
            <label class="form-label">Total</label>
            <input type="text" id="v-total" class="form-control" readonly>
          </div>
          <div class="col-12">
            <label class="form-label">Fecha y hora</label>
            <input type="datetime-local" name="fecha" class="form-control" data-now required
                   value="<?= date('Y-m-d\TH:i') ?>">
            <!-- Hora propuesta por el servidor: si no se toca, al registrar se
                 guarda la hora real del servidor (entra al turno de caja) -->
            <input type="hidden" name="fecha_original" value="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Medio de pago</label>
            <div class="pago-grid">
              <?php foreach ($metodos as $m): ?>
                <button type="button" class="pago-card<?= $m['id'] == 1 ? ' selected' : '' ?>" data-pago-id="<?= (int)$m['id'] ?>">
                  <i class="bi <?= $m['tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i><span><?= e($m['nombre']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="metodo_pago_id" id="v-pago" value="1">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-cart me-1"></i>Registrar venta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal reponer -->
<div class="modal fade" id="modalReponer" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="reponer">
        <input type="hidden" name="producto_id" id="r-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Reponer: <span id="r-nombre"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-6">
            <label class="form-label">Stock actual</label>
            <input type="text" id="r-stock" class="form-control" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">Cantidad a agregar *</label>
            <input type="number" name="cantidad" class="form-control" step="0.01" min="0.01" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-boxes me-1"></i>Reponer stock</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const modalP = new bootstrap.Modal('#modalProducto');
const modalV = new bootstrap.Modal('#modalVenta');
const modalR = new bootstrap.Modal('#modalReponer');

// --- Producto (crear/editar) ---
function abrirProducto(id, nombre, stock, min, costo, precio, activo) {
    document.getElementById('modalProductoLabel').textContent = id ? 'Editar producto' : 'Nuevo producto';
    document.getElementById('p-id').value = id;
    document.getElementById('p-nombre').value = nombre;
    document.getElementById('p-stock').value = stock;
    document.getElementById('p-stockmin').value = min;
    document.getElementById('p-costo').value = costo;
    document.getElementById('p-precio').value = precio;
    document.getElementById('p-activo').checked = !!activo;
    modalP.show();
}
document.getElementById('btnNuevo').addEventListener('click', () => abrirProducto(0, '', 0, 0, 0, '', true));
document.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
    abrirProducto(btn.dataset.id, btn.dataset.nombre, btn.dataset.stock, btn.dataset.stockmin, btn.dataset.costo, btn.dataset.precio, btn.dataset.activo);
}));

// --- Vender ---
const $vCant = document.getElementById('v-cantidad');
function actualizarTotal() {
    const p = parseFloat(document.getElementById('v-precio').value) || 0;
    const c = parseFloat($vCant.value) || 0;
    document.getElementById('v-total').value = fmtMoney(p * c);
}
$vCant.addEventListener('input', actualizarTotal);

// Medio de pago en la venta (métodos configurables)
document.querySelectorAll('#modalVenta .pago-card').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('#modalVenta .pago-card').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('v-pago').value = btn.dataset.pagoId;
}));

document.querySelectorAll('[data-vender]').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('v-id').value = btn.dataset.id;
    document.getElementById('v-nombre').textContent = btn.dataset.nombre;
    document.getElementById('v-stock').value = btn.dataset.stock;
    document.getElementById('v-precio').value = String(parseFloat(btn.dataset.precio));
    $vCant.value = 1;
    $vCant.max = btn.dataset.stock;
    actualizarTotal();
    modalV.show();
}));

// --- Reponer ---
document.querySelectorAll('[data-reponer]').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('r-id').value = btn.dataset.id;
    document.getElementById('r-nombre').textContent = btn.dataset.nombre;
    document.getElementById('r-stock').value = btn.dataset.stock;
    modalR.show();
}));
</script>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
