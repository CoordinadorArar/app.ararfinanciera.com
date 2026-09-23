# Deterioro de Cartera — Fase 6a, resultados de la validación

**Fecha:** 4 de septiembre de 2026
**Base de trabajo:** `ArarFinanciera_PRUEBAS` (ambiente Demo)
**Alcance entregado:** conexión a SIESA, snapshot congelado por corte, base de D-16 para las operaciones suspendidas, tope fiscal de RN-09 y conciliación C-3 con su pantalla
**Alcance no entregado:** ajustes y notas, que pasan a la fase 6b

---

## 1. Por qué la fase 6 se partió

La estimación de 4 horas se sabía corta desde que la sección 14.1 dimensionó la conciliación. Se separó en dos, y el criterio no fue el tamaño sino la dependencia:

- **6a** es todo lo que cuelga de la conexión a SIESA: la extracción, la base de D-16, el tope fiscal que hasta hoy operaba en modo neutro porque `saldo_siesa` estaba nulo, y la conciliación.
- **6b**, ajustes y notas, no depende de SIESA ni de nada de 6a. Es una pantalla de captura independiente que se puede hacer en cualquier momento.

Conviene dejar dicho lo que la fase 5 dio por hecho: **la extracción a `corte_saldo_siesa` no se construyó en la fase 5.** Lo único que aquella usó de SIESA fue la fecha del último documento `FAT` por cliente, para el cargue inicial. La extracción se construyó aquí, entera.

---

## 2. Las dos decisiones que abrieron la fase

El punto abierto A —hasta dónde manda el saldo de SIESA— y el punto D —qué componente del saldo es la base— estaban pendientes desde el 28 de agosto. Se cerraron el 4 de septiembre.

**A · SIESA manda sobre la base sólo para las operaciones suspendidas.** La lectura global —SIESA manda sobre toda la cartera— se descartó: habría movido el deterioro del corte completo en una magnitud no medida y roto la réplica al centavo de los tres cortes validados contra el libro, que es un criterio de aceptación vigente. El interruptor `siesa_manda_sobre_base` queda como estaba, global y congelado por corte; el alcance real lo resuelve el origen de la marca y se registra fila a fila en la columna nueva `origen_base`, no un parámetro que el usuario pueda cambiar sin advertirlo.

**D · La base es el saldo de SIESA más el interés congelado**, y quedó como política D-16. El saldo de SIESA es capital total y no trae interés de ninguna clase; sumarle el congelado es lo único que hace compatibles D-06 y D-15.

**Lo que hay que asumir y no esconder:** el saldo de SIESA **incluye el capital corriente, que D-03 excluye de la base**. Para las operaciones suspendidas, D-16 prevalece sobre D-03 en ese punto.

---

## 3. Un supuesto de la orquestación que resultó falso

Al abrir la fase se le dijo a Backend que sumar el interés congelado haría crecer el efecto de +83.049.290,58 medido el 3 de septiembre, porque aquella medición sólo contaba el capital de SIESA. **El supuesto era falso, y por una razón algebraica que conviene dejar escrita:** contra la base de factoring congelada, el interés congelado aparece en los dos lados y **se cancela exactamente**. El efecto es `SUM((saldo_siesa − capital_vencido) × pct)` y no depende del interés. La comprobación es que esa expresión da 132.476.425,50, el mismo número que la diferencia de deterioros.

Las tres cifras, sobre las 74 operaciones suspendidas con saldo `OPE` del corte de julio:

| | |
|---|---:|
| Base de factoring congelada (`capital vencido + interés congelado`) | 168.542.927,00 |
| Base de SIESA congelada (D-16) | 382.853.141,00 |
| Base de D-03 (`capital vencido + interés vencido`) | 318.554.177,00 |
| Deterioro con base de factoring congelada | 151.635.599,32 |
| **Deterioro con base de SIESA (D-16)** | **284.112.024,82** |
| Efecto de D-16 contra la base de factoring congelada | +132.476.425,50 |
| **Efecto de D-16 contra la base de D-03, la comparación de §14.1** | **−3.224.570,26** |

