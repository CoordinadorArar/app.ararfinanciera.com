# Deterioro de Cartera — Fase 3, resultados de la validación

**Fecha:** 2 de septiembre de 2026
**Corte replicado:** 31 de julio de 2026
**Libro de contraste:** `DETERIORO CARTERA A 31 DE JULIO DE 2026.xlsx`, hoja `DETERIORO`
**Base de trabajo:** `ArarFinanciera_PRUEBAS` (ambiente Demo)
**Alcance:** §11 del documento técnico — comparativo contable contra fiscal e impuesto diferido

---

## 1. Resultado

El puente del corte de julio de 2026, con la tarifa de renta congelada del corte (35 %):

| Concepto | Valor |
|---|---:|
| Deterioro contable | 1.753.977.960,01 |
| − Deterioro fiscal acumulado | 1.393.486.150,28 |
| **= Diferencia temporaria** | **360.491.809,73** |
| × Tarifa de renta (35 %) | |
| **= Impuesto diferido activo** | **126.172.133,41** |

El fiscal acumulado se compone de `fiscal_acumulado_anterior` (1.021.285.010,87) más `deduccion_fiscal_ano` (372.201.139,41), tal como define §11.

### Por rango

| Rango | Operaciones | Deterioro contable | Deducción del año | Diferencia temporaria |
|---|---:|---:|---:|---:|
| Corriente | 1.647 | 0,00 | 0,00 | −2.188.062,00 |
| A · 0 a 30 | 35 | 0,00 | 0,00 | 0,00 |
| B · 31 a 90 | 47 | 15.720.730,08 | 0,00 | 15.720.730,08 |
| C · 91 a 180 | 41 | 21.627.046,05 | 0,00 | 21.627.046,05 |
| D · 181 a 360 | 80 | 89.305.478,06 | 0,00 | 89.305.478,06 |
| E · 361 a 720 | 115 | 301.742.468,82 | 123.718.308,32 | 113.032.928,27 |
| F · 721 en adelante | 141 | 1.325.582.237,00 | 248.482.831,09 | 122.993.689,27 |
| **Total** | **2.106** | **1.753.977.960,01** | **372.201.139,41** | **360.491.809,73** |

La diferencia temporaria negativa del tramo corriente (−2.188.062,00) corresponde a operaciones que ya no tienen deterioro contable pero conservan deducción fiscal acumulada de años anteriores. Son **impuesto diferido pasivo** y se presentan por separado: nunca se netean contra el activo.

En total, **15 operaciones** presentan diferencia temporaria negativa.

---

## 2. Reversión proyectada

Año gravable en que cada operación completaría el 100 % fiscal, si subsiste:

| Año | Operaciones | Diferencia temporaria | Impuesto diferido |
|---|---:|---:|---:|
| Sin proyección | 1.657 | −2.188.062,00 | −765.821,70 |
| 2026 | 38 | −39.759.070,20 | −13.915.674,57 |
| 2027 | 69 | 87.690.328,41 | 30.691.614,94 |
| 2028 | 91 | 114.137.596,98 | 39.948.158,94 |
| 2029 | 58 | 73.957.762,35 | 25.885.216,82 |
| 2030 | 193 | 126.653.254,19 | 44.328.638,97 |
| **Total** | **2.106** | **360.491.809,73** | **126.172.133,41** |

El total cuadra al centavo con el puente del punto 1, que es el control de consistencia interna de la pantalla.

Las 38 operaciones de 2026 son exactamente las que el tope de RN-09 ya recortó: su deducción está agotada contra el saldo disponible, de ahí el signo negativo.

**La proyección es un supuesto, no una obligación.** Asume que la operación subsiste, no se prorroga y no se castiga. Una prórroga reinicia el conteo de mora y desplaza el año (control C-1, fase 7).

---

## 3. El impuesto diferido no tiene contraparte en el libro

