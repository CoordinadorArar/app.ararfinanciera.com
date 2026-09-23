# Deterioro de Cartera — Fase 7b, resultados de la validación

**Fecha:** 14 de septiembre de 2026
**Base de trabajo:** `ArarFinanciera_PRUEBAS`, con el esquema de la fase aplicado
**Alcance entregado:** los cuatro exportables de la sección 16, la paramétrica de cuentas contables y el permiso `exportar`
**Alcance no entregado:** los controles C-4 y C-5, que siguen dependiendo de definiciones externas

---

## 1. Por qué la fase 7b no es lo que su nombre prometía

El plan reservaba para 7b tres cosas: C-4, C-5 y los exportables. Al abrirla se midió el estado real de las dos primeras y ninguna se puede cerrar hoy:

- **C-4, sincronía de marcas con factoring**, compara las marcas del módulo contra las del sistema de factoring. Eso exige saber si ese sistema tiene un campo propio de suspensión, que es el **punto abierto B** y es del proveedor. Sigue sin respuesta desde el 28 de agosto.
- **C-5, cobertura del cargue inicial**, ya tiene implementada su parte medible: el cuadre `C-MARCAS` vigila las operaciones con marca aplicable que no se pudieron congelar. Su forma completa —separar las causas entre sin saldo en SIESA, sin antigüedad de mora y sin identificación cruzable— depende del **punto abierto C**, que sigue sin definición.

Se entregaron por tanto los exportables, que no dependen de nada externo salvo en un punto acotado, y quedó registrado que la fase 7b no está completa.

---

## 2. Una dependencia nueva, y un lock que ya estaba roto

El proyecto no tenía ninguna librería de Excel. Tenía `barryvdh/laravel-dompdf` para PDF, y para leer archivos de Contabilidad venía convirtiéndolos a CSV a mano. Se decidió instalar `phpoffice/phpspreadsheet` en vez de generar CSV, porque el libro que hay que replicar tiene varias hojas y el propósito del exportable es contrastarlo hoja por hoja.

Al instalarlo apareció un hecho que conviene dejar escrito: **el `composer.lock` del proyecto ya era incompatible con el PHP de la máquina de desarrollo**. `nette/schema`, que entra por Laravel, exige `php <8.2` y aquí corre 8.2.12, de modo que el `vendor/` existente se instaló en su día ignorando el requisito de plataforma. La instalación se hizo por tanto con el requisito de PHP omitido y fijando la rama 1.x de la librería, que cubre de PHP 7.4 a 8.3 y sirve cualquiera que sea la versión del servidor.

El resultado es limpio: **6 instalaciones, 0 actualizaciones, 0 remociones**. No se tocó ninguna dependencia existente. Como `project/vendor/` está en `.gitignore`, el día del despliegue hace falta `composer install` en el servidor.

Al margen, y sin relación con esta fase: `composer audit` reporta 64 advisories sobre 17 paquetes, y el proyecto arrastra dos paquetes abandonados.

---

## 3. Lo que se construyó

**La paramétrica de cuentas contables, vacía a propósito.** La sección 17 dice que el gasto va «contra la cuenta que defina Contabilidad», y esa definición no existe. En lugar de esperarla, se construyó el exportable completo contra una paramétrica con vigencias que se siembra el día que las cuentas existan, sin tocar código.

No se modeló como «una cuenta de gasto», porque el asiento cambia de forma según el signo: cuando el deterioro aumenta se debita gasto y se acredita la 1399, y cuando disminuye se debita la 1399 contra una cuenta de recuperación, que normalmente es de ingreso. La paramétrica lleva por tanto **concepto, cuenta débito y cuenta crédito**, con producto opcional que cae a una fila general, y su copia congelada dentro del corte.

**Los cuatro exportables:** el Excel de transición con las hojas de resultado del libro, el PDF del resumen, el archivo plano del asiento y el detalle por operación respetando los filtros de la pantalla.

