/**
 * SPA de la Mesa de Soporte TI.
 * Toda la interacción es por AJAX (fetch) contra /api, sin recargar la página.
 * Regla de seguridad: todo dato dinámico que entra a innerHTML pasa por esc().
 */
(() => {
  'use strict';

  const API_KEY = document.querySelector('meta[name="api-key"]').content;
  const POR_PAGINA = 10;

  const state = {
    page: 1,
    filtros: { estado: '', prioridad: '', tecnico: '', q: '' },
    categorias: [],
    tecnicos: [],          // técnicos activos con su carga (para asignar)
    usuarioValido: false,
    ticketAbierto: null,   // número del ticket en el modal de detalle
    reqTabla: 0,           // para descartar respuestas viejas
    usuario: null,         // perfil del usuario autenticado (RBAC)
  };

  const $ = (sel, root = document) => root.querySelector(sel);

  /** Escapa HTML para evitar XSS al construir plantillas. */
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  const fmtFecha = (iso) => iso
    ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })
    : '—';

  const debounce = (fn, ms) => {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  };

  // ---------------------------------------------------------------------
  // Cliente de la API
  // ---------------------------------------------------------------------
  async function api(method, path, body) {
    const headers = { 'X-API-Key': API_KEY, Accept: 'application/json' };
    const token = localStorage.getItem('soporte_token');
    if (token) headers['Authorization'] = 'Bearer ' + token;
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    let res;
    try {
      res = await fetch('/api' + path, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
    } catch (e) {
      throw Object.assign(new Error('No hay conexión con el servidor'), { status: 0, code: 'RED', fields: {} });
    }
    let json = null;
    try { json = await res.json(); } catch (e) { /* respuesta sin JSON */ }
    if (!res.ok) {
      const err = (json && json.error) || { code: 'ERROR', message: 'Error inesperado del servidor', fields: {} };
      if (res.status === 401 && path !== '/auth/login') {
        localStorage.removeItem('soporte_token');
        state.usuario = null;
        actualizarWidgetUsuario();
        mostrarLogin();
      }
      throw Object.assign(new Error(err.message), { status: res.status, code: err.code, fields: err.fields || {} });
    }
    return json;
  }

  function toast(mensaje, tipo = 'success') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${tipo} border-0`;
    el.setAttribute('role', 'status');
    const wrap = document.createElement('div');
    wrap.className = 'd-flex';
    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = mensaje;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    wrap.append(body, close);
    el.append(wrap);
    $('#toasts').append(el);
    const t = new bootstrap.Toast(el, { delay: 4000 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }

  // ---------------------------------------------------------------------
  // Badges
  // ---------------------------------------------------------------------
  const badgeEstado = (e) => {
    if (e === 'PENDIENTE') return '<span class="badge badge-pendiente">PENDIENTE</span>';
    const cls = { 'ABIERTO': 'primary', 'EN CURSO': 'info text-dark', 'CERRADO': 'success' }[e] || 'secondary';
    return `<span class="badge text-bg-${cls}">${esc(e)}</span>`;
  };
  const badgePrioridad = (p) => {
    const cls = { Alta: 'danger', Media: 'warning text-dark', Baja: 'secondary' }[p] || 'secondary';
    return `<span class="badge text-bg-${cls}">${esc(p)}</span>`;
  };
  const badgeSla = (sla) => {
    if (sla === 'VENCIDO') return '<span class="badge text-bg-danger">Vencido</span>';
    if (sla === 'POR_VENCER') return '<span class="badge text-bg-warning text-dark">Por vencer</span>';
    if (sla === 'EN_TIEMPO') return '<span class="badge text-bg-light border text-success">En tiempo</span>';
    return '<span class="text-muted">—</span>';
  };

  // ---------------------------------------------------------------------
  // Gestión de Autenticación y Sesión (RBAC)
  // ---------------------------------------------------------------------
  const modalLogin = () => bootstrap.Modal.getOrCreateInstance($('#modal-login'));

  function mostrarLogin() {
    $('#login-alerta').classList.add('d-none');
    $('#form-login').reset();
    modalLogin().show();
  }

  async function login(username, password) {
    const alerta = $('#login-alerta');
    alerta.classList.add('d-none');
    const btn = $('#btn-login-submit');
    btn.disabled = true;
    try {
      const { data } = await api('POST', '/auth/login', { username, password });
      localStorage.setItem('soporte_token', data.token);
      state.usuario = data.usuario;
      modalLogin().hide();
      actualizarWidgetUsuario();
      toast(`Bienvenido, ${data.usuario.nombre} (${data.usuario.rol})`);
      state.page = 1;
      await refrescar();
    } catch (e) {
      alerta.textContent = e.message;
      alerta.classList.remove('d-none');
    } finally {
      btn.disabled = false;
    }
  }

  async function logout() {
    try {
      await api('POST', '/auth/logout');
    } catch (_) {
      // Ignorar error al cerrar sesión
    }
    localStorage.removeItem('soporte_token');
    state.usuario = null;
    actualizarWidgetUsuario();
    toast('Sesión cerrada');
    mostrarLogin();
  }

  function actualizarWidgetUsuario() {
    const widget = $('#nav-user-widget');
    const btnLogin = $('#btn-login-trigger');
    const btnNuevo = $('#btn-nuevo');
    if (!state.usuario) {
      widget.classList.add('d-none');
      btnLogin.classList.remove('d-none');
      btnNuevo.classList.add('d-none');
      return;
    }
    widget.classList.remove('d-none');
    btnLogin.classList.add('d-none');
    btnNuevo.classList.remove('d-none');

    $('#nav-user-name').textContent = state.usuario.nombre;
    const badgeRole = $('#nav-user-role');
    badgeRole.textContent = state.usuario.rol;
    badgeRole.className = 'badge ' + (
      state.usuario.rol === 'ADMIN' ? 'badge-rol-admin' :
      state.usuario.rol === 'TECNICO' ? 'badge-rol-tecnico' : 'badge-rol-solicitante'
    );
  }

  async function verificarSesion() {
    const token = localStorage.getItem('soporte_token');
    if (!token) {
      mostrarLogin();
      return;
    }
    try {
      const { data } = await api('GET', '/auth/me');
      state.usuario = data;
      actualizarWidgetUsuario();
      await refrescar();
    } catch (e) {
      localStorage.removeItem('soporte_token');
      mostrarLogin();
    }
  }

  // ---------------------------------------------------------------------
  // Tablero resumen
  // ---------------------------------------------------------------------
  async function cargarResumen() {
    const { data } = await api('GET', '/resumen');
    const kpi = (titulo, n, clase, filtro) => `
      <div class="col-6 col-lg">
        <div class="card kpi ${clase} shadow-sm h-100 ${filtro ? 'kpi-clic' : ''}" ${filtro ? `data-filtro-estado="${esc(filtro)}" role="button" tabindex="0"` : ''}>
          <div class="card-body py-2">
            <div class="text-muted small">${esc(titulo)}</div>
            <div class="n">${esc(n)}</div>
          </div>
        </div>
      </div>`;
    $('#tablero-estados').innerHTML =
      kpi('Total', data.total, '', '') +
      kpi('Abiertos', data.por_estado['ABIERTO'] || 0, 'kpi-abierto', 'ABIERTO') +
      kpi('En curso', data.por_estado['EN CURSO'] || 0, 'kpi-curso', 'EN CURSO') +
      kpi('Pendientes', data.por_estado['PENDIENTE'] || 0, 'kpi-pendiente', 'PENDIENTE') +
      kpi('Cerrados', data.por_estado['CERRADO'] || 0, 'kpi-cerrado', 'CERRADO') +
      kpi('Vencidos', data.vencidos, 'kpi-vencido', '');

    $('#tablero-tecnicos').innerHTML = `<div class="row g-3">${data.carga_tecnicos.map((t) => {
      const pct = Math.min(100, Math.round((t.activos / t.maximo) * 100));
      const color = t.activos >= t.maximo ? 'bg-danger' : (t.activos >= t.maximo - 1 ? 'bg-warning' : 'bg-success');
      return `<div class="col-12 col-md-4">
        <div class="d-flex justify-content-between small"><span>${esc(t.nombre)}</span><strong>${esc(t.activos)} / ${esc(t.maximo)}</strong></div>
        <div class="progress" role="progressbar" aria-label="Carga de ${esc(t.nombre)}" aria-valuenow="${esc(t.activos)}" aria-valuemin="0" aria-valuemax="${esc(t.maximo)}">
          <div class="progress-bar ${color}" style="width:${pct}%"></div>
        </div></div>`;
    }).join('')}</div>`;
  }

  // ---------------------------------------------------------------------
  // Tabla con filtros y paginación del lado del servidor
  // ---------------------------------------------------------------------
  async function cargarTabla() {
    const id = ++state.reqTabla;
    const p = new URLSearchParams({ page: state.page, per_page: POR_PAGINA });
    const f = state.filtros;
    if (f.estado) p.set('estado', f.estado);
    if (f.prioridad) p.set('prioridad', f.prioridad);
    if (f.tecnico) p.set('tecnico', f.tecnico);
    if (f.q.trim()) p.set('q', f.q.trim());

    const res = await api('GET', '/tickets?' + p.toString());
    if (id !== state.reqTabla) return; // llegó una respuesta más nueva

    const { data, meta } = res;
    const paginas = Math.max(1, Math.ceil(meta.total / meta.per_page));
    if (meta.page > paginas) { state.page = paginas; return cargarTabla(); }

    $('#tbody').innerHTML = data.length
      ? data.map((t) => `
        <tr data-numero="${esc(t.numero)}" tabindex="0">
          <td class="fw-semibold">${esc(t.numero)}</td>
          <td>${esc(t.usuario)}</td>
          <td>${esc(t.categoria)}</td>
          <td>${badgePrioridad(t.prioridad)}</td>
          <td>${esc(t.tecnico)}</td>
          <td>${badgeEstado(t.estado)}</td>
          <td>${esc(fmtFecha(t.creado))}</td>
          <td title="Vence: ${esc(fmtFecha(t.vence))}">${badgeSla(t.sla)}</td>
        </tr>`).join('')
      : '<tr><td colspan="8" class="text-center text-muted py-4">No hay tickets que coincidan con los filtros.</td></tr>';

    const desde = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1;
    const hasta = Math.min(meta.total, meta.page * meta.per_page);
    $('#paginacion-info').textContent = `Mostrando ${desde}–${hasta} de ${meta.total} tickets`;
    renderPaginacion(meta.page, paginas);
  }

  function renderPaginacion(actual, total) {
    const item = (label, page, { activo = false, deshabilitado = false } = {}) =>
      `<li class="page-item ${activo ? 'active' : ''} ${deshabilitado ? 'disabled' : ''}">
         <button class="page-link" type="button" data-page="${esc(page)}" ${deshabilitado ? 'disabled' : ''}>${esc(label)}</button></li>`;
    let html = item('«', actual - 1, { deshabilitado: actual <= 1 });
    const ini = Math.max(1, actual - 2);
    const fin = Math.min(total, actual + 2);
    if (ini > 1) html += item('1', 1) + (ini > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
    for (let i = ini; i <= fin; i++) html += item(i, i, { activo: i === actual });
    if (fin < total) html += (fin < total - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') + item(total, total);
    html += item('»', actual + 1, { deshabilitado: actual >= total });
    $('#paginacion').innerHTML = html;
  }

  /** Refresca tablero y tabla juntos. */
  async function refrescar() {
    try {
      await Promise.all([cargarResumen(), cargarTabla()]);
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  // ---------------------------------------------------------------------
  // Filtros
  // ---------------------------------------------------------------------
  function aplicarFiltros() { state.page = 1; cargarTabla().catch((e) => toast(e.message, 'danger')); }

  function initFiltros() {
    $('#flt-estado').addEventListener('change', (e) => { state.filtros.estado = e.target.value; aplicarFiltros(); });
    $('#flt-prioridad').addEventListener('change', (e) => { state.filtros.prioridad = e.target.value; aplicarFiltros(); });
    $('#flt-tecnico').addEventListener('change', (e) => { state.filtros.tecnico = e.target.value; aplicarFiltros(); });
    $('#flt-q').addEventListener('input', debounce((e) => { state.filtros.q = e.target.value; aplicarFiltros(); }, 350));
    $('#btn-limpiar').addEventListener('click', () => {
      state.filtros = { estado: '', prioridad: '', tecnico: '', q: '' };
      ['#flt-estado', '#flt-prioridad', '#flt-tecnico', '#flt-q'].forEach((s) => { $(s).value = ''; });
      aplicarFiltros();
    });
    $('#paginacion').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-page]');
      if (!b || b.disabled) return;
      state.page = Number(b.dataset.page);
      cargarTabla().catch((err) => toast(err.message, 'danger'));
    });
    // Clic en los KPI de estado = filtrar por ese estado
    $('#tablero-estados').addEventListener('click', (e) => {
      const k = e.target.closest('[data-filtro-estado]');
      if (!k) return;
      state.filtros.estado = k.dataset.filtroEstado;
      $('#flt-estado').value = state.filtros.estado;
      aplicarFiltros();
    });
    const abrir = (e) => {
      const tr = e.target.closest('tr[data-numero]');
      if (tr && (e.type === 'click' || e.key === 'Enter')) abrirDetalle(tr.dataset.numero);
    };
    $('#tbody').addEventListener('click', abrir);
    $('#tbody').addEventListener('keydown', abrir);
  }

  async function cargarFiltroTecnicos() {
    const { data } = await api('GET', '/tecnicos?todos=1');
    $('#flt-tecnico').innerHTML = '<option value="">Todos</option>' +
      data.map((t) => `<option value="${esc(t.id)}">${esc(t.nombre)}${t.activo ? '' : ' (inactivo)'}</option>`).join('');
  }

  // ---------------------------------------------------------------------
  // Modal: nuevo ticket
  // ---------------------------------------------------------------------
  const modalNuevo = () => bootstrap.Modal.getOrCreateInstance($('#modal-nuevo'));
  const CAMPOS = {
    codigo_usuario: '#f-codigo', categoria_id: '#f-categoria', prioridad: '#f-prioridad',
    descripcion: '#f-descripcion', tecnico_id: '#f-tecnico',
  };
  const SLA_HORAS = { Alta: 4, Media: 24, Baja: 72 };

  function limpiarErrores(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach((el) => { el.textContent = ''; });
    const alerta = $('#nuevo-alerta');
    alerta.classList.add('d-none');
    alerta.textContent = '';
  }

  function marcarError(selector, mensaje) {
    const el = $(selector);
    el.classList.add('is-invalid');
    const fb = el.parentElement.querySelector('.invalid-feedback');
    if (fb) fb.textContent = mensaje;
  }

  function pintarTecnicos(select, listaDiv, { excluirId = null, sugerir = true } = {}) {
    const lista = state.tecnicos;
    const sugerido = sugerir
      ? [...lista].filter((t) => t.disponible && t.id !== excluirId)
          .sort((a, b) => a.tickets_activos - b.tickets_activos || a.nombre.localeCompare(b.nombre))[0]
      : null;
    select.innerHTML = lista.map((t) => {
      const lleno = !t.disponible;
      const actual = t.id === excluirId;
      return `<option value="${esc(t.id)}" ${lleno || actual ? 'disabled' : ''} ${sugerido && sugerido.id === t.id ? 'selected' : ''}>
        ${esc(t.nombre)} — ${esc(t.tickets_activos)}/${esc(t.maximo)} activos${lleno ? ' (sin cupo)' : ''}${actual ? ' (actual)' : ''}${sugerido && sugerido.id === t.id ? ' ★ sugerido' : ''}
      </option>`;
    }).join('');
    const mostrar = () => {
      const t = lista.find((x) => String(x.id) === select.value);
      if (!t) { listaDiv.textContent = ''; return; }
      listaDiv.innerHTML = t.tickets.length
        ? `Tickets abiertos o en curso de ${esc(t.nombre)}: ` + t.tickets.map((k) =>
            `<span class="badge text-bg-light border">${esc(k.numero)} · ${esc(k.estado)}</span>`).join(' ')
        : `${esc(t.nombre)} no tiene tickets activos.`;
    };
    select.onchange = mostrar;
    mostrar();
  }

  async function abrirNuevo() {
    const form = $('#form-nuevo');
    form.reset();
    limpiarErrores(form);
    state.usuarioValido = false;
    $('#usuario-info').textContent = 'Escribe un código para buscar…';
    $('#usuario-info').className = 'border rounded p-2 bg-white small';
    actualizarContador();
    actualizarAyudaSla();
    try {
      const [cats, tecs] = await Promise.all([
        state.categorias.length ? { data: state.categorias } : api('GET', '/categorias'),
        api('GET', '/tecnicos'),
      ]);
      state.categorias = cats.data;
      state.tecnicos = tecs.data;
      $('#f-categoria').innerHTML = '<option value="">Selecciona…</option>' +
        state.categorias.map((c) => `<option value="${esc(c.id)}">${esc(c.nombre)}</option>`).join('');
      pintarTecnicos($('#f-tecnico'), $('#tecnico-tickets'));
      if (state.usuario && state.usuario.rol === 'SOLICITANTE' && state.usuario.usuario_codigo) {
        $('#f-codigo').value = state.usuario.usuario_codigo;
        $('#f-codigo').readOnly = true;
        buscarUsuario();
      } else {
        $('#f-codigo').readOnly = false;
      }
      modalNuevo().show();
      setTimeout(() => $('#f-codigo').focus(), 300);
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  function actualizarContador() {
    const n = $('#f-descripcion').value.trim().length;
    const c = $('#desc-contador');
    c.textContent = `${n} / 1000 (mín. 20)`;
    c.className = 'form-text text-end ' + (n > 0 && n < 20 ? 'text-danger' : '');
  }

  function actualizarAyudaSla() {
    const p = $('#f-prioridad').value;
    $('#sla-ayuda').textContent = `SLA: solución en máximo ${SLA_HORAS[p]} horas.`;
  }

  const buscarUsuario = debounce(async () => {
    const codigo = $('#f-codigo').value.trim();
    const info = $('#usuario-info');
    state.usuarioValido = false;
    $('#f-codigo').classList.remove('is-invalid');
    if (!codigo) { info.textContent = 'Escribe un código para buscar…'; info.className = 'border rounded p-2 bg-white small'; return; }
    try {
      const { data } = await api('GET', '/usuarios/' + encodeURIComponent(codigo));
      if ($('#f-codigo').value.trim() !== codigo) return; // el usuario siguió escribiendo
      if (!data.activo) {
        info.innerHTML = `<span class="text-danger fw-semibold">Usuario inactivo:</span> ${esc(data.nombre)}. No se pueden registrar tickets.`;
        info.className = 'border border-danger rounded p-2 bg-white small';
        return;
      }
      state.usuarioValido = true;
      info.innerHTML = `<strong>${esc(data.nombre)}</strong><br><span class="text-muted">${esc(data.cargo)} · ${esc(data.area)}</span>`;
      info.className = 'border border-success rounded p-2 bg-white small';
    } catch (e) {
      if (e.status === 404) {
        info.innerHTML = '<span class="text-danger fw-semibold">El código no existe.</span>';
        info.className = 'border border-danger rounded p-2 bg-white small';
      } else {
        info.textContent = e.message;
      }
    }
  }, 400);

  async function guardarTicket(ev) {
    ev.preventDefault();
    const form = ev.currentTarget;
    limpiarErrores(form);
    const btn = $('#btn-guardar');
    const cuerpo = {
      codigo_usuario: $('#f-codigo').value.trim(),
      categoria_id: $('#f-categoria').value ? Number($('#f-categoria').value) : null,
      descripcion: $('#f-descripcion').value,
      prioridad: $('#f-prioridad').value,
      tecnico_id: $('#f-tecnico').value ? Number($('#f-tecnico').value) : null,
    };
    btn.disabled = true;
    try {
      const { data } = await api('POST', '/tickets', cuerpo);
      modalNuevo().hide();
      toast(`Ticket ${data.numero} registrado`);
      state.page = 1;
      await refrescar();
    } catch (e) {
      if (e.status === 422 || e.status === 409) {
        Object.entries(e.fields).forEach(([campo, msg]) => CAMPOS[campo] && marcarError(CAMPOS[campo], msg));
        const alerta = $('#nuevo-alerta');
        alerta.textContent = e.message;
        alerta.classList.remove('d-none');
        if (e.code === 'TECNICO_CARGA_MAXIMA') {
          api('GET', '/tecnicos').then((r) => { state.tecnicos = r.data; pintarTecnicos($('#f-tecnico'), $('#tecnico-tickets')); }).catch(() => {});
        }
      } else {
        toast(e.message, 'danger');
      }
    } finally {
      btn.disabled = false;
    }
  }

  function initNuevo() {
    $('#btn-nuevo').addEventListener('click', abrirNuevo);
    $('#form-nuevo').addEventListener('submit', guardarTicket);
    $('#f-codigo').addEventListener('input', buscarUsuario);
    $('#f-descripcion').addEventListener('input', actualizarContador);
    $('#f-prioridad').addEventListener('change', actualizarAyudaSla);
  }

  // ---------------------------------------------------------------------
  // Modal: detalle, cambio de estado y reasignación
  // ---------------------------------------------------------------------
  const modalDetalle = () => bootstrap.Modal.getOrCreateInstance($('#modal-detalle'));

  async function abrirDetalle(numero) {
    state.ticketAbierto = numero;
    $('#detalle-cuerpo').innerHTML = '<div class="text-muted">Cargando…</div>';
    modalDetalle().show();
    try {
      const [{ data }, tecs] = await Promise.all([api('GET', '/tickets/' + encodeURIComponent(numero)), api('GET', '/tecnicos')]);
      state.tecnicos = tecs.data;
      renderDetalle(data);
    } catch (e) {
      $('#detalle-cuerpo').innerHTML = `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`;
    }
  }

  function renderDetalle(t) {
    const hist = t.historial.map((h) => {
      let titulo;
      if (h.tipo === 'CREACION') titulo = `Ticket creado (asignado a ${esc(h.tecnico_nuevo ?? t.tecnico)})`;
      else if (h.tipo === 'REASIGNACION') titulo = `Reasignado: ${esc(h.tecnico_anterior)} → ${esc(h.tecnico_nuevo)}`;
      else titulo = `${esc(h.estado_anterior)} → ${esc(h.estado_nuevo)}`;
      return `<div class="item">
        <div class="fw-semibold">${titulo}</div>
        <div class="small text-muted">${esc(fmtFecha(h.fecha))}${h.tipo !== 'CREACION' ? ` · ${esc(h.estado_anterior)} → ${esc(h.estado_nuevo)}` : ''}</div>
        ${h.observacion ? `<div class="small">${esc(h.observacion)}</div>` : ''}
      </div>`;
    }).join('');

    const cerrado = t.estado === 'CERRADO';
    let acciones = '';

    if (cerrado) {
      acciones = '<div class="alert alert-secondary mb-0">Este ticket está CERRADO y es de solo lectura.</div>';
    } else if (state.usuario && state.usuario.rol === 'SOLICITANTE') {
      acciones = '<div class="alert alert-info small mb-0">ℹ️ <strong>Vista de seguimiento:</strong> Como solicitante puedes consultar el detalle e historial de tu ticket. Los cambios de estado los realiza el equipo técnico.</div>';
    } else if (state.usuario && state.usuario.rol === 'TECNICO' && state.usuario.tecnico_id !== t.tecnico_id) {
      acciones = '<div class="alert alert-secondary small mb-0">🔒 <strong>Asignado a otro técnico:</strong> Solo el técnico responsable o un administrador puede gestionar este ticket.</div>';
    } else {
      const transiciones = {
        'ABIERTO': ['EN CURSO'],
        'EN CURSO': ['PENDIENTE', 'CERRADO'],
        'PENDIENTE': ['EN CURSO', 'CERRADO'],
      }[t.estado] || [];

      const formEstadoHtml = transiciones.length ? `
      <form id="form-estado" class="border rounded p-3 mb-3 bg-white" novalidate>
        <div class="fw-semibold mb-2">Cambiar estado</div>
        <div class="row g-2">
          <div class="col-md-4">
            <select id="e-estado" class="form-select form-select-sm" aria-label="Nuevo estado">
              ${transiciones.map((s) => `<option value="${esc(s)}">${esc(s)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-8"><input id="e-obs" class="form-control form-control-sm" maxlength="1000" placeholder="Observación (opcional)"></div>
          <div class="col-12 d-none" id="e-solucion-wrap">
            <textarea id="e-solucion" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Solución aplicada (obligatoria para cerrar, mín. 10 caracteres)"></textarea>
          </div>
          <div class="col-12 d-grid d-md-block"><button class="btn btn-sm btn-primary" type="submit">Aplicar cambio de estado</button></div>
        </div>
      </form>` : '';

      const esAdmin = !state.usuario || state.usuario.rol === 'ADMIN';
      const formTecnicoHtml = esAdmin ? `
      <form id="form-tecnico" class="border rounded p-3 bg-white" novalidate>
        <div class="fw-semibold mb-2">Reasignar técnico <span class="badge bg-secondary ms-1">Solo Admin</span></div>
        <div class="row g-2">
          <div class="col-md-5"><select id="r-tecnico" class="form-select form-select-sm" aria-label="Nuevo técnico"></select></div>
          <div class="col-md-7"><input id="r-obs" class="form-control form-control-sm" maxlength="1000" placeholder="Observación (opcional)"></div>
          <div class="col-12 form-text" id="r-lista"></div>
          <div class="col-12 d-grid d-md-block"><button class="btn btn-sm btn-outline-primary" type="submit">Reasignar</button></div>
        </div>
      </form>` : '';

      acciones = `
      <div id="detalle-alerta" class="alert alert-danger d-none" role="alert"></div>
      ${formEstadoHtml}
      ${formTecnicoHtml}`;
    }

    $('#modal-detalle-t').textContent = `Ticket ${t.numero}`;
    $('#detalle-cuerpo').innerHTML = `
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            ${badgeEstado(t.estado)} ${badgePrioridad(t.prioridad)} ${badgeSla(t.sla)}
            <span class="text-muted small">${esc(t.categoria)}</span>
          </div>
          <dl class="row small mb-3">
            <dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9">${esc(t.solicitante.nombre)} <span class="text-muted">(${esc(t.solicitante.codigo)} · ${esc(t.solicitante.cargo)} · ${esc(t.solicitante.area)})</span></dd>
            <dt class="col-sm-3">Técnico</dt><dd class="col-sm-9">${esc(t.tecnico)}</dd>
            <dt class="col-sm-3">Creado</dt><dd class="col-sm-9">${esc(fmtFecha(t.creado))}</dd>
            <dt class="col-sm-3">Vence (SLA)</dt><dd class="col-sm-9">${esc(fmtFecha(t.vence))}</dd>
            ${t.cerrado ? `<dt class="col-sm-3">Cerrado</dt><dd class="col-sm-9">${esc(fmtFecha(t.cerrado))}</dd>` : ''}
          </dl>
          <div class="fw-semibold small">Descripción</div>
          <p class="border rounded p-2 bg-white small" style="white-space:pre-wrap">${esc(t.descripcion)}</p>
          ${t.solucion ? `<div class="fw-semibold small">Solución aplicada</div><p class="border rounded p-2 bg-white small" style="white-space:pre-wrap">${esc(t.solucion)}</p>` : ''}
          ${acciones}
        </div>
        <div class="col-lg-5">
          <div class="fw-semibold mb-2">Historial</div>
          <div class="historial">${hist}</div>
        </div>
      </div>`;

    // Tras repintar, el foco se pierde (el botón pulsado ya no existe): lo devolvemos al modal
    // para que Escape y el teclado sigan funcionando.
    $('#detalle-cuerpo').focus({ preventScroll: true });

    const formEstado = $('#form-estado');
    const formTecnico = $('#form-tecnico');

    if (formEstado || formTecnico) {
      const alerta = (msg) => { const a = $('#detalle-alerta'); if (a) { a.textContent = msg; a.classList.toggle('d-none', !msg); } };

      if (formEstado) {
        const sel = $('#e-estado');
        const toggleCampos = () => {
          const val = sel.value;
          $('#e-solucion-wrap').classList.toggle('d-none', val !== 'CERRADO');
          if (val === 'PENDIENTE') {
            $('#e-obs').placeholder = 'Motivo de pausa / pendiente (obligatorio, mín. 5 caracteres)';
          } else {
            $('#e-obs').placeholder = 'Observación (opcional)';
          }
        };
        sel.addEventListener('change', toggleCampos);
        toggleCampos();

        formEstado.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          alerta('');
          const body = { estado: sel.value, observacion: $('#e-obs').value };
          if (sel.value === 'CERRADO') body.solucion = $('#e-solucion').value;
          try {
            const { data } = await api('PATCH', `/tickets/${encodeURIComponent(t.numero)}/estado`, body);
            toast(`Ticket ${data.numero}: ahora está ${data.estado}`);
            renderDetalle(data);
            refrescar();
          } catch (e) {
            alerta(e.fields && e.fields.solucion ? e.fields.solucion : (e.fields && e.fields.observacion ? e.fields.observacion : e.message));
          }
        });
      }

      if (formTecnico) {
        pintarTecnicos($('#r-tecnico'), $('#r-lista'), { excluirId: t.tecnico_id, sugerir: true });

        // ── Validación proactiva: verificar si hay técnicos con cupo ──────────
        const btnReasignar = formTecnico.querySelector('button[type="submit"]');
        const selTecnico   = $('#r-tecnico');

        /**
         * Evalúa si el técnico seleccionado actualmente tiene cupo y
         * habilita/deshabilita el botón de reasignación en consecuencia.
         * También muestra/oculta el aviso de "todos llenos".
         */
        function evaluarDisponibilidad() {
          // Técnicos candidatos: activos, distintos al actual, con cupo
          const disponibles = state.tecnicos.filter(
            (tec) => tec.activo && tec.id !== t.tecnico_id && tec.disponible
          );

          if (disponibles.length === 0) {
            // No hay ningún técnico con cupo → bloquear completamente
            alerta(
              `⚠️ No es posible reasignar: todos los demás técnicos activos tienen su carga máxima (${state.tecnicos[0]?.maximo ?? 5} tickets activos). Espera a que alguno libere cupo.`
            );
            btnReasignar.disabled = true;
            selTecnico.disabled   = true;
            return;
          }

          // Hay al menos un técnico disponible → evaluar la opción seleccionada
          const tecSeleccionado = state.tecnicos.find((tec) => String(tec.id) === selTecnico.value);
          const seleccionValida = tecSeleccionado && tecSeleccionado.disponible && tecSeleccionado.id !== t.tecnico_id;
          btnReasignar.disabled = !seleccionValida;

          if (!seleccionValida && tecSeleccionado) {
            alerta('El técnico seleccionado no tiene cupo disponible. Elige otro.');
          } else {
            alerta('');
          }
        }

        evaluarDisponibilidad();
        selTecnico.addEventListener('change', evaluarDisponibilidad);

        formTecnico.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          alerta('');
          const id = Number($('#r-tecnico').value);
          if (!id) { alerta('Selecciona un técnico con cupo disponible'); return; }

          // Guardia de último momento: el técnico debe seguir teniendo cupo
          const tecDest = state.tecnicos.find((tec) => tec.id === id);
          if (!tecDest || !tecDest.disponible) {
            alerta(`⚠️ ${tecDest ? tecDest.nombre : 'El técnico'} ya no tiene cupo disponible. Actualiza y elige otro.`);
            api('GET', '/tecnicos').then((r) => {
              state.tecnicos = r.data;
              pintarTecnicos(selTecnico, $('#r-lista'), { excluirId: t.tecnico_id, sugerir: true });
              evaluarDisponibilidad();
            }).catch(() => {});
            return;
          }

          btnReasignar.disabled = true;
          try {
            const { data } = await api('PATCH', `/tickets/${encodeURIComponent(t.numero)}/tecnico`,
              { tecnico_id: id, observacion: $('#r-obs').value });
            toast(`Ticket ${data.numero} reasignado a ${data.tecnico}`);
            const tecs = await api('GET', '/tecnicos');
            state.tecnicos = tecs.data;
            renderDetalle(data);
            refrescar();
          } catch (e) {
            alerta(e.message);
            // Si el error es que todos están llenos, refrescar y bloquear UI
            if (e.code === 'TODOS_TECNICOS_LLENOS' || e.code === 'TECNICO_CARGA_MAXIMA') {
              api('GET', '/tecnicos').then((r) => {
                state.tecnicos = r.data;
                pintarTecnicos(selTecnico, $('#r-lista'), { excluirId: t.tecnico_id, sugerir: true });
                evaluarDisponibilidad();
              }).catch(() => {});
            } else {
              btnReasignar.disabled = false;
            }
          }
        });
      }
    }
  }

  function initAuth() {
    $('#form-login').addEventListener('submit', (ev) => {
      ev.preventDefault();
      const u = $('#login-usuario').value.trim();
      const p = $('#login-password').value;
      if (!u || !p) return;
      login(u, p);
    });

    document.querySelectorAll('.btn-demo-login').forEach((btn) => {
      btn.addEventListener('click', () => {
        const u = btn.dataset.user;
        const p = btn.dataset.pass;
        $('#login-usuario').value = u;
        $('#login-password').value = p;
        login(u, p);
      });
    });

    $('#btn-logout').addEventListener('click', logout);
    $('#btn-login-trigger').addEventListener('click', mostrarLogin);
  }

  // ---------------------------------------------------------------------
  // Arranque
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', async () => {
    initAuth();
    initFiltros();
    initNuevo();
    try {
      await cargarFiltroTecnicos();
    } catch (e) {
      toast(e.message, 'danger');
    }
    await verificarSesion();
  });
})();
