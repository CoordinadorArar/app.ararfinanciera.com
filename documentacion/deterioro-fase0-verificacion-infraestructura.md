# Deterioro de Cartera — Verificación de infraestructura

**Fecha:** 31 de agosto de 2026
**Alcance:** paso previo bloqueante de la fase 1
**Documento base:** `modulo-deterioro-cartera-tecnico.md` v2.1

Todo lo que sigue se verificó consultando directamente el servidor. Donde un hallazgo contradice el documento técnico, se indica.

---

## 1. Servidor y motor

| Dato | Valor |
|---|---|
| Servidor | `172.28.254.9:1433` |
| Versión | SQL Server **2014** (12.0.6024.0) SP3 |
| Edición | **Standard Edition (64-bit)** |
| Modelo de recuperación | `FULL` en `ArarFinanciera`, `FactoringManagerDatos` y `Modulos_Faico` |

**`Modulos_Faico` está en el mismo servidor que el resto.** El bloqueo principal del plan queda resuelto: la extracción puede hacerse con una sola sentencia cross-database, igual que `Contable.php` ya hace con `UNOEEARAR..`. No hace falta *linked server* ni transferencia por PHP.

El documento decía que el Excel se conectaba desde `172.24.15x`; esa IP no corresponde a ninguna base alcanzable hoy y no aparece en el código.

### Consecuencias de que sea 2014 Standard

Tres cosas del diseño previo no son posibles en esta edición y hay que ajustarlas:

| Función | Disponibilidad | Ajuste |
|---|---|---|
| `DATA_COMPRESSION = PAGE` | Enterprise hasta 2016 SP1 | **Se elimina.** Las tablas van sin compresión. |
| Particionado de tablas | Enterprise en 2014 | Ya se había descartado; se confirma. |
| Funciones JSON (`OPENJSON`, `FOR JSON`) | Desde 2016 | **`det_corte_parametro` no puede guardar JSON** si el motor tiene que unirse contra él. Se congela en tablas relacionales. |
| *Inlining* de UDF escalares | Desde 2019 | Confirma que `DAYS360` debe ir como expresión `CASE` inline, nunca como `CREATE FUNCTION`. |
| `STRING_AGG`, `TRIM`, `CONCAT_WS` | Desde 2017 | No usarlas. |

### Bases de prueba disponibles

Existen `Modulos_Faico_prueba`, `UNOEEARAR_PRUEBA` y `FactoringManagerDatosV1`. No existe una `ArarFinanciera_Dev`: **sigue pendiente** solicitarla para no aplicar DDL en producción.

---

## 2. El origen de datos no es lo que dice el documento

El documento describe `ResumenVigentesClientes` como una **vista** parametrizada por fecha de corte. No lo es.

**Son dos tablas físicas, ambas *heap* (sin ningún índice):**

| Tabla | Filas | Periodo cargado |
|---|---|---|
| `Modulos_Faico.dbo.ResumenVigentesClientes` | 115.027 | 2026-07 |
| `Modulos_Faico.dbo.ResumenVigentesClientes1` | 115.611 | 2026-06 |

Cada una contiene **un solo periodo**. Las llena un procedimiento almacenado:

- `sp_ObtResumenVigentesClientesFechaCorte1` → escribe en `ResumenVigentesClientes`
- `sp_ObtResumenVigentesClientesFechaCorte2` → escribe en `ResumenVigentesClientes1`

Son idénticos salvo por la tabla destino. Reciben `@FecCorte char(10)` en formato `dd/mm/aaaa` entre otros 12 parámetros, arman `#tmp_ResumenVigentesClientes` con cursores anidados y terminan en:

```sql
insert into ResumenVigentesClientes
select * from #tmp_ResumenVigentesClientes
```

**El procedimiento no borra nada antes de insertar.** Quien lo ejecuta debe vaciar la tabla a mano; si no, las filas se acumulan. Es decir, el origen es un recurso compartido, mutable y sin control de concurrencia.

### Qué implica

