# Deterioro de Cartera — Fase 5, resultados de la validación

**Fecha:** 3 de septiembre de 2026
**Base de trabajo:** `ArarFinanciera_PRUEBAS` (ambiente Demo)
**Alcance entregado:** suspensión de causación de intereses — esquema, motor de congelamiento, marcación manual, permisos y pantalla
**Alcance no entregado:** el cargue inicial de las 281 operaciones de Contabilidad y la escritura de la marca en el sistema de factoring

---

## 1. La fase se partió en dos, y por qué

El plan original tenía la fase 5 con una sola dependencia: el punto abierto B, que es externo y depende del proveedor de factoring. El requerimiento que Contabilidad agregó el 3 de septiembre —las 281 operaciones del archivo, con base tomada de SIESA— le añadió cuatro dependencias más, una de ellas un pedazo de la fase 6.

Se entregó por tanto lo que D-05 y D-06 especifican por completo y no depende de nadie:

- **Fase 5a:** esquema, paramétrica de causales, congelamiento del interés en el motor y control `C-SUSPENSION`.
- **Fase 5b:** captura del interés congelado, marcación y levantamiento, permiso propio, y la pantalla.

Lo que quedó fuera no es trabajo pendiente de hacer: es trabajo que **no se puede hacer todavía** sin decisiones de Contabilidad. La sección 6 lo cuantifica.

---

## 2. La restricción que definió el diseño

`verificarCuadres()` tiene dos controles que comparan el consolidado por operación contra el detalle de cuotas de origen:

| Control | Compara |
|---|---|
| `C-INTERES` | `SUM(saldo_intereses)` del detalle contra `SUM(interes_corriente + interes_vencido)` del consolidado |
| `C-BASE` | `SUM(base_deterioro)` del consolidado contra capital e interés vencidos **del detalle** |

D-06 obliga a que la base use el interés congelado en lugar del corriente. La forma obvia de implementarlo —sobreescribir `interes_vencido` y `base_deterioro`— **habría puesto los dos controles en FALLA**, y peor: los habría dejado ciegos a un descuadre real de la extracción, que es justo lo que vigilan.

El congelamiento vive por eso en columnas propias: `interes_vencido_congelado`, `base_congelada` e `interes_no_facturado`. `interes_vencido` y `base_deterioro` conservan **siempre** el valor extraído, y el deterioro contable se calcula sobre `ISNULL(base_congelada, base_deterioro)`. Ese `ISNULL` es lo que hace que una operación sin suspender siga comportándose exactamente como antes.

Verificado con una marca activa: la base bajó, el deterioro bajó en la proporción del porcentaje del rango, y **`C-INTERES` y `C-BASE` se mantuvieron en OK**.

---

## 3. La invariante: con cero marcas, nada se mueve

Los tres cortes están validados al centavo contra el libro. Una fase que toca el motor tiene que demostrar que no los altera.

Reejecutado el corte 3 —el único reproducible, porque las tablas de origen de los cortes 1 y 2 cambiaron— dentro de una transacción revertida:

| | Antes | Después |
|---|---|---|
| `SUM(deterioro_contable)` | 742.761.663,46 | 742.761.663,46 |
| `hash_datos` | `69d5268a…dedd6c4` | `69d5268a…dedd6c4` |
| `hash_parametros` | `4552654a…21e3aeb5` | `43254ec4…6907207e` |

El `hash_parametros` **sí cambia**, y debe cambiar: se le sumó la paramétrica de causales al conjunto congelado. Se comprobó que el valor nuevo es estable entre corridas consecutivas, que es lo que garantiza la reproducibilidad.

`C-SUSPENSION` sale en `N/A` con motivo, sin cifras y sin marcarse como falla. No en OK con cero: un cero ahí significaría "cuadra" cuando lo que ocurre es que no hay nada que cuadrar. Es el mismo defecto que este módulo ya corrigió dos veces en fases anteriores.

---

## 4. `C-SUSPENSION` no es una tautología

El control compara la reducción de base por dos caminos: `SUM(base_deterioro − base_congelada)` sobre las suspendidas, contra `SUM(interes_vencido − interes_vencido_congelado)` sobre las mismas. Deben coincidir porque el capital vencido no cambia.

Se comprobó corrompiendo **una sola columna a la vez**:

| Alteración | Resultado |
|---|---|
| `base_congelada` +1.234,56, sin tocar el interés | FALLA por exactamente −1.234,56 |
| `interes_vencido_congelado` +987,65, sin tocar la base | FALLA por exactamente 987,65 |

