<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'barberos';
$title = 'Barberos';
$subtitle = 'Equipo, documentos y esquema de comisión';

$pdo = pdo();

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $documento = trim($_POST['documento'] ?? '') ?: null;
        $telefono = trim($_POST['telefono'] ?? '') ?: null;
        $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? normalizarFechaHora($_POST['fecha_ingreso']) : null;
        $tipo_comision = in_array($_POST['tipo_comision'] ?? '', ['porcentaje', 'fijo'], true) ? $_POST['tipo_comision'] : 'porcentaje';
        $comision_valor = round(parsearNumero($_POST['comision_valor'] ?? 0), 2);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            flash('El nombre es obligatorio.', 'danger');
        } elseif ($comision_valor < 0 || ($tipo_comision === 'porcentaje' && $comision_valor > 100)) {
            flash($tipo_comision === 'porcentaje'
                ? 'El porcentaje debe estar entre 0% y 100%.'
                : 'La tarifa no puede ser negativa.', 'danger');
        } else {
            if ($id) {
                $pdo->prepare("UPDATE barberos SET nombre=?, documento=?, telefono=?, fecha_ingreso=?, tipo_comision=?, comision_valor=?, activo=? WHERE id=?")
                    ->execute([$nombre, $documento, $telefono, $fecha_ingreso, $tipo_comision, $comision_valor, $activo, $id]);
                flash('Barbero actualizado.');
            } else {
                $pdo->prepare("INSERT INTO barberos (nombre, documento, telefono, fecha_ingreso, tipo_comision, comision_valor, activo) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nombre, $documento, $telefono, $fecha_ingreso, $tipo_comision, $comision_valor, $activo]);
                flash('Barbero agregado.');
            }
            redirect('barberos.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $c = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE barbero_id = ?");
        $c->execute([$id]);
        if ($c->fetchColumn() > 0) {
            flash('No se puede eliminar: tiene atenciones registradas. Desactívalo en su lugar.', 'danger');
        } else {
            $pdo->prepare("DELETE FROM barberos WHERE id = ?")->execute([$id]);
            flash('Barbero eliminado.');
        }
        redirect('barberos.php');
    }
}

// ---------- Listado ----------
$barberos = $pdo->query("SELECT * FROM barberos ORDER BY nombre")->fetchAll();
$conteos = $pdo->query("SELECT barbero_id, COUNT(*) c, COALESCE(SUM(monto_final),0) m FROM atenciones WHERE estado = 'activa' GROUP BY barbero_id")
    ->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

require_once __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" id="btnNuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo barbero</button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Barbero</th><th>Documento</th><th>Ingreso</th>
            <th>Esquema de comisión</th>
            <th class="text-end">Atenciones</th><th class="text-end">Facturado</th>
            <th>Teléfono</th><th>Estado</th><th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$barberos): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Sin barberos registrados</td></tr>
          <?php endif; ?>
          <?php foreach ($barberos as $b): ?>
          <tr>
            <td class="fw-semibold">
              <span class="avatar me-2" style="width:30px;height:30px;font-size:11px;background:linear-gradient(135deg,hsl(<?= hueNombre($b['nombre']) ?>,65%,48%),hsl(<?= hueNombre($b['nombre']) + 40 ?>,65%,32%))"><?= e(iniciales($b['nombre'])) ?></span>
              <?= e($b['nombre']) ?>
            </td>
            <td class="text-muted"><?= e($b['documento'] ?: '—') ?></td>
            <td class="text-muted"><?= $b['fecha_ingreso'] ? e(date('d/m/Y', strtotime($b['fecha_ingreso']))) : '—' ?></td>
            <td>
              <?php if ($b['tipo_comision'] === 'fijo'): ?>
                <span class="badge" style="background:rgba(96,165,250,.15);color:#93c5fd"><?= money($b['comision_valor']) ?> / servicio</span>
              <?php else: ?>
                <span class="badge" style="background:rgba(212,175,55,.15);color:var(--gold2)"><?= (float)$b['comision_valor'] ?>%</span>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= (int)($conteos[$b['id']]['c'] ?? 0) ?></td>
            <td class="text-end"><?= money($conteos[$b['id']]['m'] ?? 0) ?></td>
            <td class="text-muted"><?= e($b['telefono'] ?: '—') ?></td>
            <td>
              <?php if ($b['activo']): ?>
                <span class="badge bg-success">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-light" data-editar
                      data-id="<?= (int)$b['id'] ?>"
                      data-nombre="<?= e($b['nombre']) ?>"
                      data-documento="<?= e($b['documento'] ?? '') ?>"
                      data-telefono="<?= e($b['telefono'] ?? '') ?>"
                      data-ingreso="<?= e($b['fecha_ingreso'] ?? '') ?>"
                      data-tipo="<?= e($b['tipo_comision']) ?>"
                      data-valor="<?= (float)$b['comision_valor'] ?>"
                      data-activo="<?= (int)$b['activo'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este barbero?');">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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

