# Crédito – Fase 4: centrales de riesgo con varios proveedores

Script: `documentacion/credito-fase4-ddl.sql` (equivale a la migración
`project/database/migrations/2026_10_07_130000_credito_fase4_centrales.php`).
Configuración: `project/config/centrales.php` (credenciales solo en `.env`).
Servicios: `project/app/Services/Centrales/`.

> No se llamó a TransUnion ni a DataCrédito reales. Todas las pruebas usaron el
> proveedor **Simulado** o proveedores sin credenciales.

## 1. Esquema aplicado en ArarFinanciera_PRUEBAS

Ejecutado el 2026-10-07 con PDO directo (pdo_sqlsrv) contra
`ArarFinanciera_PRUEBAS` (`DB_DATABASE_DEMO`). No se escribió en
`ArarFinanciera`. Se ejecutó **dos veces seguidas** sin error y sin duplicar
filas (4 filas de configuración en ambas pasadas).

| Objeto | Resultado |
|---|---|
| `ConsultasCentrales` (Id, IdProceso FK null, IdTercero FK, Proveedor, Ambiente, Simulado bit, FechaConsulta default getdate(), VigenteHasta null, IdUsuario, Exitosa bit, MensajeError nvarchar(500), ResumenJson nvarchar(max), RespuestaCruda nvarchar(max)) | creada; índices `(IdTercero, Proveedor, FechaConsulta)` e `IdProceso`; FK a `Procesos` y `Terceros` |
| `ConfiguracionCentrales` (IdConfiguracion, Clave única, Valor, IdUsuario, updated_at) | creada; seed `predeterminado=transunion`, `vigenciaDias=30`, `habilitado.transunion=1`, `habilitado.datacredito=1` |

Se eligió una tabla pequeña clave/valor en lugar de `ValoresVariables` porque
esta última se edita libremente desde la pestaña de variables (se podría
renombrar o borrar) y porque la auditoría necesita un `IdRegistro` numérico.
Cada cambio se audita en `ConfiguracionAuditoria` con
`Tabla='ConfiguracionCentrales'`, `IdRegistro=IdConfiguracion`, `Campo=Clave`.

## 2. Arquitectura

| Clase | Rol |
|---|---|
| `ProveedorCentral` (interfaz) | `consultar(array $tercero): ResultadoCentral`, `probarConexion(): array`, `configurado(): bool` |
| `ResultadoCentral` | resumen normalizado + `cruda` (XML/JSON del proveedor); calcula `totales` y alertas derivadas |
| `TransUnionProveedor` + `TransUnionSoapClient` | SOAP `consultaXml` (WS-Addressing + WS-Security firmado con llave/certificado, igual que el antiguo `NewSoap.php`) y parser del XML CIFIN |
| `DatacreditoProveedor` | adaptador HC2 de Experian **pendiente de validar** |
| `SimuladoProveedor` | respuestas deterministas, solo si `centrales.simulado_habilitado` |
| `CentralesRiesgo` | registro de proveedores, ajustes de BD, vigencia/reutilización (funciones puras), ejecución con captura de errores y persistencia |
| `CentralException` | error con `codigo` |

`$tercero` = `{documento, tipoDocumento (IdTipoDocumento), primerApellido (primera palabra de ApellidosTercero en mayúsculas), nombre, fechaExpedicion}`,
tomado del tercero real del proceso.

Códigos de error: `sin_credenciales`, `conexion`, `deshabilitado`,
`respuesta_invalida` y, además, `datos_invalidos` (el `IdTipoDocumento` del
tercero no está homologado en `tipos_documento` del proveedor).

### Resumen normalizado (ResultadoCentral)

```
proveedor, fechaConsulta, simulado, unidad:"pesos", unidadOrigen:"pesos"|"miles_de_pesos",
score: {valor, rangoMin, rangoMax, nivel} | null,
datosBasicos: {nombre, documento, tipoDocumento?, estadoDocumento, fechaExpedicion, lugarExpedicion, rangoEdad, actividadEconomica?, numeroInforme?, fechaInforme, ...},
totales: {obligacionesAlDia, obligacionesMora, saldoTotal, valorMora, cuotaMensual},
resumen: [{paquete, obligaciones, saldoTotal, participacionDeuda, obligacionesAlDia, saldoAlDia, cuotaAlDia, obligacionesMora, saldoMora, cuotaMora, valorMora}]  (solo TransUnion: ResumenPrincipal),
obligaciones: [{entidad, tipo, sector, estado: al_dia|mora|cerrada, numero, calidad, estadoReportado, saldo, cuota, valorInicial, valorMora, diasMora, cuotasMora, fechaApertura, fechaCorte}],
huella: [{entidad, fecha, motivo}],
alertas: [string]
```