Revertidas ambas, vuelve a OK con diferencia cero.

---

## 5. Lo que se probó del comportamiento

**Vigencia temporal de la marca.** Es la parte con más riesgo, porque el servidor tiene el login en español y una conversión ambigua de fecha rompería el snapshot en silencio.

- Evento posterior a la fecha de corte → la operación **no** queda suspendida.
- Evento anterior y reactivación posterior al corte → **sí** queda suspendida. Levantar una marca hoy no puede cambiar un corte de hace tres meses.
- Reactivación anterior o **igual** a la fecha de corte → no queda suspendida. El borde `=` quedó correctamente excluido.
- Marca sin `interes_congelado` → la operación no se toca, y `C-SUSPENSION` lo reporta como informativo conservando sus cifras.
- Fecha con día 25, para descartar que se leyera como mes: guardada y comparada correctamente.

**Índice único filtrado.** Una segunda marca activa sobre la misma operación falla con `SQLSTATE 23000`; una segunda marca sobre una operación cuya marca previa ya fue levantada se permite. Es el caso real de una operación que se suspende, se reactiva y se vuelve a suspender.

**Permisos.** Marcar cambia la base de deterioro y el gasto del período, así que no puede compartir permiso con consultar. Se verificó en tres niveles, y el tercero es el que importa: con un usuario que sólo tiene `consultar`, marcar devuelve **403**. La prueba se hizo redirigiendo la conexión `identidad` hacia PRUEBAS en memoria, sin tocar la de producción.

**Validaciones de entrada.** Fecha futura rechazada; observación obligatoria con tope de 500; soporte con tope de 255; causal inexistente o con vigencia cerrada rechazada; y marcar una operación que ya tiene marca activa se rechaza **con mensaje claro, antes del INSERT**, no con un error de driver contra el índice único.

**Migración.** `down()` seguido de `up()` reproduce el esquema idéntico —44 columnas, índices y llaves comparados uno a uno, incluido el índice filtrado con su predicado— y el DDL manual produce el mismo esquema que la migración, de modo que Demo y producción no divergen.

**Longitud de los textos.** La fase 4 encontró un control a 18 caracteres de abortar el cálculo completo. Aquí: descripción de `C-SUSPENSION` en 113 de 200, motivo simple en 57, motivo informativo en 100 con 999 marcas simuladas. Margen amplio en los tres.

---

## 6. El cargue inicial no se puede hacer todavía, y ahora se sabe exactamente por qué

El archivo `terceros a excluir agosto 2026.xlsx` trae 281 operaciones en tres columnas: cédula, nombre y número de operación. Limpio, sin duplicados ni vacíos. **No trae causal, ni fecha del evento, ni soporte.**

### La fecha sí se puede derivar de SIESA

Contabilidad indicó tomarla del último documento tipo `FAT` del cliente en SIESA. La conexión ya existía —`unoeearar`, base `UNOEEARAR`, compañía 7, la misma que ya usa el sitio— así que se probó sobre las 281:

- **257 de 281 operaciones obtienen fecha.** 24 no tienen ninguna factura `FAT`.
- Ordenar por `f350_rowid` o por `f350_fecha` da **el mismo resultado en los 257 casos**, de modo que la ambigüedad entre "último insertado" y "más reciente" no se materializa en estos datos.
- Hay 257 cédulas distintas para 281 operaciones: 14 clientes tienen más de una. Como las tres causales de D-05 son eventos del deudor y no de la operación, compartir la fecha entre las operaciones de un mismo cliente es defendible.

### Pero el congelamiento anclado en cortes no alcanza

Las fechas resultantes se remontan a 2022: 87 operaciones quedarían en abril de 2026 y 31 en abril de 2022. El motor congela tomando el interés del último corte anterior al evento, y sólo hay tres cortes, el más antiguo de septiembre de 2023.

| De las 281 operaciones | |
|---|---:|
| Sin factura en SIESA, sin fecha de evento | 24 |
| Con fecha anterior a todo corte calculado | 51 |
| Con corte de anclaje pero ausentes de ese corte | 92 |
| **Congelables con la regla actual** | **114** |

Y de las 206 que sí tienen corte de anclaje, **189 se anclarían en el corte de septiembre de 2023** — hasta dos años y medio antes del evento.

**La conclusión no es que el código esté mal, sino que la fuente es la equivocada:** el interés a congelar tiene que salir de SIESA, que es donde quedó registrado lo efectivamente causado, no de unos cortes que no existían cuando ocurrieron los eventos.