**Un permiso propio, `exportar`**, que no hereda de consultar: el archivo sale del sitio, el plano del asiento llega a contabilidad y el detalle lleva la cartera completa.

---

## 4. Lo que encontró la validación

Ninguno de los cinco defectos del servidor era de cálculo: las cifras cuadraron al centavo desde la primera corrida. Todos eran de **contrato, de estado o de verdad de lo que el archivo afirma**.

**El contrato del asiento confundía «listo» con «imposible».** La pantalla recibía una lista de conceptos sin cuenta; vacía significaba a la vez que todo estaba resuelto y que el asiento no se podía generar nunca, porque el corte es el primero de la serie y no hay período que ajustar. Eran indistinguibles byte por byte. Se reemplazó por un objeto de disponibilidad con motivo y mensaje. Al corregirlo apareció un tercer estado con el mismo defecto: un corte sin calcular también llegaba como disponible.

**La copia congelada de cuentas no protegía a nadie.** Decidía si existía preguntando si tenía filas, y con el PUC vacío se congelan cero filas, de modo que todo corte cerrado caía a la paramétrica viva. El día que Contabilidad sembrara las cuentas, un corte cerrado seis meses antes habría empezado a exportar un asiento que antes no existía, y habría vuelto a cambiar con cada corrección de vigencia. Se resolvió con una bandera explícita en el corte. **La inmutabilidad se probó por registro de consultas, no por resultado**: con la bandera en 1 se consulta sólo la copia del corte, con la bandera en 0 sólo la viva. Comparar resultados no habría distinguido nada, porque hoy las dos tablas están vacías.

**El Excel afirmaba como ajuste del mes lo que el asiento se negaba a afirmar.** En un corte sin corte anterior, la fila «valor ajuste del mes» se llenaba por rango con el saldo completo —1.690.801.777,06 en el corte 1— mientras su celda de total quedaba vacía. Es exactamente la afirmación que el asiento rechaza por falsa, y al ir el total en blanco nada la delataba.

**La hoja `1399` exportaba menos de lo que el libro tiene.** Leía el acumulado que el motor consumió —199 filas, 1.021.285.010,87— en vez de la tabla completa —238 filas, 1.138.378.564,83—. Las 39 filas que faltaban son acumulados de operaciones que ya no están en el corte, y las 242 filas del libro cuadran con la tabla, no con el subconjunto. Quien hiciera la marcha en paralelo habría perdido un día persiguiendo una diferencia de 117 millones que no es una diferencia. Se decidió exportar la tabla completa marcando esas filas.

**Y una regresión de rendimiento en pantallas ajenas al exportable:** la consulta que averigua si el asiento está disponible se invoca en las siete pantallas de corte y rehacía consultas que esas mismas pantallas ya traían en su propio payload. Se reescribió para responder sólo esa pregunta. Las cifras medidas por QA, que son las que valen: **120 ms** la consulta nueva, y el camino completo **298 ms contra 537 ms**, con las pantallas pasando de 7 consultas a 2 o 3. La mejora es de 1,8×, no la de 3× que se informó al implementar.

### En la interfaz

**Un interceptor global donde hacía falta una llamada.** Para que el menú conociera el corte y los permisos se envolvió `makeOptionsFetch`, que vive en `funciones-globales.js` y lo usan 25 archivos del sitio. El módulo sólo se carga en sus siete vistas, pero dentro de ellas toda llamada de la aplicación —incluida la del conmutador de ambiente del layout— pasaba por código del módulo de deterioro. Se sustituyó por una llamada explícita al final de la carga de cada pantalla: seis líneas. El envoltorio además convivía con la llamada que ya existía en Detalle, de modo que allí el menú se pintaba dos veces.

**Una respuesta de error se le descargaba al usuario como archivo.** El cliente distinguía 403, JSON y «todo lo demás es binario», sin consultar nunca el estado de la respuesta. Un 500 o un 504 con HTML se guardaba como `deterioro_resumen_2026-07-31.pdf`: un archivo que no abre y ningún mensaje. Era alcanzable —agotamiento de memoria con varios usuarios en el cierre de mes, o un timeout del proxy sobre una exportación de seis segundos— y tenía además una causa que no estaba a la vista: el `fetch` no pedía `Accept: application/json`, así que ante una excepción no controlada el servidor respondía con su página HTML en lugar de JSON. Se corrigieron las dos cosas.

