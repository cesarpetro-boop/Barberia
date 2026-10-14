/* ============================================================
   Barbería Lavadero — JS común (v2)
   ============================================================ */

/* ---------- Modales al nivel de <body> ---------- */
// Bootstrap crea su backdrop como hijo directo de <body> (z-index 1050/1055).
// Nuestras vistas renderizan los .modal dentro de .app, que crea su propio
// contexto de apilamiento (position + z-index): el backdrop quedaba ENCIMA
// del modal — se veía pero ningún clic llegaba a los campos ni a Guardar.
// Moverlos a <body> restaura el orden correcto sin cambiar nada visual.
document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));

/* ---------- Sidebar móvil ---------- */
(function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const btnMenu = document.getElementById('btnMenu');
    const close = () => {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
    };
    if (btnMenu) btnMenu.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });
    if (overlay) overlay.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    document.querySelectorAll('.sidenav a, .sidebar-foot a').forEach(a =>
        a.addEventListener('click', close)
    );
})();

/* ---------- datetime-local: colocar la hora del SERVIDOR ----------
   Se usa window.APP_AHORA (inyectado por header.php con ahora()) en lugar de
   new Date() del navegador: si los relojes difieren, las atenciones quedarían
   fuera del turno de caja (filtro fecha >= apertura). Fallback: reloj local. */
document.querySelectorAll('input[data-now]').forEach(el => {
    if (el.value) return;
    if (window.APP_AHORA) { el.value = window.APP_AHORA; return; }
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    el.value = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
});

/* ---------- Confirmación genérica ---------- */
document.addEventListener('click', ev => {
    const btn = ev.target.closest('[data-confirm]');
    if (btn && !confirm(btn.dataset.confirm)) {
        ev.preventDefault();
        ev.stopPropagation();
    }
});

/* ---------- Utilidades ---------- */
/* Formatea dinero: quita los ceros sobrantes y agrupa miles (17,000.00 → 17,000) */
const fmtMoney = n => {
    const [ent, dec = ''] = Number(n).toFixed(2).split('.');
    const decLimpio = dec.replace(/0+$/, '');
    const miles = ent.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return '$ ' + (decLimpio ? miles + '.' + decLimpio : miles);
};
const round2 = n => Math.round(n * 100) / 100;

/* ============================================================
   Widget de registro / edición de atención (RF-05..RF-10)
   ============================================================ */
