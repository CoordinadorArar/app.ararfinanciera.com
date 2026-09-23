# Deterioro de Cartera — Fase 7a, resultados de la validación

**Fecha:** 14 de septiembre de 2026
**Base de trabajo de la validación:** ninguna. Ver la sección 6: la validación fue estática y por simulación, y es la primera de la serie que no pudo tocar datos.
**Desplegado el mismo día:** `ArarFinanciera_PRUEBAS` (esquema y causales) y `ArarFinanciera` (menú y permisos). Ver la sección 10, escrita después del despliegue.
**Alcance entregado:** cierre y reapertura de corte, controles C-1 y C-2, permisos, auditoría y la pantalla que los gobierna
**Alcance no entregado:** controles C-4 y C-5 y los exportables de la sección 16, que dependen de definiciones externas y pasan a la fase 7b

---

## 1. Qué faltaba al abrir este ciclo

El esquema, el modelo, el controlador, las rutas, los permisos y el CSS de la fase 7a se escribieron el 4 de septiembre y quedaron sin validar. La revisión del 14 de septiembre encontró que **faltaba la capa de pantalla entera**: `controles.blade.php` cargaba `js/deterioro-controles.js` y ese archivo no existía en ninguna parte del repositorio. La pantalla de Controles y cierre —la única vía para clasificar bajas, cerrar, forzar el cierre, reabrir y consultar la bitácora— no funcionaba.

La vista, además, estaba a medias: tenía el modal de clasificar bajas y ningún sitio para las otras cuatro acciones, pese a que sus cuatro endpoints y sus cinco permisos existían desde el 4 de septiembre. Backend había entregado un módulo completo sin puerta de entrada.

---

## 2. Lo que se construyó

`js/deterioro-controles.js`, nuevo, mil líneas, y el resto de `controles.blade.php`: el panel de cierre con sus requisitos, el panel de la foto de salvedad, la bitácora como panel colapsable y los modales de forzar el cierre y de reabrir.

Tres archivos en el ciclo de validación: el JS, la vista y `Deterioro.php` por el defecto de la sección 5. El despliegue agregó un cuarto, `DeterioroMenuSeeder.php`, por la razón de la sección 10.

---

## 3. Los dos bloqueantes, que eran el mismo error

QA y UI/UX trabajaron por separado y llegaron al mismo defecto por caminos distintos, lo que conviene leer como una señal y no como una coincidencia.

**La pantalla pintaba `condicionesCierre` sin mirar el estado del corte.** `Deterioro::condicionesCierre` y `Deterioro::bajasDelPeriodo` evalúan el estado vivo y no consultan si el corte está abierto o cerrado —correctamente, porque son consultas, no reglas de pantalla—. La pantalla las mostraba tal cual, y de ahí salían dos afirmaciones falsas en un módulo contable:

- **Sobre un corte ABIERTO, sin calcular:** «Requisitos pendientes · 0 de 3 · El corte cumple los tres requisitos de cierre», el ancla en verde «Se puede cerrar» y los tres puntos del tablero en verde. La pantalla declaraba superados controles que nunca se corrieron. Y con corte anterior el error se invertía y empeoraba: `bajasDelPeriodo` devuelve **toda la cartera del corte anterior** como baja sin clasificar, así que la pantalla ofrecía miles de operaciones con el botón «Clasificar» activo tres centímetros debajo del aviso que pedía calcular el corte.
- **Sobre un corte CERRADO:** los bloqueos vivos seguían existiendo, así que la pantalla mostraba «2 por resolver» y enlaces «Clasificar bajas» y «Explicar partidas» hacia pantallas donde ya nada se puede editar. En el mismo render, el panel de cierre vaciaba la lista de requisitos y los botones de la tabla estaban deshabilitados con el tooltip que explica que el corte está cerrado. La pantalla se contradecía a sí misma.

La corrección no fue parchear cada zona: se introdujeron dos salidas únicas, `ctrlSinCalcular()` y `ctrlCerrado()`, que neutralizan tarjetas, anclas, tablero y paneles C-1 y C-2 de una sola vez. En un corte abierto nada se pinta en verde y los estados vacíos nombran la causa real —«se identifican al calcular el corte»— en lugar de afirmar una comparación que no se ejecutó.

**El modal de clasificar la salida no validaba nada y perdía lo digitado.** Enviaba con la causal vacía; el controlador responde un objeto con `res` en `bad` y su texto, y nunca una clave `errors`, de modo que la rama que llamaba a `showErrors` era código muerto y los dos contenedores de error de la vista no se llenaban jamás. Peor: ocultaba el modal *antes* de comprobar la respuesta, así que quien olvidaba la causal veía desaparecer el modal con su observación escrita y recibía sólo una alerta. Ahora valida en cliente, escribe en los dos contenedores y sólo cierra el modal cuando el servidor acepta.

