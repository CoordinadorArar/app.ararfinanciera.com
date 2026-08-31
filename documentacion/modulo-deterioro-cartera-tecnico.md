# Módulo de Deterioro de Cartera — Documentación técnica

**Versión:** 2.1 · 31 de agosto de 2026
**Fuente analizada:** `DETERIORO_CARTERA_A_31_DE_JULIO_DE_2026.xlsx` (68 MB, 14 hojas)
**Destino:** sitio web administrativo, `app.ararfinanciera.com`
**Documento complementario:** *Módulo de Deterioro de Cartera — Documento de alcance v2.1* (versión para Contabilidad)

Este documento es la referencia técnica del desarrollo. Contiene la ingeniería inversa del proceso actual, las políticas adoptadas por Gerencia y el diseño del módulo. Donde el documento de alcance simplifica para una audiencia contable, aquí se conserva el detalle.

---

# PARTE I — El proceso actual (AS-IS)

Cada regla de esta parte proviene de una fórmula concreta del libro. Las celdas se citan para que cualquier afirmación pueda verificarse.

## 1. Origen de los datos

Dos conexiones ODBC a SQL Server definidas en `xl/connections.xml`:

| Conexión | Destino | Vista origen | Filas |
|---|---|---|---|
| `Consulta desde 172.24.15` | hoja `BD` | `Modulos_Faico.dbo.ResumenVigentesClientes` | 115.024 |
| `Consulta desde 172.24.151` | hoja `Mes_anterior` | `Modulos_Faico.dbo.ResumenVigentesClientes1` | 115.608 |

Ambas hacen `JOIN` con `FactoringManagerDatos.dbo.Operaciones` para traer `TotalVrEntregarBruto` como `Valor_credito`. Están parametrizadas contra `MENU!D9` (fecha de corte) y `MENU!D10` (fecha a comparar), en formato AAAAMMDD.

**Hallazgo de seguridad (prioridad alta).** La cadena de conexión se guarda con `savePassword="1"`, en texto plano, incluyendo IP, base de datos, usuario y contraseña. Cualquiera que reciba el archivo tiene acceso de lectura a la base productiva. Rotar la credencial, restringirla a las vistas necesarias y dejar de distribuir el libro con la conexión embebida.

## 2. Mapa del libro

| Hoja | Rol | Volumen |
|---|---|---|
| `MENU` | Parámetros de corte y comparación | 2 celdas |
| `BD` | Datos crudos del corte + 12 columnas calculadas | 115.024 × 45 |
| `Mes_anterior` | Mismo dato del corte anterior | 115.608 × 32 |
| `Tabla final` | Consolidado por operación | ~2.106 filas |
| `DETERIORO` | Cálculo contable y fiscal + resumen | 2.113 filas |
| `DIFERENCIAS` | Conciliación con SIESA por cliente | ~1.732 filas |
| `1399 AÑO 2025` | Provisión fiscal acumulada por operación | ~239 filas |
| `Otros` | Tablas maestras y notas de cartera | ~35 filas |
| `Pagador` | Saldo por pagaduría y variación | ~30 filas |
| `Flujo Efectivo BD` | Proyección de capital e intereses 2026-2036 | Matriz |
| `2016`, `2017`, `2018`, `Hoja1` | Cortes históricos | Ocultas |

`1399` es la cuenta PUC de deterioro de cartera. La hoja guarda por operación la provisión fiscal acumulada año por año; sus comentarios internos muestran columnas etiquetadas `AÑO 2025` y `AÑO 2024`, y el acumulado se arma sumando términos dentro de la celda: `=968025.3+1579410+2190794`.

## 3. Flujo del proceso

1. Se escriben fecha de corte y fecha de comparación en `MENU`.
2. Se refrescan las dos consultas ODBC.
3. Excel recalcula las columnas derivadas de `BD` (`AE` a `AS`).
4. Se actualiza la tabla dinámica que alimenta `Tabla final`, que consolida a nivel operación y trae el capital del mes anterior con `SUMIFS` contra `Mes_anterior`.
5. `DETERIORO` referencia fila por fila a `Tabla final`, clasifica y calcula deterioro contable y fiscal.
6. El bloque resumen `T3:AC35` agrega por producto y rango con `SUMIFS`, compara contra el mes anterior y calcula el ajuste del mes.
7. Se pegan manualmente los saldos de SIESA en `DIFERENCIAS!D` y en `DETERIORO!Q`.
8. Se revisan los cuadres de la fila 2113 y del bloque `AC`. Si dan cero, el corte está listo.

## 4. Diccionario de datos

### 4.1 Campos de origen

`IdAno`, `IdPeriodo`, `IdCliente`, `Cliente`, `IdPagador`, `Pagador`, `IdComisionista`, `Comisionista`, `IdOperacion`, `FecOperacion`, `IdCuota`, `IdDetalleOperacion`, `TasaInteresCliente`, `SaldoCapital`, `SaldoIntereses`, `SaldoInteresesCausado`, `SaldoMora`, `SaldoMoraCausado`, `SaldoAdmon`, `SaldoNetoRecibir`, `FecInicialCorriente`, `FecFinalCorriente`, `FecInicialMora`, `DiasVencidos`, `DiasCorriente`, `ReliquidaMora`, `TipoOperacion`, `NomOperacion`, `NomModOperacion`, `Valor_credito`.

