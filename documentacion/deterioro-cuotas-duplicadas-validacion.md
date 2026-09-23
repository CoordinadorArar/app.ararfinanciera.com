# Deterioro de Cartera — Cuotas repetidas del origen, resultados de la validación

**Fecha:** 15 de septiembre de 2026
**Base de trabajo:** `ArarFinanciera_PRUEBAS`, con el esquema aplicado
**Origen del caso:** un usuario abrió el detalle de cuotas de la operación 8267 y encontró, después de la cuota 36, los números 69, 70, 71 y 72

---

## 1. Qué resultó ser

Las cuotas 69 a 72 son **copia exacta** de las cuotas 33 a 36: misma `fec_inicial_corriente`, misma `fec_final_corriente`, mismo `saldo_capital`, mismo `saldo_intereses`, mismos `dias_vencidos`. Difieren únicamente en `id_cuota` y en `id_detalle_operacion` (926678‑926681 frente a 926714‑926717).

**El duplicado lo entrega el sistema de factoring.** `Modulos_Faico.dbo.ResumenVigentesClientes`, en producción, devuelve 36 filas para esa operación con `IdCuota` de 5 a 72, incluidas las cuatro repetidas. La extracción del módulo es un `INSERT ... SELECT` sin filtro y las copia tal cual.

Medición sobre los cuatro cortes, 426.264 filas:

| Corte | Filas repetidas | Operaciones | Capital | Interés | Base excluida |
|---|---:|---:|---:|---:|---:|
| 1 | 4 | 1 | 737.805,00 | 35.563,00 | 0,00 |
| 2 | 4 | 1 | 737.805,00 | 35.563,00 | 0,00 |
| 3 | 0 | 0 | — | — | — |
| 4 | 4 | 1 | 737.805,00 | 35.563,00 | 0,00 |

Siempre la misma operación, siempre LIBRANZAS. **Ninguna de las repetidas está vencida**, así que hoy no aportan un peso a la base de deterioro; vencen a partir de diciembre de 2028.

---

## 2. Por qué no se validó la continuidad de la numeración

Fue la primera hipótesis: si una operación va por la cuota 24, la siguiente debería ser la 25, y un salto delataría la intrusa. **No funciona, y conviene que quede escrito para que nadie lo reintente.**

Esa misma operación **empieza en la cuota 5** —las cuatro primeras se pagaron y salieron del resumen de vigentes— y tiene un hueco de la 37 a la 68. Los saltos en la numeración son el estado normal de cualquier operación con pagos. Un filtro de continuidad señalaría operaciones sanas por millares y atraparía este caso por casualidad, no por criterio.

---

## 3. El criterio que se adoptó

Dos cuotas de la misma operación son indistinguibles cuando coinciden en **`fec_inicial_corriente`, `fec_final_corriente`, `saldo_capital` y `saldo_intereses`**. Las dos fechas, no sólo el vencimiento: ocupar el mismo tramo de tiempo con el mismo dinero es lo que no puede pasar dos veces.

- Sobrevive la de menor `id_cuota`; las demás quedan marcadas apuntando a ella en la columna nueva `duplicada_de`. Es una convención y no un juicio: no afirma que la baja sea la correcta, sino que de N filas indistinguibles se cuenta una y las otras N−1 son el exceso.
- Una cuota sin fecha de vencimiento **no se compara con ninguna**. Hoy no hay ninguna así en los cuatro cortes, pero sin esa guarda dos filas incompletas se declararían gemelas.
- **`id_detalle_operacion` no entra en el criterio.** Es justamente el campo que hace que las dos filas parezcan distintas; como discriminante no excluiría nada. Va a la pantalla como dato de verificación contra factoring.

**El falso positivo medido es cero.** Se corrió en paralelo el criterio laxo —sólo vencimiento y saldos— y da exactamente el mismo resultado en los cuatro cortes. Elegir el criterio estricto no cuesta ninguna detección y es más defendible.

---

## 4. La decisión: copia fiel en el detalle, exclusión en el consolidado

