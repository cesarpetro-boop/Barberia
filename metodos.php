<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'metodos';
$title = 'Métodos de pago';
$subtitle = 'Efectivo y pagos digitales';

$pdo = pdo();

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = in_array($_POST['tipo'] ?? '', ['efectivo', 'digital'], true) ? $_POST['tipo'] : 'efectivo';
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            flash('El nombre es obligatorio.', 'danger');
        } else {
            if ($id) {
                $pdo->prepare("UPDATE metodos_pago SET nombre=?, tipo=?, activo=? WHERE id=?")
                    ->execute([$nombre, $tipo, $activo, $id]);
                flash('Método de pago actualizado.');
            } else {
                $pdo->prepare("INSERT INTO metodos_pago (nombre, tipo, activo) VALUES (?,?,?)")
                    ->execute([$nombre, $tipo, $activo]);
                flash('Método de pago agregado.');
            }
            redirect('metodos.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $c1 = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE metodo_pago_id = ?");
        $c1->execute([$id]);
        $c2 = $pdo->prepare("SELECT COUNT(*) FROM ventas_productos WHERE metodo_pago_id = ?");
        $c2->execute([$id]);
        if (($c1->fetchColumn() + $c2->fetchColumn()) > 0) {
            flash('No se puede eliminar: tiene movimientos registrados. Desactívalo en su lugar.', 'danger');
        } else {
            $pdo->prepare("DELETE FROM metodos_pago WHERE id = ?")->execute([$id]);
            flash('Método de pago eliminado.');
        }
        redirect('metodos.php');
    }
}

// ---------- Listado ----------
$metodos = $pdo->query("SELECT * FROM metodos_pago ORDER BY tipo, nombre")->fetchAll();
$usos = $pdo->query("
    SELECT metodo_pago_id, COUNT(*) c FROM atenciones GROUP BY metodo_pago_id
")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

require_once __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" id="btnNuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo método</button>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header">Métodos configurados</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Método</th><th>Tipo</th><th class="text-end">Atenciones</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
              <?php if (!$metodos): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Sin métodos de pago</td></tr>
              <?php endif; ?>
              <?php foreach ($metodos as $m): ?>
              <tr>
                <td class="fw-semibold">
                  <span class="badge-pago <?= $m['tipo'] ?> me-2" title="<?= $m['tipo'] === 'efectivo' ? 'Efectivo' : 'Digital' ?>">
                    <i class="bi <?= $m['tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
                  </span>
                  <?= e($m['nombre']) ?>
                </td>
                <td>
                  <?php if ($m['tipo'] === 'efectivo'): ?>
                    <span class="badge" style="background:rgba(52,211,153,.15);color:#6ee7b7">Efectivo · entra a caja</span>
                  <?php else: ?>
                    <span class="badge" style="background:rgba(96,165,250,.15);color:#93c5fd">Digital · no entra a caja</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= (int)($usos[$m['id']]['c'] ?? 0) ?></td>
                <td>
                  <?php if ($m['activo']): ?>
                    <span class="badge bg-success">Activo</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-light" data-editar
                          data-id="<?= (int)$m['id'] ?>"
                          data-nombre="<?= e($m['nombre']) ?>"
                          data-tipo="<?= e($m['tipo']) ?>"
                          data-activo="<?= (int)$m['activo'] ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este método de pago?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
  </div>

  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>¿Cómo funciona?</div>
      <div class="card-body">
        <div class="d-flex gap-3 mb-4">
          <span class="badge-pago efectivo"><i class="bi bi-cash-coin"></i></span>
          <div>
            <strong class="small">Efectivo</strong>
            <p class="text-muted small mb-0">Ingresa a la caja. Se suma al <b>efectivo esperado</b> al cerrar el turno y se compara con el arqueo.</p>
          </div>
        </div>
        <div class="d-flex gap-3 mb-4">
          <span class="badge-pago transferencia"><i class="bi bi-bank"></i></span>
          <div>
            <strong class="small">Digital (transferencia, tarjeta, etc.)</strong>
            <p class="text-muted small mb-0">No entra a la caja física. Al cerrar el turno se declara cuánto se recibió por este medio y se compara con lo registrado.</p>
          </div>
        </div>
        <div class="alert alert-info">
          <i class="bi bi-lightbulb me-2"></i>
          <span class="small">Agrega los medios que uses en la barbería: transferencia, tarjeta, Mercado Pago, etc.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalMetodo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="m-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMetodoLabel">Nuevo método de pago</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Nombre *</label>
            <input name="nombre" id="m-nombre" class="form-control" required maxlength="50" placeholder="Ej.: Transferencia, Tarjeta, Mercado Pago…">
          </div>
          <div class="col-12">
            <label class="form-label">Tipo *</label>
            <select name="tipo" id="m-tipo" class="form-select">
              <option value="efectivo">Efectivo — entra a la caja</option>
              <option value="digital">Digital — no entra a la caja</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="m-activo" value="1" checked>
              <label class="form-check-label" for="m-activo">Activo</label>
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

<script>
const modalM = new bootstrap.Modal('#modalMetodo');
const lblM = document.getElementById('modalMetodoLabel');

function abrirMetodo(id, nombre, tipo, activo) {
    lblM.textContent = id ? 'Editar método de pago' : 'Nuevo método de pago';
    document.getElementById('m-id').value = id;
    document.getElementById('m-nombre').value = nombre;
    document.getElementById('m-tipo').value = tipo;
    document.getElementById('m-activo').checked = !!activo;
    modalM.show();
}
document.getElementById('btnNuevo').addEventListener('click', () => abrirMetodo(0, '', 'efectivo', true));
document.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
    abrirMetodo(btn.dataset.id, btn.dataset.nombre, btn.dataset.tipo, btn.dataset.activo);
}));
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