Es la primera cifra del módulo que no replica nada preexistente. El libro **no calcula impuesto diferido** — es precisamente el vacío que §11 señala: *"esa brecha es un activo por impuesto diferido que hoy no se mide por operación"*.

La validación tiene por tanto dos mitades.

### Mitad contrastable contra el libro

Los insumos del comparativo sí cuadran, y todos vienen validados de las fases 1 y 2:

| Cifra | Celda | Estado |
|---|---|---|
| Deterioro contable | `M2113` / fila 8 | Diferencia 0,00 (fase 1) |
| Deterioro fiscal individual | `AC30` | Diferencia 0,00 (fase 2) |
| Deterioro fiscal general | `AC32` | Diferencia 0,00 (fase 2) |
| Acumulado anterior | hoja `1399 AÑO 2025` | Validado por doble vía (fase 2) |

Por construcción, **el fiscal acumulado y la diferencia temporaria son derivadas de celdas ya cuadradas**, así que heredan esa validación.

### Mitad no contrastable

El impuesto diferido y la proyección de reversión se validaron por dos vías alternativas:

1. **Aritmética independiente** (control `C-DIFERIDO`): el motor multiplica fila a fila y luego suma; el control suma primero y multiplica después. Ambos caminos dan `126.172.133,4055`, con diferencia 0,0000.
2. **Congelamiento**: se alteró `det_param_convencion.tarifa_renta` a 0,99 y se reejecutó el corte. El impuesto diferido **no cambió**, confirmando que lee la copia congelada de `det_corte_param_convencion` y no la paramétrica viva.

> **Decisión pendiente de negocio.** La cifra de impuesto diferido es nueva y requiere **aceptación explícita de Contabilidad y del asesor tributario** antes de llevarse a estados financieros. No es algo que el desarrollo pueda resolver.

---

## 4. Controles de cuadre

Los tres controles nuevos, sobre el corte de julio de 2026:

| Código | Descripción | Valor detalle | Valor resumen | Diferencia | Estado |
|---|---|---:|---:|---:|---|
| `C-DIF-TEMP` | Diferencia temporaria contra contable menos fiscal acumulado | 360.491.809,73 | 360.491.809,73 | 0,00 | **OK** |
| `C-DIFERIDO` | Impuesto diferido contra la diferencia temporaria por la tarifa | 126.172.133,4055 | 126.172.133,4055 | 0,00 | **OK** |
| `C-REVERSION` | Operaciones con base de deterioro y sin año de reversión | 0 | 0 | 0,00 | **OK** |

**Los 11 controles del módulo están en cero**: cinco de la fase 1, tres de la fase 2 y estos tres.

---

## 5. Tres defectos encontrados y corregidos

Se documentan porque los tres pasaron desapercibidos a los controles automáticos y sólo aparecieron en la validación dirigida.

### a) Error de un año en la proyección de reversión

La fórmula sumaba los años de espera hasta alcanzar los 361 días **y además** los años completos de deducción, pasándose por uno. Afectaba a **193 operaciones, el 43 % de las proyectadas**.

Verificación: ese grupo tiene 60 días de mora al corte, cruza los 361 días durante 2027, y necesita cuatro años de deducción al 33 % → 2027, 2028, 2029 y **2030**. El módulo decía 2031. El síntoma visible era un hueco en 2030 que ningún año llenaba.

Corregido restando uno cuando hay espera. Recálculo independiente sobre las 2.106 operaciones: **cero desvíos**, y el caso sin espera no se desplazó.

### b) Dos totales distintos del mismo concepto en la misma pantalla

El panel de reversión tomaba valor absoluto fila a fila antes de sumar, de modo que los tramos en diferido pasivo se sumaban en positivo:

| | Franja del puente | Tabla de reversión |
|---|---:|---:|
| Diferencia temporaria | 360.491.809,73 | 442.198.012,13 |
| Impuesto diferido | 126.172.133,41 | 154.769.304,25 |