1. **El módulo no ejecuta el procedimiento.** Lee la tabla tal como esté, valida que `IdAno`/`IdPeriodo` coincidan con la fecha de corte pedida y copia las filas a su propio snapshot. Se conserva el procedimiento operativo actual.
2. El valor del módulo aumenta: hoy el corte vive en una tabla que se sobrescribe cada mes; el módulo lo vuelve permanente y reproducible.
3. Reconstruir un corte pasado exigiría vaciar y recargar la tabla compartida. **No es posible sin coordinación**, y limita la migración histórica de la fase 8.
4. Hoy la tabla contiene justo el corte de julio de 2026 y su comparativo de junio, así que la réplica de la fase 1 puede hacerse de inmediato.

### Diferencia de conteo contra el documento

El documento reporta 115.024 y 115.608 filas; hoy hay 115.027 y 115.611, **tres filas más en cada una**. La tabla se recargó después de que se armó el Excel. Al validar la réplica hay que contar contra el libro, no contra estas cifras.

### Correcciones embebidas en el procedimiento

El propio procedimiento trae parches de datos escritos a mano, que el módulo hereda sin poder controlarlos:

```sql
-- excluye una operación del ajuste por prórroga
... and @IdOperacion <> 2343

-- Error puntual de operciones  [sic]
update #tmp_ResumenVigentesClientes set FecFinalCorriente = DATEADD(day,30, FecInicialCorriente)
  from #tmp_ResumenVigentesClientes where IdOperacion in (3894,3893)
```

Conviene que Cartera confirme si siguen siendo necesarios.

---

## 3. Estructura y contenido del corte de julio de 2026

79 columnas. Importes en `money`, tasas en `real`, fechas en `smalldatetime`, e `IdAno`/`IdPeriodo`/`IdCliente` en `varchar`. Ambas tablas tienen exactamente las mismas columnas.

| Métrica | Valor |
|---|---|
| Filas | 115.027 |
| Operaciones distintas | **2.106** |
| Clientes distintos | 1.716 |
| Grano único | **`(IdOperacion, IdCuota)`** — `IdDetalleOperacion` no aporta unicidad |

Las 2.106 operaciones coinciden exactamente con las ~2.106 filas que el documento atribuye a `Tabla final`.

### Totales

| Concepto | Valor |
|---|---|
| Saldo capital | 15.419.137.327,00 |
| Saldo intereses | 14.446.330.983,00 |
| Saldo administración | 3.405.712.082,00 |
| Saldo mora | **0,00** (la columna existe pero viene en cero) |
| **Capital vencido** | **1.484.366.526,00** |
| **Interés vencido** | **692.519.031,00** |
| **Base de deterioro (RN-03)** | **2.176.885.557,00** |

Vencido y corriente se parten con `DAYS360(FecFinalCorriente, corte) > 0`: 6.471 cuotas vencidas contra 108.556 corrientes.

### Distribución por producto

| NomOperacion | Cuotas | Operaciones |
|---|---|---|
| LIBRANZAS | 114.648 | 2.074 |
| FINANCIACION | 368 | 21 |
| LETRA DE CAMBIO | 10 | 10 |
| FACTORING | 1 | 1 |

La cartera es casi enteramente libranzas. Los bloques de FACTORING del resumen quedarán prácticamente vacíos, y la regla RN-05 (el interés de mora no aplica a factoring) afecta a una sola cuota.

---

## 4. Corrección importante: cómo se determina "Corriente"

El diseño anterior asumía que la clasificación cae en `"Corriente"` cuando `FecInicialMora` es nula. **Es falso: ninguna fila la tiene nula.**

El procedimiento la deriva así:

```sql
select IdOperacion, FecFinalCorriente = min(FecFinalCorriente)
  into #a from #tmp_ResumenVigentesClientes group by IdOperacion
update #tmp_ResumenVigentesClientes set FecInicialMora = a.FecFinalCorriente ...
```

Es decir, **`FecInicialMora` es el vencimiento de la cuota pendiente más antigua**. Para una operación al día esa fecha está en el futuro, con lo cual `DAYS360(FecInicialMora, corte)` sale **negativo**, el `BUSCARV` aproximado del Excel falla y el `SI.ERROR` devuelve `"Corriente"`.

El disparador es el **valor negativo**, no el nulo. Verificado sobre el corte de julio:

