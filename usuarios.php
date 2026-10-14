<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'usuarios';
$title = 'Usuarios';
$subtitle = 'Actores del sistema: administrador (acceso total) y barbero';

$pdo = pdo();

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $rol = in_array($_POST['rol'] ?? '', ['admin', 'barbero'], true) ? $_POST['rol'] : 'admin';
        $barbero_id = (int)($_POST['barbero_id'] ?? 0) ?: null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $clave = $_POST['contrasena'] ?? '';

        if ($nombre === '' || $usuario === '') {
            flash('Nombre y usuario son obligatorios.', 'danger');
        } else {
            if ($id) {
                if ($clave !== '') {
                    $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=?, barbero_id=?, activo=?, contrasena=? WHERE id=?")
                        ->execute([$nombre, $usuario, $rol, $barbero_id, $activo, password_hash($clave, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=?, barbero_id=?, activo=? WHERE id=?")
                        ->execute([$nombre, $usuario, $rol, $barbero_id, $activo, $id]);
                }
                flash('Usuario actualizado.');
            } else {
                if ($clave === '') {
                    flash('La contraseña es obligatoria para un usuario nuevo.', 'danger');
                } else {
                    $pdo->prepare("INSERT INTO usuarios (nombre, usuario, contrasena, rol, barbero_id, activo) VALUES (?,?,?,?,?,?)")
                        ->execute([$nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $rol, $barbero_id, $activo]);
                    flash('Usuario creado.');
                }
            }
            redirect('usuarios.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ((int)$_SESSION['user_id'] === $id) {
            flash('No puedes eliminarte a ti mismo.', 'danger');
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
            flash('Usuario eliminado.');
        }
        redirect('usuarios.php');
    }
}

// ---------- Listado ----------
$usuarios = $pdo->query("
    SELECT u.*, b.nombre AS barbero_nombre FROM usuarios u
    LEFT JOIN barberos b ON b.id = u.barbero_id
    ORDER BY u.rol, u.nombre
")->fetchAll();
$barberos = $pdo->query("SELECT * FROM barberos WHERE activo = 1 ORDER BY nombre")->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" id="btnNuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo usuario</button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Usuario</th><th>Rol</th><th>Barbero vinculado</th><th>Último acceso</th><th>Estado</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php if (!$usuarios): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Sin usuarios</td></tr>
          <?php endif; ?>
          <?php foreach ($usuarios as $u):
              $hue = hueNombre($u['nombre']);
          ?>
          <tr>
            <td class="fw-semibold">
              <span class="avatar me-2" style="width:32px;height:32px;font-size:12px;background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($u['nombre'])) ?></span>
              <?= e($u['nombre']) ?>
              <div class="text-muted small">@<?= e($u['usuario']) ?></div>
            </td>
            <td>
              <span class="badge rol-<?= $u['rol'] ?>">
                <?= $u['rol'] === 'admin' ? 'Administrador' : 'Barbero' ?>
              </span>
            </td>
            <td class="text-muted small"><?= e($u['barbero_nombre'] ?: '—') ?></td>
            <td class="text-muted small"><?= $u['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : 'Nunca' ?></td>
            <td>
              <?php if ($u['activo']): ?>
                <span class="badge bg-success">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-light" data-editar
                      data-id="<?= (int)$u['id'] ?>"
                      data-nombre="<?= e($u['nombre']) ?>"
                      data-usuario="<?= e($u['usuario']) ?>"
                      data-rol="<?= e($u['rol']) ?>"
                      data-barbero="<?= (int)($u['barbero_id'] ?? 0) ?>"
                      data-activo="<?= (int)$u['activo'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
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

<div class="row g-4 mt-1">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body d-flex gap-3">
        <span class="badge rol-admin" style="font-size:13px;padding:8px 12px">Admin</span>
        <div><strong class="small">Administrador — acceso total</strong>
          <p class="text-muted small mb-0">Todo: registra y edita atenciones y cobros, abre y cierra la caja con arqueo, maneja inventario (vender/reponer), registra adelantos y descuentos, y configura barberos, servicios, métodos, usuarios, respaldos, auditoría y reportes.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body d-flex gap-3">
        <span class="badge rol-barbero" style="font-size:13px;padding:8px 12px">Barbero</span>
        <div><strong class="small">Barbero</strong>
          <p class="text-muted small mb-0">Ve el dashboard, sus propias atenciones y su liquidación. Vincula el usuario a un barbero.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="u-id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUsuarioLabel">Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Nombre completo *</label>
            <input name="nombre" id="u-nombre" class="form-control" required maxlength="100">
          </div>
          <div class="col-6">
            <label class="form-label">Usuario *</label>
            <input name="usuario" id="u-usuario" class="form-control" required maxlength="50">
          </div>
          <div class="col-6">
            <label class="form-label" id="u-clave-label">Contraseña *</label>
            <input type="password" name="contrasena" id="u-clave" class="form-control" maxlength="255">
            <div class="form-text" id="u-clave-help">Mínimo 6 caracteres.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Rol *</label>
            <select name="rol" id="u-rol" class="form-select">
              <option value="admin">Administrador</option>
              <option value="barbero">Barbero</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Vincular a barbero</label>
            <select name="barbero_id" id="u-barbero" class="form-select">
              <option value="">— Ninguno —</option>
              <?php foreach ($barberos as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= e($b['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Solo para rol barbero: limita lo que ve a sus propias atenciones.</div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="u-activo" value="1" checked>
              <label class="form-check-label" for="u-activo">Activo (puede iniciar sesión)</label>
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
const modalU = new bootstrap.Modal('#modalUsuario');
const lblU = document.getElementById('modalUsuarioLabel');
const inpClave = document.getElementById('u-clave');
const lblClave = document.getElementById('u-clave-label');
const helpClave = document.getElementById('u-clave-help');

function abrirUsuario(id, nombre, usuario, rol, barbero, activo) {
    lblU.textContent = id ? 'Editar usuario' : 'Nuevo usuario';
    document.getElementById('u-id').value = id;
    document.getElementById('u-nombre').value = nombre;
    document.getElementById('u-usuario').value = usuario;
    inpClave.value = '';
    if (id) {
        inpClave.required = false;
        lblClave.textContent = 'Contraseña (dejar vacío para no cambiar)';
        helpClave.textContent = 'Mínimo 6 caracteres.';
    } else {
        inpClave.required = true;
        lblClave.textContent = 'Contraseña *';
        helpClave.textContent = 'Mínimo 6 caracteres.';
    }
    document.getElementById('u-rol').value = rol;
    document.getElementById('u-barbero').value = barbero;
    document.getElementById('u-activo').checked = !!activo;
    modalU.show();
}
document.getElementById('btnNuevo').addEventListener('click', () => abrirUsuario(0, '', '', 'admin', 0, true));
document.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
    abrirUsuario(btn.dataset.id, btn.dataset.nombre, btn.dataset.usuario, btn.dataset.rol, btn.dataset.barbero, btn.dataset.activo);
}));
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