### Magnitud de lo que está en juego

Las 225 operaciones del archivo presentes en el corte de julio suman **860.903.554,26** de deterioro contable: el **49,1 %** del corte. La lista de suspensiones no es un caso de borde.

### Una colisión que apareció al medir

De esas 225, **19 no generan deterioro hoy** —8 en rango A y 11 corrientes, con 1.015.301,00 de base— porque su porcentaje de RN-04 es cero. D-15 dice que *todas* las del archivo generan deterioro, y para estas el saldo de SIESA no cambia nada: cero por cualquier base sigue siendo cero. O la suspensión las reclasifica a un rango deteriorable, que sería una regla nueva de mucho peso, o se deterioran sólo las que ya tienen mora.

### Sobre la causal

Contabilidad indicó que hoy es imposible determinarla. Asignar una de las tres reales sin fundamento sería inventar un dato contable. Lo que corresponde es una causal propia del cargue —`SIN_DETERMINAR`— que deje las 281 marcas identificables y completables después. Queda planteado, sin implementar.

---

## 7. Un incidente en producción

Un subagente probó `DeterioroMenuSeeder` sin llamar a `Ambiente::aplicar('demo')`. El seeder usa la conexión por defecto, que en este proyecto **apunta a `ArarFinanciera`, la base de producción**, incluso ejecutando desde la máquina de desarrollo. El seeder es idempotente y no falla: escribió en silencio.

Se insertaron dos filas en `Submenus` (`/deterioro-contable-fiscal` y `/deterioro-evolucion`) y seis en `PermisosRoles`. Como las fases 2 a 4 no están desplegadas, quedaron **dos ítems visibles en el menú lateral del sitio en vivo apuntando a rutas inexistentes**, para los roles Administrador, Gerente y Contador.

El subagente reportó haber limpiado lo que escribió, pero sólo había borrado una parte. La verificación independiente del estado real de la tabla —no del relato— encontró el resto.

Revertido con guarda previa y transacción: `Submenus` de 25 a 23, `PermisosRoles` de 96 a 90, exactamente 2 y 6 filas, sin huérfanos y con las cinco filas de la fase 1 intactas.

Lo que sí se descartó: **no hubo ampliación de permisos**. Las siete rutas de deterioro tenían roles 1, 2 y 7 de forma uniforme, así que producción ya concedía Gerente y Contador desde la fase 1. El relleno de seis permisos que apareció al probar en PRUEBAS era una carencia de PRUEBAS.

**Medidas tomadas:** advertencia en el encabezado del seeder, nota permanente en la memoria del proyecto, y de aquí en adelante todo prompt a un subagente que toque base de datos declara explícitamente que la conexión por defecto es producción y que toda escritura va en transacción revertida. La instrucción anterior decía "verifica contra PRUEBAS", que resultó no ser suficiente.

---

## 8. Los defectos que UI/UX rechazó

La pantalla se rechazó en la primera revisión por dos defectos de presentación.

**El guion del dato ausente se estaba apagando.** `susGuion` pintaba con la clase `det-cero` el guion del interés no congelado. `det-cero` es el gris reservado para **ceros confirmados y triviales**: comunica "esto no importa". Aquí el guion significa lo contrario —no se pudo determinar el interés a la fecha del evento—, que es el caso que más atención necesita de la pantalla. Es la tercera vez en el módulo que un dato ausente se confunde con un cero sin importancia, y la primera vez que se detecta antes de llegar al usuario.

**Un `det-panel-cab` fuera de su panel.** El bloque "Efecto sobre este corte" llevaba encabezado sin envolverlo en `.det-panel`, un patrón que no existe en ninguna otra parte del módulo.

Se corrigieron ambos, más dos mejoras aceptadas: clase propia `det-estado-vigente` para no mezclar la semántica del estado de una marca con la del estado de un corte, y tooltip de Bootstrap en lugar de `title` nativo para el aviso "No aplica a este corte", que es la señal más importante de la pantalla.

### El desajuste silencioso que sí se resolvió desde el diseño

Las marcas viven fuera del corte y el motor las aplica según la fecha del evento. Una marca puede estar **vigente y aun así no afectar el corte que se está viendo**, porque su evento es posterior a la fecha de corte o porque el corte no se ha recalculado.