function initCorteForm(form) {
    const $barbero = form.querySelector('.cf-barbero');
    const $tipo = form.querySelector('.cf-tipo');
    const $comval = form.querySelector('.cf-comval');
    const $pagoId = form.querySelector('.cf-pago-id');
    const $selBox = form.querySelector('.serv-seleccionados');
    const $err = form.querySelector('.cf-error');
    const $limpiar = form.querySelector('.cf-limpiar');
    const $otroPanel = form.querySelector('[data-otro-panel]');
    const $otroMonto = form.querySelector('.otro-monto');
    const $otroMotivo = form.querySelector('.otro-motivo');

    // Si falta algún elemento esencial no inicializamos: evita errores JS
    // que dejarían inoperante todo el formulario de la página.
    if (!$barbero || !$tipo || !$comval || !$pagoId || !$selBox) return;

    // Estado: servicios seleccionados  id -> {id, nombre, precio, cantidad}
    const sel = {};

    function actualizar() {
        let subtotal = 0, unidades = 0;
        Object.values(sel).forEach(s => {
            subtotal += s.precio * s.cantidad;
            unidades += s.cantidad;
        });
        subtotal = round2(subtotal);

        const total = subtotal;

        const valor = parseFloat($comval.value) || 0;
        // "Otro valor" es un ingreso libre, no un servicio atendido: no suma tarifa fija.
        const numServicios = Math.max(1, Object.values(sel).filter(s => !s.otro).length);
        let comision = ($tipo.value === 'fijo')
            ? valor * numServicios
            : total * valor / 100;
        comision = round2(comision);

        const cobrado = total;

        form.querySelector('.cf-subtotal').textContent = fmtMoney(subtotal);
        form.querySelector('.cf-total').textContent = fmtMoney(total);
        form.querySelector('.cf-comision').textContent = fmtMoney(comision);
        form.querySelector('.cf-pct-label').textContent =
            ($tipo.value === 'fijo' ? '(fijo/serv)' : '(' + (valor ? valor + '%' : '—') + ')');
        form.querySelector('.cf-cobrado').textContent = fmtMoney(cobrado);
    }

    function renderSel() {
        $selBox.innerHTML = '';
        Object.values(sel).forEach(s => {
            const div = document.createElement('div');
            div.className = 'serv-line' + (s.otro ? ' sl-otro' : '');
            if (s.otro) {
                // "Otro valor": monto fijo y motivo en lugar de cantidad.
                div.innerHTML =
                    '<span class="sl-nombre">' + escapeHtml(s.nombre) + ' <b>' + fmtMoney(s.precio) + '</b>' +
                    (s.nota ? '<i class="sl-motivo">' + escapeHtml(s.nota) + '</i>' : '') + '</span>' +
                    '<span class="sl-total">' + fmtMoney(s.precio) + '</span>' +
                    '<button type="button" class="sl-x" title="Quitar">×</button>';
            } else {
                div.innerHTML =
                    '<span class="sl-nombre">' + escapeHtml(s.nombre) + ' <b>' + fmtMoney(s.precio) + '</b></span>' +
                    '<span class="sl-qty">' +
                    '<button type="button" class="qty-btn" data-act="-1" aria-label="Menos">−</button>' +
                    '<input type="number" class="qty-input" value="' + s.cantidad + '" min="1" max="99" aria-label="Cantidad">' +
                    '<button type="button" class="qty-btn" data-act="1" aria-label="Más">+</button>' +
                    '</span>' +
                    '<span class="sl-total">' + fmtMoney(round2(s.precio * s.cantidad)) + '</span>' +
                    '<button type="button" class="sl-x" title="Quitar">×</button>';

                const menos = div.querySelector('[data-act="-1"]');
                const mas = div.querySelector('[data-act="1"]');
                const input = div.querySelector('.qty-input');
                menos.addEventListener('click', () => { s.cantidad = Math.max(1, s.cantidad - 1); renderSel(); actualizar(); });
                mas.addEventListener('click', () => { s.cantidad = Math.min(99, s.cantidad + 1); renderSel(); actualizar(); });
                input.addEventListener('change', () => {
                    s.cantidad = Math.max(1, Math.min(99, parseInt(input.value) || 1));
                    renderSel(); actualizar();
                });
            }
            div.querySelector('.sl-x').addEventListener('click', () => {
                delete sel[s.id];
                const chip = form.querySelector('.chip[data-id="' + s.id + '"]');
                if (chip) chip.classList.remove('selected');
                if (s.otro) mostrarOtro(false);
                renderSel(); actualizar();
            });

            $selBox.appendChild(div);
        });
        $selBox.classList.toggle('vacio', Object.keys(sel).length === 0);
    }

    /** Muestra/oculta el panel de monto libre y enfoca el campo al abrir. */
    function mostrarOtro(visible) {
        if (!$otroPanel) return;
        $otroPanel.classList.toggle('d-none', !visible);
        if (visible && $otroMonto) $otroMonto.focus();
    }

    function error(msg) {
        $err.textContent = '⚠ ' + msg;
        $err.classList.remove('d-none');
        $err.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(() => $err.classList.add('d-none'), 6000);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /* --- Paso 1: barbero --- */
    form.querySelectorAll('.barbero-card').forEach(btn => {
        btn.addEventListener('click', () => {
            form.querySelectorAll('.barbero-card').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            $barbero.value = btn.dataset.id;
            $tipo.value = btn.dataset.tipo;
            $comval.value = btn.dataset.valor;
            actualizar();
        });
    });

    /* --- Paso 2: servicios (multi-selección) --- */
    form.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const id = chip.dataset.id;
            const esOtro = chip.dataset.otro === '1';
            if (sel[id]) {
                delete sel[id];
                chip.classList.remove('selected');
                if (esOtro) {
                    mostrarOtro(false);
                    if ($otroMonto) $otroMonto.value = '';
                    if ($otroMotivo) $otroMotivo.value = '';
                }
            } else {
                sel[id] = {
                    id: id,
                    nombre: chip.dataset.nombre,
                    precio: esOtro ? (parseFloat($otroMonto && $otroMonto.value) || 0) : (parseFloat(chip.dataset.precio) || 0),
                    cantidad: 1,
                    otro: esOtro,
                    nota: ''
                };
                chip.classList.add('selected');
                if (esOtro) mostrarOtro(true);
            }
            renderSel(); actualizar();
        });
    });

    /* --- "Otro valor": monto y motivo en vivo --- */
    if ($otroMonto) $otroMonto.addEventListener('input', () => {
        const id = $otroMonto.dataset.id;
        if (!sel[id]) return;
        sel[id].precio = Math.max(0, parseFloat($otroMonto.value) || 0);
        renderSel(); actualizar();
    });
    if ($otroMotivo) $otroMotivo.addEventListener('input', () => {
        const id = $otroMotivo.dataset.id;
        if (!sel[id]) return;
        sel[id].nota = $otroMotivo.value;
        renderSel();
    });

    /* --- Paso 3: medio de pago --- */
    form.querySelectorAll('.pago-card').forEach(btn => {
        btn.addEventListener('click', () => {
            form.querySelectorAll('.pago-card').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            $pagoId.value = btn.dataset.pagoId;
        });
    });

    /* --- Envío: validar y sincronizar arrays --- */
    form.addEventListener('submit', ev => {
        if (!$barbero.value) { ev.preventDefault(); error('Selecciona un barbero (paso 1).'); return; }
        if (Object.keys(sel).length === 0) { ev.preventDefault(); error('Selecciona al menos un servicio (paso 2).'); return; }
        // "Otro valor" exige monto > 0 y motivo (RF-07).
        const otro = Object.values(sel).find(s => s.otro);
        if (otro) {
            if (!(otro.precio > 0)) {
                ev.preventDefault();
                error('En "Otro valor" indica un monto mayor que cero.');
                if ($otroMonto) $otroMonto.focus();
                return;
            }
            if (!$otroMotivo || !$otroMotivo.value.trim()) {
                ev.preventDefault();
                error('En "Otro valor" debes indicar el motivo del ingreso.');
                if ($otroMotivo) $otroMotivo.focus();
                return;
            }
        }
        form.querySelectorAll('input[name="servicio_ids[]"], input[name="cantidades[]"], input[name="precios[]"], input[name="motivos[]"]')
            .forEach(i => i.remove());
        Object.values(sel).forEach(s => {
            const h1 = document.createElement('input');
            h1.type = 'hidden'; h1.name = 'servicio_ids[]'; h1.value = s.id;
            const h2 = document.createElement('input');
            h2.type = 'hidden'; h2.name = 'cantidades[]'; h2.value = s.cantidad;
            form.appendChild(h1); form.appendChild(h2);
            // Monto libre y motivo viajan en el mismo índice que su servicio.
            const h3 = document.createElement('input');
            h3.type = 'hidden'; h3.name = 'precios[]'; h3.value = s.otro ? s.precio : '';
            const h4 = document.createElement('input');
            h4.type = 'hidden'; h4.name = 'motivos[]'; h4.value = s.otro ? (s.nota || '') : '';
            form.appendChild(h3); form.appendChild(h4);
        });
    });

    /* --- Limpiar --- */
    if ($limpiar) $limpiar.addEventListener('click', () => {
        Object.keys(sel).forEach(k => delete sel[k]);
        form.querySelectorAll('.barbero-card.selected, .chip.selected, .pago-card.selected')
            .forEach(el => el.classList.remove('selected'));
        $barbero.value = '';
        $tipo.value = 'porcentaje';
        $comval.value = '0';
        $pagoId.value = '1';
        form.querySelector('.cf-notas').value = '';
        if ($otroMonto) $otroMonto.value = '';
        if ($otroMotivo) $otroMotivo.value = '';
        mostrarOtro(false);
        renderSel(); actualizar();
    });

    /* --- Modo edición: precargar datos --- */
    if (form.dataset.iniciales) {
        let ini = {};
        try {
            ini = JSON.parse(form.dataset.iniciales) || {};
        } catch (e) {
            ini = {};
        }
        const bc = form.querySelector('.barbero-card[data-id="' + ini.barbero_id + '"]');
        if (bc) bc.click();
        Object.entries(ini.servicios || {}).forEach(([id, s]) => {
            const chip = form.querySelector('.chip[data-id="' + id + '"]');
            if (chip && !sel[id]) {
                sel[id] = { id: id, nombre: s.nombre, precio: s.precio, cantidad: s.cantidad, otro: !!s.otro, nota: s.nota || '' };
                chip.classList.add('selected');
                if (s.otro) {
                    if ($otroMonto) $otroMonto.value = s.precio || '';
                    if ($otroMotivo) $otroMotivo.value = s.nota || '';
                    mostrarOtro(true);
                }
            }
        });
        const pc = form.querySelector('.pago-card[data-pago-id="' + ini.pago_id + '"]');
        if (pc) pc.click();
        form.querySelector('.cf-notas').value = ini.notas || '';
        const f = form.querySelector('.cf-fecha');
        if (f && ini.fecha) f.value = String(ini.fecha).replace(' ', 'T');
    }

    renderSel(); actualizar();
}
document.querySelectorAll('.corte-form').forEach(initCorteForm);