Grano: **operación–cuota** (`IdOperacion` + `IdCuota`).

### 4.2 Campos calculados en `BD`

| Col | Campo | Fórmula |
|---|---|---|
| AE | `Capital_corriente` | `SI(Dias_mora<=0; SaldoCapital; 0)` |
| AF | `Interes_corriente` | `SI(Dias_mora<=0; SaldoIntereses; 0)` |
| AG | `Capital_Vencido` | `SI(Dias_mora>0; SaldoCapital; 0)` |
| AH | `Interes_Vencido` | `SI(Dias_mora>0; SaldoIntereses; 0)` |
| AI | `Interes_Mora2` | `SI(Producto="FACTORING"; 0; SI(Dias_mora>0; SaldoCapital*0,0233/30*Dias_mora; 0))` |
| AJ | `Dias_mora` | `DAYS360(FecFinalCorriente; MENU!D9)` |
| AK | `CalificacionABC` | `BUSCARV(DAYS360(FecInicialMora; MENU!D9); Otros!B:C; 2; 1)`, `"Corriente"` si falla |
| AL | `dias_mora_cliente` | `SI(CalificacionABC="Corriente"; 0; DAYS360(FecInicialMora; MENU!D9))` |
| AM | `Total_vencido` | vacía, sin uso |
| AN | `Operacio_cuota` | `IdOperacion & "-" & IdCuota` |
| AO | `capital mes anterior` | `BUSCARV(Operacio_cuota; Mes_anterior!AE:AF; 2; 0)` |
| AP | `Producto` | `BUSCARV(NomOperacion; Otros!F:G; 2; 0)` |
| AQ/AR | `año/mes_vencimiento` | `AÑO`/`MES` de `FecFinalCorriente` |
| AS | `corrienteporcouta` | `SI(Dias_mora<=0; "Cuota Corriente"; "Cuota Vencida")` |

Conviven dos definiciones de mora, y la distinción importa:

- `Dias_mora` se mide desde el **vencimiento de la cuota**; sirve para partir saldos entre corriente y vencido.
- `CalificacionABC` y `dias_mora_cliente` se miden desde **`FecInicialMora`**, la fecha en que la operación entró en mora, y determinan el porcentaje de deterioro.

El nombre `dias_mora_cliente` es engañoso: se calcula por operación, no por cliente. Política confirmada: la clasificación es por operación (D-01). En el módulo el campo se llamará `dias_mora_operacion`.

### 4.3 Tablas maestras (`Otros`)

**Rangos** (`Otros!B:E`), con `BUSCARV` aproximado sobre el límite inferior:

| Límite inferior | Descripción | Código |
|---|---|---|
| 0 | 0 hasta 30 | A |
| 31 | 31 hasta 90 | B |
| 91 | 91 hasta 180 | C |
| 181 | 181 hasta 360 | D |
| 361 | 361 hasta 720 | E |
| 721 | 721 hasta 9999 | F |

**Mapeo producto** (`Otros!F:G`): FINANCIACION → FINANCIACION; LETRA DE CAMBIO → FACTORING; LIBRANZAS → LIBRANZAS; DESCUENTO DE CHEQUES → FACTORING; FACTORING → FACTORING.

**Notas de gestión** (`Otros!K:M`): lista manual por cliente con observaciones tipo `RESERVA`, `PROCESO JURIDICO`, `REVISAR CUOTA`, `RECLASIFICAR A OTRA CUENTA`. Agrupada por mes de forma informal, sin fecha ni autor.

## 5. Reglas de negocio catalogadas

### RN-01 · Días de mora
`DAYS360`, convención comercial de 360 días. Para una obligación con 2 años de mora real devuelve unos 10 días menos que el calendario, lo que desplaza operaciones en los bordes 360/361 y 720/721.

### RN-02 · Clasificación por rango
`BUSCARV` aproximado sobre los límites inferiores de `Otros!B`. Devuelve `"Corriente"` cuando no hay `FecInicialMora`.

### RN-03 · Base de deterioro
`DETERIORO!I = G + H` = capital vencido + interés vencido. Excluye `SaldoAdmon`, `SaldoMora` y el interés de mora calculado.

### RN-04 · Porcentajes contables
Definidos en `DETERIORO!V4:AA4`:

| Rango | Días | % |
|---|---|---|
| A | 0 a 31 | 0 % |
| B | 31 a 90 | 8 % |
| C | 91 a 180 | 23 % |
| D | 181 a 360 | 53 % |
| E | 361 a 720 | 78 % |
| F | más de 721 | 100 % |

`DETERIORO!M6` es una cadena de seis `SI` que compara la clasificación contra cada código.

> **Defecto D-A.** El primer `SI` compara contra `$V$14`, que contiene el texto `"0 A 31"`, en lugar de `$V$15`, que contiene `"A"`. La condición nunca se cumple. Hoy no produce error porque el porcentaje del rango A es 0 %, pero si algún día se decide provisionar el rango A, el cálculo devolverá cero en silencio.
>
> **Defecto D-B.** En el mismo bloque, `SI(Y(I3=$Y$15)…)` compara la base en pesos contra la letra `"D"` en vez de comparar la clasificación.

