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
| 10 | No existe regla de suspensión de causación de intereses en el sistema. Contabilidad lleva la lista vigente **en un archivo de Excel aparte**, por fuera del libro de deterioro y por fuera de la base de datos | Se facturan intereses que no deberían causarse, y el estado de las suspensiones vive en un archivo que nadie más puede consultar |
| 11 | Dependencia de una sola persona y de su archivo | Continuidad operativa |
| 12 | **El sistema de factoring entrega cuotas repetidas.** `ResumenVigentesClientes` devuelve para una misma operación dos filas indistinguibles —mismas fechas, mismos saldos— que sólo difieren en `IdCuota` e `IdDetalleOperacion` | El saldo de la operación queda inflado, en el libro y en cualquier sistema que copie el origen sin mirar |

**Sobre D-A, D-B y el hallazgo 7:** se corrigen de forma explícita en el módulo. Toda diferencia contra el Excel que resulte de esas correcciones debe documentarse una por una durante la marcha en paralelo, no absorberse en silencio.

**Sobre el hallazgo 12, encontrado el 15 de septiembre de 2026.** Lo detectó un usuario al abrir el detalle de cuotas de la **operación 8267**, que muestra los números 5 a 36 y después 69, 70, 71 y 72. Las cuatro últimas son copia exacta de las cuotas 33 a 36.

Medido sobre los cuatro cortes de `ArarFinanciera_PRUEBAS`, 426.264 filas: **una sola operación afectada, la 8267, de producto LIBRANZAS**, con 4 filas repetidas en los cortes 1, 2 y 4 —737.805,00 de capital y 35.563,00 de interés en cada uno— y ninguna en el corte 3. **Ninguna está vencida**, de modo que hoy no aportan un peso a la base de deterioro; vencen a partir de diciembre de 2028.

Quien recalcule esos cortes debe esperar exactamente eso y nada más: los cortes 1, 2 y 4 pierden 737.805,00 de capital y 35.563,00 de interés en esa operación, el corte 3 no se mueve, y **el deterioro de los cuatro queda igual**.

El defecto está en el origen y no en el módulo: `Modulos_Faico.dbo.ResumenVigentesClientes` entrega 36 filas para esa operación con `IdCuota` de 5 a 72. La extracción las copia tal cual, porque es un `INSERT ... SELECT` sin filtro, y eso es deliberado: ver la sección 13.

**Ninguno de los controles de RN-12 podía verlo.** C-CUOTAS, C-CAPITAL y C-INTERES comparan el detalle contra el consolidado, y el consolidado se deriva del mismo detalle: un duplicado consistente pasa en verde por los dos lados. Es un punto ciego estructural, no un defecto de esos tres controles, y es lo que justifica los dos cuadres nuevos de la sección 15.

**Lo que se descartó, y por qué queda escrito.** La primera hipótesis fue validar la continuidad de la numeración: si una operación va por la cuota 24, la siguiente debe ser la 25. **No sirve.** Esa misma operación empieza en la cuota 5 —las cuatro primeras se pagaron y salieron del resumen de vigentes— y tiene un hueco de la 37 a la 68. Los saltos en la numeración son el estado normal de cualquier operación con pagos: un filtro de continuidad señalaría operaciones sanas por millares y sólo atraparía este caso por casualidad.

---

# PARTE II — Políticas adoptadas

Definidas por Gerencia el 28 de agosto de 2026 y complementadas el 31 de agosto. D-14 y D-15 las agregó Contabilidad el 3 de septiembre de 2026, al entregar el listado vigente de operaciones con intereses suspendidos. Son la referencia normativa del desarrollo: cualquier cambio posterior se maneja como nueva versión de este documento.

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
| D-14 | Cargue inicial de suspensiones | La lista de operaciones que **hoy** tienen la causación suspendida existe en un archivo de Excel de Contabilidad y entra al módulo por cargue, no marcándolas una por una. Es el estado inicial del que parte la marcación manual de D-05; de ahí en adelante rige D-05. |
| D-15 | Base de las operaciones del cargue inicial | Todas las operaciones de ese archivo **generan deterioro**, y su base se toma del **saldo que tienen en SIESA**, no del saldo del sistema de factoring. Aplica también a las que ya no existen en la base de factoring. |
| D-16 | Composición de la base tomada de SIESA | La base de una operación suspendida con saldo en SIESA es **el saldo de SIESA más el interés congelado** de D-06. El saldo de SIESA es capital total y no trae interés; sumarle el congelado es lo único que hace compatibles D-06 y D-15. Aplica **sólo a las operaciones suspendidas**: el resto de la cartera conserva la base de factoring de D-03. |

**Alcance de D-15.** No es una excepción cosmética: cambia el origen de la base de cálculo para un subconjunto de operaciones y **resuelve parcialmente el punto abierto A** en el sentido de que SIESA manda sobre la base, pero sólo para ese subconjunto y no para toda la cartera. El parámetro `siesa_manda_sobre_base` quedó previsto en la fase 1 como interruptor **global por corte**; D-15 exige además un alcance **por operación**, porque en el mismo corte convivirán operaciones con base de factoring y operaciones con base de SIESA.

**Consecuencia sobre el plan.** D-15 hace que la fase 5 dependa de la conexión de lectura a SIESA, que estaba planificada en la fase 6. La fase 5 ya no es independiente de la fase 6: ver la sección 19.

## Puntos abiertos

