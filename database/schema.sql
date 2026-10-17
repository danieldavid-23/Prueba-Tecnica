-- =====================================================================
-- Esquema: Mesa de Soporte TI  (MariaDB 10.4+ / MySQL 8 - XAMPP)
-- Es re-ejecutable: elimina y vuelve a crear la base de datos completa.
-- =====================================================================
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS soporte_ti;
CREATE DATABASE soporte_ti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE soporte_ti;

CREATE TABLE categorias (
    id     INT          NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id     INT          NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(20)  NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    cargo  VARCHAR(100) NOT NULL,
    area   VARCHAR(100) NOT NULL,
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_codigo (codigo),
    KEY idx_usuarios_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE tecnicos (
    id     INT          NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    email  VARCHAR(150) NOT NULL,
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tecnicos_email (email)
) ENGINE=InnoDB;

-- Cuentas de usuario para autenticación y control de acceso basado en roles (RBAC).
-- Roles disponibles:
--  * ADMIN: Administrador/Coordinador de TI (control total, reasignación, cambio de estado, métricas).
--  * TECNICO: Técnico asignado (gestiona sus propios tickets, pasa a EN CURSO, PENDIENTE o CERRADO).
--  * SOLICITANTE: Empleado que genera requerimientos (crea tickets para sí mismo y consulta su avance).
CREATE TABLE cuentas (
    id            INT          NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           VARCHAR(20)  NOT NULL,
    usuario_id    INT          NULL,
    tecnico_id    INT          NULL,
    nombre        VARCHAR(120) NOT NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cuentas_username (username),
    UNIQUE KEY uq_cuentas_email (email),
    KEY idx_cuentas_rol (rol),
    CONSTRAINT fk_cuentas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT fk_cuentas_tecnico FOREIGN KEY (tecnico_id) REFERENCES tecnicos (id) ON DELETE SET NULL,
    CONSTRAINT ck_cuentas_rol CHECK (rol IN ('ADMIN','TECNICO','SOLICITANTE'))
) ENGINE=InnoDB;

-- Sesiones activas para tokens de autenticación Bearer
CREATE TABLE sesiones (
    id         VARCHAR(64) NOT NULL,
    cuenta_id  INT         NOT NULL,
    expira_en  DATETIME    NOT NULL,
    creado_en  DATETIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sesiones_cuenta (cuenta_id),
    KEY idx_sesiones_expira (expira_en),
    CONSTRAINT fk_sesiones_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de consecutivos: una fila por año. Se bloquea con SELECT ... FOR UPDATE dentro de la
-- transacción de creación, lo que garantiza números únicos aunque dos usuarios registren a la vez.
CREATE TABLE consecutivos (
    anio   SMALLINT NOT NULL,
    ultimo INT      NOT NULL DEFAULT 0,
    PRIMARY KEY (anio),
    CONSTRAINT ck_consecutivos_ultimo CHECK (ultimo >= 0)
) ENGINE=InnoDB;

-- Las fechas se guardan como DATETIME en la hora local de la aplicación (America/Cancun, -05:00).
CREATE TABLE tickets (
    id             BIGINT        NOT NULL AUTO_INCREMENT,
    numero         VARCHAR(14)   NOT NULL,
    usuario_id     INT           NOT NULL,
    categoria_id   INT           NOT NULL,
    tecnico_id     INT           NOT NULL,
    descripcion    VARCHAR(1000) NOT NULL,
    prioridad      VARCHAR(5)    NOT NULL,
    estado         VARCHAR(12)   NOT NULL DEFAULT 'ABIERTO',
    solucion       TEXT          NULL,
    creado_en      DATETIME      NOT NULL,
    fecha_limite   DATETIME      NOT NULL,
    actualizado_en DATETIME      NOT NULL,
    cerrado_en     DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_numero (numero),
    -- Índices pensados para los filtros y el tablero
    KEY idx_tickets_estado (estado),
    KEY idx_tickets_prioridad (prioridad),
    KEY idx_tickets_tecnico_estado (tecnico_id, estado),
    KEY idx_tickets_usuario (usuario_id),
    KEY idx_tickets_creado (creado_en, id),
    KEY idx_tickets_vencimiento (estado, fecha_limite),
    CONSTRAINT fk_tickets_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id),
    CONSTRAINT fk_tickets_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
    CONSTRAINT fk_tickets_tecnico   FOREIGN KEY (tecnico_id)   REFERENCES tecnicos (id),
    CONSTRAINT ck_tickets_numero    CHECK (numero REGEXP '^TK-[0-9]{4}-[0-9]{6}$'),
    CONSTRAINT ck_tickets_prioridad CHECK (prioridad IN ('Alta','Media','Baja')),
    CONSTRAINT ck_tickets_estado    CHECK (estado IN ('ABIERTO','EN CURSO','PENDIENTE','CERRADO')),
    CONSTRAINT ck_tickets_descr     CHECK (CHAR_LENGTH(TRIM(descripcion)) >= 20),
    CONSTRAINT ck_tickets_limite    CHECK (fecha_limite > creado_en),
    CONSTRAINT ck_tickets_cierre    CHECK (estado <> 'CERRADO' OR (solucion IS NOT NULL AND cerrado_en IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE ticket_historial (
    id                  BIGINT       NOT NULL AUTO_INCREMENT,
    ticket_id           BIGINT       NOT NULL,
    tipo                VARCHAR(15)  NOT NULL,
    estado_anterior     VARCHAR(12)  NULL,
    estado_nuevo        VARCHAR(12)  NOT NULL,
    tecnico_anterior_id INT          NULL,
    tecnico_nuevo_id    INT          NULL,
    observacion         VARCHAR(1000) NULL,
    creado_en           DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_historial_ticket (ticket_id, creado_en, id),
    CONSTRAINT fk_hist_ticket   FOREIGN KEY (ticket_id)           REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_tec_ant  FOREIGN KEY (tecnico_anterior_id) REFERENCES tecnicos (id),
    CONSTRAINT fk_hist_tec_nuevo FOREIGN KEY (tecnico_nuevo_id)   REFERENCES tecnicos (id),
    CONSTRAINT ck_hist_tipo     CHECK (tipo IN ('CREACION','ESTADO','REASIGNACION')),
    CONSTRAINT ck_hist_est_ant  CHECK (estado_anterior IS NULL OR estado_anterior IN ('ABIERTO','EN CURSO','PENDIENTE','CERRADO')),
    CONSTRAINT ck_hist_est_nuevo CHECK (estado_nuevo IN ('ABIERTO','EN CURSO','PENDIENTE','CERRADO'))
) ENGINE=InnoDB;