/* ============================================================
   Arqueo de caja: diferencias en vivo (efectivo + digital)
   ============================================================ */
function initArqueo(inputId, vistaId) {
    const $input = document.getElementById(inputId);
    const $vista = document.getElementById(vistaId);
    if (!$input || !$vista) return;
    const esperado = parseFloat($vista.dataset.esperado) || 0;
    function calc() {
        const declarado = parseFloat($input.value) || 0;
        const d = round2(declarado - esperado);
        $vista.textContent = (d >= 0 ? '+ ' : '− ') + fmtMoney(Math.abs(d));
        $vista.classList.remove('exacta', 'sobrante', 'faltante');
        if (Math.abs(d) < 0.005) $vista.classList.add('exacta');
        else if (d > 0) $vista.classList.add('sobrante');
        else $vista.classList.add('faltante');
    }
    $input.addEventListener('input', calc);
    calc();
}
initArqueo('monto_cierre', 'diferencia_vista');
initArqueo('digital_declarado', 'digital_dif_vista');

/* ---------- Exportar tabla a CSV (RF-18) ---------- */
function exportarCSV(archivo, filas) {
    const esc = v => {
        v = (v === null || v === undefined) ? '' : String(v);
        return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    };
    const csv = filas.map(f => f.map(esc).join(',')).join('\r\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = archivo;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

/* ---------- Imprimir / PDF (RF-18) ---------- */
document.querySelectorAll('[data-imprimir]').forEach(btn => {
    btn.addEventListener('click', () => window.print());
});

/* ============================================================
   UX/UI IMPROVEMENTS — Reloj en vivo, Contadores animados, Entradas
   ============================================================ */

/* ---------- Reloj en vivo ---------- */
(function () {
    const el = document.getElementById('reloj-live');
    if (!el) return;
    function tick() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        el.textContent = h + ':' + m + ':' + s;
    }
    tick();
    setInterval(tick, 1000);
})();

/* ---------- Animación de contadores en KPIs ---------- */
(function () {
    const fmtInt  = n => Math.round(n).toLocaleString('es');
    const fmtMon  = n => {
        const v = Math.round(n);
        return '$ ' + v.toLocaleString('es');
    };

    function animateCounter(el, target, formatter, duration) {
        const start = performance.now();
        const step = ts => {
            const progress = Math.min((ts - start) / duration, 1);
            // Ease-out cubic
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatter(target * ease);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // Intersection Observer: anima cuando el KPI entra en vista
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el = entry.target;

            if (el.dataset.countInt !== undefined) {
                const target = parseInt(el.dataset.countInt) || 0;
                animateCounter(el, target, fmtInt, 900);
                observer.unobserve(el);
            } else if (el.dataset.countMoney !== undefined) {
                const target = parseFloat(el.dataset.countMoney) || 0;
                animateCounter(el, target, fmtMon, 1100);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('[data-count-int], [data-count-money]').forEach(el => {
        observer.observe(el);
    });
})();

/* ---------- Animación staggered de cards en secciones ---------- */
(function () {
    const rows = document.querySelectorAll('.row.g-4');
    rows.forEach((row, ri) => {
        row.querySelectorAll('.card').forEach((card, ci) => {
            card.classList.add('animate-in');
            card.style.setProperty('--delay', (400 + ri * 80 + ci * 60) + 'ms');
        });
    });
})();

/* ---------- Barra de progreso: animar al cargar ---------- */
(function () {
    // Las barras arrancan en 0 via CSS y transicionan al valor real
    // Usamos un pequeño delay para que la animación CSS sea visible
    setTimeout(() => {
        document.querySelectorAll('.kpi-bar-fill').forEach(bar => {
            const w = bar.style.width;
            bar.style.width = '0%';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { bar.style.width = w; });
            });
        });
    }, 100);
})();