- Montos siempre en **pesos**. Si el proveedor entrega miles
  (`*_MONTOS_EN_MILES=true`, por defecto en ambos) se multiplican por 1000 y
  `unidadOrigen = "miles_de_pesos"`.
- `totales` se calcula sobre las obligaciones no cerradas.
- Alertas derivadas: obligaciones en mora (cantidad y valor), documento en
  estado distinto de "vigente", sin historial (sin obligaciones ni score).
- `diasMora` en TransUnion es la altura/edad de mora (`EdadMora`,
  `AlturaMora` o `DiasMora`, el primero que venga).

### Parser de TransUnion

`InformacionComercial.xml` del repositorio es el **WSDL** (no una respuesta) y
`request.xml` es un proyecto SoapUI con la petición. No hay ninguna respuesta
real en el repositorio. El fixture `tests/fixtures/centrales/transunion-consulta.xml`
es **anonimizado y reconstruido** a partir de los campos que leía el antiguo
`js/procesos.js` (`Tercero`, `Consolidado/ResumenPrincipal/Registro`,
`SectorFinancieroAlDia/Obligacion`) y de la estructura CIFIN conocida
(sectores `Sector<Nombre><AlDia|EnMora|Extinguidas>`, `HuellaConsulta/Consulta`,
`Score/Puntaje`). Raíz aceptada: cualquier elemento con hijo `Tercero`, o el
propio `Tercero`. Un registro único o una lista se tratan igual (iteración
SimpleXML). Si falta `Tercero` o viene vacío: `respuesta_invalida`.

### Vigencia y reutilización

- Vigencia por **proveedor + tercero + ambiente**: se reutiliza la última
  consulta exitosa con `VigenteHasta > ahora`.
- `VigenteHasta = FechaConsulta + vigenciaDias` (valor vigente al consultar).
  Cambiar la vigencia afecta solo a las consultas nuevas.
- `forzar` ignora la vigente; requiere la acción `forzarConsultaCentrales`
  (`config/procesos.php`: estado 2, roles 1 y 2). Nota para Frontend: esta
  acción aparece ahora en `acciones` de `detalle-proceso` para roles 1 y 2 en
  estado 2; no cambia `accionPrincipal`.
- Las consultas fallidas también se guardan (`Exitosa=0`, `MensajeError`,
  `VigenteHasta` NULL) como trazabilidad.
- Lógica pura y probada sin BD: `CentralesRiesgo::vigenteHasta`,
  `reutilizable`, `plan`.

### Precondición de la transición 2→3

`FlujoProceso::validar` exige, además del tratamiento de datos completo, al
menos **una consulta de centrales exitosa y vigente del tercero**
(`CentralesRiesgo::tieneVigente`): última consulta exitosa por proveedor con la
misma regla de reutilización (`VigenteHasta > ahora`, mismo `Ambiente` del
proveedor). Solo cuentan los proveedores **disponibles** (misma regla que
`disponibles()`: habilitados y configurados); el Simulado solo cuenta si
`centrales.simulado_habilitado` es true. Una consulta de un proveedor
deshabilitado o sin credenciales no cuenta. Si no hay ninguna:
`422 {message: "Consulta al menos una central de riesgo antes de aprobar."}`
(se valida después del tratamiento y después del rol).

### Historial del proceso

Cada llamada a `centrales-consultar` registra una fila en `ProcesosHistorial`
**sin cambio de estado** (`EstadoAnterior = EstadoNuevo = 2`), con
`Observacion` = `Consulta de centrales: TransUnion (nueva); DataCrédito (Experian) (error: sin_credenciales)`
(variantes: `(reutilizada)`, `(nueva, forzada)`). No afecta a `propios`
(usa `EstadoAnterior IS NULL`) ni al reenvío de correo (solo estados 0/5).
Frontend debe mostrar estas filas como evento, no como transición.

### `/consulta-centrales-riesgo`

