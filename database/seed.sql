-- =====================================================================
-- Datos semilla (ejecutar DESPUÉS de schema.sql)
--  * 12 usuarios (1 inactivo)  * 4 técnicos (1 inactivo)  * 4 categorías
--  * 25 tickets (12 activos, 13 cerrados) con historial; varios con SLA
--    vencido o por vencer. Las fechas son relativas a la hora de instalación
--    para que el SLA se vea realista (hora local fija -05:00, America/Cancun).
-- =====================================================================
SET NAMES utf8mb4;
USE soporte_ti;
SET time_zone = '-05:00';
SET @ahora = NOW();

INSERT INTO categorias (nombre) VALUES ('Hardware'), ('Software'), ('Red'), ('Otro');

INSERT INTO usuarios (codigo, nombre, cargo, area, activo) VALUES
 ('U001', 'Laura Martínez Ruiz',      'Analista Contable',        'Finanzas',         1),
 ('U002', 'Carlos Hernández Soto',    'Jefe de Ventas',           'Comercial',        1),
 ('U003', 'Mariana López Vega',       'Asistente Administrativa', 'Administración',   1),
 ('U004', 'Jorge Ramírez Pineda',     'Coordinador de Logística', 'Operaciones',      1),
 ('U005', 'Sofía Castillo Mena',      'Ejecutiva de Cuentas',     'Comercial',        1),
 ('U006', 'Andrés Torres Núñez',      'Analista de RRHH',         'Recursos Humanos', 1),
 ('U007', 'Patricia Gómez Ibarra',    'Gerente de Compras',       'Compras',          1),
 ('U008', 'Ricardo Mendoza Cruz',     'Diseñador Gráfico',        'Marketing',        1),
 ('U009', 'Elena Vargas Ortiz',       'Auxiliar Contable',        'Finanzas',         0),
 ('U010', 'Miguel Ángel Rojas Paz',   'Supervisor de Almacén',    'Operaciones',      1),
 ('U011', 'Daniela Flores Quiroz',    'Recepcionista',            'Administración',   1),
 ('U012', 'Fernando Salazar Leal',    'Analista de Datos',        'Tecnología',       1);

INSERT INTO tecnicos (nombre, email, activo) VALUES
 ('Ana Beltrán',      'ana@soporte.local',   1),
 ('Bruno Castro',     'bruno@soporte.local', 1),
 ('Carla Domínguez',  'carla@soporte.local', 1),
 ('Diego Espinoza',   'diego@soporte.local', 0);

-- Cuentas de acceso (Admin, Técnicos, Solicitantes)
-- Credenciales de prueba:
--  * admin  / admin123  (Rol: ADMIN - control total)
--  * ana    / ana123    (Rol: TECNICO - Ana Beltrán, id: 1)
--  * bruno  / bruno123  (Rol: TECNICO - Bruno Castro, id: 2)
--  * carla  / carla123  (Rol: TECNICO - Carla Domínguez, id: 3)
--  * laura  / laura123  (Rol: SOLICITANTE - Laura Martínez Ruiz, U001, id: 1)
--  * carlos / carlos123 (Rol: SOLICITANTE - Carlos Hernández Soto, U002, id: 2)
--  * sofia  / sofia123  (Rol: SOLICITANTE - Sofía Castillo Mena, U005, id: 5)
INSERT INTO cuentas (username, email, password_hash, rol, usuario_id, tecnico_id, nombre, activo, creado_en) VALUES
 ('admin',  'admin@soporte.local',  '$2y$10$z6jJXpmNZjGzhC7k49McbuvOayRRNUs2QUdhYzCSWU/OkqYYJ00Gu', 'ADMIN',       NULL, NULL, 'Administrador TI',     1, @ahora - INTERVAL 30 DAY),
 ('ana',    'ana@soporte.local',    '$2y$10$IFzLyrVD2LP904vVxo6NHucVdJGjqodwq8lURRDX9Iq81lZf3./1y', 'TECNICO',     NULL, 1,    'Ana Beltrán',          1, @ahora - INTERVAL 30 DAY),
 ('bruno',  'bruno@soporte.local',  '$2y$10$/SVeQgcO.7moH7NKBK47BOXxmqnRU/CJCp1hhp6WYtKyxiuYx0n7W', 'TECNICO',     NULL, 2,    'Bruno Castro',         1, @ahora - INTERVAL 30 DAY),
 ('carla',  'carla@soporte.local',  '$2y$10$08AAwcg2KBmAJipv7Tjqf.ur/SZ2jnevooOMrM.s5RznGEzieXHda', 'TECNICO',     NULL, 3,    'Carla Domínguez',      1, @ahora - INTERVAL 30 DAY),
 ('laura',  'laura@empresa.local',  '$2y$10$4a5AAXIohhdz9DakWOYOHe/I66mjxJxPktCJ26nvbvgjo9IcitODa', 'SOLICITANTE', 1,    NULL, 'Laura Martínez Ruiz',  1, @ahora - INTERVAL 30 DAY),
 ('carlos', 'carlos@empresa.local', '$2y$10$OeQRuLwhkBjl4J4aTgd2G.1DyCKdJKu6pMbUPMtTkPLERjoS9t0hW', 'SOLICITANTE', 2,    NULL, 'Carlos Hernández Soto', 1, @ahora - INTERVAL 30 DAY),
 ('sofia',  'sofia@empresa.local',  '$2y$10$7gnkmn.29zCGjtq4s6712uvGexPTy.MecOVMtvCOqPOHK4rPZHF7W', 'SOLICITANTE', 5,    NULL, 'Sofía Castillo Mena',  1, @ahora - INTERVAL 30 DAY);