### RN-05 · Interés de mora
2,33 % mensual sobre capital, prorrateado por día, multiplicado por los días de mora. No aplica a FACTORING. Se calcula en `BD` pero no entra a la base de deterioro.

### RN-06 · Agregación por producto
Tres bloques: FINANCIACION (fila 18), FACTORING (21) y LIBRANZAS (24), cada uno partido en capital e interés y cruzado contra los seis rangos con `SUMIFS` sobre columnas completas.

### RN-07 · Deterioro fiscal, método individual
`DETERIORO!O6 = SI(O(clasificación=E; clasificación=F); base × 33 %; 0)`, con el 33 % en `AB29`. Corresponde al artículo 145 del ET y al artículo 1.2.1.18.20 del Decreto 1625 de 2016.

### RN-08 · Deterioro fiscal, método general
Calculado en paralelo en `DETERIORO!Z31:AB32` con 5 % (rango C), 10 % (D) y 15 % (E+F). Los dos métodos son excluyentes. **Política adoptada: individual (D-04).** El general se conserva como parámetro desactivado.

### RN-09 · Tope del acumulado fiscal
`DETERIORO!N6`:

```
N = MAX(0; SI(P + O > R; R − P; O))
donde  P = provisión fiscal acumulada de años anteriores  ('1399 AÑO 2025')
       O = 33 % de la base del año en curso
       R = MIN(Q; I) = saldo SIESA topado contra la base en mora
```

Se deduce el 33 % anual, nunca por encima de lo que falta para completar el saldo. Es la pieza que el módulo debe reproducir con exactitud.

### RN-10 · Comparativo con el mes anterior
`DETERIORO!T9` trae el deterioro del mes anterior por rango, **digitado a mano**. `T10` calcula el ajuste del mes, que es el gasto contable del período (`AC34`).

### RN-11 · Conciliación con SIESA
`DIFERENCIAS` compara por cliente el saldo de SIESA (columna D, pegado a mano) contra el del sistema de factoring (`SUMIFS(BD!N:N; BD!C:C; cliente)`). La diferencia se explica con la columna G (prórrogas por cobrar / reservas) y el residuo debe quedar en cero.

El total de prórrogas y reservas (`DIFERENCIAS!G1739`) se inyecta como fila adicional en `DETERIORO!F3`, clasificada como Corriente / A, de modo que suma capital sin generar deterioro.

### RN-12 · Controles de cuadre

| Celda | Control |
|---|---|
| `M2113` | Deterioro contable detalle − resumen = 0 |
| `O2113` | Deterioro fiscal detalle − resumen = 0 |
| `P2113` | Acumulado fiscal detalle − hoja 1399 = 0 |
| `AC25` | Capital total − saldo SIESA = 0 |
| `AC26` | Interés total − suma del detalle = 0 |
| `AD30` | Fiscal resumen − fiscal detalle = 0 |
| `AC34` | Gasto contable por deterioro del mes |
| `AC35` | Deducción fiscal del año |

## 6. Entradas manuales del proceso actual

1. Fecha de corte y fecha de comparación (`MENU`).
2. Saldos SIESA por cliente (`DIFERENCIAS!D`).
3. Prórrogas y reservas por cliente (`DIFERENCIAS!G`) y su nota (`I`).
4. Cartera SIESA por operación (`DETERIORO!Q`), que alimenta el tope fiscal.
5. Deterioro del mes anterior por rango (`DETERIORO!V9:AA9`).
6. Provisión fiscal acumulada por operación y por año (hoja `1399 AÑO 2025`).
7. Notas de gestión por cliente (`Otros!K:M`).

## 7. Defectos y riesgos del proceso actual

| # | Hallazgo | Impacto |
|---|---|---|
| 1 | Credenciales de producción en texto plano en el archivo | Seguridad |
| 2 | Un archivo de 68 MB por corte; histórico disperso en hojas ocultas | No hay serie temporal consultable |
| 3 | Comparativo contra un solo mes, digitado a mano | Riesgo de error en el gasto del período |
| 4 | Siete entradas manuales sin trazabilidad | No hay auditoría |
| 5 | `SUMIFS` y `BUSCARV` sobre columnas completas de 115 mil filas | Minutos de recálculo, cuelgues |
| 6 | Defectos D-A y D-B en la cadena de porcentajes (RN-04) | Errores latentes |
| 7 | `Tabla final!Q` calcula la variación como `capital mes anterior − IdOperacion` en vez de `− Saldo_Capital` | Columna de variación inservible |
| 8 | Parámetros dispersos entre fórmulas y celdas sueltas, sin vigencia | Un cambio de política obliga a reescribir fórmulas |
| 9 | Acumulado fiscal mantenido sumando términos dentro de una celda | Irreproducible y frágil |
| 10 | No existe regla de suspensión de causación de intereses | Se facturan intereses que no deberían causarse |
| 11 | Dependencia de una sola persona y de su archivo | Continuidad operativa |

**Sobre D-A, D-B y el hallazgo 7:** se corrigen de forma explícita en el módulo. Toda diferencia contra el Excel que resulte de esas correcciones debe documentarse una por una durante la marcha en paralelo, no absorberse en silencio.

