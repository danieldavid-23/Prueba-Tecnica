<?php
/** @var string $apiKey  Clave inyectada por Kernel::spa() para que la SPA consuma la API. */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-key" content="<?= htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') ?>">
  <title>Mesa de Soporte TI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/main.css" rel="stylesheet">
  <link href="/css/layout.css" rel="stylesheet">
  <link href="/css/components.css" rel="stylesheet">
  <link href="/css/responsive.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container-xl d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <span class="navbar-brand mb-0 h1 fs-5">🛠️ Mesa de Soporte TI</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <!-- Info de usuario logueado -->
      <div id="nav-user-widget" class="d-none d-flex align-items-center gap-2 text-white small">
        <span class="text-white-50">👤</span>
        <span id="nav-user-name" class="fw-semibold"></span>
        <span id="nav-user-role" class="badge"></span>
        <button class="btn btn-outline-light btn-sm ms-2 py-0 px-2" id="btn-logout" type="button" title="Cerrar sesión">Salir</button>
      </div>
      <!-- Botón de login si no hay sesión -->
      <button class="btn btn-outline-light btn-sm d-none" id="btn-login-trigger" type="button">Iniciar sesión</button>
      <button class="btn btn-primary btn-sm px-3" id="btn-nuevo" type="button">+ Nuevo ticket</button>
    </div>
  </div>
</nav>

<main class="container-xl pb-5">
  <!-- Tablero resumen -->
  <section aria-label="Resumen" class="mb-4">
    <div class="row g-3" id="tablero-estados"></div>
    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white fw-semibold">Carga activa por técnico <span class="text-muted fw-normal">(máx. 5 tickets activos)</span></div>
      <div class="card-body" id="tablero-tecnicos"><div class="text-muted">Cargando…</div></div>
    </div>
  </section>

  <!-- Filtros -->
  <section class="card shadow-sm mb-3" aria-label="Filtros">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1" for="flt-estado">Estado</label>
          <select id="flt-estado" class="form-select form-select-sm">
            <option value="">Todos</option><option>ABIERTO</option><option>EN CURSO</option><option>PENDIENTE</option><option>CERRADO</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1" for="flt-prioridad">Prioridad</label>
          <select id="flt-prioridad" class="form-select form-select-sm">
            <option value="">Todas</option><option>Alta</option><option>Media</option><option>Baja</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1" for="flt-tecnico">Técnico</label>
          <select id="flt-tecnico" class="form-select form-select-sm"><option value="">Todos</option></select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1" for="flt-q">Buscar (número o usuario)</label>
          <input id="flt-q" type="search" class="form-control form-control-sm" placeholder="TK-2026-… o nombre" maxlength="100" autocomplete="off">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button id="btn-limpiar" class="btn btn-outline-secondary btn-sm" type="button">Limpiar filtros</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Tabla -->
  <section class="card shadow-sm" aria-label="Tickets">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="tabla-tickets">
        <thead class="table-light">
          <tr><th>Número</th><th>Usuario</th><th>Categoría</th><th>Prioridad</th><th>Técnico</th><th>Estado</th><th>Creado</th><th>SLA</th></tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted" id="paginacion-info"></small>
      <nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0" id="paginacion"></ul></nav>
    </div>
  </section>
</main>

<!-- Modal: nuevo ticket -->
<div class="modal fade" id="modal-nuevo" tabindex="-1" aria-labelledby="modal-nuevo-t" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" id="form-nuevo" novalidate>
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="modal-nuevo-t">Registrar ticket</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="nuevo-alerta" role="alert"></div>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label" for="f-codigo">Código del usuario solicitante</label>
            <input class="form-control" id="f-codigo" maxlength="20" autocomplete="off" placeholder="Ej. U001">
            <div class="invalid-feedback"></div>
          </div>
          <div class="col-md-7">
            <div class="form-label">Solicitante</div>
            <div class="border rounded p-2 bg-white small" id="usuario-info" aria-live="polite">Escribe un código para buscar…</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-categoria">Categoría</label>
            <select class="form-select" id="f-categoria"></select>
            <div class="invalid-feedback"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-prioridad">Prioridad</label>
            <select class="form-select" id="f-prioridad">
              <option>Alta</option><option selected>Media</option><option>Baja</option>
            </select>
            <div class="form-text" id="sla-ayuda"></div>
            <div class="invalid-feedback"></div>
          </div>
          <div class="col-12">
            <label class="form-label" for="f-descripcion">Descripción</label>
            <textarea class="form-control" id="f-descripcion" rows="4" maxlength="1000"></textarea>
            <div class="invalid-feedback"></div>
            <div class="form-text text-end" id="desc-contador">0 / 1000 (mín. 20)</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="f-tecnico">Técnico asignado <span class="text-muted small">(sugerido: el de menor carga)</span></label>
            <select class="form-select" id="f-tecnico"></select>
            <div class="invalid-feedback"></div>
            <div class="form-text" id="tecnico-tickets"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-guardar">Registrar ticket</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: detalle -->
<div class="modal fade" id="modal-detalle" tabindex="-1" aria-labelledby="modal-detalle-t" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="modal-detalle-t">Detalle del ticket</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="detalle-cuerpo" tabindex="-1"></div>
    </div>
  </div>
</div>

<!-- Modal: Iniciar sesión -->
<div class="modal fade" id="modal-login" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modal-login-t" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0">
      <div class="modal-header bg-dark text-white border-bottom-0 py-3">
        <h2 class="modal-title fs-5" id="modal-login-t">🔐 Iniciar Sesión · Mesa de Soporte TI</h2>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-danger d-none py-2 small" id="login-alerta" role="alert"></div>
        <form id="form-login" novalidate>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="login-usuario">Usuario o correo</label>
            <input class="form-control" id="login-usuario" placeholder="Ej. admin, ana, laura" autocomplete="username" required>
            <div class="invalid-feedback">Ingresa tu usuario o correo</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="login-password">Contraseña</label>
            <input type="password" class="form-control" id="login-password" placeholder="••••••••" autocomplete="current-password" required>
            <div class="invalid-feedback">Ingresa tu contraseña</div>
          </div>
          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="btn-login-submit">Ingresar al sistema</button>
        </form>

        <hr class="my-3 text-muted">
        <div class="small text-muted mb-2 fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Acceso rápido de prueba para sustentación (1 clic):</div>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-outline-dark btn-sm text-start py-1 px-3 btn-demo-login" data-user="admin" data-pass="admin123">
            🟣 <strong>Administrador:</strong> admin <span class="text-muted">(Control total y reasignación)</span>
          </button>
          <button type="button" class="btn btn-outline-dark btn-sm text-start py-1 px-3 btn-demo-login" data-user="ana" data-pass="ana123">
            🟢 <strong>Técnico:</strong> ana <span class="text-muted">(Atención de tickets y soluciones)</span>
          </button>
          <button type="button" class="btn btn-outline-dark btn-sm text-start py-1 px-3 btn-demo-login" data-user="laura" data-pass="laura123">
            🟠 <strong>Solicitante:</strong> laura <span class="text-muted">(Creación y seguimiento personal)</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>
</body>
</html>
