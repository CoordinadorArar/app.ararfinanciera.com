# Crédito – Fase 6: interfaz general (backend)

> Solo lectura. No se escribió en ninguna base. No se envió nada a SIESA ni a las
> centrales. No hay tablas nuevas, así que esta fase no trae DDL.

## 1. Cambios

| Archivo | Cambio |
|---|---|
| `routes/web.php` | `POST /inicio-resumen` en el grupo `auth`, sin `submenu.accion`, porque el inicio es para todos los usuarios autenticados. |
| `app/Http/Controllers/HomeController.php` | `inicioResumen()`. `index()` sigue devolviendo `view('home')` sin datos. |
| `app/Models/Procesos.php` | `resumenInicio($idUsuario, $roles, $flujo, $limite = 10)`: contadores por estado y pendientes, con la misma visibilidad que la bandeja. |
| `app/Exceptions/Handler.php` | Si la petición espera JSON: 401 y 419 con `{message}`. |
| `resources/views/layouts/footer/footer.blade.php` | Ya no usa `$_SERVER["REMOTE_ADDR"]`, que daba error fuera de HTTP. Primero se cambió por `request()->ip()`; después Frontend lo rediseñó y ahora solo muestra «© {año} Arar Financiera», sin IP ni reloj. |
| `tests/Feature/InicioResumenTest.php` | Pruebas nuevas (§5). |

`/` y `/home` siguen en `HomeController::index`. El constructor ya exige `auth`,
y `/home` además pasa por `submenu.permiso`.

## 2. Contrato `POST /inicio-resumen`

Middleware: `web`, `auth`. No recibe parámetros.

```json
{
  "usuario": {"nombre": "William Hernandez", "rol": 1, "rolNombre": "Administrador"},
  "kpis": [
    {"clave": "procesos-estado-2", "titulo": "Consulta centrales de riesgo", "valor": 4, "estado": 2,
     "url": "<base>/lista-procesos?estado=2"},
    {"clave": "clientes-siesa-pendientes", "titulo": "Clientes pendientes en SIESA", "valor": 6,
     "url": "<base>/crear-cliente-siesa"}
  ],
  "pendientes": [
    {"idProceso": 6, "cliente": "WILLIAM HERNANDEZ", "estado": 2, "estadoNombre": "Consulta centrales de riesgo",
     "fecha": "2022-11-16 11:02", "accion": "centrales", "url": "<base>/lista-procesos?proceso=6"}
  ],
  "pendientesTotal": 4,
  "accesos": [{"titulo": "Lista de procesos", "url": "<base>/lista-procesos", "icono": "fas fa-list"}]
}
```

- **usuario**: `nombre` es `users.nombreUsuario`. `rol` es el primer `IdRol` de
  `User::obtenerRol`, el mismo que usan el sidebar y `submenu.permiso`.
  `rolNombre` lista todos los roles del usuario, separados por coma, o es `null`.
- **kpis de procesos**: solo aparecen si el submenú `/lista-procesos` está entre
  los permitidos del usuario. Hay un KPI por cada estado visible en la bandeja
  (`FlujoProceso::estadosVisibles`), ordenados 1..5 y luego 0 (Rechazado). Los
  estados sin procesos llevan `valor` 0. El asesor (`soloPropios`) solo cuenta
  sus procesos (`Procesos::propios`), con la misma regla que la bandeja. La
  `url` usa `?estado=N`, un filtro que la bandeja ya acepta en `enrutar()` de
  `js/procesos.js`.
- **KPI SIESA**: solo aparece si el usuario tiene permiso a `/crear-cliente-siesa`.
  `valor` es `contadores.pendiente` de `Terceros::listadoClientesSiesa('', 'pendiente', 1, 1)`,
  así que trae un solo registro. La pantalla SIESA **no** lee filtros por query,
  pero ya abre con el filtro `pendiente` por defecto. Si la consulta falla, se
  registra con `report()` y se omite el KPI.
- **pendientes**: procesos en un estado `e` en el que el rol puede ejecutar la
  transición `e → e+1` (`FlujoProceso::puedeTransicion`, `config/procesos.php`),
  dentro de los estados visibles y con la misma visibilidad. Se ordenan del más
  antiguo al más reciente por `ISNULL(updated_at, FechaCreacion)` y se devuelven
  máximo 10 (`TOP 10`). `accion` es `FlujoProceso::accionPrincipal`. La `url` abre
  el detalle con `?proceso=ID`, que la bandeja ya soporta.
- **pendientesTotal** (campo adicional): total de procesos pendientes para el
  rol, aunque haya más de 10. Sirve para un enlace «ver todos».
- **accesos**: se arman con la misma fuente que el sidebar
  (`Admin::obtenerSubMenus(rol)`, solo `EstadoSubmenu = 1`). Se ordenan por
  `Menus.Orden` y luego por `IdSubmenu`, sin duplicados (el rol 2 tiene permisos
  repetidos en `PermisosRoles`), sin `/perfil-usuario` ni `/log-out`, y son
  máximo 6. `icono` es la clase extraída de `CodigoSubmenu` (por ejemplo
  `fas fa-list`), o `null`.
- Las URLs son absolutas (`url()`), igual que en el sidebar.

