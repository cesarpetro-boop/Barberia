<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'respaldos';
$title = 'Respaldos';
$subtitle = 'Copia de seguridad automática diaria y manual (RNF-11)';

$pdo = pdo();

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    csrf_check();

    if ($accion === 'respaldar') {
        try {
            $path = hacerRespaldo($pdo);
            flash('✔ Respaldo generado: ' . basename($path));
        } catch (Exception $ex) {
            flash('Error al respaldar: ' . $ex->getMessage(), 'danger');
        }
        redirect('respaldos.php');
    }

    if ($accion === 'eliminar') {
        $nombre = basename((string)($_POST['archivo'] ?? ''));
        $path = dir_respaldos() . '/' . $nombre;
        if ($nombre !== '' && is_file($path) && str_ends_with($nombre, '.sql')) {
            unlink($path);
            audit($pdo, 'eliminar_respaldo', 'respaldos', null, 'Eliminó respaldo ' . $nombre);
            flash('Respaldo eliminado: ' . $nombre, 'success');
        } else {
            flash('Archivo no válido.', 'danger');
        }
        redirect('respaldos.php');
    }

    if ($accion === 'restaurar') {
        $nombre = basename((string)($_POST['archivo'] ?? ''));
        $path = dir_respaldos() . '/' . $nombre;
        if ($nombre !== '' && is_file($path) && str_ends_with($nombre, '.sql')) {
            try {
                $n = restaurarRespaldo($pdo, $path);
                audit($pdo, 'restaurar_respaldo', 'respaldos', null, 'Restauró respaldo ' . $nombre . ' (' . $n . ' sentencias)');
                flash('✔ Base de datos restaurada desde ' . $nombre);
            } catch (Exception $ex) {
                flash('Error al restaurar: ' . $ex->getMessage(), 'danger');
            }
        } else {
            flash('Archivo no válido.', 'danger');
        }
        redirect('respaldos.php');
    }
}

// Descarga por GET
if (($_GET['descargar'] ?? '') !== '') {
    $nombre = basename((string)$_GET['descargar']);
    $path = dir_respaldos() . '/' . $nombre;
    if ($nombre !== '' && is_file($path) && str_ends_with($nombre, '.sql')) {
        audit($pdo, 'descargar_respaldo', 'respaldos', null, 'Descargó respaldo ' . $nombre);
        descargarRespaldo($path);
    }
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$respaldos = respaldos_listar();

require_once __DIR__ . '/inc/header.php';
?>

<div class="row g-4 align-items-start">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-cloud-arrow-down me-2"></i>Respaldo manual</div>
      <div class="card-body">
        <p class="text-muted small">
          La copia de seguridad se genera <b>automáticamente una vez al día</b> cuando el
          administrador inicia sesión. También puedes crearla a mano antes de cambios importantes.
        </p>
        <form method="post" class="d-grid gap-2">
          <?= csrf_input() ?>
          <input type="hidden" name="accion" value="respaldar">
          <button type="submit" class="btn btn-gold btn-lg">
            <i class="bi bi-plus-circle me-2"></i>Respaldar ahora
          </button>
        </form>
        <div class="form-text">
          <i class="bi bi-info-circle me-1"></i>
          Los archivos se guardan en <code>respaldos/</code> (bloqueada al público) con el formato <code>auto_AAA-MM-DD</code> o <code>respaldo_...</code>.
        </div>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header"><i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar base de datos</div>
      <div class="card-body">
        <p class="text-muted small">
          Restaura toda la base de datos desde un respaldo existente. <b>Sobreescribe los datos actuales.</b>
        </p>
        <?php if (!$respaldos): ?>
          <div class="text-muted small">No hay respaldos disponibles.</div>
        <?php else: ?>
          <form method="post" class="row g-2">
            <?= csrf_input() ?>
            <input type="hidden" name="accion" value="restaurar">
            <div class="col">
              <select name="archivo" class="form-select">
                <?php foreach ($respaldos as $r): ?>
                  <option value="<?= e($r['nombre']) ?>"><?= e($r['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-auto d-grid">
              <button type="submit" class="btn btn-outline-warning"
                onclick="return confirm('¿Seguro? Esto sobreescribe TODA la base de datos actual.')">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-earmarks me-2"></i>Historial de respaldos</span>
        <span class="text-muted small fw-normal"><?= count($respaldos) ?> archivo(s)</span>
      </div>
      <div class="card-body p-0">
        <?php if (!$respaldos): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-cloud-arrow-down fs-1 d-block mb-2 opacity-50"></i>
            Aún no hay respaldos.<br>
            <small>Se generan automáticamente cada día o con el botón "Respaldar ahora".</small>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th>Archivo</th><th>Fecha</th><th class="text-end">Tamaño</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($respaldos as $r): ?>
                  <tr>
                    <td>
                      <i class="bi bi-file-earmark-text me-2"></i>
                      <?php if (str_starts_with($r['nombre'], 'auto_')): ?>
                        <span class="badge bg-success me-2">auto</span>
                      <?php else: ?>
                        <span class="badge bg-secondary me-2">manual</span>
                      <?php endif; ?>
                      <?= e($r['nombre']) ?>
                    </td>
                    <td class="text-muted small"><?= e(date('d/m/Y H:i:s', $r['fecha'])) ?></td>
                    <td class="text-end text-muted small"><?= e(formatoBytes($r['tam'])) ?></td>
                    <td class="text-end">
                      <div class="d-flex gap-1 justify-content-end">
                        <a href="respaldos.php?descargar=<?= urlencode($r['nombre']) ?>" class="btn btn-sm btn-outline-info" title="Descargar">
                          <i class="bi bi-download"></i>
                        </a>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este respaldo?')">
                          <?= csrf_input() ?>
                          <input type="hidden" name="accion" value="eliminar">
                          <input type="hidden" name="archivo" value="<?= e($r['nombre']) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
