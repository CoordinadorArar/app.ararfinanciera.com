# Crédito – Fase 1: validación del esquema en ArarFinanciera_PRUEBAS

Script: `documentacion/credito-fase1-ddl.sql` (equivale a la migración
`project/database/migrations/2026_10_07_100000_credito_fase1_pagadurias.php`).

Ejecutado el 2026-10-07 con PDO directo (pdo_sqlsrv) contra
`ArarFinanciera_PRUEBAS` (conexión demo, `DB_DATABASE_DEMO`). No se escribió en
`ArarFinanciera`. El script se ejecutó **dos veces seguidas** sin error, así que
es idempotente.

### Re-ejecución tras la corrección de QA (seeds no destructivos)

- `UsaReglaSMMLV = 1` para 6 y 8 solo se aplica en la ejecución que **crea** la
  columna (`@reglaCreada` en el DDL, `$reglaCreada` en la migración).
- Las reglas de edad solo se siembran en pagadurías **sin ninguna regla**.

Prueba en PRUEBAS: se puso `UsaReglaSMMLV = 0` en la pagaduría 6, se ejecutó el
DDL dos veces más (sin error) y se consultó:

```sql
SELECT IdPagaduria, UsaReglaSMMLV FROM Pagadurias WHERE IdPagaduria IN (6, 8);
SELECT COUNT(*) reglas, COUNT(DISTINCT IdPagaduria) pagadurias FROM PagaduriasReglasEdad;
```
Resultado: 6 → `0` (no se pisó), 8 → `1`; reglas 42 en 14 pagadurías (sin
duplicados). Después se restauró `UsaReglaSMMLV = 1` en la 6.

### Nota de despliegue

La migración `2026_10_07_100000_credito_fase1_pagadurias` **no está registrada
en la tabla `migrations` de PRUEBAS** (el esquema se aplicó con el DDL; la
última migración registrada allí es de 2022). Además, `php artisan migrate`
usa la conexión por defecto (`DB_DATABASE`, producción), no la de demo. El
despliegue debe hacerse de forma controlada: ejecutar el DDL equivalente
(ajustando la guarda de `DB_NAME()`) o la migración apuntando explícitamente a
la base deseada, y registrar la fila en `migrations` para que no se repita.

Motor: Microsoft SQL Server 2014 SP3 (nivel de compatibilidad 120). No hay
`JSON_VALUE`, `CREATE OR ALTER` ni `DROP ... IF EXISTS`.

## 1. Estado previo encontrado

- `Pagadurias` solo tenía `IdPagaduria` (bigint identity) y `NombrePagaduria`
  (varchar(50)). **`EstadoPagaduria` no existía**, aunque la migración de 2022
  la declara (`estadoPagaduria`). Se creó como `bit not null default 1`.
- `CuposConfigCalculos.TipoDescuentoMaximo` es `char(1)`.
- `ValoresVariables`: `TasaInteres = 2.13` (en % mensual) y
  `SalarioMinimoMensual = 2000000`.

## 2. Consultas de verificación y resultado

```sql
SELECT IdPagaduria, NombrePagaduria, EstadoPagaduria, UsaReglaSMMLV, UmbralSMMLV
FROM Pagadurias ORDER BY IdPagaduria;
```
14 filas (IDs 1–10 y 13–16), todas con `EstadoPagaduria = 1` y `UmbralSMMLV = 2.00`.
`UsaReglaSMMLV = 1` solo en 6 (FIDUPREVISORA) y 8 (FUERZA AEREA).

```sql
SELECT COUNT(*) reglas, COUNT(DISTINCT IdPagaduria) pagadurias FROM PagaduriasReglasEdad;
SELECT EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro, COUNT(*) n
FROM PagaduriasReglasEdad GROUP BY EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro;
```
| reglas | pagadurías |
|---|---|
| 42 | 14 |

| EdadMin | EdadMax | PlazoMaximo | PorcentajeSeguro | n |
|---|---|---|---|---|
| 18 | 70 | 120 | 0.003000 | 14 |
| 71 | 74 | 48 | 0.003000 | 14 |
| 75 | 99 | 48 | 0.005625 | 14 |

```sql
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ConfiguracionAuditoria' ORDER BY ORDINAL_POSITION;
```
`Id bigint NO`, `Tabla nvarchar NO`, `IdRegistro bigint NO`, `Campo nvarchar NO`,
`ValorAnterior nvarchar(max) YES`, `ValorNuevo nvarchar(max) YES`,
`IdUsuario bigint YES`, `Fecha datetime NO (getdate())`.

