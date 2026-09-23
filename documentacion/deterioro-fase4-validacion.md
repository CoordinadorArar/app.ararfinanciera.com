# Deterioro de Cartera — Fase 4, resultados de la validación

**Fecha:** 2 de septiembre de 2026
**Corte replicado:** 31 de julio de 2026
**Libro de contraste:** `DETERIORO CARTERA A 31 DE JULIO DE 2026.xlsx`, hoja `DETERIORO`, filas 8, 9 y 10
**Base de trabajo:** `ArarFinanciera_PRUEBAS` (ambiente Demo)
**Alcance:** histórico y descomposición del movimiento del mes — pantalla Evolución

---

## 1. Resultado

**La ecuación del movimiento cierra al centavo** en el corte de julio de 2026:

| Concepto | Operaciones | Valor |
|---|---:|---:|
| Deterioro al 30 de junio de 2026 | | 1.690.801.777,06 |
| + Altas | 38 | 0,00 |
| + Variación de las que continúan | 2.068 | 67.862.208,22 |
| − Bajas | 46 | 4.686.025,27 |
| **= Deterioro al 31 de julio de 2026** | **2.106** | **1.753.977.960,01** |

El total coincide con `SUM(deterioro_contable)` del corte, que es la cifra ya validada contra el libro en la fase 1.

**Gasto del período: 63.176.182,95.**

Las 38 altas aportan 0,00. No es un error de cálculo: las operaciones nuevas de julio están al día, y una operación corriente no genera deterioro. Es la comprobación de que el término existe y se mide, aunque este mes valga cero.

---

## 2. La diferencia contra el libro está localizada

El libro calcula el gasto del mes como `T10 = AC34 − T9`, donde **`T9` es el deterioro del mes anterior digitado a mano** (RN-10). El módulo lo toma del corte de junio ya calculado. De ahí que las dos cifras no coincidan:

| Concepto | Módulo | Libro | Diferencia |
|---|---:|---:|---:|
| Deterioro del mes anterior | 1.690.801.777,06 | 1.696.218.156,21 | −5.416.379,15 |
| Deterioro de este corte | 1.753.977.960,01 | 1.753.977.960,01 | **0,00** |
| Gasto del período | 63.176.182,95 | 57.759.803,80 | −5.416.379,15 |

**Toda la diferencia viene de la fila digitada**, y se puede señalar rango por rango:

| Rango | Módulo (junio) | Libro (fila 9) | Diferencia |
|---|---:|---:|---:|
| B · 31 a 90 | 4.208.420,72 | 4.617.601,76 | −409.181,04 |
| C · 91 a 180 | 14.799.785,16 | 14.215.638,29 | +584.146,87 |
| D · 181 a 360 | 79.844.289,06 | 79.844.289,06 | **0,00** |
| E · 361 a 720 | 306.565.431,12 | 312.156.776,10 | −5.591.344,98 |
| F · 721 en adelante | 1.285.383.851,00 | 1.285.383.851,00 | **0,00** |
| | | **Suma** | **−5.416.379,15** |

Dos rangos cuadran al centavo y los otros tres suman exactamente la diferencia total. Es el resultado más útil de la fase: **la discrepancia no es difusa, tiene tres direcciones concretas** y quien la revise sabe dónde mirar.

El deterioro **de este corte** cuadra en cero, así que el módulo y el libro no discrepan en el cálculo. Discrepan en el punto de partida.

---

## 3. El control del libro es informativo, no un fallo

`C-LIBRO` compara el gasto del módulo contra el del libro y publica los 5.416.379,15. **No se marca como falla.** Sería incorrecto: el módulo no está obligado a reproducir una cifra escrita a mano en una hoja de cálculo, y tratarla como error entrenaría al usuario a ignorar los controles rojos.

Esto obligó a introducir un **tercer estado de cuadre**, `N/A`, junto a `OK` y `FALLA`. Lo usan tres situaciones distintas:

- controles de fases posteriores evaluados sobre un corte que no las tiene calculadas;
- controles que dependen de un corte anterior, en el primer corte de una serie;
- `C-LIBRO` cuando hay cifras del libro cargadas, que conserva su diferencia a la vista.

