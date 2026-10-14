<?php
/**
 * Modales de apertura y cierre de caja (RF-12).
 * Requiere: $caja (resultado de cajaActual() o null).
 */
?>
<!-- Modal Abrir Caja -->
<div class="modal fade" id="modalAbrirCaja" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="abrir_caja">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Abrir caja</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Inicia el turno con la base de efectivo que tienes en la caja (puede ser $ 0).</p>
          <label class="form-label">Base de efectivo (monto inicial)</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">$</span>
            <input type="number" name="monto_apertura" class="form-control" step="0.01" min="0" value="0" required autofocus>
          </div>
          <div class="form-text">Se suma al calcular el efectivo esperado al cerrar.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-unlock me-1"></i>Abrir caja</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cerrar Caja (solo admin: el barbero abre, no cierra) -->
<?php if (!empty($caja) && !esRol('barbero')): ?>
<div class="modal fade" id="modalCerrarCaja" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="cerrar_caja">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Cerrar caja · arqueo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="caja-resumen mb-3">
            <div class="d-flex justify-content-between small py-1">
              <span class="text-muted">Base al abrir</span>
              <strong><?= money($caja['monto_apertura']) ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
              <span class="text-muted">Atenciones del turno</span>
              <strong><?= (int)($caja['atenciones_turno'] ?? 0) ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
              <span class="text-muted">Ingresos del turno</span>
              <strong><?= money($caja['ingresos_turno']) ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
              <span class="text-muted">Efectivo esperado (base + efectivo)</span>
              <strong class="text-gold"><?= money($caja['efectivo_esperado']) ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
              <span class="text-muted">Pagos digitales del turno</span>
              <strong style="color:#93c5fd"><?= money($caja['digital_esperado'] ?? 0) ?></strong>
            </div>
          </div>

          <label class="form-label">Efectivo contado (arqueo)</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">$</span>
            <input type="number" name="monto_cierre" id="monto_cierre" class="form-control" step="0.01" min="0"
                   value="<?= quitarCeros(number_format((float)$caja['efectivo_esperado'], 2, '.', '')) ?>" required>
          </div>
          <div class="caja-diferencia mt-2">
            <span class="text-muted small">Diferencia efectivo</span>
            <strong id="diferencia_vista" class="diferencia exacta"
                    data-esperado="<?= quitarCeros(number_format((float)$caja['efectivo_esperado'], 2, '.', '')) ?>">+ $ 0</strong>
          </div>

          <label class="form-label mt-3">Pagos digitales recibidos <span class="text-muted">(transferencias, tarjetas)</span></label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">$</span>
            <input type="number" name="digital_declarado" id="digital_declarado" class="form-control" step="0.01" min="0"
                   value="<?= quitarCeros(number_format((float)($caja['digital_esperado'] ?? 0), 2, '.', '')) ?>" required>
          </div>
          <div class="caja-diferencia mt-2">
            <span class="text-muted small">Diferencia digital</span>
            <strong id="digital_dif_vista" class="diferencia exacta"
                    data-esperado="<?= quitarCeros(number_format((float)($caja['digital_esperado'] ?? 0), 2, '.', '')) ?>">+ $ 0</strong>
          </div>

          <label class="form-label mt-3">Notas <span class="text-muted">(opcional)</span></label>
          <input type="text" name="notas" class="form-control" maxlength="255" placeholder="Ej.: transferencia pendiente de acreditar…">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-cash-coin me-1"></i>Cerrar caja</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