---

## 4. Lo demás que se corrigió

- **Forzar el cierre ocupaba el sitio del botón principal.** El contenedor alinea a la derecha y el orden de concatenación dejaba «Forzar el cierre con salvedad» en el extremo derecho, que en toda la aplicación es la posición de la acción principal, y además era el único botón vivo cuando el cierre limpio estaba bloqueado. Salió de la fila de botones y quedó como enlace rojo discreto debajo. El criterio, que conviene no revertir: el peso de una acción irreversible va en la confirmación —motivo escrito obligatorio, requisitos enumerados en rojo, aviso de marca permanente—, no en el señuelo. Un botón junto al primario deshabilitado convierte la excepción en la salida obvia.
- **El modal del forzado grisaba los requisitos que el usuario estaba a punto de saltarse.** El gris es correcto en la foto del cierre, donde ya no hay nada que resolver; en el modal minimiza justo lo que tiene que pesar. Los dos ejes quedaron separados: el punto rojo indica gravedad y la ausencia de enlace indica que no hay acción disponible.
- **Se dibujaba el forzado sin comprobar el permiso que exige la ruta.** La ruta de cierre está tras el middleware `deterioro.permiso:cerrar` y `forzarCierre` sólo se verifica dentro del controlador: quien tuviera `forzarCierre` sin `cerrar` veía la acción y recibía 403. Con los roles del seeder hoy no se reproduce —`ROLES_FORZAR_CIERRE` está contenido en `ROLES_CIERRE`—, pero los permisos se editan desde la pantalla de gestión del sitio.
- **El ancla de la bitácora iba en verde** con el rótulo «Trazabilidad completa». En esta pantalla el verde significa requisito cumplido; la bitácora no es un requisito y «completa» afirma una calidad que nadie verificó. Pasó a badge neutro.
- Concordancia de número («Faltan 1 requisitos»), estado vacío de la bitácora fuera de la tabla, el ancla que ahora despliega el panel colapsable, el rechazo del servidor que recarga la pantalla, y varias cosméticas.

---

## 5. El único defecto que era de servidor

La pantalla mostraba «Cerrado el 2026-08-05 09:12 por el usuario 3» y «Usuario 4 · 2026-08-05», porque el modelo no resolvía el nombre. La bitácora sí lo hacía desde la fase 1, y ese fue el patrón.

`corte()` pasó a resolver `usuario_cierre`, `bajasDelPeriodo()` agregó `usuario`, y `cerrarCorte()` **congela el nombre dentro del JSON de la foto de salvedad** en vez de resolverlo al leer. Esto último no es un detalle de implementación: la foto se consulta años después, cuando el identificador puede no resolver a nada, y una foto que pierde el autor con el tiempo no sirve para lo que existe.

La pantalla degrada al identificador cuando el nombre viene nulo, de modo que los cierres anteriores a este cambio y los usuarios borrados siguen leyéndose.

---

## 6. Cómo se validó, y qué no se validó

**Cuando se hizo esta validación, nada de la fase 7a estaba desplegado en ninguna base, así que no se ejecutó una sola prueba contra datos.** Es la primera validación de la serie en esa situación y hay que leer sus resultados con ese límite puesto por delante. El despliegue vino después, ese mismo día, y está en la sección 10.

Lo que sí se hizo:

- `node --check` sobre el JS y `php -l` sobre el modelo.
- **Un arnés de DOM simulado** que carga `deterioro-comun.js` y `deterioro-controles.js` en un contexto aislado, extrae los identificadores reales de la vista y devuelve nulo para cualquiera que la vista no tenga. Diez escenarios, cero errores de ejecución: corte sin corte anterior, sin permisos, respuesta mínima con claves ausentes, los tres bloqueos con nulos en todas las columnas, abierto con corte anterior y cartera grande, cerrado limpio, cerrado con salvedad y bloqueos vivos, baja clasificada por un usuario que ya no existe, permiso de forzar sin permiso de cerrar, y salvedad de un cierre viejo sin nombre.
- **Contrato cliente-servidor campo por campo** contra los alias SQL de las nueve consultas que alimentan la pantalla. Toda propiedad leída existe.
- Rutas, nombres de parámetro y reglas de validación; los trece manejadores declarados en la vista contra el ámbito global del script; las treinta y tres clases CSS contra la hoja de estilos; los helpers contra sus firmas.
- **Que las tres correcciones de Backend no rompen a nadie más.** `corte()` la consumen diez sitios y `bajasDelPeriodo()` la consume también la pantalla de Evolución; ninguno itera las claves genéricamente, y los dos joins son por clave primaria, de modo que no pueden multiplicar filas.