Contra la comparación que hizo la sección 14.1 **el efecto cambió de signo**: era +83.049.290,58 y hoy es −3.224.570,26. Dos causas, ninguna un defecto: la población pasó de 172 operaciones a 74, porque sólo se suspenden las marcas con interés congelado; y en el rango F, que deteriora al 100 %, el saldo de SIESA está por **debajo** de la base de factoring, de modo que 31 operaciones aportan −33.028.660,00 y se comen los +29.804.089,74 de los rangos B a E.

Efecto total del congelamiento sobre el corte de julio: **−41.240.651,37**, de 1.753.977.960,01 a 1.712.737.308,64.

---

## 4. La invariante: con cero marcas, nada contable se mueve

Los tres cortes están validados al centavo contra el libro, y una fase que toca el motor tiene que demostrar que no los altera. Reejecutado el corte 2 con cero marcas dentro de una transacción revertida, y comparado **columna por columna**:

| | Antes | Después |
|---|---:|---:|
| `deterioro_contable` | 1.753.977.960,01 | 1.753.977.960,01 |
| `base_deterioro` | 2.176.885.557,00 | 2.176.885.557,00 |
| `deterioro_fiscal_individual` | 565.102.413,48 | 565.102.413,48 |

De 42 columnas × 2.106 operaciones, **ninguna columna contable se mueve**. Las que sí cambian son las que esta fase existe para cambiar:

| Columna | Operaciones | Efecto |
|---|---:|---:|
| `saldo_siesa` | 1.935 | recién poblada |
| `saldo_topado` | 94 | −109.042.332,00 |
| `deduccion_fiscal_ano` | 31 | −30.002.959,60 |
| `diferencia_temporaria` | 31 | +30.002.959,60 |
| `impuesto_diferido_activo` | 31 | +10.501.035,86 |

**El movimiento del tope está acotado y es exactamente RN-09**, no un efecto colateral: las 94 topadas son precisamente aquellas con `saldo_siesa < base_deterioro`, y 109.042.332,00 es la suma de esa diferencia sobre ellas. El impuesto diferido sube 30.002.959,60 × 0,35, la tarifa de renta congelada. Ningún `saldo_siesa` negativo.

Hasta hoy el tope operaba en modo neutro: con `saldo_siesa` nulo, `R` tomaba la base. Esta fase lo pone a operar por primera vez.

---

## 5. Dos controles que no vigilaban nada, encontrados por QA

Esta es la parte de la fase que más valor tuvo, y no estaba en el plan.

### `C-SIESA-EXTRAC` era una tautología

El control debía vigilar la extracción de SIESA, que es **la única fuente externa nueva de la fase**. QA lo probó corrompiendo una columna a la vez y no fallaba:

| Alteración | Resultado original |
|---|---|
| `saldo` de una fila enlazada del snapshot, +9.999.999 | **OK, diferencia 0** |
| `tipo_docto_cruce` `OPE` → `XXX` en esa misma fila | **OK, diferencia 0** |

Era algebraico: `no_enlazado` se calculaba como *todo lo que no cumple (enlaza Y es OPE)*, con lo cual `enlazado + no_enlazado ≡ total` para cualquier contenido de la tabla. Y su único modo de falla declarado —que la unión multiplicara filas— **estaba cerrado por el esquema**: la llave primaria `(id_corte, id_operacion)` rechaza el duplicado.

Rehecho para comparar el saldo que el motor dejó en `det_deterioro_operacion.saldo_siesa` contra el que el snapshot tiene para esas mismas operaciones. QA lo reverificó con método propio, una columna a la vez y revirtiendo entre casos:

| Caso | Resultado |
|---|---|
| `saldo` +9.999.999 (antes salía OK) | FALLA por −9.999.999,00 |
| `tipo_docto_cruce` `OPE`→`XXX` (antes salía OK) | FALLA por +576.685,00 |
| `saldo_siesa` de la operación a nulo | FALLA por −576.685,00 |
| fila del snapshot borrada | FALLA por +576.685,00 |
| `saldo_siesa` +1.234,56 | FALLA por +1.234,56 |
| consecutivo del snapshot a un `OPE` inexistente | FALLA por +576.685,00 |

