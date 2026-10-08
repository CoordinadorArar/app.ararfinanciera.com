# Crédito – Fase 3: gestión de procesos de crédito

Script: `documentacion/credito-fase3-ddl.sql` (equivale a la migración
`project/database/migrations/2026_10_07_120000_credito_fase3_procesos.php`).
Configuración del flujo: `project/config/procesos.php`.
Servicio: `App\Services\FlujoProceso`.

## 1. Esquema aplicado en ArarFinanciera_PRUEBAS

Ejecutado el 2026-10-07 con PDO directo (pdo_sqlsrv) contra
`ArarFinanciera_PRUEBAS` (`DB_DATABASE_DEMO`). No se escribió en
`ArarFinanciera`. Se ejecutó **dos veces seguidas** sin error y sin duplicar
filas: es idempotente.

| Objeto | Resultado |
|---|---|
| `MotivosRechazo` (IdMotivo int PK, NombreMotivo nvarchar(150), EstadoMotivo bit default 1) | creada, 6 motivos |
| `ProcesosHistorial` (IdHistorial, IdProceso FK, EstadoAnterior null, EstadoNuevo, IdMotivo FK null, Observacion nvarchar(500) null, IdUsuario, Fecha default getdate()) | creada, índice por IdProceso |
| `ProcesosDocumentos` (IdProcesoDocumento, IdProceso FK, IdDocumento FK a DocumentosSolicitados, Estado, Ruta, NombreArchivo, IdMotivo FK null, Observacion, IdUsuario, Fecha, updated_at) | creada, índice único `(IdProceso, IdDocumento)` |

### Motivos de rechazo

No había tabla: los motivos vivían solo en el `<select id="motivosRechazo">`
oculto de `procesos/aprobar-credito.blade.php`. Se sembraron con los mismos IDs:

| Id | Motivo |
|---|---|
| 1 | No tiene cupo (también lo usa el rechazo automático sin cupo de la Fase 2) |
| 2 | Mal hábito de pago |
| 3 | Compra cartera en proceso |
| 4 | No cumple política de antigüedad |
| 5 | Proceso jurídico en curso |
| 6 | No cumple política compra de cartera |

### Backfill

**Historial** (una fila por proceso sin historial, `EstadoAnterior` NULL,
observación "Estado inicial (migración)", `IdUsuario` = `Procesos.IdUsuario`,
`Fecha` = `FechaCreacion`): **5 filas** (procesos 6, 8, 9, 10 en estado 2 y 7
en estado 5). Segunda ejecución: 5 filas (sin duplicados).

**Documentos** (desde los CSV `DocumentosCargados`/`DocumentosAprobados` y el
documento 2 desde `TratamientoDatos.IdProceso`): **20 filas**.

| Proceso | CSV cargados / aprobados | Filas | Estado resultante |
|---|---|---|---|
| 6 | `7,8,9,5,1,3,4,10,2,` / vacío | 9 | 9 cargado |
| 7 | `2,3,4,5,7,8,1,9,10,` / `2,3,4,5,7,8,1,` | 9 | 7 aprobado, 2 cargado (9 y 10) |
| 8 | `2,` / vacío | 1 | doc 2 cargado |
| 9 | `2,` / vacío | 1 | doc 2 cargado |
| 10 | vacío | 0 | sin tratamiento ni documentos |

Rutas reconstruidas:
- Documento 2: `app/public/tratamiento_datos/<archivo de TratamientoDatos.RutaFormato>`.
- Resto: la que armaba el código anterior al subir,
  `app/public/documentos-soporte-<doc>-<proceso>/<doc>-<proceso>-<NombreDocumento>.pdf`
  (los nombres de `DocumentosSolicitados` ya están en CamelCase sin espacios).
  Verificado: `ver-documento-soporte/1/6` descarga el PDF real del SFTP (296 KB).