Lo que queda por comprobar en un navegador de verdad, y no se puede afirmar desde aquí: que el foco de teclado se comporte cuando la alerta de error se abre sobre el modal todavía abierto. Los z-index dicen que la alerta se dibuja encima —1060 sobre 1055 sobre 1050— y el ratón funciona sin duda; lo que podría no responder es Enter o Esc, porque Bootstrap mantiene una trampa de foco en el modal. Es degradación de teclado, no pantalla bloqueada.

---

## 7. Decisiones que quedan registradas

Para que nadie las «corrija» después sin saber que fueron deliberadas:

1. **El forzado es un enlace, no un botón.** Ver la sección 4.
2. **El gris está reservado a la foto del cierre.** Significa «ya no se puede resolver», no «es menos grave».
3. **La bitácora es un panel dentro de Controles, no una pantalla.** No existe ruta, vista, entrada de menú ni permiso de pantalla para una bitácora propia, el endpoint filtra por corte y la pregunta real —quién cerró, forzó o clasificó este corte— es del corte que ya está en pantalla.
4. **C-4 y C-5 se muestran en gris con la leyenda de que no están disponibles en esta fase, y jamás en verde.** Un verde sin dato detrás es una afirmación falsa.
5. **En un corte cerrado con salvedades, las anclas y tarjetas de C-1, C-2 y cuadres siguen mostrando el recuento vivo.** Es un hecho sobre el corte, no una invitación: las acciones ya desaparecieron. Es justo lo que la salvedad significa.

---

## 8. Pendientes

Los nueve de la fase 6a siguen abiertos tal como quedaron escritos. Los que esta fase cambia o agrega:

1. **El punto abierto F se volvió bloqueante.** La fase 6a lo dejó anotado: no hay tolerancia de materialidad definida para la conciliación, y C-3 exige explicar toda partida antes de cerrar. Ese bloqueo **ya está activo** en el código entregado. Si el residuo trae partidas de centavos, el módulo obligará a explicar ruido y el control se degradará a trámite. Es decisión de política contable y hoy no está tomada.
2. **`C-CONCILIA` ya no está en `N/A`.** La fase 6a lo dejó neutro a propósito, anotando que en la fase 7 había que recordar activarlo. Se activó.
3. **El despliegue sigue sin poder hacerse por migración**, aunque ya esté hecho por DDL. La migración de la fase 5 crea `det_suspension_interes` sin guarda y esa tabla ya existe, así que `php artisan migrate` fallaría ahí y nunca llegaría a las de las fases 6a y 7a. Mientras eso no se corrija, el único camino es el DDL manual en el orden del encabezado de `deterioro-fase7-ddl.sql`. Lo que este pendiente advertía sobre los permisos quedó resuelto: ver la sección 10.
4. **Sobre un corte cerrado queda una redacción con marco de «pendiente».** El tablero sigue diciendo «La clasificación de la salida es requisito para cerrar el corte» sobre un corte ya cerrado. No bloquea —el estado se declara sin ambigüedad en cuatro sitios y no hay ninguna acción ofrecida—, pero es deuda de redacción.
5. **El preloader no está envuelto en `try/finally`** en ninguna de las siete pantallas del módulo: una petición que rechace deja el overlay pegado hasta recargar. En esta pantalla importa más, porque esa ruta se dispara justo cuando el servidor ya está rechazando algo.
6. **Los tooltips más allá de la primera página de la tabla no se inicializan**, porque ninguna pantalla del módulo se engancha al evento de repintado. Preexistente y transversal a las siete.
7. **Controles y cierre es un callejón sin salida en la navegación.** Enlaza a las seis pantallas hermanas y ninguna enlaza a ella. Se dejó fuera del alcance a propósito: son doce archivos ajenos a esta fase.
8. **Sigue sin haber una sola prueba automatizada.** `project/tests/` conserva el andamiaje de Laravel. Toda la validación de las siete fases es manual, y este ciclo lo agrava: por primera vez la validación no pudo tocar datos, y un arnés de simulación no es un sustituto de una prueba.
9. **`tmp_routes.json` sigue sin versionar en la raíz.** Ajeno a esta fase, pero no debería arrastrarse al commit.

---

## 9. Aislamiento del ciclo de validación

