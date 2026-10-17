# Mesa de Soporte TI

Aplicación web de una sola página (SPA) para registrar, asignar y dar seguimiento a tickets de soporte
internos. Backend en **PHP 8 sin framework** con **API REST** en JSON, base de datos **MariaDB/MySQL** (XAMPP) y
frontend con **JavaScript (fetch) + Bootstrap 5**. Los estilos están separados en `public/css/` y el código del navegador en `public/js/`. Toda la interacción ocurre por AJAX, sin recargar la página.

> **Autor:** Daniel Hernandez · Practicante de Desarrollo SW

## Uso de herramientas de IA

Durante el desarrollo utilicé una herramienta de IA como apoyo para resolver dudas, revisar alternativas y organizar parte de la documentación. Algunas sugerencias se tomaron como punto de partida, pero se revisaron y ajustaron al proyecto. Estoy estudiando el código para poder explicar cómo funciona, justificar las decisiones y realizar cambios durante la sustentación.

---

## Requisitos

| Componente | Versión | Notas |
|---|---|---|
| XAMPP | con **PHP 8.1 o superior** | Trae Apache, **MariaDB** y phpMyAdmin. Comprueba la versión con `C:\xampp\php\php.exe -v` |
| MariaDB | 10.4 o superior (la de XAMPP) | También funciona con MySQL 8 |
| Navegador | Moderno | Bootstrap 5 se carga desde CDN (jsDelivr): se necesita internet al abrir la página |

La extensión `pdo_mysql` ya viene activada en XAMPP.

## Instalación (XAMPP en Windows)

1. Abre el **Panel de control de XAMPP** y pulsa **Start** en **MySQL** (es MariaDB). Apache solo es necesario si lo vas a usar para servir la app.
2. Descarga o clona el proyecto, por ejemplo en `C:\soporte-ti`.
3. **Configuración** (este archivo NO se versiona): copia `config\config.example.php` como `config\config.php`.
   Los valores por defecto son los de XAMPP (usuario `root` sin contraseña); solo cambia `api_key` si quieres.