**A · Hasta dónde llega la prioridad de los saldos de SIESA.**
Está definido que el módulo los consulta por sí mismo (D-13). Falta precisar si mandan únicamente para la conciliación y para el tope fiscal de RN-09, como ocurre hoy, o si el saldo de SIESA reemplaza al del sistema de factoring como base del cálculo del deterioro cuando difieran. La segunda opción cambia el resultado y exige definir qué hacer con la antigüedad de la mora, que SIESA no maneja. Requerido antes de la fase 6.

> **Resuelto en parte el 3 de septiembre de 2026.** D-15 decide que SIESA manda sobre la base, pero sólo para las operaciones del cargue inicial de suspensiones. Para el resto de la cartera el punto A sigue abierto. La advertencia sobre la antigüedad de la mora no se resolvió: se convirtió en el punto C, que ahora es bloqueante para la fase 5 y no para la 6.

> **Cerrado el 4 de septiembre de 2026, al abrir la fase 6.** SIESA manda sobre la base **sólo para las operaciones suspendidas**. Para el resto de la cartera la base sigue saliendo del sistema de factoring, con lo cual la réplica al centavo de los tres cortes validados contra el libro se conserva y el criterio de aceptación de julio de 2026 sigue vigente. La lectura global de A —SIESA manda sobre toda la cartera— quedó descartada: habría movido el deterioro del corte completo en una magnitud no medida y roto ese criterio.
>
> El interruptor `siesa_manda_sobre_base` queda como estaba, global y congelado por corte; el alcance real lo resuelve el origen de la marca de suspensión y se registra operación por operación en la columna `origen_base`, no un parámetro que el usuario pueda cambiar sin advertirlo.

**B · Cómo se marca la suspensión dentro del sistema de factoring.**
Hay que verificar con el proveedor si el sistema cuenta con un campo propio para suspender la causación de intereses y si su proceso de facturación lo respeta. De eso depende el escenario de integración (sección 14), el esfuerzo y los permisos de base de datos. Es la definición con mayor impacto sobre el cronograma. Requerido antes de la fase 5.

**C · Con qué antigüedad de mora se deteriora una operación que ya no está en la base de factoring.**
D-15 ordena que las operaciones del cargue inicial generen deterioro, y algunas ya no existen en `ResumenVigentesClientes`. El deterioro contable es `base × porcentaje del rango`, y el rango sale de los días de mora, que salen de `FecInicialMora` del sistema de factoring. Si la operación no está ahí **no hay fecha, no hay días, no hay rango y no hay porcentaje**: el módulo no puede calcular su deterioro, ni siquiera teniendo el saldo de SIESA. SIESA no maneja antigüedad de mora.

Las opciones son: fijar el rango F (721 días o más, 100 %) por presunción para todas; conservar la última antigüedad conocida del último corte en que la operación apareció, envejeciéndola mes a mes; o digitar la fecha del evento en el archivo de cargue y contar desde ahí. Las tres dan cifras distintas. **Bloqueante para la fase 5**: sin esta definición el deterioro de esas operaciones no se puede calcular.

**D · Qué saldo de SIESA es la base.**
D-03 define la base como capital vencido más intereses vencidos, y D-06 congela el interés dentro de esa base. Falta precisar qué cuenta o qué componente del saldo de SIESA sustituye a esa base: si el saldo de SIESA trae capital e interés juntos, se necesita saber cómo se separan para no duplicar ni perder el interés congelado, y si trae sólo capital, hay que decidir si el interés congelado se le suma. Requerido antes de la fase 5.

> **Cerrado el 4 de septiembre de 2026 por D-16.** La exploración del 3 de septiembre respondió la parte fáctica: el saldo de SIESA es capital corriente más capital vencido, y **no trae interés de ninguna clase**. Sobre eso, la base de una operación suspendida es **saldo de SIESA más interés congelado**.
>
> Queda una consecuencia que hay que asumir explícitamente y no esconder: **el saldo de SIESA incluye el capital corriente, que D-03 excluye de la base**. Para las operaciones suspendidas, D-16 prevalece sobre D-03 en ese punto.
>
> **Corrección medida el 4 de septiembre.** Al implementar se supuso que sumar el interés congelado haría crecer el efecto de +83.049.290,58 medido el 3 de septiembre. **No es así, y el supuesto era falso por una razón algebraica:** contra la base de factoring congelada el interés congelado aparece en los dos lados y **se cancela exactamente**. El efecto es `SUM((saldo_siesa − capital_vencido) × pct)` y no depende del interés.
>
> Las tres cifras, sobre las 74 operaciones suspendidas con saldo `OPE` del corte de julio:
>
> | | |
> |---|---:|
> | Base de factoring congelada (`capital vencido + interés congelado`) | 168.542.927,00 |
> | Base de SIESA congelada (D-16) | 382.853.141,00 |
> | Base de D-03 (`capital vencido + interés vencido`) | 318.554.177,00 |
> | Deterioro con base de factoring congelada | 151.635.599,32 |
> | **Deterioro con base de SIESA (D-16)** | **284.112.024,82** |
> | Efecto de D-16 contra la base de factoring congelada | +132.476.425,50 |
> | **Efecto de D-16 contra la base de D-03, que es la comparación de §14.1** | **−3.224.570,26** |
>
> Contra la comparación que hizo la sección 14.1, **el efecto cambió de signo**: era +83.049.290,58 y hoy es −3.224.570,26. Dos causas, ninguna de ellas un defecto: la población pasó de 172 operaciones a 74, porque sólo se suspenden las marcas con interés congelado; y en el rango F, que deteriora al 100 %, el saldo de SIESA está **por debajo** de la base de factoring, con lo que 31 operaciones aportan −33.028.660,00 y se comen los +29.804.089,74 de los rangos B a E.
>
> Efecto total del congelamiento sobre el corte de julio: **−41.240.651,37**, de 1.753.977.960,01 a 1.712.737.308,64, repartido en −38.016.081,11 de las de origen factoring y −3.224.570,26 de las de origen SIESA.