-- Consecutivo del año en curso: los 25 tickets de prueba usan del 1 al 25.
INSERT INTO consecutivos (anio, ultimo) VALUES (YEAR(@ahora), 25);

-- Tickets (del más antiguo al más reciente: id 1..25 = consecutivo)

INSERT INTO tickets (id, numero, usuario_id, categoria_id, tecnico_id, descripcion, prioridad, estado, solucion, creado_en, fecha_limite, actualizado_en, cerrado_en) VALUES
 (1, CONCAT('TK-', YEAR(@ahora), '-', LPAD(1, 6, '0')), 10, 3, 4, 'Mover el punto de red del escritorio a la nueva ubicación del almacén.', 'Baja', 'CERRADO', 'Se instaló el nuevo punto y se certificó el cableado.', @ahora - INTERVAL 24000 MINUTE, @ahora - INTERVAL 24000 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 22560 MINUTE, @ahora - INTERVAL 22560 MINUTE),
 (2, CONCAT('TK-', YEAR(@ahora), '-', LPAD(2, 6, '0')), 7, 4, 1, 'Solicito la baja del equipo viejo de compras y su respaldo de información.', 'Baja', 'CERRADO', 'Se respaldó la información y se dio de baja el activo.', @ahora - INTERVAL 18000 MINUTE, @ahora - INTERVAL 18000 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 15000 MINUTE, @ahora - INTERVAL 15000 MINUTE),
 (3, CONCAT('TK-', YEAR(@ahora), '-', LPAD(3, 6, '0')), 8, 2, 4, 'Instalar la suite de diseño en el nuevo equipo del área de marketing.', 'Baja', 'CERRADO', 'Se instaló y licenció el software, se capacitó al usuario.', @ahora - INTERVAL 15000 MINUTE, @ahora - INTERVAL 15000 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 10200 MINUTE, @ahora - INTERVAL 10200 MINUTE),
 (4, CONCAT('TK-', YEAR(@ahora), '-', LPAD(4, 6, '0')), 3, 2, 1, 'Excel se cierra solo al abrir el archivo de presupuesto anual.', 'Media', 'CERRADO', 'Se reparó la instalación de Office y se recuperó el archivo.', @ahora - INTERVAL 12000 MINUTE, @ahora - INTERVAL 12000 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 10800 MINUTE, @ahora - INTERVAL 10800 MINUTE),
 (5, CONCAT('TK-', YEAR(@ahora), '-', LPAD(5, 6, '0')), 1, 4, 4, 'Solicito configurar firma de correo con el nuevo formato corporativo.', 'Baja', 'CERRADO', 'Se configuró la firma y se compartió la plantilla.', @ahora - INTERVAL 9600 MINUTE, @ahora - INTERVAL 9600 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 7200 MINUTE, @ahora - INTERVAL 7200 MINUTE),
 (6, CONCAT('TK-', YEAR(@ahora), '-', LPAD(6, 6, '0')), 4, 2, 4, 'Requiero acceso al módulo de inventarios del ERP para mi usuario.', 'Media', 'CERRADO', 'Se asignó el rol correspondiente y se validó con el usuario.', @ahora - INTERVAL 9000 MINUTE, @ahora - INTERVAL 9000 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 7200 MINUTE, @ahora - INTERVAL 7200 MINUTE),
 (7, CONCAT('TK-', YEAR(@ahora), '-', LPAD(7, 6, '0')), 2, 1, 2, 'La pantalla de mi equipo parpadea y se apaga de forma intermitente.', 'Alta', 'CERRADO', 'Se reemplazó el cable de video interno y se probó durante una hora.', @ahora - INTERVAL 7200 MINUTE, @ahora - INTERVAL 7200 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 7020 MINUTE, @ahora - INTERVAL 7020 MINUTE),
 (8, CONCAT('TK-', YEAR(@ahora), '-', LPAD(8, 6, '0')), 5, 3, 4, 'Switch del segundo piso sin respuesta, varios equipos sin red.', 'Alta', 'CERRADO', 'Se reinició el switch y se actualizó el firmware, se monitoreó la red.', @ahora - INTERVAL 6000 MINUTE, @ahora - INTERVAL 6000 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 5640 MINUTE, @ahora - INTERVAL 5640 MINUTE),
 (9, CONCAT('TK-', YEAR(@ahora), '-', LPAD(9, 6, '0')), 6, 1, 3, 'El teclado de mi laptop tiene varias teclas que no responden.', 'Media', 'CERRADO', 'Se sustituyó el teclado por uno nuevo en garantía.', @ahora - INTERVAL 5400 MINUTE, @ahora - INTERVAL 5400 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 4800 MINUTE, @ahora - INTERVAL 4800 MINUTE),
 (10, CONCAT('TK-', YEAR(@ahora), '-', LPAD(10, 6, '0')), 8, 4, 1, 'Solicito un segundo monitor para el área de diseño gráfico.', 'Baja', 'ABIERTO', NULL, @ahora - INTERVAL 4800 MINUTE, @ahora - INTERVAL 4800 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 4800 MINUTE, NULL),
 (11, CONCAT('TK-', YEAR(@ahora), '-', LPAD(11, 6, '0')), 12, 2, 2, 'No puedo conectarme a la base de datos de análisis desde mi equipo.', 'Media', 'CERRADO', 'Se corrigió la regla del firewall y se restableció la conexión.', @ahora - INTERVAL 4320 MINUTE, @ahora - INTERVAL 4320 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 3600 MINUTE, @ahora - INTERVAL 3600 MINUTE),
 (12, CONCAT('TK-', YEAR(@ahora), '-', LPAD(12, 6, '0')), 5, 2, 3, 'El antivirus muestra una alerta y bloquea la aplicación de cotizaciones.', 'Media', 'CERRADO', 'Se agregó la excepción correcta y se actualizó el antivirus.', @ahora - INTERVAL 3600 MINUTE, @ahora - INTERVAL 3600 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 3120 MINUTE, @ahora - INTERVAL 3120 MINUTE),
 (13, CONCAT('TK-', YEAR(@ahora), '-', LPAD(13, 6, '0')), 10, 1, 2, 'El mouse inalámbrico del almacén responde con retraso y se traba.', 'Baja', 'PENDIENTE', NULL, @ahora - INTERVAL 3540 MINUTE, @ahora - INTERVAL 3540 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 3200 MINUTE, NULL),
 (14, CONCAT('TK-', YEAR(@ahora), '-', LPAD(14, 6, '0')), 11, 1, 3, 'El teléfono de recepción no tiene tono y no entran llamadas.', 'Alta', 'CERRADO', 'Se reemplazó el adaptador de corriente del teléfono IP.', @ahora - INTERVAL 2880 MINUTE, @ahora - INTERVAL 2880 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 2760 MINUTE, @ahora - INTERVAL 2760 MINUTE),
 (15, CONCAT('TK-', YEAR(@ahora), '-', LPAD(15, 6, '0')), 3, 2, 1, 'No puedo iniciar sesión en el correo corporativo, marca error de credenciales.', 'Media', 'EN CURSO', NULL, @ahora - INTERVAL 1800 MINUTE, @ahora - INTERVAL 1800 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 1785 MINUTE, NULL),
 (16, CONCAT('TK-', YEAR(@ahora), '-', LPAD(16, 6, '0')), 2, 3, 1, 'La videollamada con clientes se corta constantemente en la sala de juntas.', 'Alta', 'CERRADO', 'Se priorizó el tráfico de videollamadas y se cambió el cable de la sala.', @ahora - INTERVAL 1800 MINUTE, @ahora - INTERVAL 1800 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 1740 MINUTE, @ahora - INTERVAL 1740 MINUTE),
 (17, CONCAT('TK-', YEAR(@ahora), '-', LPAD(17, 6, '0')), 11, 4, 3, 'Necesito cambiar la configuración del teléfono IP de recepción.', 'Baja', 'ABIERTO', NULL, @ahora - INTERVAL 1200 MINUTE, @ahora - INTERVAL 1200 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 1200 MINUTE, NULL),
 (18, CONCAT('TK-', YEAR(@ahora), '-', LPAD(18, 6, '0')), 4, 1, 2, 'La impresora de logística imprime con rayas negras en todas las hojas.', 'Media', 'ABIERTO', NULL, @ahora - INTERVAL 1170 MINUTE, @ahora - INTERVAL 1170 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 1170 MINUTE, NULL),
 (19, CONCAT('TK-', YEAR(@ahora), '-', LPAD(19, 6, '0')), 12, 2, 3, 'Actualizar Python y las librerías de análisis en mi equipo de trabajo.', 'Baja', 'EN CURSO', NULL, @ahora - INTERVAL 480 MINUTE, @ahora - INTERVAL 480 MINUTE + INTERVAL 72 HOUR, @ahora - INTERVAL 465 MINUTE, NULL),
 (20, CONCAT('TK-', YEAR(@ahora), '-', LPAD(20, 6, '0')), 2, 3, 1, 'Sin acceso a internet en toda el área comercial desde la mañana.', 'Alta', 'ABIERTO', NULL, @ahora - INTERVAL 360 MINUTE, @ahora - INTERVAL 360 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 360 MINUTE, NULL),
 (21, CONCAT('TK-', YEAR(@ahora), '-', LPAD(21, 6, '0')), 6, 2, 2, 'Solicito instalación de Office y acceso a la carpeta compartida de RRHH.', 'Media', 'EN CURSO', NULL, @ahora - INTERVAL 300 MINUTE, @ahora - INTERVAL 300 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 285 MINUTE, NULL),
 (22, CONCAT('TK-', YEAR(@ahora), '-', LPAD(22, 6, '0')), 1, 2, 1, 'El sistema contable se congela al generar el cierre mensual de reportes.', 'Alta', 'EN CURSO', NULL, @ahora - INTERVAL 198 MINUTE, @ahora - INTERVAL 198 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 183 MINUTE, NULL),
 (23, CONCAT('TK-', YEAR(@ahora), '-', LPAD(23, 6, '0')), 7, 3, 3, 'El WiFi del piso de compras se desconecta cada pocos minutos.', 'Media', 'ABIERTO', NULL, @ahora - INTERVAL 120 MINUTE, @ahora - INTERVAL 120 MINUTE + INTERVAL 24 HOUR, @ahora - INTERVAL 120 MINUTE, NULL),
 (24, CONCAT('TK-', YEAR(@ahora), '-', LPAD(24, 6, '0')), 5, 1, 2, 'La laptop no enciende y se requiere para una presentación con cliente.', 'Alta', 'ABIERTO', NULL, @ahora - INTERVAL 60 MINUTE, @ahora - INTERVAL 60 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 60 MINUTE, NULL),
 (25, CONCAT('TK-', YEAR(@ahora), '-', LPAD(25, 6, '0')), 1, 3, 1, 'No tengo acceso a la VPN para conectarme al servidor de archivos.', 'Alta', 'ABIERTO', NULL, @ahora - INTERVAL 30 MINUTE, @ahora - INTERVAL 30 MINUTE + INTERVAL 4 HOUR, @ahora - INTERVAL 30 MINUTE, NULL);

