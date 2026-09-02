# Deterioro de Cartera — Fase 2, resultados de la validación

**Fecha:** 2 de septiembre de 2026
**Corte replicado:** 31 de julio de 2026
**Libro de contraste:** `DETERIORO CARTERA A 31 DE JULIO DE 2026.xlsx`, hojas `DETERIORO` y `1399 AÑO 2025`
**Base de trabajo:** `ArarFinanciera_PRUEBAS` (ambiente Demo)

---

## 1. Resultado

**El módulo reproduce el cálculo fiscal del libro con diferencia cero en los dos métodos.**

| Concepto | Celda | Excel | Módulo | Diferencia |
|---|---|---:|---:|---:|
| Deterioro fiscal individual | `DETERIORO!AC30` | 565.102.413,48 | 565.102.413,48 | **0,00** |
| Deterioro fiscal general | `DETERIORO!AC32` | 278.416.355,35 | 278.416.355,35 | **0,00** |

Paramétrica congelada del corte (`det_corte_param_fiscal`), coincidente con `AA29`, `AA31` y `AB31` del libro:

| Método | % anual | Días mínimos de mora | Activo |
|---|---:|---:|---|
| INDIVIDUAL | 33 % | 361 | Sí |
| GENERAL | — | 91 | No |

El método general está desactivado por política (D-04): la deducción se resuelve por la vía individual. La cifra general se calcula igual y se conserva para contrastar con el libro.

### Deterioro fiscal individual por rango

Sólo los rangos que superan los 361 días de mora generan deducción individual, que es lo que exige RN-07:

| Rango | Operaciones | Deterioro contable | Fiscal individual | Deducción del año |
|---|---:|---:|---:|---:|
| Corriente | 1.647 | 0,00 | 0,00 | 0,00 |
| A · 0 a 30 | 35 | 0,00 | 0,00 | 0,00 |
| B · 31 a 90 | 47 | 15.720.730,08 | 0,00 | 0,00 |
| C · 91 a 180 | 41 | 21.627.046,05 | 0,00 | 0,00 |
| D · 181 a 360 | 80 | 89.305.478,06 | 0,00 | 0,00 |
| E · 361 a 720 | 115 | 301.742.468,82 | 127.660.275,27 | 123.718.308,32 |
| F · 721 en adelante | 141 | 1.325.582.237,00 | 437.442.138,21 | 248.482.831,09 |
| **Total** | **2.106** | **1.753.977.960,01** | **565.102.413,48** | **372.201.139,41** |

---

## 2. El acumulado fiscal histórico

Es el insumo que faltaba y sin el cual la fase 2 no se podía cerrar.

### Origen del dato

La hoja `1399 AÑO 2025` **no es una tabla plana**: tiene tres bloques laterales, sin fila de encabezado, con notas de negocio intercaladas en las columnas D y L.

| Bloque | Columnas | Filas | Total |
|---|---|---:|---:|
| **A** | A/B/C | 238 | **1.138.378.564,83** |
| B | I/J/K | 126 | 612.401.386,93 |
| C | Q/R/S | 47 | 271.445.088,62 |

**Los tres bloques son snapshots acumulados, no deducciones anuales independientes.** Comprobado: B y C son subconjuntos estrictos de A, y para las 126 operaciones comunes entre A y B el valor de A es mayor en 90 casos, igual en 36 y **menor en ninguno**. Caso característico: la operación 2060 tiene 192.912,00 en A y 96.456,00 en B, exactamente el doble, es decir un año más de acumulación.

Confirmado con Contabilidad: el bloque A es el saldo acumulado al cierre de 2025 e incluye todos los años anteriores; B y C son los mismos saldos a cierres previos.

**Consecuencia de diseño:** se carga **únicamente el bloque A**, con `ano_gravable = 2025`. Cargar también B y C duplicaría el acumulado, porque el motor suma todas las filas con `ano_gravable < YEAR(corte)`.

### Validación independiente del cargue

El total del bloque A cuadra con la celda `C239` de la hoja. Hay además una segunda comprobación que no depende de los totales del Excel:

- Operaciones del bloque A **sin nota** en la columna D: **199**, por 1.021.285.010,87.
- Operaciones del acumulado que **siguen vivas** en el corte de julio de 2026: **199**, por **1.021.285.010,87**.

Las 39 restantes llevan nota `BAJA`, `CANCELÓ` o `PAGO` y ya salieron de la cartera. Las dos cifras coinciden al centavo por caminos distintos.

### Los cuatro totales de la hoja, explicados

| Celda | Valor | Qué es |
|---|---:|---|
| `C239` | 1.138.378.564,83 | Total del bloque A |
| `C240` | 111.918.075,63 | Lo que se resta |
| `C241` | 1.026.460.489,20 | `C239 − C240` |
| `C242` | −5.175.478,33 | Partida conciliatoria |

`C241 − 1.021.285.010,87 = 5.175.478,33`, que es exactamente `−C242`. Es decir, **la diferencia entre lo que el libro espera como acumulado vivo y lo que el módulo encuentra vivo es la misma partida que el propio libro ya registra** como línea de cuadre. No es un desvío del módulo.

### Cargue ejecutado

