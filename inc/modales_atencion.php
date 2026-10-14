<?php
/**
 * Modal de anulación de atención (RF-09).
 * Se activa con botones [data-anular="ID"].
 */
?>
<div class="modal fade" id="modalAnular" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="anular_atencion">
        <input type="hidden" name="id" id="anular_id" value="">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Anular atención</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">La atención quedará marcada como <b>anulada</b> y se excluirá de todos los totales, la caja y las liquidaciones.</p>
          <label class="form-label">Motivo de anulación <span class="text-danger">*</span></label>
          <textarea name="motivo" class="form-control" rows="3" maxlength="255" required placeholder="Ej.: el cliente canceló, error al registrar…"></textarea>
          <div class="form-text">Se registra quién la anuló, cuándo y por qué.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Anular atención</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-anular]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('anular_id').value = btn.dataset.anular;
        const m = new bootstrap.Modal(document.getElementById('modalAnular'));
        m.show();
    });
});
</script>