| Clasificación | Cuotas | Operaciones |
|---|---|---|
| Negativo → Corriente | 96.447 | **1.647** |
| A (0–30) | 2.058 | 35 |
| B (31–90) | 2.110 | 47 |
| C (91–180) | 1.952 | 41 |
| D (181–360) | 2.921 | 80 |
| E (361–720) | 4.531 | 115 |
| F (721+) | 5.008 | 141 |
| | | **2.106** |

Suma exacta. Estas cifras son el primer punto de control de la réplica: si el módulo no reproduce esta distribución operación por operación, el `DAYS360` está mal.

Tanto "Corriente" como el rango A provisionan 0 %, así que la distinción no cambia el deterioro contable, pero sí la clasificación que se muestra en pantalla y el conteo por rango.

---

## 5. Prórrogas: el efecto de C-1 ya viene del origen

El procedimiento aplica las prórrogas antes de entregar los datos:

```sql
select IdOperacion, fecha = max(fecfinal) into #b
  from FactoringManagerDatos.dbo.cnsMovDetOpeClientes
 where FecInicial <= @FecCorte and TipoDocumento = 'PRO' group by IdOperacion

update #tmp_ResumenVigentesClientes
   set FecFinalCorriente = a.fecha, FecInicialMora = a.fecha ...
```

La prórroga **reinicia por completo** `FecInicialMora`. Confirma el control C-1 del documento: una operación prorrogada puede salir de mora y liberar deterioro de un mes al otro, sin rastro en el origen. El módulo lo detecta comparando cortes, que es justamente lo que hoy no existe.

---

## 6. Join con Operaciones

`TotalVrEntregarBruto` **sí existe** en `FactoringManagerDatos.dbo.Operaciones`, tipo `money`. El `LEFT JOIN` por `IdOperacion` cubre las 115.027 filas **sin una sola sin correspondencia**, así que puede ser `INNER JOIN` sin perder datos.

---

## 7. Hallazgo de seguridad

El usuario `consultaweb`, que la aplicación usa para todo, tiene sobre `Modulos_Faico.dbo.ResumenVigentesClientes` los permisos **`CONTROL`, `ALTER`, `INSERT` y `DELETE`**, no solo `SELECT`.

Una cuenta llamada "consulta web", compartida por las cinco conexiones de la aplicación y por el webservice legado, puede borrar o alterar la tabla origen de la cartera. Es un privilegio muy por encima de lo necesario y debería reducirse a `SELECT` antes de que el módulo entre en producción.

En la misma línea, ya se retiraron del código todas las credenciales en texto plano, pero **la contraseña sigue sin rotarse** y está en el `.docx` de alcance y en el `.xlsx` de 68 MB que circulan por correo.

---

## 8. Resumen de ajustes al plan

| Punto del plan | Estado |
|---|---|
| ¿`Modulos_Faico` alcanzable? | **Sí**, mismo servidor. Extracción cross-database en una sentencia. |
| ¿Vista parametrizada por fecha? | **No.** Son tablas que un procedimiento vacía y recarga. El módulo las lee tal cual y valida el periodo. |
| `DATA_COMPRESSION = PAGE` | **Se elimina**, no disponible en Standard 2014. |
| `det_corte_parametro` en JSON | **Se cambia a tablas relacionales**, no hay funciones JSON en 2014. |
| `"Corriente"` por `FecInicialMora` nula | **Se corrige**: se dispara por `DAYS360` negativo. |
| `DAYS360` como expresión inline | **Confirmado como obligatorio** (no hay inlining de UDF en 2014). |
| Grano de la PK | **`(id_corte, id_operacion, id_cuota)`**; `IdDetalleOperacion` no aporta. |
| Ambiente de prueba | **Pendiente.** Hay bases `_prueba` de otros sistemas, pero no de `ArarFinanciera`. |

---

## 9. Pendientes antes de continuar

1. Solicitar `ArarFinanciera_Dev` para aplicar el DDL sin tocar producción.
2. Confirmar la tabla `migrations` en producción antes de la primera corrida de `artisan migrate`.
3. Avisar al DBA del crecimiento del log: las tres bases están en `FULL` y el módulo insertará 115 mil filas por corte.
4. Preguntar a Cartera si los parches de las operaciones 2343, 3893 y 3894 siguen vigentes.
5. Reducir los permisos de `consultaweb` sobre `ResumenVigentesClientes` a solo lectura, y rotar la contraseña.