**Eliminada** (ruta y método), junto con `app/Http/Controllers/NewSoap.php`
(su lógica pasó a `TransUnionSoapClient`) y el bloque `transunion` de
`config/services.php` (unificado en `config/centrales.php` con las mismas
variables `TRANSUNION_*`). Solo la usaba `js/procesos.js` (`cargarCentrales`),
que Frontend rehará contra los endpoints nuevos. Hasta entonces, el panel
antiguo de centrales muestra «No fue posible obtener la consulta». Antes de
esta fase ya no funcionaba: consultaba un documento fijo, la extensión SOAP no
está habilitada y la llave no estaba en la ruta por defecto.

## 3. Contrato de API

Todas son `POST`, JSON, errores `{message, errors?}` con 403/422.

### Proceso (`submenu.accion:/lista-procesos` + `Procesos::puedeVer`)

Permiso: acción `centrales` (estado 2) o `aprobarCredito` (estado 4) para
estado/resultados; **consultar solo en estado 2** (en 4 es solo lectura → 422).

**`/centrales-estado`** `{idProceso}` →
```json
{"tratamientoCompleto":true,"puedeConsultar":true,"puedeForzar":true,"predeterminado":"simulado"|null,"vigenciaDias":30,
 "proveedores":[{"clave":"simulado","nombre":"Simulado (pruebas)","predeterminado":false,"ambiente":"simulado","simulado":true,
   "pendienteValidar":null,"vigente":{"fechaConsulta":"2026-10-07 15:36:58","vigenteHasta":"2026-11-06 15:36:58"}}]}
```
`proveedores` = habilitados y configurados (+ simulado si está activo).
`predeterminado` es la clave guardada solo si está disponible (existe, habilitado
y configurado); si no, `null` y ningún proveedor viene con `predeterminado:true`.
`pendienteValidar` trae texto para DataCrédito.

**`/centrales-consultar`** `{idProceso, proveedores:[1..2 claves], forzar?:bool}`
- 422: validación (`proveedores` vacío, >2, repetido, clave inexistente o simulado inactivo), proceso inexistente, acción no disponible por estado, tratamiento incompleto (`errors.tratamiento`).
- 403: sin acceso al proceso, rol sin acción `centrales`, o `forzar` sin `forzarConsultaCentrales`.
- 200:
```json
{"resultados":{
  "simulado":{"ok":true,"reutilizada":false,"simulado":true,
    "consulta":{ "...ResultadoCentral...": "...", "idConsulta":12,"fechaConsulta":"2026-10-07 15:36:58",
                 "vigenteHasta":"2026-11-06 15:36:58","vigente":true,"usuario":"Nombre","ambiente":"simulado","simulado":true}},
  "transunion":{"ok":false,"error":"TransUnion no tiene credenciales configuradas.","codigo":"sin_credenciales"}}}
```
Si un proveedor falla, los demás siguen. Nunca incluye la respuesta cruda.
Solo se reutiliza la consulta vigente de un proveedor **disponible** (habilitado y
configurado). Si el proveedor enviado está deshabilitado devuelve
`{ok:false, codigo:"deshabilitado"}` y si no tiene credenciales
`{ok:false, codigo:"sin_credenciales"}`, **aunque tenga una consulta vigente**
(no se reutiliza; el intento fallido queda registrado).

**`/centrales-resultados`** `{idProceso}` → `{"resultados":{"<clave>":{...consulta, "disponible":bool}}}`
Última consulta exitosa de **cada proveedor definido en `config/centrales.php`**
(incluidos los deshabilitados, sin credenciales y el simulado aunque esté
desactivado, como historial), cualquier ambiente; `{}` si no hay. No consulta.
`disponible` = el proveedor hoy está habilitado y configurado (el simulado,
además, activo por `.env`). `vigente` usa la misma regla que la precondición
2→3: proveedor disponible, mismo `Ambiente` actual del proveedor y
`VigenteHasta > ahora`. Una consulta de otro ambiente se mantiene en el
historial con `vigente:false`.

### Administración (`submenu.accion:/gestion-sitio` + rol 1; si no, 403)