---

## 5. Decisiones que quedan registradas

1. **El asiento se ofrece también sobre un corte calculado y no sólo cerrado**, porque Contabilidad necesita preparar el registro antes de cerrar. Pero el archivo dice lo que es: los exportables de un corte calculado llevan `_preliminar` en el nombre y el rótulo PRELIMINAR dentro, y los de un cierre con salvedades llevan `_con-salvedades` y, en el PDF, el motivo y los requisitos congelados. **El archivo declara su propia calidad sin abrirlo.**
2. **La fecha del nombre es la del corte, nunca la de descarga**, para que ordene igual alfabética que cronológicamente seis meses después.
3. **El color del aviso distingue quién puede resolverlo.** Ámbar cuando la espera tiene dueño y final —Contabilidad definirá el PUC—; gris cuando es un hecho permanente del corte, como ser el primero de la serie. Un ámbar ahí prometería una gestión que nadie puede hacer.
4. **La ausencia de distintivo significa «disponible», y sólo eso.** Un motivo que el módulo no conozca pinta gris con «No disponible», para que un estado futuro no se lea como descargable.
5. **Un corte que congeló cero cuentas no vuelve a leer la paramétrica viva.** Para incorporar un PUC sembrado después hay que reabrir y recalcular el corte, y el mensaje lo advierte. Es la consecuencia deliberada de la inmutabilidad.
6. **Los castigos se identifican por `cierra_fiscal` de la paramétrica**, nunca comparando contra el texto `CASTIGO`, que es la regla escrita en duro que el módulo evita desde la fase 1.

---

## 6. Cómo se validó

Doce corridas de los cuatro exportables contra los tres cortes, abriendo los archivos y contrastando las cifras contra la base, más un arnés de DOM alimentado con los payloads reales de las siete pantallas para la interfaz.

Lo que quedó comprobado con número:

- El detalle exportado suma **1.753.977.960,0100**, idéntico a la base, **sin redondeo**: la operación 1108 conserva sus cuatro decimales en la celda.
- El bloque resumen cuadra con las consultas de pantalla, la fila de totales cae en la **2112** igual que en el libro, y el movimiento del mes da 63.176.182,95.
- La hoja `1399` completa: 238 filas por 1.138.378.564,83, de las cuales 199 marcadas dentro del corte por 1.021.285.010,87 —al centavo la columna P— y 39 fuera por 117.093.553,96. Cero operaciones difieren entre la hoja y la columna.
- Los rechazos son siempre JSON con el patrón del módulo; **nunca archivo ni HTML**, verificado con 500, 502, 504, un 500 con JSON y un 419 de sesión caducada.
- Escala: el corte 2 ya tiene las **115.027 filas reales** de detalle de cuota. Ningún exportable las lee; trabajan sobre las 2.106 operaciones consolidadas, en 6 s y 86 MB contra un límite de 512 MB. La preocupación por el volumen del cierre de mes no aplica.

**Lo que no se pudo validar con datos: la hoja `DIFERENCIAS` nunca se ha ejercitado con una sola fila.** Ver el pendiente 1.

---

## 7. Pendientes

