<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'servicios';
$title = 'Servicios';
$subtitle = 'Catálogo con historial de precios — los cambios no alteran registros pasados';

$pdo = pdo();

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = round((float)($_POST['precio'] ?? 0), 2);
        $duracion = (int)($_POST['duracion'] ?? 0) ?: null;
        $esOtro = $id ? esOtroValor($pdo, $id) : false;
        // El servicio especial "Otro valor" siempre queda activo y con precio 0:
        // el monto lo coloca el barbero al registrar la atención (RF-07).
        $activo = ($esOtro || isset($_POST['activo'])) ? 1 : 0;
        if ($esOtro) $precio = 0.00;

        if ($nombre === '') {
            flash('El nombre es obligatorio.', 'danger');
        } elseif ($precio < 0) {
            flash('El precio no puede ser negativo.', 'danger');
        } else {
            if ($id) {
                // Registrar cambio de precio en el historial (RF-03)
                $ant = $pdo->prepare("SELECT precio FROM servicios WHERE id = ?");
                $ant->execute([$id]);
                $precioAnt = (float)$ant->fetchColumn();
                $pdo->prepare("UPDATE servicios SET nombre=?, precio=?, duracion=?, activo=? WHERE id=?")
                    ->execute([$nombre, $precio, $duracion, $activo, $id]);
                if (abs($precioAnt - $precio) > 0.005) {
                    $pdo->prepare("INSERT INTO servicios_precios (servicio_id, precio) VALUES (?,?)")
                        ->execute([$id, $precio]);
                }
                flash('Servicio actualizado.' . (abs($precioAnt - $precio) > 0.005 ? ' Cambio de precio registrado en el historial.' : ''));
            } else {
                $pdo->prepare("INSERT INTO servicios (nombre, precio, duracion, activo) VALUES (?,?,?,?)")
                    ->execute([$nombre, $precio, $duracion, $activo]);
                $nid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO servicios_precios (servicio_id, precio) VALUES (?,?)")
                    ->execute([$nid, $precio]);
                flash('Servicio agregado.');
            }
            redirect('servicios.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if (esOtroValor($pdo, $id)) {
            flash('No se puede eliminar: es el servicio especial "Otro valor" (monto libre).', 'danger');
        } else {
            $c = $pdo->prepare("SELECT COUNT(*) FROM atencion_servicios WHERE servicio_id = ?");
            $c->execute([$id]);
            if ($c->fetchColumn() > 0) {
                flash('No se puede eliminar: está usado en atenciones registradas. Desactívalo en su lugar.', 'danger');
            } else {
                $pdo->prepare("DELETE FROM servicios WHERE id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM servicios_precios WHERE servicio_id = ?")->execute([$id]);
                flash('Servicio eliminado.');
            }
        }
        redirect('servicios.php');
    }
}

// ---------- Listado ----------
$servicios = $pdo->query("SELECT * FROM servicios ORDER BY nombre")->fetchAll();
$conteos = $pdo->query("SELECT servicio_id, COUNT(*) c FROM atencion_servicios GROUP BY servicio_id")
    ->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

// Historial de precios para el modal
$historial = [];
foreach ($pdo->query("SELECT servicio_id, precio, cambiado_en FROM servicios_precios ORDER BY cambiado_en DESC") as $h) {
    $historial[$h['servicio_id']][] = $h;
}