Estados por rol para pendientes: rol 1 y 2 → 1, 2, 3, 4; rol 3 → 4; rol 4 → 2;
rol 5 → 2, 3; rol 6 → 1; roles 7 y 8 → ninguno.

Consultas: 2 en `ArarFinanciera` (un `COUNT` agrupado por estado y un `TOP 10`),
1 de SIESA si aplica, y 5 en identidad (usuario, roles, rol, menús y submenús).
Nada de N+1.

## 3. Sesión expirada en AJAX

Antes, `makeOptionsFetch` solo enviaba `X-CSRF-TOKEN`, sin `X-Requested-With`
ni `Accept: application/json`. Por eso `expectsJson()` daba `false`:

- sin sesión → `302` a `/login`. `fetch` seguía el redirect y recibía HTML con
  200, y `response.json()` fallaba;
- token CSRF vencido → `419` con HTML.

Ahora, en `Handler::register()` y solo cuando `$request->expectsJson()`:

| Caso | Respuesta |
|---|---|
| `AuthenticationException` | `401 {"message":"Tu sesión expiró. Ingresa de nuevo."}` |
| `HttpException` 419 (`TokenMismatchException`) | `419 {"message":"Tu sesión expiró. Recarga la página e ingresa de nuevo."}` |

Las peticiones que no esperan JSON se comportan igual que antes (redirect al
login / página 419). `Authenticate.php` no cambió: su `redirectTo` ya devuelve
`null` con JSON y mantiene el 403 `{message, res:'inactivo'}` para usuarios
inactivos.

**Frontend:** para que aplique, `makeOptionsFetch` debe enviar
`'X-Requested-With': 'XMLHttpRequest'` (con el `Accept: */*` por defecto de
`fetch` basta) o `Accept: application/json`.

## 4. Notificaciones (campana «99+»)

No hay ninguna fuente real. Ni en `ArarFinanciera` ni en `ArarFinanciera_PRUEBAS`
existen tablas `%otif%`, `%lert%` o `%ensaj%`. En el código, `Notifiable` del
modelo `User` no se usa y no hay tabla `notifications`. El badge
`#icon-notifications` es texto fijo y ningún JS lo actualiza. **Frontend debe
retirarlo.**

## 5. Pruebas

- `php -l` sin errores en los archivos tocados.
- `php artisan route:list`: `POST inicio-resumen → HomeController@inicioResumen`.
- `phpunit`: 85 pruebas, 1 falla **previa** que no tiene que ver con esta fase:
  `ExampleTest::test_example` espera 200 en `GET /`, pero `/` exige `auth` y responde 302.
- `tests/Feature/InicioResumenTest.php` (7 pruebas, sin BD: usa `DB::pretend`):
  - estados pendientes por cada rol del 1 al 8;
  - contadores en 0 para los estados visibles;
  - 2 consultas (`COUNT(*)` + `group by`, y `top 10`); el asesor filtra por
    `ProcesosHistorial` y el gerente no;
  - el rol sin transiciones no consulta pendientes;
  - 401 JSON con `postJson` y con `X-Requested-With`; sin JSON → redirect al login;
  - 419 JSON con `TokenMismatchException`; sin JSON → HTML 419.
- El footer se renderiza en CLI sin errores. Ya no depende de `$_SERVER` y muestra solo «© {año} Arar Financiera», sin IP ni reloj.
- Integración de solo lectura contra `ArarFinanciera_PRUEBAS` (script en el
  scratchpad, PDO inyectado con `setPdo` y `DB::listen` que bloquea todo lo que
  no sea SELECT/WITH). `controller->inicioResumen()` con `auth()->setUser()`:

| Usuario | KPIs | Pendientes | Accesos |
|---|---|---|---|
| 3, rol 1 | Registro 0, Centrales 4, Documentos 0, Aprobación 0, Aprobado 1, Rechazado 0, SIESA 6 | 4 (procesos 6, 8, 9, 10; acción `centrales`), total 4 | 6 |
| 9, rol 2 | igual que el rol 1 | igual que el rol 1 | 6 sin duplicados |
| 6, rol 6 (asesor) | todos los estados en 0 (no tiene procesos propios); sin KPI SIESA | 0 | 6 |
| 5, rol 7 | solo SIESA 6 (no tiene `/lista-procesos`) | 0 | 6 |

  `Procesos::resumenInicio` también se ejecutó directo sobre los mismos datos:
  - con rol `[6]` para el usuario 3, creador de los 5 procesos: contadores
    `[0,0,4,0,0,1]`, pendientes en el estado 1 → 0;
  - rol `[4]` → 4 pendientes en el estado 2;
  - rol `[5]` → 4;
  - rol `[3]` → 0 (no hay procesos en el estado 4).

## Despliegue: HTTPS

- En producción el `.env` debe tener `APP_URL=https://app.ararfinanciera.com`. Con ese valor `AppServiceProvider` fuerza el esquema `https` en `asset()`, `route()` y `url()`.
- Después de ajustar el `.env`, ejecutar `php artisan config:clear` (o `php artisan config:cache` si el servidor usa caché de configuración).
- En local `APP_URL` puede seguir en `http://`; en ese caso no se fuerza el esquema.