La pantalla lo señala explícitamente, cruzando las marcas vigentes contra las que el motor efectivamente aplicó. Sin eso, el usuario vería una marca activa con su interés congelado y no entendería por qué las cifras del corte no la reflejan. Verificado ejecutando la función de pintado real contra payloads reales: una marca con evento posterior al corte queda activa, **no** entra en `suspendidas[]`, y se pinta visualmente distinta de una aplicada.

---

## 9. Aislamiento

Toda la validación se hizo dentro de transacciones revertidas. Al terminar:

- `SUM(deterioro_contable)` del corte 2 en **1.753.977.960,01**, sin cambio.
- Cuadres por corte sin cambio: corte 1 → 5 OK / 9 N/A; corte 2 → 13 / 1; corte 3 → 8 / 6.
- `det_fiscal_acumulado` en 238 filas, `det_validacion_excel` en 18.
- Las tablas nuevas no existen fuera de la transacción: **la migración de la fase 5 todavía no está aplicada en PRUEBAS**.
- Ninguna escritura persistida, ni en PRUEBAS ni en producción.

---

## 10. Pendientes

1. **La migración de la fase 5 no está aplicada en ninguna base.** Ni en PRUEBAS ni en producción. Se suma a la deriva ya registrada en la fase 4: PRUEBAS tiene los objetos de las fases 2 a 4 aplicados por DDL manual, pero su tabla `migrations` sólo registra las tres de la fase 1.

2. **Cuatro definiciones pendientes de Contabilidad**, detalladas en `plantilla-suspensiones-instructivo.md`: de dónde sale el interés a congelar para eventos anteriores a los cortes —la que destraba todo lo demás—, con qué antigüedad de mora se deterioran las 56 operaciones ausentes de factoring, qué componente del saldo de SIESA es la base, y qué pasa con las 19 operaciones que hoy están al día.

3. **`ROLES_SUSPENSION` vale `[1, 2, 7]`**, igual que consultar y calcular. El mecanismo de separación es correcto —ruta, middleware y submenú técnico propios— pero con esa política ningún rol podrá consultar sin poder también marcar. Es una decisión de negocio, señalada como revisable en el propio código.

4. **La escritura de la marca en el sistema de factoring (D-12) no está implementada**, y depende del punto abierto B. Las columnas `marca_factoring_aplicada`, `valor_anterior_factoring` y `fecha_escritura` existen sin uso. Mientras tanto la marca vive sólo en el módulo, que es el escenario 2 previsto en la sección 13 del documento técnico.

5. **El control C-4 de sincronía de marcas contra factoring** no existe todavía, por la misma razón.

6. **`candidatasSuspension()` sugiere por rango E y F**, que es una heurística razonable pero no está en ninguna política. Conviene que Contabilidad confirme el criterio antes de que los usuarios lo tomen como una recomendación del sistema.

7. **Sigue sin haber cobertura de pruebas automatizadas.** `project/tests/` conserva sólo el andamiaje de Laravel. Toda la validación de las cinco fases es manual. Es el pendiente que más crece con cada fase, y esta lo agrava: el congelamiento tiene casos borde de fecha que nadie va a reejecutar a mano en cada cambio.

8. **Nada de las fases 2 a 5 está desplegado.** El orden obligatorio de despliegue sigue siendo el registrado en la fase 4, ahora con la migración de suspensiones y el seeder de causales al final.

---

## 11. Simulación del cargue inicial sobre el corte de julio de 2026

Ejecutada el 3 de septiembre de 2026 sobre `ArarFinanciera_PRUEBAS`. El corte 2 **no se puede reejecutar** —su tabla de origen ya tiene cargado otro periodo—, así que la simulación aplica los pasos de suspensión y deterioro contable sobre las filas ya calculadas, dentro de una transacción revertida. El corte validado quedó en 1.753.977.960,01 antes y después.

### Por qué `FAT` y no otro tipo de documento

Al medir apareció que `FEX` tiene **77.187 documentos contra 30.068 de `FAT`**, ambos de enero de 2022 a agosto de 2026, y que incluirlo cambiaría la fecha de 68 de las 257 cédulas, con diferencias de hasta cuatro años. Se consultó antes de cambiar la regla.

**La respuesta descarta `FEX`:** `FAT` es la **facturación automática de intereses que genera el sistema de Factoring**. La última `FAT` de un cliente es, por construcción, el último mes en que se le causaron intereses, que es exactamente la fecha del evento que D-06 necesita. `FEX` es otra cosa y no interviene, por numeroso que sea.