Queda un punto ciego **declarado y verificado como inalcanzable**: el fan-out por operación duplicada, que la llave primaria impide.

### 114 operaciones marcadas se deterioraban con la base equivocada, en silencio

La rama informativa que reportaba marcas sin congelar sólo se disparaba cuando el conteo de suspendidas de origen factoring era cero. En el corte de julio son 23, así que `C-SUSPENSION` salía en **OK** y desaparecían de la vista:

| | |
|---|---:|
| Marcas aplicables al corte, presentes en él | 211 |
| De ellas, **sin** `interes_congelado` | **114** |
| Base de esas 114, calculada con la base de D-03 | **418.382.442,00** |
| Deterioro que aportan | **346.400.904,33** |

Un corte con 346 millones de deterioro calculado en contra de D-15 y D-16 cuadraba consigo mismo en verde. Es literalmente el escenario que el control C-5 del documento técnico llama *"el peor resultado posible"*.

La causa raíz —`interes_congelado` nulo— es de la fase 5 y sigue abierta. Lo que se corrigió aquí es que el corte deje de esconderla: **`C-MARCAS`**, control nuevo e independiente de todo otro conteo, cuya cifra es la **base en pesos** y no el número de marcas, porque lo que importa es la magnitud contable del faltante.

| Escenario | `C-MARCAS` |
|---|---|
| Corte 2 (julio 2026) | **FALLA por 418.382.442,00** — 114 operaciones |
| Corte 3 (septiembre 2023) | **FALLA por 124.377.696,00** — 44 operaciones |
| Todas las marcas congeladas | OK con 0,00 |
| Sin marcas aplicables | `N/A` con motivo, nunca OK con cero |
| Marcas con evento posterior al corte | `N/A` con el mismo motivo |

QA cerró la regresión concreta: forzando a cero las suspendidas de origen factoring —el disparador exacto del defecto viejo— `C-MARCAS` sigue en FALLA por los mismos 418.382.442,00.

**Consecuencia operativa que hay que anticipar:** los dos cortes con marcas van a mostrar un control en rojo hasta que se resuelva de dónde sale el interés congelado, que es el primero de los pendientes de la fase 5. El rojo es correcto y es el punto: dice que hay cartera marcada deteriorándose con la base equivocada.

---

## 6. La conciliación C-3

| | |
|---|---:|
| Operaciones del corte | 2.106 |
| Cruzan contra un `OPE` de SIESA | 1.935 |
| **Conciliadas al peso** | **1.666** |
| Partidas | **786** |
| · `DIFERENCIA` | 269 |
| · `SOLO_FACTORING` | 171 |
| · `SOLO_SIESA` | 346 |
| Diferencia algebraica | −1.893.221.613,00 |
| Diferencia en valor absoluto | 2.136.388.485,00 |

La partición cierra por los dos lados: 1.666 + 269 + 171 = 2.106, el total de operaciones; y 1.666 + 269 = 1.935, las que cruzan.

**Por qué estas cifras no son las de la sección 14.1.** Aquella medición dio 326 diferencias por −915.297.792,00 sobre el saldo **actual** de SIESA; el snapshot está acotado al 31 de julio de 2026, que es lo correcto para la reproducibilidad. El total pasa de 8.553.341.244,10 a 8.187.874.511,05 y las conciliadas de 1.609 a 1.666. El resto de la cobertura —1.935 cruzan, 171 sin cruce— **coincide al número** con la exploración.

**Las explicaciones viven en tabla aparte a propósito.** `limpiarCorte()` borra las tablas del corte al reejecutar, y si la explicación viviera con las partidas se perdería el trabajo del usuario en cada recálculo. Verificado: tras recalcular, la explicación se reasocia a su partida con la llave lógica intacta y las demás nacen en `PENDIENTE`. `eliminarCorte()` sí se lleva las explicaciones, que es lo contrario de recalcular.