<!-- Modal crear/editar -->
<div class="modal fade" id="modalBarbero" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="b-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalBarberoLabel">Nuevo barbero</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Nombre *</label>
            <input name="nombre" id="b-nombre" class="form-control" required maxlength="100">
          </div>
          <div class="col-6">
            <label class="form-label">Documento</label>
            <input name="documento" id="b-documento" class="form-control" maxlength="30" placeholder="DNI / cédula">
          </div>
          <div class="col-6">
            <label class="form-label">Teléfono</label>
            <input name="telefono" id="b-telefono" class="form-control" maxlength="60">
          </div>
          <div class="col-6">
            <label class="form-label">Fecha de ingreso</label>
            <input type="date" name="fecha_ingreso" id="b-ingreso" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label">Esquema de comisión *</label>
            <select name="tipo_comision" id="b-tipo" class="form-select">
              <option value="porcentaje">Porcentaje sobre la venta</option>
              <option value="fijo">Tarifa fija por servicio</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" id="b-valor-label">Comisión % *</label>
            <input type="number" name="comision_valor" id="b-valor" class="form-control"
                   step="0.01" min="0" max="100" required>
            <div class="form-text" id="b-valor-help">Porcentaje del monto cobrado de cada atención.</div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="b-activo" value="1" checked>
              <label class="form-check-label" for="b-activo">Activo (aparece al registrar atenciones)</label>
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
const modalB = new bootstrap.Modal('#modalBarbero');
const lblB = document.getElementById('modalBarberoLabel');
const selTipo = document.getElementById('b-tipo');
const lblValor = document.getElementById('b-valor-label');
const inpValor = document.getElementById('b-valor');
const helpValor = document.getElementById('b-valor-help');

function ajustarTipo() {
    if (selTipo.value === 'fijo') {
        lblValor.textContent = 'Tarifa fija por servicio *';
        inpValor.max = 999999;
        helpValor.textContent = 'Se cobra esta tarifa por cada servicio de la atención.';
    } else {
        lblValor.textContent = 'Comisión % *';
        inpValor.max = 100;
        helpValor.textContent = 'Porcentaje del monto cobrado de cada atención.';
    }
}
selTipo.addEventListener('change', ajustarTipo);

function abrirBarbero(id, nombre, doc, tel, ingreso, tipo, valor, activo) {
    lblB.textContent = id ? 'Editar barbero' : 'Nuevo barbero';
    document.getElementById('b-id').value = id;
    document.getElementById('b-nombre').value = nombre;
    document.getElementById('b-documento').value = doc;
    document.getElementById('b-telefono').value = tel;
    document.getElementById('b-ingreso').value = ingreso;
    selTipo.value = tipo;
    document.getElementById('b-valor').value = valor;
    document.getElementById('b-activo').checked = !!activo;
    ajustarTipo();
    modalB.show();
}
document.getElementById('btnNuevo').addEventListener('click', () => abrirBarbero(0, '', '', '', '', 'porcentaje', 0, true));
document.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
    abrirBarbero(btn.dataset.id, btn.dataset.nombre, btn.dataset.documento, btn.dataset.telefono,
        btn.dataset.ingreso, btn.dataset.tipo, btn.dataset.valor, btn.dataset.activo);
}));
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