| Corte | Fecha | OK | FALLA | N/A |
|---|---|---:|---:|---:|
| 1 | 2026-06-30 | 5 | **0** | 9 |
| 2 | 2026-07-31 | 13 | **0** | 1 |
| 3 | 2023-09-30 | 8 | **0** | 6 |

Los 14 controles del módulo: cinco de la fase 1, tres de la fase 2, tres de la fase 3 y tres nuevos (`C-MOVIMIENTO`, `C-VARIACION`, `C-LIBRO`).

> **Sobre el corte 3.** Su fila refleja el estado **persistido**, que quedó congelado de una corrida anterior a la fase 3: sus cuatro columnas de diferido están en `NULL`. Reejecutado con el motor de hoy da **11 OK / 0 FALLA / 3 N/A**, y el deterioro contable y el `hash_datos` se reproducen idénticos — no es un problema de cálculo. Conviene recalcularlo antes de citar el 8/0/6 como referencia.

**Los dos controles nuevos no son tautologías.** Se comprobó corrompiendo columnas de forma dirigida: alterar sólo `movimiento` (marcar una VARIACION como ALTA) pone `C-MOVIMIENTO` en falla por 159.999.166,00, y alterar sólo `variacion_deterioro` lo pone en falla por 3.000.000,00 — en ambos casos **sin tocar `deterioro_contable`**. Detectan de verdad. Revertidas las alteraciones, los conteos vuelven a 13/0/1 y la suma queda intacta en 1.753.977.960,01.

---

## 4. Las cifras del libro dejan de estar escritas en el código

`det_validacion_excel` existe desde la fase 1 y **nunca se había poblado**. Se le dio uso en lugar de incrustar las cifras del libro en el motor.

```
php artisan deterioro:cargar-validacion-excel {idCorte} {archivo} --hoja=DETERIORO --ambiente=demo
```

Carga las 18 celdas de las filas 8, 9 y 10 desde un CSV (`documentacion/validacion-excel-deterioro-2026-07.csv`). Corrido tres veces seguidas: `det_validacion_excel` queda en **18 filas**, sin combinaciones duplicadas, con upsert que cambia el valor en sitio.

Consecuencia práctica: la conciliación del mes siguiente se actualiza **cargando un CSV**, no editando código y desplegando.

---

## 5. Un defecto de diseño que se corrigió antes de que existiera

`descomposicionMovimiento()` calculaba `gasto = actual − anterior`. Sin corte anterior, `anterior` resuelve a 0 y el gasto del período salía igual al **saldo completo del corte**: 1.690.801.777,06 en el corte 1, 742.761.663,46 en el corte 3.

Hoy la pantalla lo tapaba. Pero `conciliacionLibro()` lo habría publicado en cuanto se cargaran cifras del libro de un primer corte — que es justamente lo que el comando nuevo hace posible.

Se corrigió devolviendo `NULL`, el mismo criterio que ya usaba `serieHistorica()`. Y se verificó en el escenario que todavía no existe: dentro de una transacción se cargaron las 18 cifras del libro sobre el corte 1 y se comprobó que el módulo **ya no publica** 1.690.801.777,06 como gasto del período — la fila desaparece de la conciliación y `C-LIBRO` queda en `N/A` sin cifras. Rollback confirmado con huella idéntica antes y después.

El `NULL` obligó además a blindar dos consumidores que lo habrían leído como cero: `evNum = v => Number(v || 0)` en el frontend, y una conversión `(float)` en `verificarCuadres()` que habría publicado `−F10_TOTAL` como diferencia.

---

## 6. El defecto que QA rechazó

La primera entrega se rechazó por un fallo de presentación que no afectaba ninguna cifra pero invalidaba la pantalla.

El estado `N/A` es nuevo; el JavaScript estaba escrito para dos estados, con el patrón `estado === 'OK' ? 'ok' : 'falla'` repetido en cuatro archivos. **Todo lo que no fuera OK se pintaba rojo:**