---

# PARTE II — Políticas adoptadas

Definidas por Gerencia el 28 de agosto de 2026 y complementadas el 31 de agosto. Son la referencia normativa del desarrollo: cualquier cambio posterior se maneja como nueva versión de este documento.

| ID | Tema | Política |
|---|---|---|
| D-01 | Clasificación de mora | Por operación, no por cliente. No se arrastra la peor calificación del deudor al resto de sus obligaciones. |
| D-02 | Conteo de días | Año comercial de 360 días, meses de 30. Se conserva `DAYS360`. |
| D-03 | Base del deterioro | Capital vencido + intereses vencidos. Excluye administración e intereses de mora. |
| D-04 | Método fiscal | Individual, 33 % anual. El general (5/10/15 %) queda como parámetro desactivado. |
| D-05 | Suspensión de intereses | Por evento y con marcación manual. Causales: fallecimiento sin que la aseguradora pague, admisión a insolvencia, paso a cobro jurídico. No hay umbral automático por días de mora. |
| D-06 | Interés ya causado | Al suspender, el interés se congela: permanece en el activo y en la base de deterioro por el valor que tenía a la fecha del evento, y deja de crecer. **No se reversa contra el ingreso ni sale del balance.** En factoring se sigue calculando internamente sin facturarlo. |
| D-07 | Reestructuraciones | No existen. Se cierra la operación y se abre una nueva, y el conteo de mora arranca desde cero. **No se hace nada al respecto**: no se rastrea la antigüedad anterior ni se vincula la operación nueva con la cerrada. |
| D-08 | Prórrogas | Trasladan las cuotas al final de la operación. |
| D-09 | Castigos | Al castigarse, la operación desaparece de la base de datos. El módulo debe señalar, al mostrar un corte, cuáles operaciones estaban en el corte anterior y ya no aparecen, para que el usuario las marque como castigadas cuando corresponda. |
| D-10 | Garantías | No se descuentan. Mismo tratamiento para financiación, factoring y libranzas. |
| D-11 | Vinculados económicos | No aplica. Mismo tratamiento para todas las operaciones. No se implementa marca de vinculados. |
| D-12 | Marca en factoring | El módulo debe escribir la marca de suspensión en la base de datos del sistema de factoring, para que ese sistema deje de facturar los intereses de las operaciones seleccionadas. |
| D-13 | Saldos de SIESA | El módulo se conecta directamente a la base de datos de SIESA y consulta los saldos por sí mismo. No hay cargue manual de archivos. Sobre SIESA el módulo solo lee. |

## Puntos abiertos

**A · Hasta dónde llega la prioridad de los saldos de SIESA.**
Está definido que el módulo los consulta por sí mismo (D-13). Falta precisar si mandan únicamente para la conciliación y para el tope fiscal de RN-09, como ocurre hoy, o si el saldo de SIESA reemplaza al del sistema de factoring como base del cálculo del deterioro cuando difieran. La segunda opción cambia el resultado y exige definir qué hacer con la antigüedad de la mora, que SIESA no maneja. Requerido antes de la fase 6.

**B · Cómo se marca la suspensión dentro del sistema de factoring.**
Hay que verificar con el proveedor si el sistema cuenta con un campo propio para suspender la causación de intereses y si su proceso de facturación lo respeta. De eso depende el escenario de integración (sección 14), el esfuerzo y los permisos de base de datos. Es la definición con mayor impacto sobre el cronograma. Requerido antes de la fase 5.

**Observación registrada.** En las notas de cartera del archivo figura el NIT 900644447 a nombre de ARAR FINANCIERA SAS con la anotación "reclasificar a otra cuenta". Si corresponde a la propia compañía, conviene revisarlo con el asesor tributario por el segundo inciso del artículo 145 del ET. La política D-11 se mantiene tal como fue definida; esto es solo una verificación puntual y no afecta el desarrollo.

---

# PARTE III — Diseño del módulo (TO-BE)

## 8. Principios

1. **El corte es un snapshot inmutable.** Cerrado un mes, sus datos y resultados no cambian aunque después se corrija la base transaccional. Reproducir julio de 2026 dentro de dos años debe dar el mismo número.
2. **Cero reglas en el código.** Rangos, porcentajes, tasas, causales y topes viven en tablas con `vigente_desde` / `vigente_hasta`. Cambiar una política es un registro nuevo, no un despliegue.
3. **Contable y fiscal se calculan siempre juntos**, sobre la misma base, en la misma corrida.
4. **Todo dato manual es un registro con autor, fecha y motivo.**
5. **Cada cifra es trazable hasta la fila de origen.**
6. **El módulo lee de tres fuentes y escribe en una sola cosa.** Lee de la base de factoring y de la de SIESA; escribe únicamente la marca de suspensión de intereses sobre un campo de la base de factoring, y todo lo demás en su propia base.

## 9. Modelo de datos