Corregido separando la magnitud (que dimensiona la barra del gráfico) del valor con signo (que se suma y se muestra). Los totales ahora coinciden al centavo.

### c) Variaciones del período que nunca ocurrieron

El panel de movimiento trataba como cero las columnas nulas del corte anterior, reportando aumentos de +1.393.486.150,28 en fiscal acumulado y +126.172.133,41 en impuesto diferido. El corte anterior simplemente es previo a la fase 3.

**Este caso está garantizado en producción**: el primer corte que se calcule tras el despliegue siempre tendrá un predecesor sin estas columnas. Corregido distinguiendo "no hay corte anterior" de "el corte anterior no tiene datos de fase 3".

### Hallazgo sobre los controles

**`C-REVERSION` no habría detectado el defecto (a).** Sólo verifica que ninguna operación con base quede sin proyección, no que el año proyectado sea correcto. El error pasó los 11 controles en verde. Es un recordatorio de que los cuadres validan consistencia interna, no veracidad.

---

## 6. Qué se probó

- Puente contable–fiscal por operación, rango y producto.
- Impuesto diferido por doble vía aritmética y prueba de congelamiento de tarifa.
- Proyección de reversión, con recálculo independiente de la fórmula sobre las 2.106 operaciones.
- Los tres controles nuevos en cero, más los ocho heredados.
- **Idempotencia**: dos ejecuciones seguidas de `aplicarImpuestoDiferido()` dejan huella idéntica.
- **Guardas**: `validarTarifaRenta()` lanza excepción con tarifa nula y con tarifa cero.
- **Aislamiento**: la fase 3 no escribe en `det_fiscal_acumulado` (238 filas antes y después). Esa tabla la alimenta el cierre de diciembre, que es fase 7.
- Pantalla nueva y las tres existentes renderizando sin error, con las vistas Contable, Fiscal y Diferido del detalle.
- Filtro de diferido pasivo devolviendo exactamente las 15 operaciones con diferencia negativa.
- Cortes anteriores a la fase 3 mostrando aviso en vez de una ecuación en ceros.

---

## 7. Pendientes

1. **Nada de la fase 3 está en producción.** La migración `2026_09_02_100000_create_det_diferido_tables` figura pendiente y depende de las columnas de la fase 2, que tampoco están. **Orden obligatorio: fase 2 → cargar el 1399 → fase 3.**
2. **La pantalla nacerá visible para cualquier usuario autenticado** hasta que se registre `/deterioro-contable-fiscal` en `Submenus` con sus `PermisosRoles`. Mitigación parcial: el endpoint de datos sí valida permiso, de modo que un usuario sin acceso vería el marco de la pantalla y un 403, no las cifras.
3. **Aceptación formal de la cifra de impuesto diferido** por parte de Contabilidad y del asesor tributario, según el punto 3 de este informe.
4. **Sin cobertura de pruebas automatizadas.** Las líneas nuevas del motor no tienen un solo test; toda la verificación de este informe es manual. En particular, la corrección del defecto (a) descansa en un `-1` que ningún control vigila.
5. **Ningún cuadre valida el *valor* del año de reversión**, sólo su presencia. Convendría un control que contraste la proyección contra un recálculo independiente, como hace `C-FISCAL`.
6. **El 33 % sigue escrito en duro** en las vistas (`contable-fiscal.blade.php`, `detalle-operaciones.blade.php`, `resumen-corte.blade.php`). Hoy coincide con la paramétrica, pero es el mismo tipo de literal que se eliminó para la tarifa de renta.
7. **`saldo_siesa` sigue nulo** hasta la fase 6, así que el tope de RN-09 —y con él la proyección de reversión— se resuelve contra la base de deterioro.
8. **La evolución a 12 y 24 meses no se puede demostrar todavía**: sólo hay dos cortes con datos de fase 3. La pantalla está construida para funcionar cuando haya 24, y avisa explícitamente cuántos cortes quedan fuera de la gráfica.