| Corte | Verdes | Rojas | Realidad en base |
|---|---:|---:|---|
| 1 | 5 | **9** | 0 fallas, 9 N/A |
| 3 | 8 | **6** | 0 fallas, 6 N/A |
| 2 | 13 | **1** | 0 fallas, 1 N/A |

Agravante: la diferencia de un `N/A` viaja en `null` y el formateador imprimía `0,00`, así que el usuario leía *"control fallido con diferencia cero"*. En la lista de cortes el mensaje decía "Todos los cuadres en cero" junto a nueve ✗ rojas.

Corregido extrayendo un helper único (`detClaseCuadre` / `detDifCuadre` / `detFilaCuadre`) a `deterioro-comun.js`, consumido por las tres pantallas. La regla es **guion cuando la diferencia es nula**, no cuando el estado es `N/A`: por eso `C-LIBRO` del corte 2 se ve neutro **conservando** sus 5.416.379,15, que es información útil y no un fallo.

Verificado tras la corrección: corte 1 → 5 verdes / 9 grises / 0 rojas; corte 2 → 13 / 1 / 0; corte 3 → 8 / 6 / 0.

### El mismo "cero falso" de la fase 3, en otro panel

El panel "Gasto del mes" no consultaba si había corte anterior, a diferencia de los otros dos paneles de la misma página. En el corte 3 la pantalla decía a la vez *"Primer corte de la serie, no hay con qué comparar"* y *"Gasto del período: 742.761.663,46"* — el saldo completo presentado como gasto del mes.

Es el mismo defecto conceptual que la fase 3 corrigió en el panel de movimiento: **un cero, o una cifra, que no significa "no hubo" sino "no hay dato"**. Que haya reaparecido en otro panel indica que conviene tratarlo como un patrón a vigilar en cada pantalla nueva, no como un incidente aislado.

---

## 7. El defecto que ningún control podía ver

Los 14 controles pasaban, las cifras cuadraban al centavo y la pantalla se veía bien. El defecto no estaba en lo que el sistema calculaba, sino en **cuánto margen le quedaba antes de romperse**.

`verificarCuadres()` concatenaba el motivo dentro de la descripción antes de persistir, y `det_corte_cuadre.descripcion` es `nvarchar(200)`:

```
C-VARIACION:  97 (descripción) + 14 (' · no aplica: ') + 71 (motivo) = 182 caracteres
```

**Dieciocho caracteres de margen.** En SQL Server pasarse de `nvarchar(200)` no trunca en silencio: lanza *"String or binary data would be truncated"*, el `INSERT` falla y **aborta el cálculo completo del corte**. Reformular un texto de control bastaba para tumbar el motor en producción — y `C-VARIACION`, el más largo, es uno de los que esta fase acaba de introducir.

Encima, el dato nacía separado en el motor, se pegaba para guardarlo, y el frontend lo volvía a separar con una expresión regular. Cambiar el separador en el PHP habría dejado la pantalla sin motivos, en silencio.

Corregido persistiendo `motivo` e `informativo` como columnas propias. La descripción más larga baja de 182 a **104** caracteres y el motivo queda en 101, ambos sobre 200.

### El booleano que era una cadena

Al separar los campos apareció una trampa de segundo orden: **PDO sqlsrv devuelve `bit` como la cadena `"0"`**, y `"0"` es **verdadero** en JavaScript. Sin castear, la misma fila se habría leído como informativa en las pantallas que consumen `cuadres()` y como no informativa en la que consume `verificarCuadres()`. Se verificó sobre el JSON crudo —no sobre el decodificado— que el booleano llega correcto en los **cinco** caminos que devuelven cuadres.

Es el tipo de defecto que se ve bien en una pantalla y mal en otra, y que ninguna prueba sobre un solo endpoint habría encontrado.

### Dos ejes, no uno

El estado y el carácter informativo son independientes, y confundirlos fue un error de mi propio planteamiento inicial:

| Caso | Punto | Badge | Cifra |
|---|---|---|---|
| `OK` | verde | — | cifra |
| `FALLA` | rojo | — | cifra |
| `N/A` + informativo | gris | sin badge, motivo rotulado | **cifra visible** |
| `N/A` sin informativo | gris | `no aplica` | guion |