Durante la validación no se ejecutó ninguna escritura, migración, seeder ni comando de consola, y no se consultó ninguna base: ni `ArarFinanciera`, ni `ArarFinanciera_PRUEBAS`, ni `UNOEEARAR`. Toda la validación ocurrió sobre el código. El despliegue de la sección 10 es posterior y sí escribió, con el detalle que allí se registra.

Archivos tocados en este ciclo, confirmado contra el árbol de trabajo y las fechas de modificación:

- `js/deterioro-controles.js` (nuevo)
- `project/resources/views/deterioro/controles.blade.php`
- `project/app/Models/Deterioro.php`

Todo lo demás de la fase 7a conserva su fecha del 4 de septiembre.

---

## 10. Despliegue, 14 de septiembre de 2026

Escrita después de desplegar, el mismo día de la validación.

### 10.1 Lo que ya estaba

El estado real de `ArarFinanciera_PRUEBAS` estaba **por delante de lo que registraba la validación de la fase 6a**: las fases 2 a 6a ya estaban aplicadas, con `det_corte_saldo_siesa`, la columna `origen_base` y las tres causales de suspensión sembradas. La fase 6a había dejado escrito que nada de ella estaba en ninguna base; entre el 4 y el 14 de septiembre alguien la aplicó sin registrarlo. El despliegue se redujo por tanto a la fase 7a.

Es la segunda vez que el documento de una fase y la base divergen. Conviene tomarlo como lo que es: **el estado de las bases no se puede leer de los documentos, hay que consultarlo**.

### 10.2 Lo aplicado

| Paso | Base | Resultado |
|---|---|---|
| `deterioro-fase7-ddl.sql`, líneas 1 a 166 | `ArarFinanciera_PRUEBAS` | 3 tablas, 8 columnas de cierre en `det_corte`, 4 índices |
| `DeterioroCausalSalidaSeeder` | `ArarFinanciera_PRUEBAS` | 4 causales, idempotencia comprobada repitiendo la corrida |
| `DeterioroMenuSeeder` | `ArarFinanciera` | 5 → 17 submenús, 15 → 49 permisos |

Los tres cortes quedaron intactos y en `CALCULADO`, con sus sumas de deterioro contable idénticas al centavo a las de la fase 6a: 1.690.801.777,06 / 1.753.977.960,01 / 742.761.663,46. Las dos tablas nuevas de movimiento quedaron vacías, como corresponde.

El DDL corrió con su guarda propia (`IF DB_NAME() <> N'ArarFinanciera_PRUEBAS' THROW`) y los dos seeders dentro de transacciones que sólo se confirmaron tras verificar el resultado esperado.

### 10.3 El seeder de menú tuvo que corregirse antes de poder usarse

`DeterioroMenuSeeder` escribía con la conexión por defecto. Eso es un error de diseño, no un detalle: `CheckDeterioroPermiso`, `CheckSubmenuPermission` y `Admin::obtenerSubMenus()` leen menús y permisos **exclusivamente** de la conexión `identidad`, y `Ambiente::aplicar()` no la conmuta a propósito, porque la identidad es única y vive en producción. Con la conexión por defecto el seeder acertaba por casualidad —cuando nadie había conmutado a demo— y sembraba filas inútiles cuando sí.

Pasó a escribir siempre sobre `identidad`, con el mismo criterio de `Admin::db()`. La consecuencia que hay que entender antes de correrlo: **este seeder escribe en producción por diseño, en cualquier ambiente.**

### 10.4 Las páginas quedaron ocultas a propósito

La rama `main`, de la que sale producción, tiene 3 de las 8 rutas GET de deterioro. Sembrar las 8 páginas con `EstadoSubmenu = 1` puso en el menú lateral de producción 5 entradas que apuntaban a rutas inexistentes en el código desplegado, visibles para los 3 usuarios de los roles Administrador, Gerente y Contador. Es el incidente que la propia cabecera del seeder documentaba como ya ocurrido una vez.

Se corrigió poniendo en `0` el `EstadoSubmenu` de esas 5 páginas, en transacción y verificando que afectara exactamente 5 filas y que ningún permiso se moviera. **Los 49 permisos siguen vivos**, que era el objetivo del despliegue: las 9 acciones van en `EstadoSubmenu = 0` por diseño, nunca se dibujan, y son las que cierran el fallback del middleware. Lo único que se apagó fue la visibilidad de 5 entradas de menú.

> **Superado el 15 de septiembre de 2026.** Ya no hay que encender esas cinco páginas: se decidió que el menú del módulo muestre **sólo Cortes**, porque las siete pantallas restantes son el detalle de un corte y, al entrar sin uno en la URL, redirigen a Cortes —dibujarlas daría ocho entradas de menú que llevan al mismo sitio—. `deterioro-fase7-visibilidad.sql` se reescribió para dejar el menú en ese estado y ya no para encenderlas; si alguien conserva una copia de la versión anterior, no debe ejecutarla. El resto de esta sección se conserva porque explica por qué se apagaron en su momento y qué efectos tiene apagar una página.