El detalle de cuotas **sigue siendo copia literal** de lo que entregó factoring, fila por fila. La exclusión ocurre al consolidar por operación. Así el módulo conserva la prueba de qué recibió y al mismo tiempo las cifras de negocio dejan de estar infladas.

| | |
|---|---|
| `sqlExtraccion()` | Intacta. Sin `DISTINCT`, sin `WHERE`, sin `NOT EXISTS` |
| `det_corte_detalle_cuota` | Sin borrados ni ediciones. Gana `duplicada_de int NULL` |
| `sqlConsolidacion()` | Excluye las marcadas |
| C‑CUOTAS, C‑CAPITAL, C‑INTERES, C‑PARTIC, C‑BASE | Las excluyen del lado detalle, explícitamente |
| Huella del origen (`hash_datos`, sumas de origen) | **Cuenta todas las filas**, repetidas incluidas |

Esa última fila importa: la huella es la prueba de qué entregó factoring ese día, y filtrarla habría destruido justo lo que se quería conservar.

---

## 5. Los dos cuadres, y por qué son dos

`C-CUOTAS`, `C-CAPITAL` y `C-INTERES` **no podían ver esto por construcción**: comparan el detalle contra el consolidado, y el consolidado se deriva del mismo detalle, de modo que un duplicado consistente pasa en verde por los dos lados. Es un punto ciego estructural, no un fallo de esos tres controles.

- **`C-DUPLICADAS`** cuenta cuántas filas se excluyeron. Es **informativo y nunca falla**: nadie puede corregir dentro del módulo un defecto que vive en la base de factoring, y bloquear el cierre con él convertiría todos los meses en un cierre con salvedad, vaciando de sentido esa marca.
- **`C-DUPLICADAS-BASE`** mide **cuánta base de deterioro se dejó fuera**, con tolerancia cero, y sí bloquea el cierre por la vía que ya existe. Hoy vale 0,00.

**El bloqueo se arma solo.** El día que una repetida esté vencida, aportará base, el cuadre fallará y el corte no cerrará sin explicación. Nadie tiene que acordarse de nada ni poner una alarma en un calendario.

---

## 6. Cómo se validó

**Sin recalcular ningún corte.** Los cuatro cortes de PRUEBAS están calculados con el motor anterior a la fase 6a y son la referencia contra la que se ha validado el módulo desde la fase 1; recalcularlos los movería en −41.240.651,37 por efecto de D‑16, y esa decisión sigue pendiente. La verificación se hizo replicando el detalle en `tempdb` y corriendo allí **el SQL real del modelo**.

Antes de medir nada se validó el método: se corrió la consolidación **sin** el filtro nuevo y se comparó columna por columna contra el consolidado real de las 2.106 operaciones del corte 2 — **cero diferencias**. La réplica reproduce el motor, así que lo que se moviera después sería efecto del cambio y no del arnés.

### El resultado que importa

En el corte 2 se mueve **una sola operación**:

```
8267   cuotas             37 → 33
       capital_corriente  5.353.089,00 → 4.615.284,00   (−737.805,00)
       interes_corriente  1.625.167,00 → 1.589.604,00   (−35.563,00)
       capital_vencido    0,00 → 0,00
       base_deterioro     0,00 → 0,00
       rango / días mora  A / 0 → A / 0
```

Y lo que no puede moverse, no se movió: **cero operaciones** cambian `capital_vencido`, `interes_vencido`, `base_deterioro`, `rango_codigo` o `dias_mora_operacion`. Como el deterioro se deriva de la base y del rango, y ninguno cambia en ninguna operación, **el deterioro no puede moverse** — que es más fuerte que comprobar que el total coincide.

| Corte | Base | Deterioro | Capital | Cuotas |
|---|---|---|---:|---:|
| 1 | igual | 1.690.801.777,06 | −737.805 | −4 |
| 2 | 2.176.885.557, igual | 1.753.977.960,01 | −737.805 | −4 |
| 3 | igual | 742.761.663,46 | 0 | 0 |
| 4 | igual | 1.755.845.816,05 | −737.805 | −4 |