require_once __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" id="btnNuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo servicio</button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Servicio</th><th class="text-end">Precio</th><th>Duración</th><th class="text-end">Veces realizado</th><th>Estado</th><th>Historial</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php if (!$servicios): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Sin servicios registrados</td></tr>
          <?php endif; ?>
          <?php foreach ($servicios as $s):
              $hist = $historial[$s['id']] ?? [];
              $esOtro = !empty($s['es_otro_valor']);
          ?>
          <tr>
            <td class="fw-semibold">
              <?= e($s['nombre']) ?>
              <?php if ($esOtro): ?>
                <span class="badge bg-info text-dark ms-1" title="Monto libre colocado por el barbero al registrar la atención.">Monto libre</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-gold"><?= $esOtro ? '<span class="text-muted">a convenir</span>' : money($s['precio']) ?></td>
            <td><?= $s['duracion'] ? (int)$s['duracion'] . ' min' : '—' ?></td>
            <td class="text-end"><?= (int)($conteos[$s['id']]['c'] ?? 0) ?></td>
            <td>
              <?php if ($s['activo']): ?>
                <span class="badge bg-success">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (count($hist) > 1):
                  $histTxt = implode(' · ', array_map(
                      fn($h) => money($h['precio']) . ' (' . date('d/m', strtotime($h['cambiado_en'])) . ')',
                      array_slice($hist, 0, 3)
                  ));
              ?>
                <span class="text-muted small" title="Últimos cambios: <?= e($histTxt) ?>">
                  <i class="bi bi-clock-history me-1"></i><?= count($hist) ?> cambios
                </span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-light" data-editar
                      data-id="<?= (int)$s['id'] ?>"
                      data-nombre="<?= e($s['nombre']) ?>"
                      data-precio="<?= (float)$s['precio'] ?>"
                      data-duracion="<?= (int)$s['duracion'] ?>"
                      data-activo="<?= (int)$s['activo'] ?>"
                      data-otro="<?= $esOtro ? 1 : 0 ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if ($esOtro): ?>
                <span class="btn btn-sm btn-outline-secondary disabled" title="Servicio especial: no se puede eliminar"><i class="bi bi-lock"></i></span>
              <?php else: ?>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este servicio?');">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============ HISTORIAL DE PRECIOS (RF-03) ============ -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-clock-history me-2"></i>Historial de cambios de precio</div>
  <div class="card-body p-0">
    <?php if (!$historial): ?>
      <div class="text-center text-muted py-4">Sin cambios registrados.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Fecha</th><th>Servicio</th><th class="text-end">Precio anterior</th><th class="text-end">Precio nuevo</th><th class="text-end">Cambio</th></tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $sid => $hist):
                $srv = current(array_filter($servicios, fn($s) => (int)$s['id'] === (int)$sid));
                for ($i = 0; $i < count($hist); $i++):
                    $h = $hist[$i];
                    $anterior = $hist[$i + 1] ?? null;
                    $delta = $anterior ? (float)$h['precio'] - (float)$anterior['precio'] : 0;
            ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d/m/Y H:i', strtotime($h['cambiado_en']))) ?></td>
                <td class="fw-semibold small"><?= e($srv['nombre'] ?? 'Servicio eliminado') ?></td>
                <td class="text-end text-muted"><?= $anterior ? money($anterior['precio']) : '<span class="badge bg-secondary">Inicial</span>' ?></td>
                <td class="text-end fw-semibold"><?= money($h['precio']) ?></td>
                <td class="text-end">
                  <?php if ($anterior && abs($delta) > 0.005): ?>
                    <span class="small" style="color:<?= $delta > 0 ? '#6ee7b7' : '#fca5a5' ?>"><?= $delta > 0 ? '+' : '' ?><?= money($delta) ?></span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endfor; endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalServicio" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="s-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalServicioLabel">Nuevo servicio</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Nombre *</label>
            <input name="nombre" id="s-nombre" class="form-control" required maxlength="100">
          </div>
          <div class="col-6">
            <label class="form-label">Precio *</label>
            <input type="number" name="precio" id="s-precio" class="form-control" step="0.01" min="0" required>
            <div class="form-text" id="s-precio-ayuda">Se usa como precio base al registrar atenciones. Los cambios quedan en el historial.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Duración (min)</label>
            <input type="number" name="duracion" id="s-duracion" class="form-control" min="0" step="1">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="s-activo" value="1" checked>
              <label class="form-check-label" for="s-activo">Activo</label>
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
const modalS = new bootstrap.Modal('#modalServicio');
const lblS = document.getElementById('modalServicioLabel');

function abrirServicio(id, nombre, precio, duracion, activo, otro) {
    const esOtro = !!otro;
    lblS.textContent = id ? (esOtro ? 'Servicio especial' : 'Editar servicio') : 'Nuevo servicio';
    document.getElementById('s-id').value = id;
    document.getElementById('s-nombre').value = nombre;
    document.getElementById('s-precio').value = precio;
    document.getElementById('s-duracion').value = duracion;
    document.getElementById('s-activo').checked = !!activo;
    // Especial "Otro valor": el precio lo pone el barbero y siempre va activo (RF-07).
    document.getElementById('s-precio').disabled = esOtro;
    document.getElementById('s-precio').value = esOtro ? '' : precio;
    document.getElementById('s-activo').disabled = esOtro;
    document.getElementById('s-activo').checked = esOtro ? true : !!activo;
    document.getElementById('s-precio-ayuda').textContent = esOtro
        ? 'Servicio especial: el monto lo coloca el barbero al registrar la atención.'
        : 'Se usa como precio base al registrar atenciones. Los cambios quedan en el historial.';
    modalS.show();
}
document.getElementById('btnNuevo').addEventListener('click', () => abrirServicio(0, '', '', '', true, false));
document.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
    abrirServicio(btn.dataset.id, btn.dataset.nombre, btn.dataset.precio, btn.dataset.duracion, btn.dataset.activo, btn.dataset.otro);
}));
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
