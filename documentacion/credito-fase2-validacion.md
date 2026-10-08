# Crédito – Fase 2: registro del tercero e ingreso de datos

Script: `documentacion/credito-fase2-ddl.sql` (equivale a la migración
`project/database/migrations/2026_10_07_110000_credito_fase2_registro.php`).

## 1. Esquema aplicado en ArarFinanciera_PRUEBAS

Ejecutado el 2026-10-07 con PDO directo (pdo_sqlsrv) contra
`ArarFinanciera_PRUEBAS` (`DB_DATABASE_DEMO`). No se escribió en
`ArarFinanciera`. Se ejecutó **dos veces seguidas** sin error: es idempotente.

| Objeto | Resultado |
|---|---|
| `Procesos.EmailTratamientoEnviadoA` | `nvarchar(100) NULL` creada |
| `Procesos.FechaEmailTratamiento` | `datetime NULL` creada |
| `terceros_documentotercero_unique` | índice único sobre `Terceros.DocumentoTercero` creado |
| `TratamientoDatos.IdProceso` | `bigint NULL` creada (corrección QA) |

Por qué en `Procesos` y no en `TratamientoDatos`: `TratamientoDatos` es por
tercero, `Aceptado` es `NOT NULL` y el resto del código interpreta la sola
existencia de una fila como autorización otorgada. El correo se envía por
proceso (el enlace firmado lleva `idProceso`).

### Duplicados de documento

Consulta (solo lectura) ejecutada antes de crear el índice:

```sql
SELECT DocumentoTercero, COUNT(*) AS n
FROM Terceros
GROUP BY DocumentoTercero
HAVING COUNT(*) > 1;
```

Resultado en PRUEBAS: **0 filas** (6 terceros, todos con documento distinto),
por eso el índice se creó. Producción no se consultó.

El DDL y la migración solo crean el índice si esa consulta no devuelve filas
(los `NULL` cuentan como un mismo valor). Si hay duplicados el script no falla:
emite `PRINT` y deja el índice pendiente.

Cómo depurar antes de volver a ejecutar el script:

```sql
SELECT t.IdTercero, t.DocumentoTercero, t.NombresTercero, t.ApellidosTercero,
       (SELECT COUNT(*) FROM Procesos p WHERE p.IdTercero = t.IdTercero) AS procesos,
       (SELECT COUNT(*) FROM TratamientoDatos d WHERE d.IdTercero = t.IdTercero) AS tratamientos
FROM Terceros t
WHERE t.DocumentoTercero IN (SELECT DocumentoTercero FROM Terceros GROUP BY DocumentoTercero HAVING COUNT(*) > 1)
ORDER BY t.DocumentoTercero, t.IdTercero;
```

1. Por cada documento, elegir el tercero que se conserva (el que tenga procesos
   en estado más avanzado o, en empate, el `IdTercero` más reciente).
2. Reasignar a ese tercero los hijos de los demás:
   `UPDATE Procesos SET IdTercero = @conservar WHERE IdTercero = @duplicado;`
   y lo mismo en `TratamientoDatos` y en cualquier otra tabla con `IdTercero`
   (`ReferenciasTerceros`, `PagaduriaTerceros` si existen datos).
3. `DELETE FROM Terceros WHERE IdTercero = @duplicado;`
4. Repetir la consulta de duplicados; con 0 filas, volver a ejecutar el DDL.

Hacerlo dentro de una transacción y con respaldo previo.

## 2. Decisiones de validación (datos personales)

`TiposDocumentos` en PRUEBAS solo tiene `1 = Cédula de ciudadanía`. El tipo se
identifica por nombre (`App\Services\ValidadorTercero::tipoDocumento`):
`nit`/`tributari` → NIT, `extranjer`/`CE` → CE, `pasaporte`/`PA`/`PP` → PA,
`ciudadan`/`CC` → CC; cualquier otro usa la regla genérica.

| Campo | Regla |
|---|---|
| CC | numérica, 5 a 10 dígitos |
| CE | numérica, 3 a 10 dígitos |
| NIT | numérico, 6 a 10 dígitos, DV opcional con guion (`900123456-7`); si viene, se valida con el algoritmo DIAN y se guarda sin DV |
| Pasaporte | numérico, 5 a 10 dígitos (límite de la columna INT) |
| Otro tipo | alfanumérico, 3 a 15 |
| Capacidad | `Terceros.DocumentoTercero` es `int`: además debe ser numérico y ≤ 2147483647 |
| Tipo, departamento | deben existir |
| Ciudad | debe existir en `Municipios` con el `IdDepartamento` enviado |
| Fecha de nacimiento | `Y-m-d` o `Y/m/d`, no futura, edad ≥ 18 y < 100 |
| Fecha de expedición | no futura y ≥ fecha de nacimiento + 18 años |
| Teléfono | se limpian espacios, guiones, puntos y paréntesis; celular `3` + 9 dígitos, o fijo de 7 dígitos (no empieza por 0) u 8 a 10 dígitos (no empieza por 0 ni 3) |
| Correo | `email:rfc,filter`, máx. 100 |
| Nombres / apellidos | máx. 40 (tamaño de la columna) |
| Dirección / lugar de expedición | máx. 70 |

Formato de errores elegido: **422 `{message, errors}`**, igual que la Fase 1.
Las claves de `errors` son los nombres de los campos del formulario, así que
`showErrors(err.data)` sigue pintando campo por campo. Como `makeOptionsFetch`
lanza en respuestas no-OK, el front debe llamarlo con `silencioso=true` y en el
`catch` usar `showErrors(error.data)`.