-- Historial (creación, reasignaciones y cambios de estado)
INSERT INTO ticket_historial (ticket_id, tipo, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en) VALUES
 (1, 'CREACION', NULL, 'ABIERTO', NULL, 4, 'Ticket registrado.', @ahora - INTERVAL 24000 MINUTE),
 (1, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 23985 MINUTE),
 (1, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se instaló el nuevo punto y se certificó el cableado.', @ahora - INTERVAL 22560 MINUTE),
 (2, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 18000 MINUTE),
 (2, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 17985 MINUTE),
 (2, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se respaldó la información y se dio de baja el activo.', @ahora - INTERVAL 15000 MINUTE),
 (3, 'CREACION', NULL, 'ABIERTO', NULL, 4, 'Ticket registrado.', @ahora - INTERVAL 15000 MINUTE),
 (3, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 14985 MINUTE),
 (3, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se instaló y licenció el software, se capacitó al usuario.', @ahora - INTERVAL 10200 MINUTE),
 (4, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 12000 MINUTE),
 (4, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 11985 MINUTE),
 (4, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se reparó la instalación de Office y se recuperó el archivo.', @ahora - INTERVAL 10800 MINUTE),
 (5, 'CREACION', NULL, 'ABIERTO', NULL, 4, 'Ticket registrado.', @ahora - INTERVAL 9600 MINUTE),
 (5, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 9585 MINUTE),
 (5, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se configuró la firma y se compartió la plantilla.', @ahora - INTERVAL 7200 MINUTE),
 (6, 'CREACION', NULL, 'ABIERTO', NULL, 4, 'Ticket registrado.', @ahora - INTERVAL 9000 MINUTE),
 (6, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 8985 MINUTE),
 (6, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se asignó el rol correspondiente y se validó con el usuario.', @ahora - INTERVAL 7200 MINUTE),
 (7, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 7200 MINUTE),
 (7, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 7185 MINUTE),
 (7, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se reemplazó el cable de video interno y se probó durante una hora.', @ahora - INTERVAL 7020 MINUTE),
 (8, 'CREACION', NULL, 'ABIERTO', NULL, 4, 'Ticket registrado.', @ahora - INTERVAL 6000 MINUTE),
 (8, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 5985 MINUTE),
 (8, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se reinició el switch y se actualizó el firmware, se monitoreó la red.', @ahora - INTERVAL 5640 MINUTE),
 (9, 'CREACION', NULL, 'ABIERTO', NULL, 3, 'Ticket registrado.', @ahora - INTERVAL 5400 MINUTE),
 (9, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 5385 MINUTE),
 (9, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se sustituyó el teclado por uno nuevo en garantía.', @ahora - INTERVAL 4800 MINUTE),
 (10, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 4800 MINUTE),
 (11, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 4320 MINUTE),
 (11, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 4305 MINUTE),
 (11, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se corrigió la regla del firewall y se restableció la conexión.', @ahora - INTERVAL 3600 MINUTE),
 (12, 'CREACION', NULL, 'ABIERTO', NULL, 3, 'Ticket registrado.', @ahora - INTERVAL 3600 MINUTE),
 (12, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 3585 MINUTE),
 (12, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se agregó la excepción correcta y se actualizó el antivirus.', @ahora - INTERVAL 3120 MINUTE),
 (13, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 3540 MINUTE),
 (13, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 3525 MINUTE),
 (13, 'ESTADO', 'EN CURSO', 'PENDIENTE', NULL, NULL, 'En espera de repuesto por parte de almacén.', @ahora - INTERVAL 3200 MINUTE),
 (14, 'CREACION', NULL, 'ABIERTO', NULL, 3, 'Ticket registrado.', @ahora - INTERVAL 2880 MINUTE),
 (14, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 2865 MINUTE),
 (14, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se reemplazó el adaptador de corriente del teléfono IP.', @ahora - INTERVAL 2760 MINUTE),
 (15, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 1800 MINUTE),
 (15, 'REASIGNACION', 'ABIERTO', 'ABIERTO', 2, 1, 'Reasignado por balanceo de carga.', @ahora - INTERVAL 1790 MINUTE),
 (15, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 1785 MINUTE),
 (16, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 1800 MINUTE),
 (16, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 1785 MINUTE),
 (16, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, 'Se priorizó el tráfico de videollamadas y se cambió el cable de la sala.', @ahora - INTERVAL 1740 MINUTE),
 (17, 'CREACION', NULL, 'ABIERTO', NULL, 3, 'Ticket registrado.', @ahora - INTERVAL 1200 MINUTE),
 (18, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 1170 MINUTE),
 (19, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 480 MINUTE),
 (19, 'REASIGNACION', 'ABIERTO', 'ABIERTO', 2, 3, 'Reasignado por balanceo de carga.', @ahora - INTERVAL 470 MINUTE),
 (19, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 465 MINUTE),
 (20, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 360 MINUTE),
 (21, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 300 MINUTE),
 (21, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 285 MINUTE),
 (22, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 198 MINUTE),
 (22, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL, 'Se inicia la atención.', @ahora - INTERVAL 183 MINUTE),
 (23, 'CREACION', NULL, 'ABIERTO', NULL, 3, 'Ticket registrado.', @ahora - INTERVAL 120 MINUTE),
 (24, 'CREACION', NULL, 'ABIERTO', NULL, 2, 'Ticket registrado.', @ahora - INTERVAL 60 MINUTE),
 (25, 'CREACION', NULL, 'ABIERTO', NULL, 1, 'Ticket registrado.', @ahora - INTERVAL 30 MINUTE);