### Paramétricas
- `param_rango_mora`: `codigo` (A–F), `dias_desde`, `dias_hasta`, `etiqueta`, `pct_deterioro_contable`, `orden`, vigencias.
- `param_fiscal`: `metodo` (INDIVIDUAL activo, GENERAL desactivado), `pct_anual`, `dias_minimos_mora`, `pct_por_rango`, vigencias.
- `param_producto`: `nom_operacion` → `producto`.
- `param_interes`: `producto`, `tasa_mora_mensual`, `aplica_mora`, `sigue_calculando_suspendido` (verdadero en factoring, por D-06), vigencias.
- `param_causal_suspension`: `codigo`, `descripcion`, `activa`, vigencias. Semilla: fallecimiento sin cobertura, insolvencia, cobro jurídico.
- `param_convencion`: `base_dias` (360), `origen_mora` (fecha inicial de mora), `base_incluye_interes` (sí).

### Transaccionales
- `corte`: `id`, `fecha_corte`, `estado` (ABIERTO | CALCULADO | CERRADO), `usuario`, `fecha_ejecucion`, `id_param_snapshot`, `hash_datos`.
- `corte_detalle_cuota`: copia inmutable de las ~115 mil filas de origen, con `id_corte`. Particionada por corte.
- `corte_saldo_siesa`: copia inmutable de los saldos traídos de SIESA para ese corte, por cliente y operación. Se guarda con el corte para que la conciliación sea reproducible.
- `deterioro_operacion`: una fila por `id_corte` × `id_operacion`. Cliente, producto, saldos, días de mora, rango, base, `deterioro_contable`, `deterioro_fiscal_individual`, `deterioro_fiscal_general`, `fiscal_acumulado_anterior`, `deduccion_fiscal_ano`, `diferencia_temporaria`.
- `fiscal_acumulado`: `id_operacion`, `ano_gravable`, `valor_deducido`, `id_corte_origen`. Sustituye a las hojas `1399 AÑO XXXX`; se alimenta sola en el cierre de diciembre.
- `suspension_interes`: `id_operacion`, `id_causal`, `fecha_evento`, `observacion`, `soporte`, `usuario`, `interes_congelado`, `interes_calculado_no_facturado` (factoring), `marca_factoring_aplicada`, `valor_anterior_factoring`, `fecha_escritura`, `fecha_reactivacion`.
- `salida_operacion`: `id_corte`, `id_operacion`, `clasificacion` (RECAUDO_TOTAL | CASTIGO | CIERRE_CON_REAPERTURA | OTRA), `usuario`, `fecha`, `observacion`.
- `ajuste_manual`: `id_corte`, `tipo` (PRORROGA | RESERVA | RECLASIFICACION), `id_cliente`, `id_operacion`, `valor`, `motivo`, `usuario`, `fecha`, `soporte`.
- `conciliacion`: `id_corte`, `id_cliente`, `saldo_siesa`, `saldo_factoring`, `diferencia`, `explicacion`, `estado`.
- `nota_cartera`: `id_cliente`, `id_corte`, `texto`, `usuario`, `fecha`. Reemplaza `Otros!K:M`.
- `bitacora`: quién ejecutó, recalculó, marcó, ajustó, cerró, reabrió o escribió en factoring.

**No se implementa** tabla ni campo de vinculados económicos (D-11), ni relación entre operación cerrada y su reemplazo (D-07).

## 10. Motor de cálculo

Pipeline en once pasos, reejecutable mientras el corte esté abierto:

1. **Extracción de cartera.** Consulta a `ResumenVigentesClientes` con la fecha de corte. Se guarda tal cual en `corte_detalle_cuota`, con conteo de filas y hash.
2. **Extracción de SIESA.** Consulta directa a la base de SIESA. Se guarda en `corte_saldo_siesa`.
3. **Normalización.** Mapeo de producto, saneamiento de fechas, marcado de registros sin `FecInicialMora`.
4. **Derivadas por cuota.** Días de mora según `param_convencion`, partición corriente/vencido, interés de mora.
5. **Consolidación por operación.** Suma de cuotas y determinación del rango.
6. **Aplicación de suspensiones vigentes.** Para las operaciones marcadas, el interés se toma congelado al valor de `suspension_interes.interes_congelado` en lugar del valor corriente (D-06). En factoring se calcula además el interés no facturado y se acumula aparte.
7. **Deterioro contable.** Base × porcentaje del rango vigente a la fecha de corte.
8. **Deterioro fiscal.** Método individual (D-04) y, en paralelo y solo para análisis, el general.
9. **Tope acumulado fiscal.** RN-09 contra `fiscal_acumulado` y contra el saldo conciliado.
10. **Comparativo y salidas.** Diferencias contra el corte anterior por operación, cliente, producto y rango. Detección de operaciones presentes en el corte anterior y ausentes en el actual, que se cargan en `salida_operacion` como pendientes de clasificar.
11. **Cuadres y cierre.** Verificación de los ocho controles de RN-12. Cambio de estado a CALCULADO.

Objetivo de desempeño: menos de 60 segundos para 115 mil filas, con índices sobre `(id_corte, id_operacion)` y cálculo por lotes en SQL.

## 11. Comparativo contable contra fiscal

Por operación y por corte:

| Concepto | Cálculo |
|---|---|
| Base | capital vencido + interés vencido (congelado si hay suspensión) |
| Deterioro contable acumulado | base × % del rango |
| Deterioro fiscal acumulado | `fiscal_acumulado_anterior + deduccion_fiscal_ano` |
| Diferencia temporaria | contable − fiscal |
| Impuesto diferido activo | diferencia temporaria × tarifa de renta vigente |

