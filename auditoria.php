<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
requerir_login('admin');

$page = 'auditoria';
$title = 'Auditoría';
$subtitle = 'Constancia de quién hizo qué, cuándo y por qué';

$pdo = pdo();

// ---------- Filtros ----------
$accionF = $_GET['accion'] ?? '';
$tablaF = $_GET['tabla'] ?? '';
$usuarioF = trim($_GET['usuario'] ?? '');

$where = "1=1";
$params = [];
if ($accionF !== '') {
    $where .= " AND accion = ?";
    $params[] = $accionF;
}
if ($tablaF !== '') {
    $where .= " AND tabla = ?";
    $params[] = $tablaF;
}
if ($usuarioF !== '') {
    $where .= " AND usuario_nombre LIKE ?";
    $params[] = '%' . $usuarioF . '%';
}

$st = $pdo->prepare("
    SELECT * FROM auditoria
    WHERE {$where}
    ORDER BY id DESC
    LIMIT 500
");
$st->execute($params);
$logs = $st->fetchAll();

$usuarios = $pdo->query("SELECT DISTINCT usuario_nombre FROM auditoria ORDER BY usuario_nombre")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/inc/header.php';
?>

<div class="filtros">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">Acción</label>
      <select name="accion" class="form-select">
        <option value="">Todas</option>
        <?php foreach (['crear', 'editar', 'anular', 'abrir_caja', 'cerrar_caja', 'adelanto', 'descuento', 'login', 'logout'] as $a): ?>
          <option value="<?= $a ?>" <?= $accionF === $a ? 'selected' : '' ?>><?= str_replace('_', ' ', $a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Tabla</label>
      <select name="tabla" class="form-select">
        <option value="">Todas</option>
        <?php foreach (['atenciones', 'cajas', 'adelantos', 'barberos', 'servicios', 'usuarios', 'productos'] as $t): ?>
          <option value="<?= $t ?>" <?= $tablaF === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Usuario</label>
      <select name="usuario" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= e($u) ?>" <?= $usuarioF === $u ? 'selected' : '' ?>><?= e($u) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto d-grid">
      <button class="btn btn-outline-warning"><i class="bi bi-funnel me-1"></i>Filtrar</button>
    </div>
    <div class="col-auto d-grid">
      <a href="auditoria.php" class="btn btn-outline-light"><i class="bi bi-x-lg"></i></a>
    </div>
    <div class="col ms-auto text-end">
      <span class="text-muted small"><b class="text-gold"><?= count($logs) ?></b> registro<?= count($logs) === 1 ? '' : 's' ?></span>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$logs): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-journal-text fs-1 d-block mb-2 opacity-50"></i>
        Sin registros con los filtros seleccionados.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Descripción</th><th>IP</th></tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d/m/Y H:i:s', strtotime($l['creado_en']))) ?></td>
                <td class="fw-semibold small"><?= e($l['usuario_nombre']) ?></td>
                <td>
                  <?php
                  $badge = [
                      'crear' => 'background:rgba(52,211,153,.15);color:#6ee7b7',
                      'editar' => 'background:rgba(96,165,250,.15);color:#93c5fd',
                      'anular' => 'background:rgba(248,113,113,.15);color:#fca5a5',
                      'abrir_caja' => 'background:rgba(212,175,55,.15);color:#f2d06b',
                      'cerrar_caja' => 'background:rgba(212,175,55,.15);color:#f2d06b',
                      'adelanto' => 'background:rgba(96,165,250,.15);color:#93c5fd',
                      'descuento' => 'background:rgba(248,113,113,.15);color:#fca5a5',
                      'login' => 'background:rgba(52,211,153,.15);color:#6ee7b7',
                      'logout' => 'background:rgba(139,147,169,.15);color:#8b93a9',
                  ];
                  $estilo = $badge[$l['accion']] ?? 'background:rgba(139,147,169,.15);color:#8b93a9';
                  ?>
                  <span class="badge" style="<?= $estilo ?>"><?= str_replace('_', ' ', e($l['accion'])) ?></span>
                </td>
                <td class="small text-muted">
                  <?= e($l['tabla'] ?: '—') ?><?= $l['registro_id'] ? ' #' . (int)$l['registro_id'] : '' ?>
                </td>
                <td class="small">
                  <?= e($l['descripcion'] ?: '—') ?>
                  <?php if ($l['detalles']): ?>
                    <br><span class="text-muted" style="font-size:11px"><?= e(mb_substr((string)$l['detalles'], 0, 120)) ?><?= mb_strlen((string)$l['detalles']) > 120 ? '…' : '' ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= e($l['ip'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/inc/modales_caja.php'; ?>
<?php require __DIR__ . '/inc/modales_atencion.php'; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