**Sobre la suma algebraica.** El total neto esconde compensaciones: −1.893 millones netos contra 2.136 millones en valor absoluto. La pantalla muestra los dos rotulados, porque uno solo miente.

---

## 7. Un cambio de alcance de la extracción

El `HAVING` original conservaba los `OPE` con saldo cero para no confundir una operación cancelada en SIESA con una ausente. Eso arrastraba también `OPE` que **todavía no existían** a la fecha del corte: hay 3.291 en la compañía 7 cuyo primer movimiento es posterior al 31 de julio de 2026. Ninguno colisiona hoy, pero si en un corte futuro uno cruzara, el efecto sería silencioso: `saldo_siesa = 0` lleva el tope de RN-09 a `R = 0`, la deducción del año a cero, y además fabrica una partida `DIFERENCIA` falsa.

Corregido exigiendo al menos un movimiento con fecha anterior o igual a la del corte. QA verificó que no se perdió cartera legítima: 52 filas fuera, **todas `OPE`, todas con saldo 0,00, cero pesos**; ninguna cruza contra una operación del corte; ninguna tiene movimiento al 31 de julio —los primeros caen en agosto—; y las 1.935 enlazadas dan 13.404.332.278,00 con las dos variantes, sin una sola diferencia operación por operación.

---

## 8. Reproducibilidad del snapshot — un hallazgo de diseño

El criterio de aceptación exige que un corte cerrado y recalculado seis meses después devuelva los mismos valores.

**El riesgo obvio no se materializa.** Se temía que una fila cancelada desapareciera de `t353_co_saldo_abierto`. No ocurre: 403.441 de las 532.144 filas de la compañía 7 tienen saldo neto cero y siguen en la tabla. Reconstruido el snapshot del 30 de septiembre de 2023, 62.809 filas ya canceladas hoy siguen presentes y suman 4.178.812.674,94, exactamente lo que produce la reextracción de ese corte.

**El agujero real es otro y está abierto.** `t354_co_mov_saldo_abierto` **no tiene sello de creación**: de sus 33 columnas, las dos de fecha son de negocio. Un movimiento con fecha anterior o igual a la del corte, registrado en SIESA **después** de la extracción, cambia el snapshot al reextraer, y el módulo no tiene con qué detectarlo. Como `ejecutar()` reextrae en cada recálculo:

> Un corte en estado CALCULADO, recalculado después de que SIESA registre un movimiento retroactivo con fecha anterior o igual a la del corte, devuelve un número distinto.

El congelamiento efectivo llega con el estado CERRADO, de la fase 7. **No se puede medir cuántos movimientos retroactivos hay**, precisamente porque no existe el sello de creación. Queda además sin verificar la política de archivado de SIESA: el historial visible arranca el 31 de diciembre de 2021, y si el sistema purga por antigüedad los cortes viejos dejarían de ser reproducibles. Hay que preguntarlo; no se deduce de la base.

---

## 9. Lo demás que se probó

**D-16 fila a fila.** De las 97 suspendidas: 0 con `base_congelada` distinta de la esperada, 0 con `origen_base` incorrecto, 0 con `interes_vencido_congelado` nulo. De las 2.106: **0 con `interes_vencido` o `base_deterioro` tocados** — son el insumo de `C-INTERES` y `C-BASE` contra el detalle de cuotas, y sobreescribirlas dejaría esos dos controles ciegos a un descuadre real de la extracción.

**Cobertura de controles sobre las suspendidas.** 23 vigiladas por `C-SUSPENSION` (origen factoring) + 74 por `C-SIESA-BASE` (origen SIESA) = 97, el total. **Ninguna sin vigilancia.** `C-SUSPENSION` hubo que restringirlo a origen factoring: su identidad —reducción de base igual a reducción de interés— sólo se sostiene cuando el capital vencido no cambia, y con la base de SIESA deja de sostenerse. Sin acotarlo habría salido en FALLA sin que nada estuviera mal.