**E · Por qué desaparecieron de la base de factoring, y su relación con D-09.**
D-09 dice que una operación castigada desaparece de la base de datos. Si las operaciones ausentes del archivo desaparecieron por castigo, generar deterioro sobre ellas contradice el castigo, donde el deterioro acumulado se cierra en el mismo movimiento. Que Contabilidad pida deteriorarlas indica que no están castigadas, y entonces falta saber la causa real de la ausencia —probablemente estén en la contabilidad de SIESA y no en el sistema operativo de factoring, que es justo la diferencia que concilia RN-11—. Determina si estas operaciones entran por `salida_operacion` clasificadas, o si son un caso nuevo. Requerido antes de la fase 5.

**F · Tolerancia de materialidad de la conciliación.**
Abierto el 4 de septiembre de 2026 al diseñar la pantalla de conciliación. C-3 exige explicar toda partida antes de cerrar el corte, y las diferencias medidas son 326 sobre 1.935 operaciones que cruzan. **No hay definida ninguna cuantía por debajo de la cual una diferencia no deba explicarse.** Si el residuo incluye partidas de centavos, el módulo obligará a explicar ruido para poder cerrar, y el control se degrada a trámite. Es una decisión de política contable, no de diseño. Requerido antes de que el bloqueo del cierre entre en vigor, en la fase 7; no bloquea la fase 6.

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
- `param_causal_suspension`: `codigo`, `descripcion`, `activa`, vigencias. Semilla: fallecimiento sin cobertura, insolvencia, cobro jurídico. El cargue inicial de D-14 necesita además una causal para las filas del archivo que no traigan una identificable.
- `param_convencion`: `base_dias` (360), `origen_mora` (fecha inicial de mora), `base_incluye_interes` (sí).

### Transaccionales
- `corte`: `id`, `fecha_corte`, `estado` (ABIERTO | CALCULADO | CERRADO), `usuario`, `fecha_ejecucion`, `id_param_snapshot`, `hash_datos`.
- `corte_detalle_cuota`: copia inmutable de las ~115 mil filas de origen, con `id_corte`. Particionada por corte.
- `corte_saldo_siesa`: copia inmutable de los saldos traídos de SIESA para ese corte, por cliente y operación. Se guarda con el corte para que la conciliación sea reproducible.
- `deterioro_operacion`: una fila por `id_corte` × `id_operacion`. Cliente, producto, saldos, días de mora, rango, base, `deterioro_contable`, `deterioro_fiscal_individual`, `deterioro_fiscal_general`, `fiscal_acumulado_anterior`, `deduccion_fiscal_ano`, `diferencia_temporaria`. Por D-15 lleva además `origen_fila` (EXTRACCION | CARGUE_SUSPENSION) y `base_origen` (FACTORING | SIESA), para que en el mismo corte se pueda leer, fila por fila, de dónde salió el dato y por qué.
- `fiscal_acumulado`: `id_operacion`, `ano_gravable`, `valor_deducido`, `id_corte_origen`. Sustituye a las hojas `1399 AÑO XXXX`; se alimenta sola en el cierre de diciembre.
- `suspension_interes`: `id_operacion`, `id_causal`, `fecha_evento`, `observacion`, `soporte`, `usuario`, `interes_congelado`, `interes_calculado_no_facturado` (factoring), `marca_factoring_aplicada`, `valor_anterior_factoring`, `fecha_escritura`, `fecha_reactivacion`, y por D-14 `origen` (CARGUE_INICIAL | MANUAL) y `existe_en_factoring`. El `origen` es lo que activa la base de SIESA de D-15, de modo que no puede quedar implícito.
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
6. **Aplicación de suspensiones vigentes.** Para las operaciones marcadas, el interés se toma congelado al valor de `suspension_interes.interes_congelado` en lugar del valor corriente (D-06). En factoring se calcula además el interés no facturado y se acumula aparte. Para las de `origen = CARGUE_INICIAL`, la base se reemplaza por la de SIESA (D-15) y se **inyectan** las filas de las operaciones que ya no están en la extracción, marcadas con `origen_fila = CARGUE_SUSPENSION`. Va después de la consolidación y antes del deterioro contable: si se inyectara antes, las filas nuevas entrarían al consolidado sin cuotas de origen y descuadrarían los controles de detalle contra resumen.
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

### 12.1 Cargue inicial desde el archivo de Contabilidad (D-14)

La marcación de D-05 es operación por operación y hacia adelante, pero **el estado inicial ya existe**: Contabilidad mantiene un archivo de Excel con las operaciones que a hoy tienen la causación suspendida. Ese archivo es el punto de partida del módulo y entra por cargue.

El cargue sigue el patrón del comando de la fase 4 (`deterioro:cargar-validacion-excel`): archivo plano con contrato de columnas explícito, validación de todo el archivo antes de escribir una sola fila, upsert idempotente y bitácora. Nunca por edición de código.

