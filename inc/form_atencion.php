<?php
/**
 * Widget de registro/edición de atención (RF-05..RF-10).
 * Requiere: $barberos, $servicios, $metodos, $formId, $redirectUrl, $caja.
 * Opcional: $editar (resultado de obtenerAtencion()).
 */
$esEditar = !empty($editar);

// RF-07 · Servicio especial "Otro valor": monto libre colocado por el barbero.
$idOtro = 0;
foreach ($servicios as $s) {
    if (!empty($s['es_otro_valor'])) { $idOtro = (int)$s['id']; break; }
}

// RF-12 · Abrir caja obligatorio: sin caja abierta no se inicia el turno, así que
// el registro queda bloqueado tras un aviso. Editar (desde el historial) sí se permite.
if (!$esEditar && empty($caja)): ?>
<div class="card bloque-turno">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-scissors me-2"></i>Registrar atención</span>
    <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i>Turno no iniciado</span>
  </div>
  <div class="card-body text-center py-5">
    <span class="bloque-turno-icono"><i class="bi bi-wallet2"></i></span>
    <h5 class="mt-3 mb-2">Abre la caja para empezar el turno</h5>
    <p class="text-muted mb-4 mx-auto" style="max-width:430px">
      Para registrar atenciones primero debes abrir la caja del día con su base de
      efectivo. Así cada cobro queda dentro del turno y del arqueo al cierre.
    </p>
    <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
      <i class="bi bi-unlock me-1"></i>Abrir caja
    </button>
    <?php if (esRol('admin')): ?>
    <a href="caja.php" class="btn btn-outline-warning btn-lg ms-2">
      <i class="bi bi-wallet2 me-1"></i>Ver caja
    </a>
    <?php endif; ?>
  </div>
</div>
<?php return; endif; ?>

<?php