Nota: el proceso 7 está aprobado (estado 5) con los documentos 9 y 10 solo
"cargados": el flujo anterior no exigía aprobarlos. No se corrige (es histórico).

## 2. Roles y matriz de permisos

Roles reales (`identidad.Roles`, lectura): se mantienen, no se crean roles.

| IdRol | Rol | Usuarios hoy |
|---|---|---|
| 1 | Administrador | 1 |
| 2 | Gerente | 1 |
| 3 | Comité credito | 0 |
| 4 | Consulta Centrales | 0 |
| 5 | Analista credito | 0 |
| 6 | Asesor Comercial | 2 |
| 7 | Contador | 1 |
| 8 | Auxiliar Administrativo | 1 |

Fuentes que no coincidían y cómo se unificaron:

| Etapa | main.blade | ProcesosController | Procesos.php (bandeja) | Decisión |
|---|---|---|---|---|
| Centrales (2) | 1,2,5 | 1,2,5 | 5 ve 2 | 1,2,4,5 (se suma 4 por el nombre del rol) |
| Cargar documentos (3) | 1,2,6 | 1,2,4 | 4 ve 3 | 1,2,4,6 (unión) |
| Aprobar documentos (3) | 1,2,5 | 1,2,5 | 5 ve 3 | 1,2,5 |
| Aprobar crédito (4) | 1,2,3 | 1,2,6 | 3 no veía 4 | 1,2,3 (el asesor no aprueba su propio crédito; el comité ahora ve 4) |

Matriz final (rol 1 lo puede todo aunque no esté listado):

| Rol | Transiciones | Acciones | Bandeja (estados) | Alcance |
|---|---|---|---|---|
| 1 Administrador | todas | todas | 0-5 | todos los procesos |
| 2 Gerente | 1→2, 2→3, 3→4, 4→5, 1/2/3/4→0 | todas | 0-5 | todos |
| 3 Comité credito | 4→5, 4→0 | aprobarCredito, editarCredito, reenviarCorreo, rechazar (en 4) | 1-5 | todos |
| 4 Consulta Centrales | 2→3, 2→0 | centrales, cargarDocumentos, rechazar (en 2) | 2, 3 | todos |
| 5 Analista credito | 2→3, 3→4, 2→0, 3→0 | centrales, aprobarDocumentos, rechazar (en 2 y 3) | 2, 3, 4 | todos |
| 6 Asesor Comercial | 1→2, 1→0 | registro, cargarDocumentos, rechazar (en 1) | 0-5 | **solo sus procesos** |
| 7 Contador | ninguna | ninguna | ninguno | — |
| 8 Auxiliar Administrativo | ninguna | ninguna | ninguno | — |

- Transiciones válidas: 1→2, 2→3, 3→4, 4→5 y 1|2|3|4→0 (motivo obligatorio).
  5 y 0 son finales.
- Precondiciones: 2→3 exige tratamiento de datos completo
  (`Procesos::estadoTratamiento`, misma lógica de `estadoTercero` de la Fase 2);
  3→4 exige que todos los documentos con `PagaduriasDocumentos.Requerido = 1`
  (o NULL) estén aprobados.
- Varios roles por usuario: se suman permisos. "Solo sus procesos" aplica si
  todos sus roles están en `soloPropios` (hoy solo el 6).
- Proceso propio: `Procesos.IdUsuario` = usuario, o el usuario registró la
  creación (fila de historial con `EstadoAnterior` NULL).
- Etiquetas de acción (`accionPrincipal` = primera aplicable sin contar
  rechazar/editarCredito/reenviarCorreo): `registro`, `centrales`,
  `cargarDocumentos`, `aprobarDocumentos`, `aprobarCredito`, `editarCredito`,
  `reenviarCorreo` (estados 0 y 5), `rechazar`.

## 3. Decisiones