Cada fila del archivo produce una marca en `suspension_interes` con `origen = CARGUE_INICIAL`, que la distingue de las marcadas a mano después. La distinción no es decorativa: es la que activa la base de SIESA de D-15 y la que permite auditar de dónde salió cada marca.

**Las operaciones se parten en dos poblaciones**, y el módulo tiene que tratarlas distinto:

| Población | Situación | Tratamiento |
|---|---|---|
| Presentes en `ResumenVigentesClientes` | La operación existe en factoring | Se extrae y consolida como cualquier otra, pero su base se reemplaza por la de SIESA (D-15) y su interés se congela (D-06). |
| Ausentes de `ResumenVigentesClientes` | La operación ya no está en factoring | No hay fila que consolidar: el módulo tiene que **inyectarla** en `deterioro_operacion` a partir del archivo y del saldo de SIESA. |

La inyección es el punto delicado del diseño. Es la misma mecánica que RN-11 usa para meter las prórrogas y reservas como fila adicional en `DETERIORO!F3`, con una diferencia que invierte el propósito: **aquella fila entra clasificada como Corriente para que sume capital sin generar deterioro, y estas entran justamente para generarlo.** Exige además:

- Marcar el origen de la fila (`origen_fila = CARGUE_SUSPENSION` frente a `EXTRACCION`), porque una fila que no viene de la extracción rompe la premisa de los controles C-CUOTAS, C-CAPITAL y C-INTERES de RN-12, que comparan el detalle de cuotas contra el consolidado por operación. Una fila inyectada no tiene cuotas de origen y descuadraría los tres controles si se contara como si las tuviera.
- Definir la antigüedad de la mora con la que se deteriora, que es el **punto abierto C** y hoy no está resuelto.
- Conservar la inmutabilidad del snapshot: el archivo se congela con el corte igual que las paramétricas, para que reproducir el corte dentro de dos años dé el mismo número aunque el archivo haya cambiado.

**Control nuevo que esto obliga.** Toda operación del cargue inicial debe quedar con base y deterioro resueltos en el corte, o aparecer listada como no resuelta. Una operación que Contabilidad marcó y que el módulo silenciosamente no deterioró es un faltante contable, y el corte no debería poder cerrarse con faltantes de este tipo sin explicación.

### 12.2 Perfil medido del archivo entregado

Archivo `terceros a excluir agosto 2026.xlsx`, hoja `TOTAL`, entregado el 3 de septiembre de 2026. Medido contra `ArarFinanciera_PRUEBAS`.

**Estructura.** Tres columnas: `CEDULA`, `NOMBRE`, `OP`. 281 filas de datos, sin filas vacías, sin números de operación repetidos y todos numéricos. Es un archivo limpio.

**Lo que el archivo no trae.** No hay causal, no hay fecha del evento, no hay soporte. Las tres son obligatorias en D-05, y la fecha del evento no es un dato administrativo: **es el anclaje del congelamiento de D-06**, el que dice a qué valor se congela el interés. Sin ella el módulo no puede poblar `interes_congelado`, y sin `interes_congelado` la operación no se congela. También elimina una de las tres salidas del punto abierto C, la de contar la mora desde la fecha del evento digitada en el archivo.

**Cobertura contra los cortes existentes.**

| Medida | Operaciones |
|---|---:|
| En el archivo | 281 |
| Presentes en el corte de julio de 2026 | 225 |
| **Ausentes del corte de julio de 2026** | **56** |
| Ausentes también del corte de junio de 2026 | 52 |
| Sin aparecer en ninguno de los tres cortes | 15 |
| Presentes en junio y ausentes en julio | 4 |

**Magnitud.** Las 225 presentes en julio suman **860.903.554,26** de deterioro contable, que es el **49,1 %** del deterioro del corte, 1.753.977.960,01. La lista de suspensiones no es un caso marginal: toca la mitad del módulo.

**Colisión que aparece al medir.** De las 225 presentes, **19 no generan deterioro hoy** —8 en rango A y 11 corrientes, con 1.015.301,00 de base entre todas— porque su porcentaje de RN-04 es cero. D-15 dice que *todas* las del archivo generan deterioro, y para estas 19 el saldo de SIESA no cambia nada: cero por cualquier base sigue siendo cero. O la instrucción significa que la suspensión las reclasifica a un rango deteriorable, que sería una regla nueva y de mucho peso, o significa que se deterioran sólo las que ya tienen mora, que es lo que el módulo hace hoy. Hay que preguntarlo antes de implementar.

**Reparto por rango de las 225 presentes en julio:** 76 en F, 74 en E, 39 en D, 9 en C, 8 en B, 8 en A y 11 corrientes. La concentración en E y F es coherente con una lista de operaciones en cobro jurídico o insolvencia.

## 13. Integración con el sistema de factoring

Marcar dentro del módulo no basta: si el motor de facturación sigue generando la cuota de intereses, la suspensión no ocurre. La marca tiene que llegar al sistema donde se factura (D-12).