La política contable llega al 100 % a los 720 días; la norma fiscal acumula 33 % por año y necesita tres años. Esa brecha es un activo por impuesto diferido que hoy no se mide por operación.

Reportes derivados: puente contable–fiscal por rango y producto (equivalente a `AC30`, `AC34` y `AC35`); evolución de la diferencia temporaria a 12 y 24 meses; proyección del año gravable en que cada operación completa el 100 % fiscal.

**Advertencia que el módulo muestra en pantalla:** la deducción se determina sobre obligaciones que subsistan al 31 de diciembre. Los cortes mensuales son estimaciones; solo diciembre produce la cifra definitiva del año gravable.

## 12. Suspensión de causación de intereses

**Disparador (D-05).** No hay umbral por días de mora. Tres causales parametrizables: fallecimiento del deudor sin que la aseguradora pague, admisión a un proceso de insolvencia, paso a cobro jurídico.

**Marcación.** Manual, operación por operación, por usuario autorizado, con causal, fecha del evento, observación y soporte. Queda en bitácora y puede levantarse. El módulo no marca por su cuenta, pero sugiere candidatas: operaciones con mora alta sin marca, para revisión.

**Efectos (D-06).**
- El interés deja de causarse desde la fecha del evento.
- El interés reconocido hasta esa fecha se congela: permanece en el activo y en la base de deterioro por el valor que tenía. **No se reversa contra el ingreso ni sale del balance.** Esto evita tener que tocar ingresos declarados en ejercicios anteriores.
- El capital sigue deteriorándose normalmente hasta el 100 % a los 720 días.
- En factoring, el interés se sigue calculando y acumulando internamente en `interes_calculado_no_facturado`, sin facturarse, y se expone en reporte aparte.

**Efecto cruzado a vigilar.** Al congelar el interés, la base deja de crecer y con ella el gasto por deterioro del período. Los dos efectos se presentan juntos en el comparativo mensual.

## 13. Integración con el sistema de factoring

Marcar dentro del módulo no basta: si el motor de facturación sigue generando la cuota de intereses, la suspensión no ocurre. La marca tiene que llegar al sistema donde se factura (D-12).

**Escenarios**, según el punto abierto B:

| # | Situación | Comportamiento del módulo |
|---|---|---|
| 1 | El sistema de factoring ya tiene un campo de suspensión y su facturación lo respeta | El módulo actualiza ese campo directamente. Escenario objetivo. |
| 2 | El campo no existe pero el proveedor puede agregarlo | Se solicita el cambio. Mientras llega, el módulo opera con la marca en su propia base y el listado de control. |
| 3 | No existe y el sistema no se puede modificar | La marca vive solo en el módulo y la exclusión se ejecuta por procedimiento manual. Escenario menos deseable: deja la ejecución fuera del control del sistema. |

**Condiciones de la escritura, en cualquier escenario:**

- Usuario de base de datos con permiso de escritura sobre esa única columna de esa única tabla. Sobre todo lo demás, solo lectura.
- Cada escritura queda en bitácora: operación, valor anterior, valor nuevo, usuario, fecha.
- Operación por operación, con confirmación en pantalla. Sin marcación masiva automática.
- Levantar la marca ejecuta la escritura inversa.
- En cada corte el módulo compara sus marcas contra las del sistema de factoring y reporta las que no coincidan, por si alguien las modificó directamente.
- El módulo no toca tasas, saldos, cuotas ni ningún otro dato de la operación.

## 14. Integración con SIESA

Conexión de solo lectura a la base de datos de SIESA (D-13). En cada corte el módulo consulta los saldos por cliente y operación y los guarda en `corte_saldo_siesa`, congelados junto con el resto del snapshot.

Usos: alimentar la conciliación de RN-11 y alimentar el tope fiscal `R` de RN-09, que hoy se digita en `DETERIORO!Q`. Si el punto abierto A se resuelve en el sentido de que SIESA manda también sobre la base del deterioro, ese comportamiento se activa por parámetro y no requiere rediseño.

## 15. Controles

**C-1 · Prórrogas que reducen la antigüedad de la mora.**
La prórroga traslada las cuotas al final (D-08), con lo cual la operación puede bajar de rango o salir de mora y liberar deterioro. El módulo lista cada mes las operaciones cuya antigüedad bajó respecto al corte anterior, con el deterioro liberado, para validación. Tiene efecto fiscal: la deducción del 33 % exige más de un año de vencimiento y la prórroga reinicia ese conteo.

**C-2 · Salidas de la base entre cortes.**
Las operaciones castigadas desaparecen de la base (D-09). El corte cerrado conserva su copia, así que no se pierden del histórico. Al mostrar las operaciones del corte, el módulo señala cuáles estaban en el anterior y ya no aparecen, y permite marcarlas como castigadas. Las demás se clasifican como recaudo total, cierre con apertura de una nueva operación, u otra causa. La clasificación importa por dos razones: el castigo tiene tratamiento fiscal propio y el deterioro acumulado debe cerrarse en el mismo movimiento; y sin la etiqueta, una caída por cierre con reapertura se vería igual que un recaudo en la descomposición del movimiento del mes.