Queda registrado en el código, en el docblock de `fechaUltimaFacturaSiesaExacta()`, porque es el tipo de decisión que alguien revertiría por error al ver la diferencia de volumen entre los dos tipos.

### El resultado con la regla correcta

| | |
|---|---:|
| Operaciones en el archivo | 281 |
| Con fecha de evento resuelta desde SIESA | 259 |
| Sin ninguna `FAT` en SIESA | 22 |
| Cargadas y presentes en el corte de julio | 211 |
| **Suspendidas efectivamente** | **97** |
| Base liberada | 188.355.339,00 |

| Deterioro del corte de julio | |
|---|---:|
| Antes | 1.753.977.960,01 |
| Después | 1.580.260.883,14 |
| **Efecto** | **−173.717.076,87 (−9,90 %)** |

El resto del corte no se movió un peso: 934.307.127,15 antes y después. Y los cuatro controles quedaron en OK con diferencia 0,00 —`C-CUOTAS`, `C-INTERES`, `C-BASE` y `C-SUSPENSION`— con 97 operaciones suspendidas, que es la prueba de que el diseño de columnas separadas de la sección 2 aguanta en volumen.

### Sólo 97 de 211 producen efecto

De las 211 operaciones presentes en el corte, **114 quedan marcadas pero sin congelar**: no existe ningún corte anterior a su fecha de evento del cual tomar el interés. La cifra de 173,7 millones es por tanto un **piso, no el efecto total**. Cuando se resuelva de dónde sale el interés congelado, el efecto será mayor.

### Reparto por rango de las del archivo

| Rango | Operaciones | Suspendidas | Deterioro |
|---|---:|---:|---:|
| Corriente | 9 | 3 | 0,00 |
| A | 6 | 4 | 0,00 |
| B | 7 | 1 | 312.102,16 |
| C | 8 | 3 | 1.776.578,65 |
| D | 38 | 12 | 35.189.519,60 |
| E | 71 | 26 | 177.423.878,58 |
| F | 72 | 48 | 431.251.677,00 |

Las 15 operaciones en Corriente y A siguen en cero: su porcentaje de RN-04 es cero y ninguna base lo cambia. Es la colisión con D-15 que sigue sin resolverse.

### El archivo es cartera de libranzas

206 de las 211 son **LIBRANZAS** y 5 **FINANCIACIÓN**. Ninguna es FACTORING. Por eso `interes_no_facturado` da 0,00: `sigue_calculando_suspendido` sólo está activo para FACTORING, y el reporte de intereses calculados y no facturados de D-06 no tiene nada que mostrar para esta población. No es un defecto.

### Dos defectos de cruce encontrados al medir

1. **El dígito de verificación.** Dos cédulas vienen como NIT con guion (`900519994-1`, `800202163-1`) y no cruzaban contra `f200_nit`. Se corrigió intentando primero el valor tal cual y luego sin el dígito, en vez de normalizar a ciegas. Recupera 2 operaciones: de 257 a 259.

2. **El NIT enlazado como entero.** `f200_nit` es `varchar`, y enlazar la cédula como número obliga a SQL Server a convertir toda la columna, lo que **desborda con NITs de 11 dígitos** y descarta el índice. Funcionaba por casualidad, porque el CSV entrega texto. Corregido con un `(string)` explícito.

### El comando de cargue quedó aprobado

QA lo validó el 3 de septiembre de 2026: siete archivos deliberadamente rotos abortan sin escribir ninguna fila; la precedencia entre la fecha del archivo y la de SIESA funciona en las dos ramas; la idempotencia se comprobó con un diff completo columna por columna, no sólo por conteo; y se verificó que el único uso de la conexión a SIESA es de lectura.

Dos comprobaciones que conviene destacar. QA midió que **quitar el filtro por tipo de documento cambia la fecha en 103 de las 257 cédulas**, lo que confirma que la restricción a `FAT` no es un detalle. Y reprodujo a propósito el desbordamiento del NIT enlazado como entero, obteniendo el error de conversión, con lo que el `(string)` queda justificado por prueba y no por argumento.

### Qué falta para que esta cifra sea definitiva

- **De dónde sale el interés congelado** para eventos anteriores a los cortes. Es lo que convertiría 97 en 211.
- **D-15**, la base tomada del saldo de SIESA, que sigue bloqueada por el punto D.
- **Las 22 sin `FAT`**, que necesitan otra fuente de fecha: no se les generó facturación automática de intereses en el periodo que cubre SIESA.
- **Las 15 en Corriente y A**, que no producen deterioro por porcentaje cero.