```sql
SELECT name FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID('PagaduriasReglasEdad');
```
`pagaduriasreglasedad_idpagaduria_foreign`.

Consultas de la aplicación verificadas en PRUEBAS (solo lectura):

- `LEFT JOIN` de `Admin::mostrarInfoPagaduria` con filtro de tipo en el `ON`:
  pagaduría 6 con tipo `$` devuelve la fila de la pagaduría con fórmula `NULL`
  (ya no se rompe).
- Selección de fórmula sin regla SMMLV (pagaduría 5): primera fórmula no vacía
  por `IdConfigCalculo` → `IdConfigCalculo 5` (`$`).
- Consulta de última auditoría por pagaduría: ejecuta sin error (sin filas aún).
- Catálogo de pagadurías activas (`EstadoPagaduria = 1`): 14.

## 3. Reglas de edad (decisión que negocio debe revisar)

El seed unifica dos reglas que antes estaban separadas en el código:

- Registro (`form-datos.js`): 120 cuotas hasta 70 años y 48 desde 71.
- Seguro (`calcularValorCuotas`): 0.003 hasta 74 años y 0.005625 desde 75.

Resultado: 18–70 → 120 meses y 0.003; 71–74 → 48 meses y 0.003; 75–99 → 48
meses y 0.005625. **El simulador antes ofrecía 96 meses (18–74) y 60 meses
(75+)**, y `procesos.js` (edición en lista de procesos) usaba 120/84/72/48.
Ahora todo sale de `PagaduriasReglasEdad`. Negocio debe validar estos valores
en la pantalla de administración de pagadurías.

## 4. Convención de `TipoDescuentoMaximo` (confirmada)

En el código previo (`ProcesosController`, IDs [6,8]):
`ingresos > 2 × SMMLV → '%'`, en otro caso `'$'`. Las fórmulas lo confirman:
las `'$'` restan `salarioMinimoMensual` (garantizar un mínimo para ingresos
bajos) y las `'%'` dividen entre 2 (descuento máximo del 50 %).

- `UsaReglaSMMLV = 1`: ingresos > `UmbralSMMLV × SMMLV` → `'%'`; si no → `'$'`.
- `UsaReglaSMMLV = 0`: se usa la primera fórmula no vacía (`ORDER BY IdConfigCalculo`),
  sin importar el tipo.
- Al crear una pagaduría se crea una fila `'%'` (todas las pagadurías
  existentes sin regla usan `'%'`) y, si `UsaReglaSMMLV = 1`, también `'$'`.
  Al activar la regla se crea la fila que falte. Las filas nuevas quedan con la
  configuración vacía y no se usan hasta que se edita la fórmula.

## 5. Observaciones de datos en PRUEBAS (para negocio)

- La comparación entre tokens de fórmula y rubros ignora mayúsculas (validación
  al guardar, `enUso` y bloqueo al eliminar). Ejemplo: configuración 12
  (pagaduría 10) usa `distincion` y el rubro se llama `Distincion`. El token se
  guarda tal cual lo envía el cliente.
- Pagadurías 6 y 8 (con regla) **no tienen fórmula `'$'`**: con ingresos ≤ 2 ×
  SMMLV la pantalla responde 422 "no tiene una fórmula de cupo configurada para
  ese nivel de ingresos" (antes ocurría lo mismo). En PRUEBAS se puede crear la
  fila activando y guardando de nuevo la regla desde administración.
- Pagadurías 5 y 9 tienen fórmula `'$'` y `'%'` pero `UsaReglaSMMLV = 0`, así
  que siempre se usa la de menor `IdConfigCalculo` (`'$'` en la 5 y `'%'` en la 9).
  Si deben seleccionar por ingresos, hay que activarles la regla.
- `CuposConfigCalculos` tiene fórmulas para las pagadurías 11 y 12, que no
  existen en `Pagadurias`.
- Las pagadurías 13–16 no tienen fórmula.
- Nombres con `\r\n` al final (1 CAGEN, 2 CASUR).
- No se pudo comparar con producción (no se leyó `ArarFinanciera`): antes de
  desplegar hay que confirmar que en producción los IDs 6 y 8 son las mismas
  pagadurías.