**`/centrales-config-listar`** →
```json
{"predeterminado":"transunion"|null,"predeterminadoDisponible":false,"vigenciaDias":30,"simuladoHabilitado":false,
 "proveedores":[{"clave":"transunion","nombre":"TransUnion","habilitado":true,"editable":true,"configurado":false,
   "ambiente":"pruebas","predeterminado":true,"pendienteValidar":null,"ultimaConsulta":{"fecha":"...","usuario":"..."}|null}]}
```
**`/centrales-config-guardar`** `{predeterminado, habilitados:{transunion:bool, datacredito:bool}, vigenciaDias:1..365}`
→ misma respuesta que listar.
- `predeterminado`: clave de proveedor o `null` = ninguno (`""` equivale a `null`). El campo es obligatorio (`present`).
- La regla «habilitado y configurado» se valida **solo si el predeterminado cambia** respecto al guardado. Así se puede guardar la vigencia o los habilitados aunque el predeterminado guardado (p. ej. `transunion` del seed) no tenga credenciales. Pasar a `null` siempre está permitido.
- 422 si el predeterminado nuevo no está habilitado (según los `habilitados` enviados) o no tiene credenciales, si `habilitados` incluye `simulado` (se controla por `.env`) o una clave desconocida, si falta `predeterminado`, o si la vigencia está fuera de 1..365.
- `predeterminado` y `predeterminadoDisponible` en listar: la clave guardada y si hoy está disponible (existe, habilitada y configurada). Si se deshabilita el predeterminado sin cambiarlo, se guarda y queda `predeterminadoDisponible:false`.
- `null` se guarda como `Valor = ''` en `ConfiguracionCentrales`. Solo audita los valores que cambian.

**`/centrales-probar-conexion`** `{proveedor}` → `{"proveedor":"transunion","ok":false,"codigo":"sin_credenciales","mensaje":"..."}` (200).
Sin credenciales no hace ninguna llamada. Con credenciales: TransUnion descarga
el WSDL y verifica `consultaXml` (no consulta a nadie); DataCrédito hace
`GET endpoint?wsdl` y busca la operación.

## 4. Pruebas

- `php -l`: sin errores en los 16 archivos PHP tocados/creados.
- `phpunit --testsuite Unit`: **62 OK** (11 nuevas en `CentralesRiesgoTest` y 1 en `FlujoProcesoTest` para la precondición de centrales 2→3, sin BD):
  parser TransUnion con fixture (datos básicos, 5 obligaciones de 3 sectores,
  estados, totales en pesos ×1000, resumen, huella, score, alertas), registro
  único/nodos opcionales ausentes, montos en pesos, respuestas inválidas,
  sin credenciales/tipo no homologado, normalización de montos, Simulado
  (perfiles por último dígito, determinismo), vigencia/reutilización/plan,
  disponibilidad por config y ajustes, parser HC2 directo y envuelto en SOAP,
  escape de la solicitud HC2. Suite completa: 1 fallo previo y ajeno
  (`Tests\Feature\ExampleTest`, 302).