- **CSV `DocumentosCargados`/`DocumentosAprobados`**: dejan de escribirse.
  Todos los lectores de backend se migraron a `ProcesosDocumentos`
  (`estadoTercero` de la Fase 2, `verificar-todos-los-documentos`,
  `gestion-documentos-proceso`, precondición 3→4). Se quitó también la
  escritura `'2,'` de `Terceros::guardarFormatoTratamientoDatos`. Las columnas
  se conservan (no se borran) y `mostrar-info-proceso` las sigue devolviendo
  congeladas solo para el `js/procesos.js` actual, que Frontend reemplaza.
- **Documento 2 (tratamiento)**: si hubo aceptación digital (fila de
  `TratamientoDatos` del proceso con `Aceptado = 1` sin archivo) cuenta como
  `aprobado` con `origen: "digital"`. Si se cargó archivo, se revisa como los
  demás. La carga del registro (`subir-archivo-tratamiento-datos`) también
  registra la fila en `ProcesosDocumentos`. Rechazar el documento 2 borra la
  última fila de `TratamientoDatos` **del proceso** (`eliminarTratamientoDatos`
  pasa a `IdProceso`).
- **Rechazar un documento** no borra el archivo del SFTP: queda la evidencia;
  la nueva carga genera otro nombre y reemplaza la fila.
- **Nombre de archivo** armado en el servidor:
  `<documento>-<proceso>-<idDocumento>-<YmdHis>-<6 aleatorios>.pdf`; se guarda
  la ruta real y "ver" usa esa ruta. Solo se marca `cargado` si `putFileAs`
  respondió bien. PDF, máx. 10 MB. No se puede reemplazar un documento aprobado.
- **Correo de decisión del comité**: se envía después de confirmar la
  transición desde 4 (4→5 aprobado y 4→0 rechazado). Si falla, el estado queda
  aplicado y la respuesta trae `correo: false` y `message` para que la UI avise
  y permita reenviar con `enviar-email-credito`. El texto libre es la
  `observacion` de la transición (o `texto` en el reenvío), escapado.
- **Bandeja**: sin filtro de estado se listan los visibles excepto 0
  (igual que antes); los contadores son de toda la bandeja visible, sin
  aplicar búsqueda ni estado.
- **Compatibilidad del registro**: `editar-estado-proceso` con
  `estado=pendiente` sigue respondiendo `{res:'edited'}` (1→2) o
  `{res:'canceled'}` (sin cupo, 1→0 motivo 1), ahora vía `FlujoProceso`. Con
  cualquier otro estado delega en `cambiar-estado-proceso`.
- **Historial**: creación (null→1) y rechazo sin cupo (null→0 o 1→0, motivo 1,
  "Sin cupo disponible") desde `guardar-datos-financieros`; cada transición
  con usuario, motivo y observación.
- **Limpieza**: eliminadas las rutas `mostrar-valores-proceso` e
  `iniciar-proceso-credito` (solo las usaba `js/calcular.js`, que ninguna vista
  carga) y el método `mostrarValoresProceso`. `AdminController::procesoSoloInfo`
  (usado por `js/lista-asesores.js`) llama ahora a `Procesos::mostrarProcesos`.
  `RoutesController::listaProcesos` ya no arma la tabla en el servidor
  (la bandeja se carga por AJAX).

## 4. Pruebas

- `php -l` sin errores en todos los archivos tocados.
- `phpunit --testsuite Unit`: 50 pruebas OK (13 nuevas en
  `FlujoProcesoTest`, sin BD: matriz 6×6 estados × 8 roles, administrador,
  suma de roles, estados finales, motivo obligatorio, precondiciones,
  visibilidad, acciones). La suite completa tiene 1 fallo previo y ajeno:
  `Tests\Feature\ExampleTest` (GET `/` redirige a login, 302).