1. **Los tres cortes de PRUEBAS están calculados con el motor anterior a la fase 6a.** `det_conciliacion_partida` y `det_corte_saldo_siesa` están vacías en toda la base, y por eso la hoja `DIFERENCIAS` sale sólo con encabezado y la columna de cartera SIESA en cero. No es que SIESA no tenga datos: la consulta devuelve 13.105 filas candidatas para 2026-07-31. Las 326 partidas que midió la validación de la fase 6a se midieron sobre un corte que ya no existe. **Recalcular los cortes es necesario antes de la marcha en paralelo**, y tiene una consecuencia que hay que decidir, no asumir: cambiaría las cifras de referencia del módulo, porque D-16 mueve el corte de julio en −41.240.651,37.
2. **La hoja `1399` del corte 1 se contradice con la columna P de su propio libro** —201 filas marcadas dentro del corte contra una columna que suma cero—, por la misma obsolescencia del pendiente 1. Ninguna cifra está mal; se resuelve recalculando.
3. **Siete de las 39 filas fuera del corte salen sin nombre de cliente**, porque su operación nunca apareció en ningún corte y no hay de dónde resolverlo. El valor y la marca son correctos; falta el rótulo.
4. **En la hoja `DIFERENCIAS`, la columna de diferencias residuales saldrá igual que la anterior**, porque la de prórrogas y reservas va vacía: el módulo no captura esa entrada manual, que es alcance de la fase 6b. Es deliberado, pero en la marcha en paralelo se verá como dos columnas idénticas.
5. **Faltan dos definiciones de Contabilidad**, ninguna suplible por el módulo: las cuentas del PUC, y contra qué cuenta de cartera se cierra el castigo y si va en el mismo comprobante. Mientras tanto el asiento se rechaza nombrando los conceptos que faltan.
6. **`CASTIGO` se resuelve siempre contra la fila general de la paramétrica**, así que una cuenta de castigo definida por producto nunca resolvería. Hay que advertírselo a Contabilidad cuando defina el PUC.
7. **Falta el quinto punto de la sección 17**, el anexo de intereses no causados y no facturados para revelaciones. Quedó fuera del encargo; el dato ya existe en el módulo.
8. **`/deterioro-accion-exportar` todavía no está en `Submenus`.** Hasta que corra `DeterioroMenuSeeder`, el fallback del middleware lo resuelve contra la pantalla de Cortes. Hoy no hay exposición, porque ambos conjuntos son los mismos tres roles, pero el seeder tiene que correr en la misma ventana que el código.
9. **Sigue sin haber una sola prueba automatizada.** Dos de los defectos de este ciclo —el contrato ambiguo y el error HTML descargado como archivo— los habría cazado una aserción.

---

## 8. Aislamiento y despliegue

Toda la validación se hizo con `Ambiente::aplicar('demo')` y sólo SELECT. No se ejecutó ninguna escritura, migración, seeder ni comando de consola contra ninguna base, y sobre producción no hubo ni una consulta de escritura.

Aplicado en `ArarFinanciera_PRUEBAS`: el DDL de la fase, con sus dos tablas y la columna `cuentas_congeladas` en `det_corte`. La paramétrica de cuentas quedó **vacía**, que es su estado correcto, y los tres cortes intactos en `CALCULADO`.

**El orden de despliegue es esquema, luego código, luego permisos**, y esta vez el esquema es bloqueante de verdad: la consulta de disponibilidad del asiento se invoca en las siete pantallas de corte, así que sin las tablas nuevas el módulo entero deja de responder. Después del código hay que correr `composer install` en el servidor, porque `vendor/` no va al repositorio.

Archivos del ciclo:

- Nuevos: `project/app/Support/DeterioroExportador.php`, `project/resources/views/deterioro/export-resumen.blade.php`, `project/database/seeders/DeterioroCuentaContableSeeder.php`, `project/database/migrations/2026_09_14_100000_create_det_cuenta_contable_tables.php`, `documentacion/deterioro-fase7b-ddl.sql`, `js/deterioro-exportar.js`
- Modificados: `project/app/Models/Deterioro.php`, `project/app/Http/Controllers/DeterioroController.php`, `project/routes/web.php`, `project/database/seeders/DeterioroMenuSeeder.php`, `project/composer.json`, `project/composer.lock`, `css/deterioro.css`, los siete `js/deterioro-*.js` de pantalla y las siete vistas de `project/resources/views/deterioro/`
- Sin tocar: `project/resources/views/deterioro/cortes.blade.php` y `js/deterioro-cortes.js`