// Datos iniciales para JS (modo edición)
$iniciales = [];
if ($esEditar) {
    $serv = [];
    foreach ($editar['lineas'] as $l) {
        $serv[(int)$l['servicio_id']] = [
            'nombre' => (string)$l['servicio_nombre'],
            'precio' => (float)$l['precio_unit'],
            'cantidad' => (int)$l['cantidad'],
            'otro' => !empty($l['es_otro_valor']),
            'nota' => (string)($l['nota'] ?? ''),
        ];
    }
    $iniciales = [
        'barbero_id' => (int)$editar['barbero_id'],
        'tipo' => (string)$editar['tipo_comision'],
        'comision_valor' => (float)$editar['comision_valor'],
        'pago_id' => (int)$editar['metodo_pago_id'],
        'servicios' => $serv,
        'notas' => (string)$editar['notas'],
        'fecha' => (string)$editar['fecha'],
    ];
}
?>
<form method="post" class="corte-form card" data-form="<?= e($formId) ?>"
      action="<?= e($redirectUrl) ?>"
      data-iniciales='<?= $esEditar ? json_encode($iniciales, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '' ?>'>
  <?= csrf_input() ?>
  <input type="hidden" name="accion" value="<?= $esEditar ? 'editar_atencion' : 'guardar_atencion' ?>">
  <?php if ($esEditar): ?>
    <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
  <?php endif; ?>
  <input type="hidden" name="barbero_id" class="cf-barbero" value="">
  <input type="hidden" name="tipo_comision" class="cf-tipo" value="porcentaje">
  <input type="hidden" name="comision_valor" class="cf-comval" value="0">
  <input type="hidden" name="metodo_pago_id" class="cf-pago-id" value="1">

  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-scissors me-2"></i><?= $esEditar ? 'Editar atención ' . e($editar['codigo']) : 'Registrar atención' ?></span>
    <?php if (!$esEditar): ?>
      <span class="text-muted small fw-normal d-none d-sm-inline" title="El corte queda registrado con solo elegir barbero y servicio; fecha y pago en efectivo ya vienen por defecto.">
        <i class="bi bi-lightning-charge me-1"></i>3 toques: barbero → servicio → registrar
      </span>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <!-- 1 · BARBERO -->
    <div class="cf-paso">
      <div class="cf-label d-flex justify-content-between align-items-center">
        <span><span class="cf-num">1</span> Elige al barbero</span>
        <span class="cf-hint text-muted small"><i class="bi bi-hand-index me-1"></i>Toca una tarjeta</span>
      </div>
      <div class="barbero-grid">
        <?php foreach ($barberos as $b):
            $hue = hueNombre($b['nombre']);
        ?>
          <button type="button" class="barbero-card" data-id="<?= (int)$b['id'] ?>"
                  data-tipo="<?= e($b['tipo_comision']) ?>" data-valor="<?= (float)$b['comision_valor'] ?>"
                  aria-pressed="false"
                  title="<?= e($b['nombre']) ?> · <?= $b['tipo_comision'] === 'fijo' ? 'Comisión fija ' . money($b['comision_valor']) . ' por servicio' : 'Comisión ' . (float)$b['comision_valor'] . '% del total' ?>">
            <span class="avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,65%,48%),hsl(<?= $hue + 40 ?>,65%,32%))"><?= e(iniciales($b['nombre'])) ?></span>
            <span class="bc-name"><?= e($b['nombre']) ?></span>
            <span class="bc-pct"><?= $b['tipo_comision'] === 'fijo' ? money($b['comision_valor']) . '/serv' : (float)$b['comision_valor'] . '%' ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 2 · SERVICIOS (varios, RF-06) -->
    <div class="cf-paso">
      <div class="cf-label d-flex justify-content-between align-items-center">
        <span><span class="cf-num">2</span> Servicios <span class="text-muted fw-normal">(elige varios)</span></span>
        <span class="cf-contador badge rounded-pill bg-warning text-dark d-none">0</span>
      </div>
      <div class="chip-grid">
        <?php foreach ($servicios as $s):
            if (!empty($s['es_otro_valor'])) continue; // El chip de "Otro valor" va aparte, abajo.
        ?>
          <button type="button" class="chip" data-id="<?= (int)$s['id'] ?>" data-precio="<?= (float)$s['precio'] ?>" data-nombre="<?= e($s['nombre']) ?>">
            <?= e($s['nombre']) ?><b><?= money($s['precio']) ?></b>
          </button>
        <?php endforeach; ?>
        <?php foreach ($servicios as $s):
            if (empty($s['es_otro_valor'])) continue;
        ?>
          <button type="button" class="chip chip-otro" data-id="<?= (int)$s['id'] ?>" data-precio="0"
                  data-nombre="<?= e($s['nombre']) ?>" data-otro="1">
            <i class="bi bi-pencil-square me-1"></i><?= e($s['nombre']) ?><b>a convenir</b>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="serv-seleccionados"></div>

      <!-- Monto libre + motivo (RF-07): aparece al elegir "Otro valor" -->
      <div class="otro-valor-panel d-none" data-otro-panel>
        <div class="ovp-titulo"><i class="bi bi-pencil-square me-1"></i>Otro valor — cobro a convenir</div>
        <p class="ovp-ayuda mb-2">Para clientes que no llegan con el dinero completo: cobra lo que tengan en el momento y deja el motivo del ingreso.</p>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Monto cobrado *</label>
            <input type="number" name="otro_monto" class="form-control otro-monto" step="0.01" min="0.01" inputmode="decimal"
                   data-id="<?= (int)$idOtro ?>" placeholder="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Motivo del ingreso * <span class="text-muted">(obligatorio)</span></label>
            <input type="text" name="otro_motivo" class="form-control otro-motivo" maxlength="255" data-id="<?= (int)$idOtro ?>"
                   placeholder="Ej: Abono del corte, debe el resto, servicio extra…">
          </div>
        </div>
      </div>
    </div>

    <!-- 3 · DATOS -->
    <div class="cf-paso">
      <div class="cf-label"><span class="cf-num">3</span> Fecha y notas</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Fecha y hora</label>
          <input type="datetime-local" name="fecha" class="form-control cf-fecha" data-now required
                 value="<?= $esEditar ? '' : date('Y-m-d\TH:i') ?>">
          <?php if (!$esEditar): ?>
            <!-- Hora propuesta por el servidor: si no se toca, al registrar se
                 guarda la hora real del servidor (evita quedar fuera del turno) -->
            <input type="hidden" name="fecha_original" value="<?= date('Y-m-d\TH:i') ?>">
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Notas <span class="text-muted">(opcional)</span></label>
          <input type="text" name="notas" class="form-control cf-notas" maxlength="255" placeholder="Observaciones">
        </div>
      </div>
      <div class="cf-label mt-3 mb-2">Medio de pago</div>
      <div class="pago-grid">
        <?php foreach ($metodos as $m): ?>
          <button type="button" class="pago-card<?= (!$esEditar && (int)$m['id'] === 1) ? ' selected' : '' ?>" data-pago-id="<?= (int)$m['id'] ?>">
            <i class="bi <?= $m['tipo'] === 'efectivo' ? 'bi-cash-coin' : 'bi-bank' ?>"></i>
            <span><?= e($m['nombre']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- VISTA PREVIA -->
    <div class="cf-preview">
      <div class="cf-pv-row"><span>Subtotal servicios</span><strong class="cf-subtotal">$ 0</strong></div>
      <div class="cf-pv-row"><span>Total a cobrar</span><strong class="cf-total">$ 0</strong></div>
      <div class="cf-pv-row"><span>Comisión del barbero <span class="cf-pct-label">(—)</span></span><strong class="cf-comision text-gold">$ 0</strong></div>
      <div class="cf-pv-row cf-pv-total"><span>Total cobrado</span><strong class="cf-cobrado">$ 0</strong></div>
    </div>

    <div class="cf-error alert alert-danger py-2 px-3 mb-3 d-none" role="alert"></div>

    <button type="submit" class="btn-gold w-100 btn-lg">
      <i class="bi bi-check2-circle me-2"></i><?= $esEditar ? 'Guardar cambios' : 'Registrar atención' ?>
    </button>
    <?php if (!$esEditar): ?>
      <button type="button" class="btn btn-outline-warning w-100 mt-2 cf-limpiar">Limpiar</button>
    <?php endif; ?>
  </div>
</form>