`C-LIBRO` del corte 2 es `N/A`, informativo **y** con cifra: las tres cosas a la vez.

---

## 8. Qué se probó

- Ecuación del movimiento cerrando al centavo, y `actual` coincidiendo con `SUM(deterioro_contable)`.
- Bajas del período: 46 operaciones por 4.686.025,27, exactamente el término de la descomposición, sin redondeo.
- Filas 8 y 10 del libro reproducidas al centavo; la diferencia de la fila 9 localizada en tres rangos.
- Los tres controles nuevos, con **falla forzada y revertida** para probar que detectan.
- **Idempotencia**: `aplicarMovimientoMes()` dos veces deja `CHECKSUM_AGG` idéntico.
- **Guardas**: sin corte anterior el método devuelve 0 sin excepción; dimensión inválida en el comparativo lanza `InvalidArgumentException`.
- Comando de carga corrido tres veces, con upsert verificado.
- Contrato del endpoint campo por campo contra el JavaScript, y los 31 `getElementById` de la pantalla nueva con su `id` en el blade.
- Las cinco pantallas del módulo respondiendo 200 y renderizando sin excepción, ejecutando las funciones de pintado reales contra los payloads reales de los tres cortes.
- **Aislamiento**: `det_fiscal_acumulado` en 238 filas antes y después de cada corrida.
- **Reversibilidad de la migración**: se ejecutaron `down()` y `up()` de verdad sobre PRUEBAS dentro de una transacción revertida, y se comparó el esquema resultante contra el que produce el DDL manual —tipo, longitud, precisión, nulabilidad y definición del default— campo por campo. Idénticos, y ambos iguales al original. El `down()` suelta el default constraint del `bit` antes del `DROP COLUMN`, que en SQL Server no se suelta solo.

---

## 9. Pendientes

> Los pendientes **4, 5, 10 y 13** se cerraron el 3 de septiembre de 2026. La lista se conserva íntegra como registro de lo que dejó abierto la fase; el detalle del cierre está en la sección 10.

1. **Nada de las fases 2, 3 y 4 está en producción.** Verificado por diferencia de catálogo: `ArarFinanciera` sólo registra las tres migraciones de la fase 1, le faltan cinco tablas fiscales y **trece columnas** en `det_deterioro_operacion`.

2. **El orden de despliegue no es una recomendación, es un requisito.** Comprobado en la práctica: contra producción, `deterioro-resumen-datos` devuelve 500 porque la consulta suma columnas que allí no existen. Si se sube el código antes de migrar, **la pantalla de resumen que hoy está en uso queda caída**. Orden obligatorio:

   `subir código → php artisan migrate → cargar el 1399 → cargar las cifras del libro → refrescar los cortes`

3. **Deriva del registro de migraciones en `ArarFinanciera_PRUEBAS`.** La base tiene todos los objetos de las fases 2 a 4, pero su tabla `migrations` sólo registra las tres de la fase 1, porque en Demo se aplicaron con los DDL manuales. Un `php artisan migrate` contra PRUEBAS intentaría recrear columnas existentes y fallaría. Conviene reconciliarlo antes de que alguien lo corra por costumbre.

4. **`/deterioro-evolucion` no está registrada en `Submenus`**, igual que `/deterioro-contable-fiscal` de la fase 3. `CheckSubmenuPermission` deja pasar cualquier ruta que no encuentre, así que el marco de la pantalla lo ve cualquier usuario autenticado. Las cifras no: el endpoint de datos sí exige `deterioro.permiso:consultar`.

5. **Dos redacciones del mismo mensaje.** El texto que arma el controlador tras calcular un corte quedó ignorado por el frontend, que lo recompone. Hoy no hay inconsistencia visible —esa rama sólo se consume en error—, pero el texto muerto conserva la redacción vieja, "Todos los cuadres en cero", que es el defecto ya corregido del lado JS. Cualquier consumidor futuro del endpoint la recibiría.

6. **Sin cobertura de pruebas automatizadas.** `project/tests/` sólo tiene el andamiaje de Laravel. Toda la validación de las cuatro fases es manual y no reejecutable en integración continua. Es el pendiente que más crece con cada fase.