```
php artisan deterioro:cargar-acumulado-fiscal 1399-bloqueA-2025.csv --origen=EXCEL_1399 --ambiente=demo
Ambiente: demo · base ArarFinanciera_PRUEBAS
Leídas: 238 · insertadas: 238 · actualizadas: 0
```

Sin operaciones repetidas. El comando es todo-o-nada y hace upsert por `(id_operacion, año)`, de modo que es repetible.

---

## 3. El tope de RN-09

Con el acumulado cargado, el tope **por fin opera**. Antes, con `fiscal_acumulado_anterior = 0`, la condición `P + O > R` exigía `0,33 · base > base`, imposible: el control `C-FISCAL-TOPE` daba cero **por vacuidad** y la rama de recorte nunca llegaba a ejecutarse.

| Concepto | Sin acumulado | Con acumulado |
|---|---:|---:|
| Acumulado anterior (P) | 0,00 | 1.021.285.010,87 |
| Deducción del año | 565.102.413,48 | **372.201.139,41** |
| Efecto del tope | 0,00 | **−192.901.274,07** |
| Operaciones recortadas | 0 | **38** |

El conteo de 38 usa el criterio `deduccion_fiscal_ano < deterioro_fiscal_individual`, el mismo del filtro "Solo topadas" del detalle, para que la cifra mostrada cuadre con las filas que el usuario puede inspeccionar.

---

## 4. Controles de cuadre

Sobre el corte de julio de 2026:

| Código | Descripción | Valor detalle | Valor resumen | Diferencia | Estado |
|---|---|---:|---:|---:|---|
| `C-FISCAL` | Fiscal individual contra la base de los rangos que deducen | 565.102.413,48 | 565.102.413,48 | 0,00 | **OK** |
| `C-FISCAL-ACUM` | Acumulado del detalle contra los años anteriores | 1.021.285.010,87 | 1.021.285.010,87 | 0,00 | **OK** |
| `C-FISCAL-TOPE` | Deducción del año por encima del tope disponible | 0,00 | 0,00 | 0,00 | **OK** |

Los cinco controles de la fase 1 (`C-BASE`, `C-CAPITAL`, `C-CUOTAS`, `C-INTERES`, `C-PARTIC`) siguen en cero.

`C-FISCAL` es un control genuinamente independiente y no una tautología: el motor decide operación por operación comparando `dias_mora_operacion` contra `dias_minimos_mora`, mientras el control decide por rango, tomando los rangos cuyo `dias_desde` alcanza ese mismo mínimo. Son dos caminos sobre dos paramétricas distintas.

---

## 5. Desempeño

| Concepto | Valor |
|---|---:|
| Filas de origen | 115.027 |
| Operaciones consolidadas | 2.106 |
| Duración total del corte | 7.962 ms |
| Paso fiscal (refresco aislado) | 181 ms |

Dentro del objetivo del §10 del documento técnico (menos de 60 segundos para 115 mil filas).

---

## 6. Qué se probó

- Cálculo fiscal individual y general contra `AC30` y `AC32` del libro.
- Cargue del acumulado desde `1399 AÑO 2025`, con validación cruzada independiente por operaciones vivas.
- Operación del tope de RN-09 sobre 38 operaciones reales.
- Los tres controles fiscales en cero, más los cinco de la fase 1.
- Congelamiento de la paramétrica fiscal por corte (`det_corte_param_fiscal`).
- Refresco del paso fiscal sin recalcular el corte completo.

### Cómo se refrescó el corte

Los cortes de `ArarFinanciera_PRUEBAS` se calcularon originalmente desde `Modulos_Faico` de producción (115.027 filas). Recalcularlos completos en ambiente Demo habría usado `Modulos_Faico_prueba` (80.647 filas) y cambiado la base de comparación con el libro. Por eso el acumulado se incorporó con `aplicarDeterioroFiscal($idCorte, $fechaCorte)`, que es un `UPDATE` sobre las operaciones ya extraídas y **no toca la tabla origen**. La extracción de la fase 1 queda intacta, y por eso las cifras contables siguen coincidiendo con el libro.

---

## 7. Pendientes

1. **Nada de la fase 2 está en producción.** Las cinco tablas fiscales no existen en `ArarFinanciera`, la migración `2026_09_01_100000_create_det_fiscal_tables` figura pendiente y el acumulado tampoco está cargado allí.
2. **El orden de despliegue es obligatorio**: subir código → `php artisan migrate` → cargar el `1399` → refrescar los cortes. Invertir los dos últimos pasos persistiría en producción una deducción sobrestimada en 192.901.274,07.
3. **Sin fuente por operación para años anteriores a 2018.** Las hojas `2016` y `2017` del libro no tienen columna de operación: arrancan en CLIENTE. Si alguna vez se exige reconstruir el acumulado año por año en vez de tomar el saldo, ese tramo no es cargable con el dato actual.
4. **`saldo_siesa` sigue nulo** hasta la fase 6, así que el tope `R` se resuelve contra la base de deterioro. Es el comportamiento neutro previsto, pero el tope todavía no incorpora el saldo de SIESA.
5. **Sin cobertura de pruebas automatizadas.** Toda la validación de este informe es manual.