- `route:list` (6 rutas nuevas con su middleware), `route:cache` y `route:clear` sin errores.
- Integración contra PRUEBAS dentro de una transacción con **ROLLBACK**
  (proveedor Simulado; TransUnion y DataCrédito con credenciales vacías), 69
  verificaciones OK y la BD quedó igual (conteos de `ConsultasCentrales`,
  `ProcesosHistorial`, `ConfiguracionAuditoria` y contenido de
  `ConfiguracionCentrales`):
  - estado (solo simulado disponible, tratamiento completo, vigente null → luego vigente);
  - consultar nueva → reutilizada (mismo `idConsulta`) → forzar (admin) nueva;
  - ambos: simulado reutilizado + TransUnion `sin_credenciales`; DataCrédito `sin_credenciales` + simulado forzado OK;
  - filas guardadas (fallidas con `Exitosa=0`, `MensajeError`, sin vigencia) y sin respuesta cruda en el JSON;
  - historial: 5 eventos 2→2 con la observación esperada;
  - proceso 10 sin tratamiento: 422; validaciones 422; proceso inexistente 422;
  - analista (rol 5): forzar 403, sin forzar reutiliza, `puedeForzar:false`; asesor en proceso ajeno 403;
  - estado 4: consultar 422, estado/resultados 200;
  - admin: listar (`transunion` guardado, `predeterminadoDisponible:false`; estado con `predeterminado:null`); guardar vigencia sin cambiar el predeterminado no configurado 200; cambiar a `datacredito` sin credenciales 422; `null` 200; `""` = `null`; sin campo 422; `habilitados.simulado` 422; vigencia 0 422; cambiar a `simulado` 200 (`predeterminadoDisponible:true`); 5 filas de auditoría (`vigenciaDias 30>20`, `predeterminado transunion>''`, `habilitado.transunion 1>0`, `predeterminado ''>simulado`, `vigenciaDias 20>15`); repetir sin cambios no audita; TransUnion deshabilitado → `deshabilitado`; nueva consulta con vigencia 15 días;
  - probar conexión TransUnion/DataCrédito sin credenciales → `sin_credenciales`; simulado ok; inválido 422; no admin 403 en los 3 endpoints;
  - simulado desactivado por config: consultar 422, estado sin proveedores y `predeterminado:null`, listar con `simulado` guardado y `predeterminadoDisponible:false`;
  - 2→3: sin consultas 422 con el mensaje; solo consultas del simulado con el simulado desactivado 422; consulta simulada vencida 422; nueva consulta vigente con simulado activo 200 (estado 3);
  - consulta vigente de TransUnion (fila insertada) con TransUnion deshabilitado → `deshabilitado`, habilitado sin credenciales → `sin_credenciales` (no se reutiliza);
  - resultados: incluye TransUnion y simulado como historial con `disponible:false` (simulado desactivado), y `disponible:true` para el simulado al reactivarlo;
  - TransUnion habilitado sin credenciales con consulta vigente: `vigente:false` en resultados, no cuenta en `tieneVigente` y 2→3 422; con credenciales (config en memoria, sin llamar al proveedor) cuenta y resultados `vigente:true`, `disponible:true`; la misma consulta con `Ambiente` distinto sigue en resultados con `vigente:false`, no cuenta y 2→3 422.

## 5. Puesta en marcha

### Variables de `.env`

```
CENTRALES_PREDETERMINADO=transunion   # valor inicial; luego manda Administración del sitio
CENTRALES_VIGENCIA_DIAS=30            # valor inicial; luego manda Administración del sitio
CENTRALES_SIMULADO=                   # vacío: true solo con APP_ENV=local. En producción dejar vacío o false.

TRANSUNION_AMBIENTE=pruebas|produccion
TRANSUNION_WSDL=                      # URL del WSDL de InformacionComercial
TRANSUNION_USER=
TRANSUNION_PASSWORD=
TRANSUNION_KEY_PATH=                  # ruta absoluta a la llave privada PEM (fuera de public)
TRANSUNION_CERT_PATH=                 # ruta absoluta al certificado PEM
TRANSUNION_CODIGO_INFORMACION=5702
TRANSUNION_MOTIVO_CONSULTA=1
TRANSUNION_TIMEOUT=30
TRANSUNION_VERIFY_PEER=true
TRANSUNION_MONTOS_EN_MILES=true

DATACREDITO_AMBIENTE=pruebas|produccion
DATACREDITO_ENDPOINT=
DATACREDITO_USUARIO=
DATACREDITO_PASSWORD=
DATACREDITO_CODIGO_SUSCRIPTOR=
DATACREDITO_PRODUCTO=64
DATACREDITO_OPERACION=consultarHC2
DATACREDITO_NAMESPACE=http://ws.hc2.dc.com/v1
DATACREDITO_TIPO_IDENTIFICACION=1
DATACREDITO_CERT_PATH=                # opcional: certificado cliente (mTLS)
DATACREDITO_KEY_PATH=
DATACREDITO_KEY_PASSWORD=
DATACREDITO_TIMEOUT=30
DATACREDITO_VERIFY_PEER=true
DATACREDITO_MONTOS_EN_MILES=true
```

Un proveedor está "configurado" con: TransUnion → WSDL, usuario, clave, llave y
certificado; DataCrédito → endpoint, usuario y clave. Si se usa
`php artisan config:cache`, volver a ejecutarlo después de cambiar `.env`.

Requisitos del servidor: **habilitar `extension=soap` en `E:\xampp\php\php.ini`**
(hoy está comentada; sin ella TransUnion responde `conexion` con «La extensión
SOAP de PHP no está habilitada») y reiniciar Apache. `curl`/`openssl` ya están.

### Qué validar con TransUnion cuando haya contrato