7. **`evolucionDiferenciaTemporaria()` quedó como alias de `serieHistorica()`**, y la serie cambió de forma en esta fase: se le agregaron tres campos. La consume la pantalla de la fase 3. Se comprobó que renderiza sin excepción en los tres cortes, pero es un cambio de contrato hacia una pantalla anterior.

8. **Los datos de prueba mezclan series sin relación.** La serie histórica del corte de junio de 2026 incluye el corte de septiembre de 2023. No es un defecto del código, pero distorsiona la lectura de la gráfica mientras se valide sobre estos cortes.

9. **La evolución a 12 y 24 meses sigue sin poder demostrarse**: hay tres cortes, y sólo dos encadenados. La pantalla está construida para funcionar cuando haya 24 y avisa cuántos quedan fuera.

10. **`modo_compatibilidad_excel` sigue viajando como la cadena `"0"`** en cuatro endpoints. Hoy ningún JavaScript lo lee, así que no hay defecto activo — pero es **exactamente la misma trampa** que `informativo`, y el arreglo se hizo puntual en `cuadres()` sin generalizarse. `Deterioro::corte()` usa `DB::selectOne` crudo, de modo que un `$casts` en el modelo tampoco lo cubriría. Conviene resolverlo antes de que alguien consuma ese campo y descubra que `"0"` es verdadero.

11. **Los cortes 1 y 2 ya no son reproducibles en `ArarFinanciera_PRUEBAS`.** Sus tablas de origen cambiaron: `ResumenVigentesClientes` está vacía y `ResumenVigentesClientes1` trae junio de 2026, así que `ejecutarCorte` aborta sobre ellos. Sólo el corte 3 conserva su origen. Las cifras de este informe salen del estado ya persistido, que es válido, pero **la réplica desde cero no se puede repetir hoy**. Además `ejecutarCorte` no permite elegir tabla de origen, aunque `Deterioro::ejecutar()` sí acepta ese parámetro.

12. **`/deterioro-resumen-fiscal` es un endpoint huérfano**: ningún JavaScript lo llama. Devuelve datos correctos, pero nadie los pide.

13. **La fila de conciliación con el libro sigue escrita a mano** en `js/deterioro-evolucion.js`, sin pasar por el helper unificado. No corre riesgo de truncamiento porque no viene de `det_corte_cuadre`, pero es el último sitio de la interfaz con el estilo viejo y añade una fila de cuadre que no corresponde a ningún control.

---

## 10. Pendientes cerrados el 3 de septiembre de 2026

Cuatro pendientes de bajo riesgo, ninguno con efecto sobre las cifras. QA aprobó los cuatro sobre `ArarFinanciera_PRUEBAS`, sin reejecutar ningún corte y sin dejar escrituras persistidas.

### Pendiente 10 · el booleano que era una cadena, generalizado

`modo_compatibilidad_excel` viajaba crudo desde `bit`, y PDO sqlsrv lo entrega como la cadena `"0"`, que en JavaScript es **verdadera**. Es la misma trampa que la fase 4 resolvió puntualmente en `informativo` sin generalizar.

Se castea `(bool)` en `Deterioro::corte()` y en `Deterioro::listarCortes()`, que son los dos únicos puntos por donde sale: `corte()` alimenta los cuatro endpoints que devuelven el objeto en la clave `corte`, y `listarCortes()` lo arrastra por su `SELECT c.*`. Un `$casts` en el modelo no habría servido: ambos son `DB::select` crudos.

Verificado sobre el **JSON crudo**, no sobre el decodificado —decodificar oculta justamente este defecto—: `"modo_compatibilidad_excel":false` aparece literalmente en los cinco caminos por los tres cortes, quince casos. `corte(99999)` sigue devolviendo `NULL`, que es el contrato del que dependen los cuatro endpoints para responder que el corte no existe.

### Pendiente 5 · la redacción vieja que sobrevivía en el servidor