**El día que el código llegue a producción hay que encenderlas, y no se logra re-ejecutando el seeder.** `submenu()` es idempotente sólo por ruta: si la ruta existe devuelve su id y no reconcilia ninguna columna. Eso se dejó como está a propósito y el porqué quedó escrito en el docblock del seeder: `EstadoSubmenu` dejó de ser un valor derivado del código para ser estado operativo, y un seeder que lo sobreescribiera devolvería estas cinco páginas al menú la próxima vez que alguien lo corriera por cualquier otro motivo.

El encendido vive por tanto en su propio artefacto versionado, `deterioro-fase7-visibilidad.sql`, con guarda de base —la inversa de la de los DDL, porque es el único script del módulo que corre contra producción—, transacción y verificación de que queden 8 páginas visibles y 0 acciones visibles. Se ejecuta **después** del esquema y del código, nunca antes.

Tres cosas que conviene tener presentes mientras las páginas estén apagadas:

- **Los permisos de esas 5 páginas existen y están vivos, pero no se pueden revocar desde la interfaz.** La pantalla de Gestión del sitio arma sus casillas con la misma consulta filtrada por `EstadoSubmenu = 1`, así que esas filas no se dibujan. Quitarle una de estas páginas a un rol exige SQL mientras dure la ventana. Se resuelve solo al encenderlas.
- **Apagar no cierra el acceso, y encender no lo abre.** Ningún control de acceso mira `EstadoSubmenu`: los dos middleware buscan por `RutaSubmenu`. El único lector de la columna en todo el árbol es `Admin::obtenerSubMenus()`. En cuanto el código esté desplegado, las 5 rutas serán alcanzables escribiendo la URL aunque el menú no las dibuje.
- **No hace falta cerrar sesión ni limpiar el navegador** para que el cambio se vea: el menú se pide en cada carga.

### 10.5 Lo que el despliegue dejó pendiente

1. **Producción tiene los permisos por delante del esquema.** `ArarFinanciera` conserva sólo las tablas `det_*` de la fase 1, mientras los roles 1, 2 y 7 ya tienen concedidas las nueve acciones. Las rutas no dependen de `EstadoSubmenu`, así que son alcanzables por URL directa: si el código de las fases 4 a 7a llega al docroot antes que el esquema, esos endpoints fallarán contra tablas que no existen. Es fallo SQL, no corrupción, pero **el orden correcto es esquema primero, código después**.
2. **`ArarFinanciera_PRUEBAS` tiene filas huérfanas de menú y permisos** —el menú 8, los submenús 19 a 23 y sus 9 permisos, que son el juego de la fase 1— sembradas por una corrida anterior con el ambiente demo aplicado. Nadie las lee, porque todo lo de identidad se lee de producción. La corrección del seeder impide que vuelva a ocurrir pero no limpia lo ya sembrado. Quedan para limpiar o para documentar por qué se dejan.
3. **`submenu()` no converge al estado declarado, y se decidió que siga así.** Hoy «idempotente» significa «no duplica», no «deja la fila como el archivo dice». Para `EstadoSubmenu` eso es lo correcto por la razón de 10.4, y quedó escrito en el docblock. Lo que sí queda pendiente, con poco valor y ningún apuro, es reconciliar las columnas cosméticas: `IdSubmenu 21` guarda `Detalle por operacion` sin tilde desde la fase 1. Si alguna vez se hace, que sea con una lista blanca explícita de columnas que excluya `EstadoSubmenu` por nombre, nunca con un `updateOrInsert` genérico.
   **Lo que de verdad faltó aquí fue observabilidad, no convergencia.** Nadie se habría enterado de que las 5 páginas estaban apagadas si no se hubiera ido a mirar. Un seeder que al terminar reporte las diferencias entre lo declarado y lo que hay, sin tocar nada, convierte una auditoría manual en una línea de salida de cada corrida.
4. **El `insert` de `Menus` omite `Orden`**, que es `tinyint NULL` y es la primera clave de ordenamiento de `Admin::obtenerMenus()`. En producción el menú ya existía con `Orden = 6` y no molesta; sobre una base nueva, Deterioro entraría con `Orden` nulo y quedaría primero en la barra lateral.
5. **Una sola aserción automatizada sobre `EstadoSubmenu` tras correr el seeder habría contado esta historia sola.** El pendiente 8 de la sección 8 sigue siendo el que más crece.