**C-3 · Conciliación con SIESA.**
Diferencias por cliente entre el saldo de SIESA y el del sistema de factoring, con captura de explicación y estado. El corte no se puede cerrar con partidas sin explicar.

**C-4 · Sincronía de marcas con factoring.**
Comparación de las marcas de suspensión del módulo contra las del sistema de factoring, para detectar cambios hechos por fuera.

## 16. Pantallas

| Pantalla | Contenido |
|---|---|
| Resumen del corte | Matriz producto × rango con capital, interés, base y deterioro. Réplica de `T14:AB27` con semáforos de cuadre. |
| Detalle por operación | Grilla filtrable y exportable, con enlace al detalle de cuotas y señalización de operaciones ausentes respecto al corte anterior. |
| Contable contra fiscal | Comparativo por operación y consolidado, diferencia temporaria, impuesto diferido y proyección de reversión. |
| Evolución | Series históricas y descomposición del movimiento del mes. |
| Intereses suspendidos | Operaciones marcadas, causal, fecha del evento, interés congelado, interés no facturado del mes y acumulado, estado de la marca en factoring. |
| Conciliación | Cruce con SIESA por cliente. |
| Controles | C-1 a C-4. |
| Ajustes y notas | Prórrogas, reservas, reclasificaciones y observaciones, con soporte y autor. |
| Parámetros | Rangos, porcentajes, tasas y causales, con vigencias. Acceso restringido. |
| Exportables | Excel con la estructura actual para la transición, PDF del resumen, archivo plano del asiento contable. |

## 17. Salidas contables

- Ajuste del deterioro del período contra la cuenta 1399, con el gasto contra la cuenta que defina Contabilidad.
- Detalle del ajuste por producto para el mayor auxiliar.
- Anexo de deducción fiscal del año gravable.
- Anexo de diferencias temporarias para impuesto diferido.
- Anexo de intereses no causados y de intereses calculados no facturados en factoring, para revelaciones.

## 18. Seguridad y permisos

| Conexión | Alcance |
|---|---|
| Base de factoring | Lectura sobre las vistas de cartera. Escritura únicamente sobre el campo de suspensión de intereses. |
| Base de SIESA | Solo lectura sobre los saldos requeridos. |
| Base del módulo | Lectura y escritura completas. |

Credenciales gestionadas en el servidor de la aplicación, nunca en el cliente ni en archivos distribuibles. Perfiles diferenciados para consultar, calcular, marcar suspensiones, ajustar parámetros y cerrar o reabrir cortes.

## 19. Plan de trabajo

| Fase | Alcance | Estimación | Depende de |
|---|---|---|---|
| 0 | Definición de políticas con Contabilidad | 1 a 4 horas | Completada |
| 1 | Modelo de datos, snapshot y cálculo contable, replicando julio de 2026 | 4 horas | — |
| 2 | Cálculo fiscal individual, acumulado por año y tope | 4 horas | — |
| 3 | Comparativo contable contra fiscal e impuesto diferido | 4 horas | — |
| 4 | Histórico y navegación entre cortes | 2 horas | — |
| 5 | Suspensión de intereses y marcación en factoring | 3 horas + integración, por estimar según escenario | Punto B |
| 6 | Conexión a SIESA, conciliación, ajustes y notas | 4 horas | Punto A |
| 7 | Controles C-1 a C-4, cierre de corte, permisos, auditoría y exportables | 6 horas | — |
| 8 | Migración del histórico y marcha en paralelo | 6 a 8 horas | — |

**Marcha en paralelo:** tres cortes completos ejecutando Excel y módulo al mismo tiempo. El Excel se retira cuando los tres cierren con diferencia cero, validados por Contabilidad.

## 20. Criterios de aceptación

- Réplica del corte de julio de 2026 con diferencia máxima de 1 peso en cada uno de los ocho cuadres de RN-12, salvo las diferencias explicadas por las correcciones de D-A, D-B y el hallazgo 7.
- Casos borde probados explícitamente: 30/31 días, 90/91, 180/181, 360/361, 720/721; operación sin `FecInicialMora`; operación que sale de mora entre cortes; operación con saldo negativo; operación prorrogada que baja de rango; operación marcada como suspendida; operación que desaparece entre cortes.
- Recálculo completo de un corte de 115 mil filas en menos de 60 segundos.
- Un corte cerrado y recalculado seis meses después devuelve exactamente los mismos valores.
- Toda cifra del resumen permite descender hasta la cuota de origen en un máximo de tres clics.
- Toda escritura sobre la base de factoring queda registrada en bitácora con valor anterior y nuevo.

## 21. Riesgos

| Riesgo | Mitigación |
|---|---|
| El escenario 2 del punto B deja la fase 5 dependiendo del proveedor | Resolver el punto B antes que el A. Diseñar la fase 5 de modo que el módulo funcione con marca propia mientras llega el campo. |
| Escribir en la base de un sistema de terceros | Permiso acotado a una columna, bitácora de cada escritura, control C-4 de sincronía, y sin marcación masiva. |
| Las reglas del Excel se replican con sus defectos | D-A, D-B y el hallazgo 7 se corrigen de forma explícita y documentada. |
| El acumulado fiscal histórico está incompleto o es inconsistente | Auditar las hojas `1399` antes de migrar. Es el insumo del tope de RN-09 y un error ahí se arrastra por años. |
| Cambios en `ResumenVigentesClientes` o en el esquema de SIESA rompen el ETL | Contrato de datos versionado y validación de esquema en cada extracción. |
| El punto A se resuelve a favor de que SIESA mande sobre la base | Dejarlo previsto como parámetro desde la fase 1 para no rediseñar. |