`ejecutarCorte()` contaba `!== 'OK'` y remataba con *"Todos los cuadres en cero"*. Es el defecto que la fase 4 ya había corregido del lado JS: con 5 OK y 9 N/A el texto afirmaba una falsedad. El frontend recompone el mensaje y no consume esa rama salvo en error, así que no había defecto visible — pero cualquier consumidor futuro del endpoint la habría recibido.

Ahora el PHP cuenta los tres estados y arma el texto con la misma lógica que `js/deterioro-cortes.js`. Se comprobó comparando **cadena contra cadena** las dos implementaciones sobre siete tripletes `(falla, ok, n/a)` —los tres reales y cuatro casos borde, incluida la concordancia de singular— extrayendo las expresiones de los archivos reales y no de una copia. Idénticas en los siete.

### Pendiente 4 · las dos pantallas nuevas quedan bajo control de permisos

`/deterioro-contable-fiscal` y `/deterioro-evolucion` no estaban en `Submenus`, y `CheckSubmenuPermission` deja pasar cualquier ruta que no encuentra registrada: el marco de las dos pantallas lo veía cualquier usuario autenticado. Se agregaron al `DeterioroMenuSeeder`, que es idempotente por `RutaSubmenu`.

Corrido dos veces dentro de una transacción revertida: la primera inserta las dos filas y la segunda no inserta nada; los `IdSubmenu` de las cinco filas preexistentes no cambian; tras el rollback los conteos de `Submenus` y `PermisosRoles` vuelven a su valor original.

**El rótulo se dejó con margen.** `NombreSubmenu` es `varchar(30)` y el nombre propuesto, `Comparativo contable / fiscal`, medía **29**. Es la misma clase de defecto que el truncamiento de `descripcion` de la sección 7: en SQL Server pasarse de un `varchar` no trunca en silencio, lanza excepción y **abortaría el seeder completo**, y un rótulo de menú es exactamente el tipo de texto que alguien reformula sin medirlo. Quedó en `Contable / fiscal`, 17 de 30, y la advertencia del encabezado del seeder —que sólo cubría la ruta— ahora cubre también el nombre.

### Pendiente 13 · la última fila de interfaz con el estilo viejo

La pantalla de Evolución añadía a mano una fila `Conciliación con el libro · informativa` que no correspondía a ningún control de la base. Era redundante desde que la fase 4 introdujo `C-LIBRO`, que ya viaja en `cuadres`, ya se pinta con `detFilaCuadre`, ya sale en gris y ya rotula su motivo como informativo.

Se eliminó. Verificado que no se perdió información: ejecutando la función de pintado real contra los payloads reales, `C-LIBRO` sigue presente y en el corte 2 **conserva sus 5.416.379,15** en gris con el motivo rotulado, no un guion. Los conteos por color quedan en 5/9/0, 13/1/0 y 8/6/0 — antes había una fila gris de más. El panel de conciliación con el libro y su aviso quedaron intactos.

---

## 11. Dos hallazgos nuevos

1. **Sembrar el menú concede permisos que hoy no existen, más allá de las dos pantallas nuevas.** Al correr el seeder en la transacción de prueba insertó **12** filas de `PermisosRoles`, no 6: seis por las dos páginas nuevas para los roles 1, 2 y 7, y **otras seis de relleno** porque los roles 2 (Gerente) y 7 (Contador) no tienen hoy permiso sobre las tres páginas de la fase 1 que ya están en producción. El seeder no distingue: su bucle concede `ROLES_CONSULTA` sobre todas las páginas. Correrlo en producción, por tanto, **amplía el acceso a Gerente y Contador sobre pantallas que hoy sólo ve Administrador**. Es una decisión de negocio, no un detalle técnico, y hay que resolverla antes de sembrar.

2. **La migración de `Submenus` no refleja la tabla real.** `2022_07_18_201746_create_submenus_table.php` declara tres columnas de datos y la tabla en PRUEBAS tiene además `CodigoSubmenu varchar(50)`, que es la que guarda el icono y la que el seeder escribe. La columna se agregó fuera de las migraciones. No afecta al módulo de deterioro, pero cualquiera que lea la migración para conocer el esquema se llevará una idea equivocada.