**Sobre la calidad del dato que entrega el origen.** `ResumenVigentesClientes` devuelve en algunos casos cuotas repetidas (hallazgo 12 de la sección 7). La extracción del módulo **no las filtra a propósito**: es un `INSERT ... SELECT` sin `DISTINCT` ni `WHERE`, de modo que el detalle de un corte es la copia literal de lo que el sistema de factoring entregó ese día y el módulo puede demostrarlo años después. La corrección se aplica al consolidar, no al extraer, y queda medida en los dos cuadres de la sección 15. **Corregirlo de verdad es cosa del sistema de factoring**, y el caso concreto —la operación 8267— debe reportarse a quien lo administra: el módulo no escribe en esa base salvo la marca de suspensión de D-12.

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

**Adelanto de la conexión a la fase 5 (D-15).** El tercer uso ya está decidido y llega antes de lo previsto: las operaciones del cargue inicial de suspensiones toman su base del saldo de SIESA. Eso obliga a tener la lectura de SIESA disponible en la fase 5, no en la 6, con dos consecuencias:

- La fase 5 necesita conectividad, credenciales de solo lectura y el mapeo de la operación de factoring contra su equivalente en SIESA. Ese mapeo es un supuesto que hay que verificar: si SIESA identifica por cliente y no por operación, D-15 no se puede aplicar por operación y habría que repartir el saldo del cliente entre sus operaciones, que es un problema distinto y peor.
- El interruptor `siesa_manda_sobre_base` no alcanza, porque es global por corte. Se necesita alcance por operación, resuelto por el origen de la marca de suspensión y no por un parámetro que el usuario pueda cambiar sin advertirlo.

### 14.1 Exploración de SIESA — hallazgos del 3 de septiembre de 2026

Antes de diseñar la fase 6 se exploró el esquema real de `UNOEEARAR`. Tres resultados cambian supuestos del diseño.

**SIESA sí identifica por operación.** Era el riesgo mayor del punto abierto A: "si SIESA identifica por cliente y no por operación, D-15 no se puede aplicar por operación". **No es el caso.** La cartera abierta vive en `t353_co_saldo_abierto`, con 532.137 filas para la compañía 7, y su tipo de documento de cruce `OPE` lleva en `f353_consec_docto_cruce` **el número de operación de factoring**.

Comprobado contra el corte de julio de 2026: **1.935 de las 2.106 operaciones del corte (91,9 %) cruzan contra un `OPE`**, y de esas **1.609 coinciden al peso con el capital de factoring**. El problema de repartir el saldo del cliente entre sus operaciones no existe.

**El saldo de SIESA es capital total, no la base de deterioro.** La coincidencia al peso se da contra `capital_corriente + capital_vencido`. Es decir, el saldo de SIESA:

- **incluye el capital corriente**, que la base de deterioro excluye por D-03;
- **no incluye interés**, ni corriente ni vencido.

Esto tiene una consecuencia que hay que resolver antes de implementar D-15: si la base de las operaciones suspendidas pasa a ser el saldo de SIESA, entonces **contradice D-06**, que dice que el interés congelado permanece en la base. Un saldo de capital no tiene interés que congelar. Las dos políticas no pueden aplicarse a la vez tal como están escritas.

**La fórmula del saldo.** En esta instalación las columnas `f353_total_*_pendientes` están en cero; el saldo vivo es `f353_total_db − f353_total_cr`. Total de la compañía 7: **8.553.341.244,10** en 128.812 filas y 1.812 terceros.

**Magnitud de D-15 sobre las suspendidas.** De las 211 operaciones marcadas presentes en el corte de julio, **172 tienen saldo `OPE`** y 39 no.

| Sobre las 172 comparables | |
|---|---:|
| Base de deterioro hoy, de factoring | 624.281.420,00 |
| Saldo de SIESA | 949.762.343,00 |
| Deterioro hoy | 522.715.159,25 |
| Deterioro con la base de SIESA | 605.764.449,83 |
| **Efecto de D-15** | **+83.049.290,58** |

D-15 **aumenta** el deterioro, en dirección contraria al congelamiento de D-06, que lo reducía en 173,7 millones. No se compensan ni se suman de forma simple: si la base pasa a ser el saldo de SIESA, el congelamiento del interés deja de tener efecto sobre esas operaciones, porque ya no hay interés en la base.

Las 39 sin saldo `OPE` traen hoy 296.955.673,61 de deterioro y necesitan decisión propia: conservar la base de factoring o quedar en cero.

**Conciliación RN-11, dimensionada.** Entre las 1.935 operaciones que cruzan, la diferencia agregada contra el capital de factoring es de **−915.297.792,00**, concentrada en 326 operaciones. Más 171 operaciones del corte sin ningún `OPE` en SIESA. Ese es el trabajo real de C-3, y confirma que las 4 horas estimadas para la fase 6 se quedan cortas.

### 14.2 Reproducibilidad del snapshot — medido el 4 de septiembre de 2026

El criterio de aceptación exige que un corte cerrado y recalculado seis meses después devuelva exactamente los mismos valores. El snapshot de SIESA se acota por fecha de movimiento, y eso obliga a preguntarse si una reextracción futura puede dar otro número.

**El riesgo obvio no se materializa.** Se temía que una fila cancelada desapareciera de `t353_co_saldo_abierto`. No ocurre en esta instalación: 403.441 de las 532.144 filas de la compañía 7 tienen saldo neto cero y **siguen en la tabla**. Reconstruido el snapshot del 30 de septiembre de 2023, 62.809 filas ya canceladas hoy siguen presentes y suman 4.178.812.674,94, que es exactamente lo que produce la reextracción de ese corte.