1. URL del WSDL y endpoint de pruebas/producción; usuario y clave vigentes.
2. Llave y certificado para WS-Security (convertir el `.pfx/.jks` a PEM) y si
   siguen exigiendo firma RSA-SHA1 + WS-Addressing + Timestamp como el SoapUI de 2022.
3. Si `ParametrosConsultaDTO` acepta `primerApellido` (el WSDL de 2022 no lo
   trae; se envía y SOAP lo ignora si no existe).
4. `codigoInformacion` (5702) y `motivoConsulta` (1) del producto contratado.
5. Homologación de tipos de documento (`tipos_documento` en `config/centrales.php`; hoy solo CC → 1).
6. Con una respuesta real: raíz y nombres de nodos (`Tercero`, sectores,
   `SaldoObligacion`, `EdadMora`, `HuellaConsulta`, `Score/Puntaje` y su rango),
   formato numérico (separadores) y si los montos vienen en miles.
   Ajustar `TransUnionProveedor::interpretar` y el fixture.
7. `probarConexion` en el ambiente de pruebas antes de habilitarlo.

### Qué validar con Experian (DataCrédito) cuando haya contrato

Ver sección 6. Todo el mapeo está en `DatacreditoProveedor::mapear` y la
petición en `DatacreditoProveedor::solicitud`.

## 6. Supuestos sobre DataCrédito (pendientes de validar con la especificación oficial)

- Servicio SOAP HC2 (Historia de Crédito) con operación `consultarHC2`,
  namespace `http://ws.hc2.dc.com/v1`, elemento `<solicitud>` con
  `clave, identificacion, primerApellido, producto, tipoIdentificacion, usuario`
  (+ `codigoSuscriptor` si se configura). Producto por defecto `64`.
- Autenticación por usuario/clave en el cuerpo y, si aplica, certificado cliente.
- Respuesta: XML con `Informe` (directo o como texto escapado dentro del
  `return` del SOAP). Atributos supuestos:
  - `Informe@fechaConsulta`, `@respuesta`, `@identificacionDigitada`;
  - `NaturalNacional@nombres/@primerApellido/@segundoApellido/@nombreCompleto`, `Identificacion@estado/@fechaExpedicion/@ciudad/@numero`, `Edad@min/@max`;
  - cuentas `TarjetaCredito`, `CuentaCartera`, `CuentaAhorro`, `CuentaCorriente` con `@entidad, @numero, @fechaApertura, @sector (1 financiero, 2 cooperativo, 3 real, 4 telecomunicaciones), @calidad`, `Valores/Valor@saldoActual, @saldoMora, @cuota, @valorInicial, @diasMora, @cuotasMora, @fecha`, `Estados/EstadoCuenta@codigo`;
  - `Score@puntaje` (sin rango ni nivel);
  - `Consulta@entidad, @fecha, @razon` (huella).
- Estado: `mora` si `saldoMora > 0` o `diasMora > 0`; si no, `al_dia`. **No se
  distinguen cuentas cerradas** hasta conocer los códigos de `EstadoCuenta`.
- Montos en miles de pesos (`DATACREDITO_MONTOS_EN_MILES=true`).
- `estadoDocumento` se toma tal cual (código); la alerta de documento no
  vigente se activará si el código no contiene «vigente»: revisar con la tabla oficial.
- `probarConexion` usa `GET <endpoint>?wsdl`.

## 7. Pendientes

- Frontend: rehacer el panel de centrales (estado 2 y lectura en 4) con
  `centrales-estado/consultar/resultados`, y la sección de Administración del
  sitio con `centrales-config-*`. Mostrar los eventos 2→2 del historial.
- Habilitar la extensión SOAP en PHP para TransUnion.
- Validar TransUnion y DataCrédito con respuestas reales (secciones 5 y 6).
- Rotar las credenciales que quedaron en texto plano en
  `project/app/Http/Controllers/transunion/request.xml` y
  `project/config/transunion/pruebasArarFinanciera-soapui-project.xml`
  (siguen en disco y en el historial de git) y sacar llaves/certificados de
  `app/` y `config/` hacia una ruta fuera del proyecto.
- Aplicar el DDL en producción (`ArarFinanciera`) cuando se apruebe; el script
  actual se niega a correr fuera de PRUEBAS.
- Un endpoint de respuesta cruda para rol 1 no se implementó (no requerido).