### Lo demás que se comprobó

- **El marcado es exacto:** las cuatro filas, con `duplicada_de` en 33, 34, 35 y 36 respectivamente, y las supervivientes en nulo.
- **Bordes:** un grupo de tres filas idénticas deja **una** superviviente y marca dos, ambas apuntando a la misma; repetir el marcado no cambia la elección. Dos filas idénticas con vencimiento nulo **no se marcan**, mientras las demás repetidas del corte sí.
- **Los cuadres:** C‑CUOTAS pasa a 115.023 contra 115.023 y C‑CAPITAL, C‑INTERES, C‑PARTIC y C‑BASE quedan en OK. `C-DUPLICADAS` no puede quedar en FALLA por construcción —su motivo nunca es nulo—. `C-DUPLICADAS-BASE` **sí falla cuando se le fuerza base**: con 100.000 de capital vencido y 5.000 de interés en una repetida, pasó a FALLA con 105.000 mientras el otro seguía informativo. Sin filas marcadas, ambos van a N/A con motivo y **sin cifras**, no a OK con cero.
- **La huella del origen:** el `hash_datos` guardado del corte 3 se reproduce exactamente recalculándolo sobre todas las filas, repetidas incluidas.
- **La interfaz:** título «37 registros, 33 en los totales», cuatro badges «Repite la 33/34/35/36», la superviviente sin marca, columna `Id detalle` siempre presente, filtro que se desmarca al ocultarse, y el corte sin evaluar **sin un solo cero en ninguna ruta**.

---

## 7. Lo que queda abierto

1. **El defecto sigue en el sistema de factoring.** El módulo lo rodea; no lo corrige. **Hay que reportar la operación 8267 a quien administra `Modulos_Faico`**, porque cualquier otro sistema que copie ese origen heredará el mismo saldo inflado.
2. **Diferencia deliberada contra el libro de Excel.** El libro incluye las repetidas, porque sale de la misma consulta. En la marcha en paralelo aparecerán 737.805,00 de capital de diferencia en esa operación, sin efecto sobre el deterioro. Se explica como las de D‑A, D‑B y el hallazgo 7.
3. **Los cortes existentes no se recalcularon**, así que no tienen la marca. Se distinguen por la ausencia de la fila `C-DUPLICADAS` en sus cuadres, y la pantalla lo muestra como «no medido». Quien los recalcule debe esperar exactamente lo de la tabla de la sección 6 y nada más.
4. **El patrón de tooltips por repintado** que se introdujo en esta pantalla resuelve una limitación que arrastraban las siete, pero **se dejó deliberadamente acotado al Detalle**. Extenderlo es un requerimiento propio con su paso por QA.
5. Sigue sin haber pruebas automatizadas. Este cambio se validó con réplicas en `tempdb` y un arnés de DOM, no con una suite.

---

## 8. Aislamiento

No se ejecutó ninguna migración, seeder ni comando de consola. Las únicas escrituras fueron sobre tablas temporales de `tempdb`, y ninguna fila de `ArarFinanciera_PRUEBAS` se modificó: 426.264 filas de detalle, 0 marcadas, los cuatro cortes intactos. Sobre `Modulos_Faico` sólo hubo SELECT. Producción no se tocó.

Aplicado en `ArarFinanciera_PRUEBAS`: la columna `duplicada_de`. La marca se escribirá cuando cada corte se recalcule.

**Archivos del cambio.** Nuevos: `documentacion/deterioro-cuotas-duplicadas-ddl.sql`, `project/database/migrations/2026_09_16_100000_add_duplicada_de_to_det_corte_detalle_cuota.php`. Modificados: `project/app/Models/Deterioro.php`, `project/app/Http/Controllers/DeterioroController.php`, `project/app/Support/DeterioroExportador.php`, `project/resources/views/deterioro/detalle-operaciones.blade.php`, `js/deterioro-detalle.js`, `css/deterioro.css`.