**`C-SIESA-BASE` no es una tautología.** Falla por exactamente la cifra alterada en sus tres componentes y vuelve a OK al revertir: `base_congelada` +1.000.000 → FALLA 1.000.000; `saldo_siesa` +777.777 → FALLA −777.777; `interes_vencido_congelado` +500.000 → FALLA −500.000.

**Idempotencia.** Dos corridas seguidas: 42 columnas × 2.106 operaciones con **0 diferencias**, snapshot idéntico en los dos sentidos, 786 partidas idénticas, 19 controles idénticos.

**Migración.** `up() → down() → up()` reproduce el esquema idéntico. El `down()` borra exactamente 8 filas de `det_corte_cuadre` en los 4 códigos de la fase, sobreviven 44 filas y 15 códigos incluido `C-SUSPENSION` de la fase 5: **ninguno ajeno**. El DDL manual produce el mismo esquema.

**Endpoints.** 13 combinaciones de filtros, y los agregados de cobertura **no cambian al filtrar** en ninguna de las 12 válidas: si cambiaran, el usuario leería un total distinto según lo que estuviera mirando. `explicarPartida()` rechaza estado inválido, explicación vacía, 501 caracteres y operación que no es partida; acepta 500 exactos, y reexplicar actualiza en vez de duplicar.

**Permisos** en tres niveles, con `identidad` redirigida en memoria: con permiso pasa, sin permiso **403**, sin rol **403**, módulo ausente del menú **403**. La regla quedó escrita una sola vez —`tiene()` delega en `evaluar()`, que el middleware también consume—, de modo que pantalla y servidor no pueden divergir.

**Longitudes.** `codigo` máximo 14 de 20; `descripcion` 113 de 200; `motivo` 107 de 200. Margen amplio. La fase 4 encontró un control a 18 caracteres de abortar el cálculo; aquí no hay nada cerca del borde.

**Rendimiento.** Extracción de SIESA 4,0–4,6 s; enlace 125–187 ms; conciliación 462–503 ms. Corte completo de 80.647 filas en 31,3 s en frío y 14,5 s en caliente, dentro del objetivo de 60 s.

---

## 10. La pantalla

UI/UX definió los lineamientos antes de implementar y revisó después. **Rechazó la primera versión por un defecto real:** la franja ámbar que marca una partida pendiente no se pintaba. La clase iba sobre el `<tr>` y se resolvía con `box-shadow`, que con `border-collapse: collapse` no se renderiza en ningún navegador; los dos precedentes del módulo funcionan porque van sobre `<td>`. El resultado era que una fila pendiente se veía idéntica a una explicada y sólo quedaba el badge, que es justo la señal de gestión que la pantalla existe para dar.

**Un segundo defecto, del mismo tipo que ya se rechazó en la fase 5:** el gate de permiso estaba planteado a puertas abiertas —si la clave llegaba ausente o nula se pintaba el botón—, de modo que un usuario sin permiso chocaría contra un 403. Ofrecer una acción que va a fallar es peor que no ofrecerla.

**La deuda que apareció al revisar.** El defecto por el que se rechazó la pantalla de la fase 5 —pintar un dato ausente con `det-cero`, el gris reservado a ceros confirmados y triviales— **seguía vivo en cuatro puntos ya desplegables** del módulo. Es la cuarta vez que este defecto aparece. Se corrigieron los cuatro y se creó la clase `det-ausente` con su helper en `deterioro-comun.js`, para que la próxima vez no haya que decidirlo de nuevo.

Decisiones de diseño que conviene dejar registradas, porque alguien podría revertirlas por parecer arbitrarias:

- **Tres paneles ordenados por gravedad, no por volumen.** Las operaciones sin respaldo en SIESA y los saldos de SIESA sin operación van primero aunque el panel de diferencias tenga más filas: los dos primeros son fallas estructurales de cruce, el tercero es discrepancia de monto entre fuentes que sí se reconocen.
- **Las columnas ausentes se eliminan, no se rellenan.** Una columna con 171 guiones es ruido y rompe el ordenamiento.
- **El signo de la diferencia no se colorea.** En un comparativo fiscal el rojo para el negativo es correcto; en una conciliación es un error semántico, porque +40 millones es tan grave como −40. La gravedad la lleva el estado de la partida.
- **Las 1.666 conciliadas no generan fila, panel ni filtro**: aparecen una sola vez como cifra, para que no compitan por la atención.
- **El avance se comunica en conteo absoluto**, no en porcentaje: un 31 % no le dice al usuario cuántas le faltan.