- `route:list`, `route:cache` y `route:clear` sin errores.
- Pruebas de integración contra PRUEBAS dentro de una transacción con
  ROLLBACK (al final el historial volvió a 5 filas):
  - bandeja admin (5 registros, contadores), paginación + búsqueda, filtro inválido 422;
  - 2→3 con tratamiento completo OK; 2→3 sin tratamiento 422; 3→4 con documentos pendientes 422 (lista los nombres);
  - 5→0 422; rechazo sin motivo 422; motivo inexistente 422; rechazo con motivo 200 e historial con nombres;
  - aprobar documentos, aprobar no cargado 422, rechazar sin observación 422, `verify`, 3→4 OK, 4→5 OK, detalle completo;
  - asesor (usuario 6): bandeja vacía, 403 en detalle/cambio/ver/estado-registro de procesos ajenos; con proceso propio lo ve, pero 2→3 y 2→0 dan 403 por rol;
  - registro `pendiente` 1→2 `edited`, repetido 422, sin cupo `canceled` con historial motivo 1;
  - acciones fuera de etapa (subir, editar crédito, reenviar correo) 422;
  - ver documento real desde SFTP 200 PDF; documento no cargado 404;
  - plantilla de correo renderizada con texto libre escapado.
  No se envió ningún correo real ni se subió ningún archivo.

## 5. Complementos (cierre de brechas con la UI)

- `lista-procesos-filtro`: cada registro trae `monto` (valor solicitado). Orden
  en el servidor con `orden` ∈ {cliente, monto, estado, fecha} y `direccion`
  ∈ {asc, desc}; por defecto fecha (última actualización) desc; desempate por
  IdProceso en la misma dirección.
- Historial (`historial-proceso` y `detalle-proceso.historial`): campo `rol`
  con el/los nombres de rol del autor (identidad, separados por coma).
- `detalle-proceso.financiero`: `cuota` (sin seguro), `seguro`,
  `porcentajeSeguro`, `cuotaTotal`. `cuota`/`seguro` se recalculan con la tasa
  guardada en el proceso y la regla de edad vigente; `cuotaTotal` es la
  guardada (`Procesos.ValorCuota`). En procesos anteriores a la Fase 1 pueden no
  sumar igual (ej.: proceso 6: 96.718 + 4.350 ≠ 116.717 guardado).
- `calcular-condiciones-proceso`: mismo cálculo que `editar-proceso`
  (incluido `nuevoCupo`), sin guardar; exige la acción `editarCredito`.
- `cambiar-estado-proceso`: `texto` opcional (máx. 2000) como cuerpo del
  correo al salir del estado 4; si no llega se usa la `observacion`.

Verificado en PRUEBAS con ROLLBACK: los 4 órdenes, orden/dirección inválidos
422, vista previa 200 sin modificar el proceso, vista previa sobre el cupo 422
con `montoMaximo`, vista previa fuera del estado 4 422, `texto` > 2000 422,
`rol` = "Administrador" en el historial.

## 6. Observaciones de QA cerradas

- Registro para roles `soloPropios`: si el último proceso del tercero es de
  otro asesor y está activo (1-4), `validar-documento`, `estado-registro` (por
  idTercero) y `guardar-datos-personales` responden sin datos
  (`paso:'finalizado'`, «Este cliente tiene un proceso activo con otro
  asesor.»; en guardar, 422). Si está en 0 o 5 se continúa en paso 2 sin datos
  de ese proceso (`proceso:null`) y se crea un proceso nuevo.
- `enviar-email-credito` en estado 0: solo si el último movimiento del
  historial es 4→0; si no, 422.
- `consulta-centrales-riesgo`: exige la acción `centrales` (estado 2) o
  `aprobarCredito` (estado 4), además del acceso al proceso.
- `cambiar-estado-proceso`: cualquier excepción al preparar o enviar el correo
  se registra en el log y responde 200 con `correo:false` y `message`.
- Estado 1 renombrado a «Registro».

Verificado en PRUEBAS con ROLLBACK (sin enviar correos: SMTP apuntado a un
puerto cerrado y plantilla que lanza excepción).