## 3. Pruebas ejecutadas

- `php -l` sin errores en todos los archivos tocados.
- `phpunit --testsuite Unit`: 38 pruebas, 133 aserciones, OK
  (nuevas: `tests/Unit/ValidadorTerceroTest.php` y
  `EvaluadorFormulaTest::test_valores_operacion_recupera_rubros_guardados`).
- `route:list` muestra las rutas nuevas con `auth` y
  `CheckSubmenuAccion:/form-crear-tercero`; `route:cache` y `route:clear` OK.
- Prueba de humo en PRUEBAS dentro de una transacción con `ROLLBACK`:
  alta de tercero, segundo envío del mismo documento (actualiza el mismo
  `IdTercero`, no duplica), documento de otro tercero (422), vista previa
  (no inserta procesos), confirmación doble (mismo `IdProceso`), sin cupo
  (proceso en estado 0, `res: sinCupo`) y `estado-registro` en cada caso.
  El rollback dejó identidades consumidas (huecos en `IdTercero`/`IdProceso`),
  sin filas.
- El PDF en blanco de `/formato-tratamiento-datos` se genera (dompdf, inline).

## 4. Notas

- `insertGetId` en sqlsrv usa `PDO::lastInsertId()` (`@@IDENTITY` de la
  sesión). No hay triggers en `ArarFinanciera_PRUEBAS` ni en `Protdatos`, así
  que equivale a `SCOPE_IDENTITY()`.
- `Protdatos.Comprobante`: la identidad es `ID`; `ComprobanteID` no es
  identidad y en los registros de los otros sistemas vale lo mismo que `ID`.
  Ahora se inserta, se toma `ID` con `insertGetId` y se actualiza
  `ComprobanteID = ID`, y se usa ese valor en `Autorizacion`, todo en una
  transacción de la conexión `protdatos`. `Ruta` guarda la ruta real en el
  servidor SFTP (`SFTP_ROOT` + `/app/public/tratamiento_datos/<archivo>`).
  `TratamientoDatos.RutaFormato` conserva el formato relativo porque
  `gestionDocumentosSoporte`/`verDocumentoSoporte` lo parten por `/`.
- La conexión `protdatos` no cambia con el ambiente demo: cargar el formato
  desde demo escribe en `Protdatos` real (comportamiento previo).
- Despliegue: como en la Fase 1, la migración no queda registrada en
  `migrations` de PRUEBAS; ejecutar el DDL (ajustando la guarda de
  `DB_NAME()`) o la migración contra la base correcta. El código nuevo lee
  `Procesos.EmailTratamientoEnviadoA`/`FechaEmailTratamiento`: sin el DDL,
  `/estado-registro` y `/validar-documento` fallan.

## 5. Correcciones tras QA

- `/calcular-datos-financieros` y `/guardar-datos-financieros` responden 422 `{message}` si `estado-registro` del tercero no es `paso:2` (proceso en 2, 3, 4 o 5). En la confirmación se repite la verificación dentro de la transacción, después de `SELECT ... WITH (UPDLOCK, HOLDLOCK)` sobre todos los procesos del tercero; probado con dos sesiones: la segunda queda bloqueada (error 1222 con `LOCK_TIMEOUT`).
- `ingresos` y `valorSolicitado`: `required|numeric|gt:0|max:2147483647`, 422 `{message, errors}`.
- Tratamiento por proceso: `TratamientoDatos.IdProceso` se llena al cargar el formato y al aceptar por correo; `documentoCargado` = `DocumentosCargados` del proceso contiene 2 o fila del proceso con ruta; `aceptado` = fila del proceso con `Aceptado = 1` sin ruta. Las filas históricas se asocian con el backfill (sección 6).
- Doble envío sin cupo: si existe un proceso en estado 0 del mismo tercero y usuario, mismo valor y cuotas, creado hace menos de 2 minutos, se devuelve ese `idProceso`.
- Todo probado en PRUEBAS con ROLLBACK; DDL reaplicado dos veces sin error.

## 6. Backfill de TratamientoDatos.IdProceso

`TratamientoDatos` no tiene columna de fecha, así que cada fila con `IdProceso` NULL recibe el último proceso del tercero (`ORDER BY FechaCreacion DESC, IdProceso DESC`). Las filas de terceros sin procesos quedan en NULL. Solo toca filas en NULL, así que es idempotente. Está en el DDL (vía `EXEC`, porque la columna puede crearse en el mismo lote) y en la migración.

Aplicado dos veces en PRUEBAS sin error. Filas asociadas: 8→6, 9→7, 11→8, 13→9.

Comparación del paso de cada proceso en estado ≥ 2 entre la lógica anterior (por tercero) y la actual (por proceso), antes y después del backfill:

| Proceso | Estado | Antes (por tercero) | Ahora (por proceso, tras backfill) |
|---|---|---|---|
| 6 | 2 | finalizado | finalizado |
| 7 | 5 | finalizado | finalizado |
| 8 | 2 | finalizado | finalizado |
| 9 | 2 | finalizado | finalizado |
| 10 | 2 | 3 | 3 |

Regresiones de finalizado a paso 3: **0**. En PRUEBAS los procesos con tratamiento también tienen el documento 2 en `DocumentosCargados`, así que se probó aparte, con ROLLBACK, el caso de aceptación digital sin `IdProceso`: antes del backfill el proceso 10 daba paso 3 y después da `finalizado`.

Limitación: si un tercero ya tenía un proceso posterior a su autorización histórica (por ejemplo, un reintento tras un rechazo), la autorización queda asociada a ese último proceso.