**El agujero real es otro y está abierto.** `t354_co_mov_saldo_abierto` **no tiene sello de creación**: de sus 33 columnas, las dos de fecha son de negocio. Un movimiento con fecha anterior o igual a la del corte, registrado en SIESA **después** de la extracción, cambia el snapshot al reextraer, y el módulo no tiene con qué detectarlo. Como `ejecutar()` reextrae en cada recálculo, la condición exacta es:

> Un corte en estado CALCULADO, recalculado después de que SIESA registre un movimiento retroactivo con fecha anterior o igual a la del corte, devuelve un número distinto.

El congelamiento efectivo llega con el estado CERRADO, que es de la fase 7. Hasta entonces el snapshot es reproducible sólo en ausencia de movimientos retroactivos. **No se puede medir cuántos hay**, precisamente porque no existe el sello de creación.

Queda además sin verificar la política de archivado de SIESA: el historial visible arranca el 31 de diciembre de 2021, y si el sistema purga por antigüedad, los cortes viejos dejarían de ser reproducibles. Hay que preguntarlo, no se puede deducir de la base.

## 15. Controles

**C-1 · Prórrogas que reducen la antigüedad de la mora.**
La prórroga traslada las cuotas al final (D-08), con lo cual la operación puede bajar de rango o salir de mora y liberar deterioro. El módulo lista cada mes las operaciones cuya antigüedad bajó respecto al corte anterior, con el deterioro liberado, para validación. Tiene efecto fiscal: la deducción del 33 % exige más de un año de vencimiento y la prórroga reinicia ese conteo.

**C-2 · Salidas de la base entre cortes.**
Las operaciones castigadas desaparecen de la base (D-09). El corte cerrado conserva su copia, así que no se pierden del histórico. Al mostrar las operaciones del corte, el módulo señala cuáles estaban en el anterior y ya no aparecen, y permite marcarlas como castigadas. Las demás se clasifican como recaudo total, cierre con apertura de una nueva operación, u otra causa. La clasificación importa por dos razones: el castigo tiene tratamiento fiscal propio y el deterioro acumulado debe cerrarse en el mismo movimiento; y sin la etiqueta, una caída por cierre con reapertura se vería igual que un recaudo en la descomposición del movimiento del mes.

**C-3 · Conciliación con SIESA.**
Diferencias por cliente entre el saldo de SIESA y el del sistema de factoring, con captura de explicación y estado. El corte no se puede cerrar con partidas sin explicar.

**C-4 · Sincronía de marcas con factoring.**
Comparación de las marcas de suspensión del módulo contra las del sistema de factoring, para detectar cambios hechos por fuera.

**C-5 · Cobertura del cargue inicial de suspensiones (D-14, D-15).**
Toda operación del archivo de Contabilidad tiene que quedar en el corte con base y deterioro resueltos. El control lista las que no lo estén, separando las causas: sin saldo en SIESA, sin antigüedad de mora con la que determinar el rango, o con identificación que no se pudo cruzar contra ninguna de las dos bases. Una operación que Contabilidad marcó y que el módulo no deterioró es un faltante contable, y el silencio es el peor resultado posible: el corte cuadraría consigo mismo mientras omite cartera deteriorable. El corte no se cierra con faltantes sin explicar.

Los controles C-CUOTAS, C-CAPITAL y C-INTERES de RN-12 comparan el detalle de cuotas contra el consolidado por operación, y las filas inyectadas no tienen cuotas de origen. **Deben excluirse explícitamente de esos tres controles**, no absorberse en la tolerancia: absorberlas dejaría los tres controles ciegos a un descuadre real de la extracción, que es justo lo que vigilan.

**C-DUPLICADAS · Cuotas que el origen entrega repetidas.**
El sistema de factoring devuelve, para una misma operación, filas indistinguibles entre sí: mismas fechas, mismos saldos, distinto `IdCuota` e `IdDetalleOperacion` (hallazgo 12 de la sección 7). El módulo las **conserva en el detalle**, porque el detalle es la prueba de qué entregó el origen, y las **excluye del consolidado**, para que el saldo de la operación no quede inflado. Dos cuotas de la misma operación se consideran indistinguibles cuando coinciden en `fec_inicial_corriente`, `fec_final_corriente`, `saldo_capital` y `saldo_intereses`; sobrevive la de menor `IdCuota` y las demás quedan marcadas apuntando a ella. Una cuota sin fecha de vencimiento no se compara con ninguna.

Son **dos cuadres, no uno**, porque miden cosas distintas: `C-DUPLICADAS` cuenta cuántas filas se excluyeron y es **informativo, nunca falla** —nadie puede corregir dentro del módulo un defecto que vive en la base de factoring, y bloquear el cierre con él convertiría todos los meses en un cierre con salvedad, vaciando de sentido esa marca—; `C-DUPLICADAS-BASE` mide **cuánta base de deterioro se dejó fuera** y sí bloquea el cierre. Hoy vale cero, porque ninguna repetida está vencida. El día que una lo esté, el cuadre falla y el corte no cierra sin explicación, sin que nadie tenga que acordarse: se arma solo.

Estos dos cuadres cubren el punto ciego de los tres anteriores. El criterio se evalúa una sola vez, al calcular el corte, y queda congelado con él: un corte calculado antes de que existiera la medición no tiene la marca, y la pantalla lo muestra como «no medido», nunca como «cero repetidas».

## 16. Pantallas