---

## Anexo A · Mapa de fórmulas del archivo actual

| Concepto | Celda | Fórmula |
|---|---|---|
| Días de mora por cuota | `BD!AJ` | `DAYS360(FecFinalCorriente; MENU!D9)` |
| Clasificación | `BD!AK` | `SI.ERROR(BUSCARV(DAYS360(FecInicialMora; MENU!D9); Otros!B:C; 2; 1); "Corriente")` |
| Días de mora de la operación | `BD!AL` | `SI(CalificacionABC="Corriente"; 0; DAYS360(FecInicialMora; MENU!D9))` |
| Interés de mora | `BD!AI` | `SI(Producto="FACTORING"; 0; SI(Dias_mora>0; SaldoCapital*0,0233/30*Dias_mora; 0))` |
| Capital mes anterior | `Tabla final!P` | `SUMAR.SI.CONJUNTO(Mes_anterior!N:N; …cliente; …modalidad; …operación)` |
| Base de deterioro | `DETERIORO!I` | `=G+H` |
| Clasificación por rango | `DETERIORO!L` | `SI.ERROR(BUSCARV(J; Otros!B:D; 3; 1); 0)` |
| Deterioro contable | `DETERIORO!M` | cadena de 6 `SI` sobre `V15:AA15` × `V4:AA4` |
| Deterioro fiscal individual | `DETERIORO!O` | `SI(O(L=E; L=F); I*33 %; 0)` |
| Deducción fiscal del año | `DETERIORO!N` | `MAX(0; SI(P+O>R; R−P; O))` |
| Acumulado fiscal anterior | `DETERIORO!P` | `SI.ERROR(BUSCARV(A; '1399 AÑO 2025'!A:C; 3; 0); 0)` |
| Saldo topado | `DETERIORO!R` | `SI(Q>=I; I; 0) + SI(Q<I; Q; 0)` |
| Fiscal individual total | `DETERIORO!AB30` | `(Z25+Z26+AA25+AA26)*33 %` |
| Fiscal general total | `DETERIORO!Z32:AB32` | `(X25+X26)*5 % + (Y25+Y26)*10 % + (Z25+Z26+AA25+AA26)*15 %` |
| Gasto contable del mes | `DETERIORO!AC34` | `=AB10` |
| Deducción fiscal del año | `DETERIORO!AC35` | `=N2112` |

## Anexo B · Respuestas de Gerencia

Transcripción textual, 28 de agosto de 2026:

| Consulta | Respuesta |
|---|---|
| Clasificación por operación o por cliente | Por operación, ya que un cliente puede tener varias operaciones y algunas en mora y otras no. |
| Días comerciales o calendario | Se maneja 360, de 30 en 30 días. |
| Base del deterioro | Sí, esa es la fórmula del deterioro. |
| Método fiscal | Se aplica individual del 33 % anual. |
| Momento de la suspensión de intereses | Cuando fallecen y la aseguradora no paga, admitidos en solvencia o cuando el cliente va a cobro jurídico, sería manual ya que puede ser cualquiera. Ver la posibilidad de priorizar los saldos de SIESA dado que son los finales. |
| Interés ya causado | Se reversan y salen del activo, con lo cual la base de deterioro queda solo con capital y el interés a esa fecha, pero este interés ya no seguirá creciendo en deterioro; sin embargo, en factoring debe seguir contando pero sin facturarlo. |
| Reestructuraciones y prórrogas | Reestructuraciones no se hacen ya que se crea una nueva operación tras cerrar la anterior. La prórroga lo que hace actualmente es mover las cuotas al final. |
| Castigos | Las operaciones al castigarse se van del sistema, desaparecen de la base de datos. |
| Garantías | Para todos aplican igual. |
| Vinculados económicos | No aplica, para todas las operaciones es igual. |

Precisiones del 31 de agosto de 2026:

- El módulo se conecta a la base de datos de SIESA y consulta los saldos por sí mismo; no es un cargue manual.
- El deterioro se toma del capital vencido más el interés congelado hasta la fecha de suspensión. Solo deja de crecer el interés.
- Al mostrar las operaciones debe señalarse cuáles estaban en el corte anterior y no en el actual, para que el usuario pueda marcarlas como castigadas si es necesario.
- Sobre las reestructuraciones no se hace nada: al iniciarse la nueva operación todo se reinicia como si la anterior no hubiera existido.
- El módulo debe marcar la operación en la base de datos del sistema de factoring para que deje de facturar los intereses.

---

*Las referencias normativas (artículo 145 del Estatuto Tributario y artículos 1.2.1.18.19 y siguientes del Decreto 1625 de 2016) se citan como contexto y deben confirmarse con el asesor tributario antes de parametrizar el módulo.*