4. **Base de datos** (elige una opción):
   - **phpMyAdmin** (<http://localhost/phpmyadmin>): pestaña **Importar** → elige `database/schema.sql` → *Continuar*;
     luego importa `database/seed.sql`. (`schema.sql` crea la base `soporte_ti`; no hace falta crearla antes.)
   - **Consola:** `C:\xampp\php\php.exe database\install.php`
5. **Levantar la aplicación** desde la carpeta del proyecto:
   ```bat
   C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
   ```
   Abre <http://localhost:8000>.

> Alternativa con Apache: copia el proyecto a `C:\xampp\htdocs\soporte-ti`, apunta el `DocumentRoot` a su carpeta
> `public/` (incluye `.htaccess` con `mod_rewrite`) o crea un VirtualHost.

Los scripts de BD son **re-ejecutables**: borran y recrean la base `soporte_ti`, así que sirven para volver a dejar
los datos de prueba en su estado original (la base debe llamarse `soporte_ti`).

En Linux/macOS es igual: `cp config/config.example.php config/config.php`, `mysql -u root < database/schema.sql`,
`mysql -u root < database/seed.sql` y `php -S localhost:8000 -t public public/index.php`.

### Configuración (`config/config.php`)

```php
return [
    'app'     => ['timezone' => 'America/Bogota'],
    'db'      => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'soporte_ti', 'user' => 'root', 'password' => ''],
    'api_key' => 'una-clave-larga-y-secreta',
];
```

### Datos de prueba

12 usuarios (`U001`–`U012`; **`U009` está inactivo**), 4 técnicos (**Diego está inactivo**), 4 categorías y
25 tickets (12 activos y 13 cerrados) con historial. Hay tickets con SLA **vencido** y **por vencer**
(las fechas son relativas al momento de la instalación y usan la hora fija `-05:00`, Cancún). **Ana Beltrán** queda con 5 tickets activos (tope) para
poder probar esa regla.

---

## Arquitectura

```
public/            index.php (front controller), css/, js/, .htaccess
views/app.php      HTML de la SPA (inyecta la API key como <meta>)
src/
  Kernel.php       arranque, cableado de dependencias y rutas
  Core/            Config, Database (PDO), Router, Request, Response, ApiKeyGuard, ApiException
  Controller/      HTTP: validan la forma de la petición y devuelven JSON (sin lógica de negocio)
  Service/         Lógica de negocio: validación, flujo de estados, tope de carga, SLA, historial
  Domain/          Estado, Prioridad, Sla, Reglas  (lógica pura, sin BD)
  Repository/      Acceso a datos: SOLO aquí hay SQL, siempre con sentencias preparadas (PDO)
database/          schema.sql, seed.sql, install.php
postman/           Colección de Postman
config/            config.example.php (versionado) · config.php (local, ignorado por git)
```

La interfaz consume la misma API REST (`fetch`); no hay consultas sueltas por archivo.

**Nota de seguridad:** como esta prueba exige que la SPA envíe `X-API-Key`, la clave que utiliza el navegador puede verse desde las herramientas de desarrollo. Esta implementación sirve para la prueba local; en producción conviene usar autenticación por sesión/token y controles de acceso por usuario.

## API REST

Todas las rutas exigen el encabezado `X-API-Key`, responden `application/json` y usan un **formato único de error**:

```json
{ "error": { "code": "TRANSICION_INVALIDA", "message": "Un ticket ABIERTO no puede pasar a CERRADO", "fields": {} } }
```

| Método | Ruta | Respuestas |
|---|---|---|
| GET | `/api/tickets?estado=&prioridad=&tecnico=&q=&page=&per_page=` | 200, 422 |
| GET | `/api/tickets/{numero}` | 200, 404 |
| POST | `/api/tickets` | 201, 422 |
| PATCH | `/api/tickets/{numero}/estado` | 200, 404, 409, 422 |
| PATCH | `/api/tickets/{numero}/tecnico` | 200, 404, 409, 422 |
| GET | `/api/usuarios/{codigo}` | 200, 404 |
| GET | `/api/tecnicos` (`?todos=1` incluye inactivos) | 200 |
| GET | `/api/categorias` _(auxiliar)_ | 200 |
| GET | `/api/resumen` _(auxiliar: tablero)_ | 200 |

Cualquier ruta: **401** sin clave o con clave inválida, **405** método no permitido, **400** JSON inválido, **500** error
interno (mensaje genérico; el detalle solo va al log del servidor, nunca trazas PHP ni SQL).

Códigos de error de negocio: `VALIDACION` (422), `TRANSICION_INVALIDA` (409), `TECNICO_CARGA_MAXIMA` (409 al reasignar,
422 al crear), `TODOS_TECNICOS_LLENOS` (409 cuando todos los demás técnicos activos tienen su carga máxima y no es posible
reasignar), `TICKET_CERRADO` (409), `NO_ENCONTRADO` (404), `NO_AUTORIZADO` (401).

### Ejemplos (`curl`)

```bash
KEY="X-API-Key: 2026123456"; URL=http://localhost:8000/api

# Listar (filtros + paginación)
curl -H "$KEY" "$URL/tickets?estado=EN%20CURSO&prioridad=Alta&tecnico=1&q=laura&page=1&per_page=10"

# Detalle con historial
curl -H "$KEY" $URL/tickets/TK-2026-000010

# Datos del solicitante / técnicos con su carga / categorías / resumen del tablero
curl -H "$KEY" $URL/usuarios/U001
curl -H "$KEY" $URL/tecnicos
curl -H "$KEY" $URL/categorias
curl -H "$KEY" $URL/resumen

# Crear ticket  -> 201 con el ticket creado (envuelto en "data")
curl -X POST -H "$KEY" -H "Content-Type: application/json" $URL/tickets -d '{
  "codigo_usuario": "U001", "categoria_id": 3, "prioridad": "Alta", "tecnico_id": 3,
  "descripcion": "No hay acceso a internet en la oficina de contabilidad"
}'

# Cambiar estado (ABIERTO > EN CURSO > CERRADO)
curl -X PATCH -H "$KEY" -H "Content-Type: application/json" $URL/tickets/TK-2026-000026/estado \
  -d '{"estado": "EN CURSO", "observacion": "Se inicia la atención"}'

# Cerrar: la solución es obligatoria (campo "solucion"; por compatibilidad también se acepta en "observacion")
curl -X PATCH -H "$KEY" -H "Content-Type: application/json" $URL/tickets/TK-2026-000026/estado \
  -d '{"estado": "CERRADO", "solucion": "Se reinició el router y se restableció el servicio"}'

# Reasignar técnico
curl -X PATCH -H "$KEY" -H "Content-Type: application/json" $URL/tickets/TK-2026-000026/tecnico \
  -d '{"tecnico_id": 2, "observacion": "Balanceo de carga"}'

# Errores: sin clave (401), salto de estado (409), validación (422)
curl -i $URL/tickets
curl -i -X PATCH -H "$KEY" -H "Content-Type: application/json" $URL/tickets/TK-2026-000020/estado -d '{"estado":"CERRADO","solucion":"x"}'
curl -i -X POST  -H "$KEY" -H "Content-Type: application/json" $URL/tickets -d '{}'
```

**Colección de Postman:** `postman/SoporteTI.postman_collection.json` (38 peticiones: éxito y errores 400/401/404/405/409/422).
Impórtala, ajusta `baseUrl` y `apiKey` si hace falta y ejecútala **en orden sobre una BD recién instalada**
(guarda `numero`/`numero2` entre peticiones).

---

## Reglas de negocio

- **Flujo de estados:** solo `ABIERTO → EN CURSO → CERRADO`; sin saltos ni reapertura (409). Cerrar exige la solución (422).
  Cada cambio de estado o de técnico, y la creación, quedan en `ticket_historial` (fecha/hora, estado anterior y nuevo,
  técnico anterior y nuevo, observación).
- **Tope de carga:** un técnico con 5 tickets activos (ABIERTO + EN CURSO) no recibe más. Solo técnicos activos.
  La interfaz sugiere por defecto al de menor carga y deshabilita a los que no tienen cupo.
- **Reasignación bloqueada cuando todos están llenos:** si en el momento de reasignar todos los demás técnicos activos
  ya tienen 5 tickets, el servidor devuelve `TODOS_TECNICOS_LLENOS` (409) y el frontend bloquea el botón y el selector
  preventivamente al abrir el modal, sin esperar al intento fallido.
- **SLA:** Alta 4 h · Media 24 h · Baja 72 h. `fecha_limite` se calcula al crear. En tickets no cerrados el indicador es
  **Vencido** (pasó el límite) o **Por vencer** (resta menos del 25 % del tiempo total).
- **Validación en servidor** de todos los campos (descripción de 20 a 1000 caracteres, usuario existente y activo, etc.).

## Decisiones técnicas

**1. Consecutivo del ticket seguro ante concurrencia.** Tabla `consecutivos(anio, ultimo)`. Dentro de la transacción de
creación: se asegura la fila del año (`INSERT … ON DUPLICATE KEY UPDATE`, no-op si existe), se lee con
`SELECT ultimo … FOR UPDATE` —que **bloquea esa fila hasta el COMMIT**— y se incrementa. Si dos usuarios registran al
mismo tiempo, el segundo espera al primero, por lo que nunca obtienen el mismo número; y si la transacción falla, el número
no se consume (sin huecos). Un año distinto es otra fila, por eso el consecutivo **reinicia cada año**. `numero` además
tiene `UNIQUE` como red de seguridad.

**2. Tope de carga sin condiciones de carrera.** Antes de contar los tickets activos de un técnico, la transacción toma un
`SELECT … FOR UPDATE` sobre la fila del técnico (y las transiciones de estado bloquean la fila del ticket). Así, dos
asignaciones simultáneas al mismo técnico se serializan y la segunda ve el conteo ya actualizado. Las transacciones usan
`READ COMMITTED` para que, tras obtener el bloqueo, la lectura vea lo que la otra transacción confirmó. Al reasignar se
bloquean los dos técnicos siempre en orden de id para evitar interbloqueos.

**3. SLA en una clase pura (`Domain\Sla`)** y estados/prioridades como `enum`: las reglas son fáciles de leer y de
probar sin base de datos.

## Seguridad

- Solo sentencias preparadas (PDO, `ATTR_EMULATE_PREPARES = false`). Los comodines de `LIKE` del buscador se escapan.
- Las restricciones de negocio también están en la BD (`CHECK` de estado, prioridad, longitud de descripción, solución obligatoria al cerrar, `FOREIGN KEY`, `UNIQUE`).
- Salida escapada en el navegador (`esc()` en toda plantilla dinámica); verificado con una descripción
  `<img onerror=…>` / `<script>`, que se muestra como texto y no se ejecuta.
- Credenciales y API key fuera del código (`config/config.php`, ignorado por git; se versiona `config.example.php`).
- `display_errors` apagado; los errores se registran en el log y el cliente solo recibe un mensaje genérico.
- La clave se compara con `hash_equals`.

**Limitación conocida:** como la SPA es un cliente de navegador que consume la API, el servidor inyecta la API key en la
página (`<meta name="api-key">`). En un sistema real habría autenticación por usuario/sesión; aquí se sigue el requisito de la
prueba (API protegida por `X-API-Key`).

## Fuera de alcance

No se implementaron los puntos extra (Docker, PHPUnit, exportación CSV, despliegue en línea).