| Pantalla | Contenido |
|---|---|
| Resumen del corte | Matriz producto × rango con capital, interés, base y deterioro. Réplica de `T14:AB27` con semáforos de cuadre. |
| Detalle por operación | Grilla filtrable y exportable, con enlace al detalle de cuotas y señalización de operaciones ausentes respecto al corte anterior. |
| Contable contra fiscal | Comparativo por operación y consolidado, diferencia temporaria, impuesto diferido y proyección de reversión. |
| Evolución | Series históricas y descomposición del movimiento del mes. |
| Intereses suspendidos | Operaciones marcadas, causal, fecha del evento, interés congelado, interés no facturado del mes y acumulado, estado de la marca en factoring. Por D-14 y D-15 muestra además el origen de la marca, si la operación existe en factoring y de qué base salió su deterioro, con las no resueltas del cargue inicial señaladas. |
| Conciliación | Cruce con SIESA por cliente. |
| Controles | C-1 a C-5. |
| Ajustes y notas | Prórrogas, reservas, reclasificaciones y observaciones, con soporte y autor. |
| Parámetros | Rangos, porcentajes, tasas y causales, con vigencias. Acceso restringido. |
| Exportables | Excel con la estructura actual para la transición, PDF del resumen, archivo plano del asiento contable. |

**En el menú lateral sólo se dibuja Cortes.** Las otras siete pantallas son el detalle de un corte: necesitan uno en la URL y, al entrar sin él, redirigen a Cortes. Dibujarlas daría ocho entradas de menú que llevan todas al mismo sitio. Se entra a ellas desde la fila del corte y se navega entre ellas con los botones del encabezado, que arrastran el corte elegido. La consecuencia buscada es que **nunca haya duda de qué mes se está mirando**, porque el corte se eligió antes de entrar; en un módulo contable esa ambigüedad no es un detalle menor.

Las siete siguen existiendo como submenú, con `EstadoSubmenu = 0`, porque de ellas cuelga el permiso —igual que de las acciones—. Apagar una página no debilita ni fortalece el acceso: ningún control de permisos mira esa columna, y su único lector es el que arma el menú.

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
| 5 | Suspensión de intereses, cargue inicial y marcación en factoring | 3 horas + integración, por estimar según escenario | Puntos B, C, D, E y lectura de SIESA |
| 6a | Conexión a SIESA, base de D-16, tope fiscal y conciliación C-3 | por medir | Punto A, cerrado |
| 6b | Ajustes y notas | 2 horas | — |
| 7a | Controles C-1 y C-2, cierre y reapertura de corte, permisos y auditoría | por medir | — |
| 7b | Exportables | por medir | — |
| 7c | Controles C-4 y C-5 | por estimar | Puntos B y C |
| 8 | Migración del histórico y marcha en paralelo | 6 a 8 horas | — |

**Reordenamiento que introduce D-15 (3 de septiembre de 2026).** La fase 5 tenía una sola dependencia, el punto B, que es externa y del proveedor de factoring. Ahora tiene cinco, y una de ellas es un pedazo de la fase 6: la lectura de SIESA. Dos consecuencias prácticas:

- **La fase 5 ya no se puede entregar completa de forma independiente.** Conviene partirla: la marcación manual de D-05, la bitácora y el congelamiento del interés no necesitan SIESA y se pueden construir ya; el cargue inicial de D-14 y la base de SIESA de D-15 quedan detrás de la conexión y de los puntos C, D y E.
- **Adelantar la lectura de SIESA desde la fase 6 a la fase 5** es preferible a duplicarla. La extracción a `corte_saldo_siesa` sirve a las dos fases sin cambios, así que se construye una vez en la fase 5 y la fase 6 la consume.

La estimación de 3 horas de la fase 5 no incluye ninguna de estas dos cosas y queda desactualizada.

**Partición de la fase 6 (4 de septiembre de 2026).** La estimación de 4 horas ya se sabía corta desde que la conciliación quedó dimensionada en la sección 14.1. Se separó en dos:

- **6a** —conexión a SIESA, snapshot congelado por corte, base de D-16 para las suspendidas, tope fiscal de RN-09 y conciliación C-3 con su pantalla— es lo que desbloquea D-15 y lo que activa el tope, que hasta hoy operaba en modo neutro porque `saldo_siesa` estaba nulo.
- **6b** —ajustes y notas— no depende de SIESA ni de nada de 6a: es una pantalla de captura independiente y se puede hacer en cualquier momento.

Lo que la fase 5 dio por hecho y 6a corrige: la extracción a `corte_saldo_siesa` **no se construyó** en la fase 5. Lo único que la fase 5 usó de SIESA fue la fecha del último documento `FAT` por cliente, para el cargue inicial.

**Partición de la fase 7 (14 de septiembre de 2026).** Se separó por dependencia, con el mismo criterio que la fase 6:

- **7a** —controles C-1 y C-2, cierre y reapertura de corte con su foto de salvedad, los cinco permisos nuevos y la bitácora— no depende de nada externo y es lo que permite cerrar un mes contable dentro del módulo. Entregada y validada; ver `deterioro-fase7-validacion.md`.
- **7b** —C-4, sincronía de marcas con factoring; C-5, cobertura del cargue inicial; y los exportables de la sección 16— depende de definiciones que hoy no existen: C-4 del punto B, que es del proveedor de factoring, y C-5 del contrato del archivo de Contabilidad.