---

## 11. Pendientes

1. **La migración de la fase 6a no está aplicada en ninguna base**, ni en PRUEBAS ni en producción. Se suma a la deriva ya registrada: PRUEBAS tiene los objetos de las fases 2 a 4 por DDL manual y su tabla `migrations` sólo registra las tres de la fase 1.

2. **`php artisan migrate` no puede usarse hoy.** La migración de la fase 5 hace `Schema::create('det_suspension_interes')` sin guarda y esa tabla ya existe en Demo, así que el comando fallaría ahí y **nunca llegaría a la de la fase 6a**. El único camino de despliegue hoy es el DDL manual.

3. **El permiso `conciliar` todavía no es real.** `/deterioro-accion-conciliar` no existe en `Submenus`, así que el fallback deliberado del middleware lo resuelve contra `/deterioro-cortes`: **cualquiera que pueda abrir la pantalla de Cortes puede explicar partidas**. Lo mismo vale para `suspender`, de la fase 5. Tampoco están registradas las pantallas de las fases 4, 5 y 6, y `CheckSubmenuPermission` deja pasar toda ruta que no encuentra. **El seeder tiene que desplegarse en la misma ventana que el código**, no después.

4. **La causa raíz de las 114 sigue abierta.** De dónde sale el interés congelado para eventos anteriores a los cortes es el primero de los pendientes de la fase 5, y hasta que se resuelva `C-MARCAS` seguirá en rojo en los dos cortes con marcas.

5. **Punto abierto F, nuevo:** no hay definida una tolerancia de materialidad para la conciliación. C-3 exigirá explicar toda partida antes de cerrar el corte, y si el residuo trae partidas de centavos el módulo obligará a explicar ruido y el control se degradará a trámite. Es decisión de política contable. No bloquea la fase 6a; sí tiene que estar resuelta antes de que el bloqueo del cierre entre en vigor en la fase 7.

6. **`C-CONCILIA` está siempre en `N/A` a propósito**, porque el bloqueo del cierre es de la fase 7. Su cifra sí se mueve correctamente. Queda anotado para que en esa fase se recuerde activarlo.

7. **`C-MARCAS` podría doblar el conteo en un escenario que hoy no ocurre**: agrega sobre el join a las marcas sin `DISTINCT`, y un corte pasado podría ver una marca reactivada más una posterior activa. Hoy hay 0 operaciones con más de una marca, así que la cifra es exacta.

8. **Sigue sin haber cobertura de pruebas automatizadas.** `project/tests/` conserva sólo el andamiaje de Laravel. Toda la validación de las seis fases es manual, y es el pendiente que más crece con cada una.

9. **Nada de las fases 2 a 6a está desplegado.** El orden obligatorio sigue siendo el registrado en la fase 4, ahora con la migración de SIESA y el seeder de menú al final.

---

## 12. Aislamiento

Toda la validación se hizo dentro de transacciones revertidas, con el esquema de la fase aplicado y revertido en cada prueba. Estado verificado contra las tablas, no contra el relato:

- `ArarFinanciera_PRUEBAS`: 0 tablas de la fase 6a, columna `origen_base` ausente, 0 cuadres con códigos de la fase, los tres cortes en 1.690.801.777,06 / 1.753.977.960,01 / 742.761.663,46, 259 marcas (115 con interés, 144 sin), bitácora sin un solo `EXPLICAR_PARTIDA`.
- `ArarFinanciera` (producción): intacta. 0 tablas de la fase, sin `origen_base`, 0 cuadres de la fase.
- Sobre `UNOEEARAR` (SIESA) sólo hubo SELECT, en ninguna prueba y en ningún momento otra cosa.
- Ninguna migración ni seeder ejecutado.