**Partición de la 7b (14 de septiembre de 2026).** Al abrirla se midió el estado de C-4 y C-5 y ninguno se puede cerrar hoy: C-4 depende del punto B, que es del proveedor de factoring, y la forma completa de C-5 del punto C. De C-5 ya opera la parte medible, el cuadre `C-MARCAS`. Los exportables no dependen de nada de eso y se entregaron solos; los dos controles pasan a **7c**. Ver `deterioro-fase7b-validacion.md`.

**Lo que 7b agrega al orden de despliegue:** el esquema pasa a ser bloqueante de verdad, porque la consulta de disponibilidad del asiento se invoca en las siete pantallas de corte y sin sus tablas el módulo deja de responder. Y como `phpoffice/phpspreadsheet` es dependencia nueva y `vendor/` no va al repositorio, después del código hay que correr `composer install` en el servidor. El orden es esquema → código → `composer install` → permisos → visibilidad.

**Lo que 7a activa y conviene saber antes de desplegarla:** el bloqueo del cierre por C-3 queda operante, de modo que el **punto abierto F —la tolerancia de materialidad de la conciliación— pasa de anotación a requisito**. Sin esa decisión de política contable, el módulo exigirá explicar partidas de centavos antes de dejar cerrar el mes.

**Marcha en paralelo:** tres cortes completos ejecutando Excel y módulo al mismo tiempo. El Excel se retira cuando los tres cierren con diferencia cero, validados por Contabilidad.

## 20. Criterios de aceptación

- Réplica del corte de julio de 2026 con diferencia máxima de 1 peso en cada uno de los ocho cuadres de RN-12, salvo las diferencias explicadas por las correcciones de D-A, D-B y el hallazgo 7.
- **Diferencia esperada por el hallazgo 12:** el libro incluye las cuotas que el origen entrega repetidas, porque sale de la misma consulta; el módulo las excluye del consolidado. En el corte de julio de 2026 eso son **737.805,00 de capital y 35.563,00 de interés** en la operación 8267, sin efecto sobre el deterioro porque ninguna está vencida. Es una diferencia deliberada y se explica como las de D-A, D-B y el hallazgo 7, con la particularidad de que aquí el error no está en el libro sino en el sistema de factoring: el libro lo hereda igual que lo heredaría cualquier sistema que copie el origen sin mirar.
- Casos borde probados explícitamente: 30/31 días, 90/91, 180/181, 360/361, 720/721; operación sin `FecInicialMora`; operación que sale de mora entre cortes; operación con saldo negativo; operación prorrogada que baja de rango; operación marcada como suspendida; operación que desaparece entre cortes.
- Recálculo completo de un corte de 115 mil filas en menos de 60 segundos.
- Un corte cerrado y recalculado seis meses después devuelve exactamente los mismos valores.
- Toda cifra del resumen permite descender hasta la cuota de origen en un máximo de tres clics.
- Toda escritura sobre la base de factoring queda registrada en bitácora con valor anterior y nuevo.
- **Cobertura completa del cargue inicial (D-14, D-15):** todas las operaciones del archivo de Contabilidad quedan con base y deterioro resueltos en el corte, o listadas en C-5 con la causa. El conteo de operaciones del archivo cuadra contra el conteo de marcas creadas, y la suma de las bases tomadas de SIESA se puede rastrear operación por operación. Recargar el mismo archivo dos veces deja el corte idéntico.

## 21. Riesgos

| Riesgo | Mitigación |
|---|---|
| El escenario 2 del punto B deja la fase 5 dependiendo del proveedor | Resolver el punto B antes que el A. Diseñar la fase 5 de modo que el módulo funcione con marca propia mientras llega el campo. |
| Escribir en la base de un sistema de terceros | Permiso acotado a una columna, bitácora de cada escritura, control C-4 de sincronía, y sin marcación masiva. |
| Las reglas del Excel se replican con sus defectos | D-A, D-B y el hallazgo 7 se corrigen de forma explícita y documentada. |
| El acumulado fiscal histórico está incompleto o es inconsistente | Auditar las hojas `1399` antes de migrar. Es el insumo del tope de RN-09 y un error ahí se arrastra por años. |
| Cambios en `ResumenVigentesClientes` o en el esquema de SIESA rompen el ETL | Contrato de datos versionado y validación de esquema en cada extracción. |
| El punto A se resuelve a favor de que SIESA mande sobre la base | Dejarlo previsto como parámetro desde la fase 1 para no rediseñar. |
| **D-15 obliga a inyectar filas que no vienen de la extracción**, y eso rompe la premisa de los controles de detalle contra resumen | Marcar el origen de cada fila y excluir las inyectadas de C-CUOTAS, C-CAPITAL y C-INTERES de forma explícita, nunca absorbiéndolas en la tolerancia. Cubrirlas con C-5. |
| **SIESA podría identificar por cliente y no por operación**, con lo cual D-15 no se puede aplicar por operación | Verificarlo antes de estimar la fase 5. Si es por cliente, repartir el saldo entre las operaciones del cliente es un problema distinto y hay que devolverlo a Contabilidad, no resolverlo por criterio propio. |
| **El archivo del cargue inicial es una entrada manual más**, de las que el hallazgo 4 señala como sin trazabilidad | Cargarlo por comando con contrato de columnas, validación previa a la escritura, bitácora y congelado junto con el corte. Es el mismo tratamiento que recibió el libro de validación en la fase 4. |

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
