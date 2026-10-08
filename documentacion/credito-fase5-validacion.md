# Crédito – Fase 5: creación del cliente en SIESA

> **No se envió nada a SIESA.** Durante el desarrollo y las pruebas
> `SIESA_ENVIO_HABILITADO=false`. No se llamó a `WSUNOEE`/`ImportarXML` ni a
> ningún endpoint de creación. En las pruebas, la URL del web service se
> sobrescribió con `http://127.0.0.1:9/no-enviar` como segunda barrera.

No hay tablas nuevas, así que esta fase no tiene migración ni DDL.

## 1. Arquitectura

| Pieza | Rol |
|---|---|
| `config/services.php` → `siesa.envio_habilitado` | Interruptor `SIESA_ENVIO_HABILITADO`. Si falta, vale **false**. `.env` y `.env.example` lo traen en `false`. |
| `App\Services\Siesa\RegistroClienteSiesa` | Servicio puro: normaliza los datos, NIT/DV, apellidos, estados por paso, registros de ancho fijo y XML `<Importar>`. No consulta la BD ni envía nada. |
| `App\Services\Siesa\ClienteSoapSiesa` | Cliente SOAP `ImportarXML` de **este módulo**. Si el interruptor está apagado, `enviar()` sale antes de llegar a curl. `interpretar()` valida la respuesta con simplexml y no hace `echo`/`exit`. |
| `App\Models\Terceros` | `LoadClientes` (nunca devuelve todos), `cuentaBancariaCliente`, `estadoSiesaCliente` y `listadoClientesSiesa` (paginado). Solo hacen SELECT. |
| `TerceroSiesaController` | Expone los 4 endpoints nuevos. Se eliminaron los 7 métodos viejos y `procesarPeticionSiesa`. |

**Contable no se tocó.** `ContableController` tiene su propio
`procesarPeticionSiesa` y arma su XML por su cuenta, así que no comparte cliente
con este módulo. Por eso el interruptor solo actúa sobre las rutas
`/siesa-*` (grep de `procesarPeticionSiesa`, `services.siesa` y
`TerceroSiesaController` antes del cambio). Deterioro solo lee
`services.siesa.id_cia`, que no cambió.

### Rutas viejas: eliminadas

`/creacion-tercero-siesa`, `/creacion-cliente-siesa`, `/creacion-proveedor`,
`/creacion-impretension`, `/creacion-impretencion-proveedor`,
`/crear-pagoelec-bancolombia` y `/crear-pagoelec-bancobogota` solo las llamaba
`js/crear-cliente-siesa.js`; ninguna otra vista ni ningún `route()` las usaba.
Se eliminaron para que no quede ningún camino directo a SIESA. **Mientras
Frontend no rehaga la pantalla, el botón actual de la vista
`crear-cliente-siesa` recibe 404.** La vista GET `/crear-cliente-siesa` sigue
igual.

## 2. Contrato de API

Todas las rutas son `POST`, con los middleware `auth` y
`submenu.accion:/crear-cliente-siesa`. Los errores usan el mismo formato de las
fases anteriores: `{message, errors?}`, con 403 (permiso) o 422 (validación o
negocio).

### POST /siesa-clientes

Entrada: `{busqueda?: string≤100, estado?: "pendiente"|"parcial"|"completo", page?: int≥1 (1), perPage?: int 1..100 (20)}`

```json
{
  "registros": [{
    "idCliente": "900508834-2", "nit": "900508834", "dv": "2", "nombre": "…",
    "fecha": "2024-05-10",
    "siesa": {"tercero": true, "cliente": true, "proveedor": false},
    "estado": "parcial"
  }],
  "total": 8, "page": 1, "perPage": 20,
  "contadores": {"pendiente": 6, "parcial": 8, "completo": 2271},
  "envioHabilitado": false
}
```

- Universo: los clientes de `FactoringManagerDatos..Clientes` con
  `FecModifica >= 2022-01-01`, el mismo filtro que ya usaba
  `validarUsuariosSiesa`. Se ordenan por `FecModifica` desc y luego por `IdCliente`.
- `busqueda` busca con LIKE en `IdCliente` y en «nombre + apellido». Los
  caracteres `% _ [` se escapan.
- Cliente y proveedor se buscan en la sucursal `001`, con el mismo criterio que
  `/siesa-validar` (`estadoSiesaCliente`).
- Estado: `completo` si existen tercero, cliente y proveedor; `pendiente` si no
  existe ninguno; `parcial` en cualquier otro caso. `fecha` es `FecModifica`.
- `total` cuenta los registros del filtro `estado` (o todos si no se manda
  estado). `contadores` cuenta sobre la búsqueda, sin aplicar el filtro de estado.

### POST /siesa-validar

Entrada: `{idCliente: string}`, con regex `^[A-Za-z0-9-]{1,20}$`. Se acepta
string o número.

```json
{"pasos": [{"clave": "tercero", "nombre": "Tercero (0200)", "estado": "listo|bloqueado|omitido|hecho", "motivos": ["…"]}], "envioHabilitado": false}
```

Claves, en orden: `tercero`, `cliente`, `proveedor`, `impuestos_cliente`,
`impuestos_proveedor`, `pago_bancolombia`, `pago_bogota`. Si el cliente no
existe, responde 422 con `errors.idCliente`.

### POST /siesa-vista-previa (solo administrador, rol 1)

Entrada: `{idCliente, paso?}`. Cualquier otro rol recibe 403
`{message:"La vista previa de SIESA es solo para el administrador."}`.

```json
{"pasos": [{"clave","nombre","estado","motivos","registros": ["<registro de ancho fijo>"], "xml": "<Importar>…<Clave>********</Clave>…</Importar>" | null}], "envioHabilitado": false}
```

El backend devuelve `registros` y `xml` para todos los pasos salvo los
`omitido`, que traen `registros: []` y `xml: null`. No se envía nada.

Cómo lo muestra la pantalla (`planPasos` y `bloqueCodigo` en
`js/crear-cliente-siesa.js`):

- Paso `listo`: se muestra el registro plano.
- Paso `bloqueado` **solo por dependencia** (todos sus motivos son
  «Requiere que … exista en SIESA.» y la dependencia está `listo` en el plan):
  pasa a `listo` en el plan y **sí se muestra el registro**. En la ejecución en
  cadena se enviará después de su dependencia.
- Paso `bloqueado` por al menos un **dato faltante**: no se muestra el registro;
  aparece «No se enviará: <motivos>».
- Paso `omitido`: aparece «No se enviará: <motivo>».
- Paso `hecho`: aparece «Ya existe en SIESA; no se reenvía.».

### POST /siesa-ejecutar-paso

Entrada: `{idCliente, paso}` (obligatorio).

1. Si la validación falla, responde 422.
2. Si el envío está deshabilitado, responde **422**
   `{message:"El envío a SIESA está deshabilitado (modo solo vista previa).", errors:{envio:[…]}}`.
   Esto ocurre antes de cualquier consulta a la BD.
3. Si el cliente no existe, responde 422.
4. Si el paso no está `listo`, responde 422 con
   `errors.paso = motivos`, o `["El paso ya existe en SIESA."]` cuando está en `hecho`.
5. Si pasa todo lo anterior, envía **solo ese paso** y responde
   `{ok, paso, detalle, erroresSiesa:[{nroLinea, valor, detalle}]}`.

Los pagos electrónicos quedan `bloqueado` mientras el proveedor (t202) no
exista en SIESA, así que no se pueden enviar antes. **Este endpoint no se probó
con el envío habilitado.**

## 3. Pasos, dependencias y «hecho»

| Paso | Registro(s) | Depende de (en SIESA) | «hecho» si existe |
|---|---|---|---|
| tercero | 0200 v08 | — | `t200` con `f200_id = nit` y `f200_id_cia = SIESA_WS_ID_CIA` |
| cliente | 0201 v09 | tercero | `t201` en la sucursal 001 |
| proveedor | 0202 v03 | tercero | `t202` en la sucursal 001 |
| impuestos_cliente | 0046 + 0047 v01 | cliente | `t046` **y** `t047` en la sucursal 001 |
| impuestos_proveedor | 0049 v01 | proveedor | `t049` en la sucursal 001 |
| pago_bancolombia | 0634 v01, formato 41 | proveedor | `t633`+`t634` con `f634_id_formato = 41` |
| pago_bogota | 0634 v01, formato 7 | proveedor | `t633`+`t634` con `f634_id_formato = 7` |

Un paso está `listo` si no existe en SIESA, tiene todos sus datos obligatorios y
su dependencia ya existe en SIESA. Si le falta algo queda `bloqueado` y
`motivos` dice qué. Los pagos sin cuenta bancaria real quedan `omitido`.

Datos obligatorios por paso:

- **tercero:** identificación; tipo de identificación homologado; nombre o
  razón social; dirección; código DANE (departamento de 2 dígitos y ciudad de 3).
  Para persona natural, la fecha de nacimiento. Para NIT (tipo N), el DV debe
  coincidir con el cálculo de la DIAN.
- **cliente / proveedor:** identificación, nombre, dirección, DANE y fecha de ingreso.
- **impuestos:** identificación.
- **pagos:** tipo de identificación, banco, número de cuenta y tipo de cuenta homologado.

## 4. Fuentes de datos (FactoringManagerDatos, solo lectura)

| Dato | Fuente | Observación |
|---|---|---|
| NIT / DV | `Clientes.IdCliente` (puede venir como `NIT-DV`) y `Clientes.DigitoVerificaCli` | Una sola función, `nitDv()`: toma el sufijo `-d`, luego `DigitoVerificaCli` y, si no hay ninguno, el DV calculado (`ValidadorTercero::digitoVerificacion`). **Todos** los registros usan el NIT sin DV. |
| Tipo de identificación | `Clientes.TipoIdentificacionCliente` (catálogo `TiposIdentificacion`) | 1 CC → `C`/tercero 1; 2 NIT → `N`/tercero 2; 4 CE → `E`/tercero 1. Los demás (3 TI, 5 RUT) quedan **sin homologar** y bloquean el paso. El cruce con t200 confirma 1→C (3868) y 2→N (105). |
| Fecha de nacimiento | `Clientes.FecNacimiento` | 239 de 4086 clientes no la tienen. Si es persona natural, el tercero queda **bloqueado**. |
| Fecha de ingreso | `Clientes.FecAperturaCliente` | Está en todos los clientes; la mínima es 2012-08-27. Reemplaza los valores fijos `20160601`/`20160101`. |
| Cuenta bancaria | `CuentasTerceros` (`NumCuenta`, `TipoCuenta`, `IdEntidadF`, `Principal`) + `EntidadesFinancieras.Codigo` | Se cruza `IdTercero` con `IdCliente` o con el NIT y se prefiere la cuenta `Principal`. **Solo hay 4 filas (3 terceros)**, así que en la práctica los pagos electrónicos quedan **omitidos** para casi todos los clientes. `Codigo` coincide con `t016_mm_bancos.f016_id` (01 Bogotá, 07 Bancolombia). |
| Tipo de cuenta | `TiposCuenta`: 1 AHORROS, 2 CORRIENTE | Se lleva a SIESA `f633_tipo_cuenta`: 2 ahorros, 1 corriente. Se dedujo de los registros 0634 reales («abono cuenta de ahorro» ↔ 2). **Hay que validarlo en pruebas.** |
| Dirección, DANE, teléfonos, correo | `DirOficinaCliente`, `CiudadesAct.CodDaneCiudad`, `TelOficinaCliente`, `TelMovilCliente`, `EmailCliente` | `CiudadesAct` ahora va con LEFT JOIN: los 9 clientes sin DANE ya no «desaparecen» y quedan bloqueados con su motivo. |

Prueba de solo lectura (2026-10-07): universo de 2285 clientes; 6 pendientes,
8 parciales y 2271 completos.

## 5. Normalización y campos de ancho fijo

- `campo()` recorta y rellena por **caracteres** (`mb_substr`/`mb_strlen`).
  Los textos largos se recortan y las tildes ya no desalinean.
- `normalizar()` aplica a nombres, apellidos, razón social y dirección:
  mayúsculas; quita tildes (ÁÀÄÂ→A, etc.); **Ñ→N** (pedido del comentario
  original en `creacionClienteSiesa`); Ç→C. Después reemplaza por espacio
  cualquier carácter que no sea ASCII imprimible (`°`, `º`, `¿`, `€`, emojis,
  comillas tipográficas, caracteres de control, etc.) y colapsa los espacios. El correo solo se recorta (`trim`), no se pasa a mayúsculas.
- Apellidos: el primero es la primera palabra más las partículas iniciales
  (`DE`, `DEL`, `LA`, `LAS`, `LOS`, `SAN`), y el segundo es el resto. Así
  «de la Hoz Gómez» queda como `DE LA HOZ` / `GOMEZ`. Un solo apellido deja el
  segundo vacío, sin error.
- Razón social: «nombres apellido1 apellido2», sin espacios sobrantes.
- Persona jurídica (N): apellido1, apellido2 y nombres van vacíos. El contacto
  es la razón social y el DV es el real. Para C/E el DV sigue siendo `0`, igual
  que antes; SIESA guarda NULL para C.
- XML: cada `<Linea>` y los datos de conexión se escapan con
  `htmlspecialchars(ENT_XML1)`. Un `&` o un `<` en la dirección ya no rompe el
  XML. El encabezado es `000000100000001007`. El fin es
  `{total de líneas + 2, 7 dígitos}99990001007`: `0000003…` con un registro y
  `0000004…` para 0046+0047, igual que antes.

## 6. Layout por registro (sin cambios de posiciones ni longitudes)

Posiciones en base 0. Todos los registros empiezan con un prefijo de 19
caracteres: `NNNNNNN` + tipo(4) + subtipo `00` + versión(2) + cía `007` +
actualiza(1).

**0200 (905):** prefijo `0000002 0200 00 08 007 1` · 19 tercero(15) · 34 tercero(25) · 59 DV(3) · 62 tipo ident(1) · 63 tipo tercero(1) · 64 razón social(100) · 164 apellido1(29) · 193 apellido2(29) · 222 nombres(40) · 262 establecimiento(50) · 312 `100000` · 318 contacto=nombres(50) · 368 dirección(40) · 408 dir2(40) · 448 dir3(40) · 488 país `169`(3) · 491 dpto(2) · 493 ciudad(3) · 496 barrio(40) · 536 teléfono(20) · 556 fax(20) · 576 postal(10) · 586 email(255) · 841 fecha nacimiento(8) · 849 CIIU(4) · 853 `01` · 855 celular(50).

**0201 (1045):** prefijo `0000002 0201 00 09 007 0` · 19 tercero(15) · 34 `001` · 37 `1` · 38 razón social(40) · 78 `COP` · 81 `VEN1` · 85 `A` · 86 `C00` · 89 `000` · 92 cupo `000000000000000.0000␠`(21) · 113 corp(15) · 128 suc corp(3) · 131 `001␠` · 135 grupo dto(4) · 139 `001` · 142 `1` · 143 `0000.00` · 150 `␠␠␠␠␠␠0` · 157 `␠␠␠␠100` · 164 `1000` · 168 CO(3) · 171 observación(255) · 426 contacto=nombres(50) · 476 dirección(40) · 516 dir2(40) · 556 dir3(40) · 596 país(3) · 599 dpto(2) · 601 ciudad(3) · 604 barrio(40) · 644 teléfono(20) · 664 fax(20) · 684 postal(10) · 694 email(255) · 949 fecha ingreso(8) · 957 CO op(3) · 960 UN(20) · 980 EDI(4) · 984 EAN(35) · 1019 vig. cupo(8) · 1027 `0000.00` · 1034 `00` · 1036 motivo(3) · 1039 `VEN1` · 1043 `0` · 1044 `0`.

**0202 (723):** prefijo `0000002 0202 00 03 007 0` · 19 tercero(15) · 34 `001` · 37 `1` · 38 razón social(40) · 78 `COP` · 81 `PVAC` · 85 `C30` · 88 `0␠␠` · 91 `+000000000000000.0000` · 112 `015␠` · 116 `1` · 117 notas(255) · 372 contacto=razón social(50) · 422 dirección(40) · 462 dir2(40) · 502 dir3(40) · 542 país(3) · 545 dpto(2) · 547 ciudad(3) · 550 barrio(40) · 590 teléfono=celular(20) · 610 fax(20) · 630 postal(10) · 640 email(50) · 690 fecha ingreso(8) · 698 `000.00` · 704 `0000000000000.00` · 720 `000`.

**0046 / 0047 / 0049 (46 c/u):** prefijo (`0000002 0046 00 01 007 1`, `0000003 0047 …`, `0000002 0049 …`) · 19 tercero(15) · 34 sufijo fijo `0011␠␠1␠IV19` (0046 y 0049) o `00180␠1␠9001` (0047).

**0634 (839):** prefijo `0000002 0634 00 01 007 0` · 19 tercero(15) · 34 `001` · 37 `1` · 38 banco(10) · 48 cuenta(30) · 78 tipo cuenta(1) · 79 formato(8) · 87 `1` · 88 `1` · 89 dato_01…dato_15 (50 c/u).
- Formato 41 (Bancolombia PAB): 01 NIT · 02 tipo ident Bancolombia (C→1, E→2, N→3) · 03 `000000000` · 04 cuenta · 05 `6` · 06 `10` · 07 vacío · 08 `302`.
- Formato 7 (Banco de Bogotá): 01 tipo ident (C/N/E) · 02 NIT · 03 `1` · 04 cuenta · 05 `001` · 06 `1` · 07 `N` · 08 vacío · 09 `7000`.

Las constantes de negocio se dejaron tal como estaban: vendedor `VEN1`,
condición de pago `C00`/`C30`, clase de proveedor `PVAC`, tipo de proveedor
`015`, `Indicador_Bloqueado=1` en el 0201, los sufijos de impuestos y los datos
fijos de los formatos.

## 7. Pruebas

- `tests/fixtures/siesa/*.txt` (golden). Se generaron con un script aislado que
  copia **literalmente** el armado con `str_pad` de cada método viejo, con datos
  ficticios ASCII. Los valores fijos con bug se inyectaron como variables
  (fechas, banco/cuenta, NIT en los datos 0634). No se llamó al controlador viejo
  ni a SIESA.
- `tests/Unit/RegistroClienteSiesaTest.php` (11 pruebas):
  - golden idéntico para los 7 pasos;
  - el mismo golden con minúsculas, tildes y espacios extra;
  - longitudes totales (905/1045/723/46/839) que no cambian con tildes, ñ,
    textos de 200+ caracteres, un solo apellido o nulos, y solo ASCII imprimible;
  - posiciones clave de 0200, 0201, 0202 y 0634;
  - NIT sin DV en todos los registros para `900508834-2`;
  - `nitDv`, apellidos con partículas, normalización y `campo` con multibyte;
  - estados de los pasos (`bloqueado` por fecha o dependencia, `omitido` sin
    cuenta, `listo`, `hecho`, DV inválido, tipo sin homologar);
  - XML con clave enmascarada, fin `0000003`/`0000004`, escape de `&`/`<` y XML
    que se puede parsear;
  - `interpretar()` con éxito, con errores (diffgram) y con respuestas inválidas,
    sin excepciones.
- `tests/Feature/SiesaEnvioDeshabilitadoTest.php`: sin la variable
  `SIESA_ENVIO_HABILITADO` (se quita de getenv, `$_ENV` y `$_SERVER` y se
  restaura al final), `config/services.php` da `envio_habilitado = false`. Con el
  interruptor apagado, `enviar()` devuelve el mensaje sin llamar a curl; `/siesa-ejecutar-paso`
  responde 422 con el mensaje y valida los parámetros.
- Prueba de humo de solo lectura (scratchpad): los 4 endpoints se invocaron
  directamente sobre el controlador, contra `ArarFinanciera_PRUEBAS` como
  conexión por defecto y con SELECT a FactoringManagerDatos/UNOEEARAR. Un guardia
  de `DB::listen` rechazaba cualquier sentencia que no fuera SELECT/WITH. Los
  resultados están en la §4. La vista previa enmascara la clave y ejecutar
  respondió 422.

## 8. Puesta en marcha

1. **Quién lo habilita:** solo el administrador del servidor (infraestructura o
   coordinación de desarrollo), editando `project/.env`, con autorización
   escrita de contabilidad/tesorería. Nunca desde la aplicación.
2. **Primero en el ambiente de pruebas de SIESA:**
   - `SIESA_WS_CONEXION=<conexión de pruebas>` y, si aplica, `SIESA_WS_URL`;
   - `SIESA_ENVIO_HABILITADO=true`;
   - `php artisan config:clear` (o `config:cache`).
3. **Qué validar en pruebas, con 2 o 3 clientes reales (uno CC, uno NIT y, si
   existe, uno con cuenta en `CuentasTerceros`):**
   - Usar `/siesa-vista-previa` y revisar los registros paso por paso.
   - Ejecutar en orden: tercero → cliente → proveedor → impuestos cliente →
     impuestos proveedor → pagos.
   - Después de cada paso, revisar t200, t201, t202, t046/t047, t049 y t633/t634,
     y confirmar que `/siesa-validar` lo marca `hecho`.
   - Confirmar el valor de `printTipoError` en caso de éxito (se toma como éxito
     todo lo distinto de `1`, igual que el código anterior, siempre que no haya
     filas `Table` con `f_detalle`).
   - Confirmar la homologación del tipo de cuenta (1 ↔ corriente, 2 ↔ ahorros),
     el código de tipo de identificación de Bancolombia (1/2/3) y los datos fijos
     de los formatos 41 y 7.
   - Confirmar que SIESA acepta para NIT (N) apellidos y nombres vacíos con
     `tipo tercero = 2`.
   - Revisar `Indicador_Bloqueado=1` del 0201: el cliente se crea bloqueado,
     como antes.
4. **Producción:** solo cuando lo anterior esté aprobado. Se cambia
   `SIESA_WS_CONEXION=Real`, se deja `SIESA_ENVIO_HABILITADO=true` y se limpia la
   caché de configuración. Para volver a modo solo vista previa, basta con
   `false` + `config:clear`.

## 9. Bugs corregidos

1. El NIT se recibía sin validar. Ahora `idCliente` se valida como string
   (`^[A-Za-z0-9-]{1,20}$`) y se acepta también si llega como número.
2. Quitar el DV era inconsistente (proveedor y pagos usaban `IdCliente` con DV).
   Ahora hay una sola función, `nitDv()`, para todos los registros.
3. Los apellidos se separaban y luego se descartaban, y `$apellidos[1]` lanzaba
   ErrorException con un solo apellido. Ahora el separado es real y no falla.
4. No se reemplazaba ñ por n. Ahora se reemplaza (y se quitan las tildes).
5. `str_pad` contaba bytes y no recortaba. Ahora se usa `mb_*`, con recorte y
   relleno por caracteres.
6. `Fecha_Nacimiento = date('Ymd')`. Ahora se usa `FecNacimiento`; si falta, el
   paso queda bloqueado.
7. Las fechas de ingreso eran fijas (`20160601`/`20160101`). Ahora se usa
   `FecAperturaCliente`.
8. Banco `01` y cuenta `1111111111` de relleno (en SIESA hay 1855 cuentas con
   ese número), y NIT fijo `63555656`/`10000000000` en los datos 0634. Ahora se
   usan la cuenta real y el NIT real; si no hay cuenta, el paso se omite.
9. Variables cruzadas `$cliente`/`$cliente1` en `creacionProveedor`. Se resolvió
   con el flujo único.
10. `if(($LoadClientes) > 0)` sin `count`. Se eliminó con el flujo nuevo.
11. `ValidarProveedor` se calculaba y no se usaba en los pagos. Ahora los pagos
    dependen de que el proveedor exista en SIESA.
12. `procesarPeticionSiesa` hacía `echo`/`exit` y usaba simplexml sin validar.
    Ahora `ClienteSoapSiesa` devuelve `{ok, detalle, errores}` y valida el XML.
    Si falla la conexión, el usuario solo ve «No fue posible conectar con SIESA.
    Intente de nuevo o contacte al administrador.». El detalle técnico (error
    de curl y código HTTP, sin credenciales) va al log como `warning`
    («SIESA ImportarXML sin conexión»).
13. `LoadClientes('')` traía todos los clientes. Ahora devuelve `[]`, y el
    listado es paginado (`listadoClientesSiesa`).
14. Con `LoadClientes` vacío no había respuesta. Ahora responde 422 «El cliente
    no existe en FactoringManager.».
15. Los clientes sin ciudad en `CiudadesAct` se perdían por el INNER JOIN. Ahora
    se usa LEFT JOIN y quedan bloqueados con su motivo.
16. Los caracteres `&`/`<` rompían el XML. Ahora se escapan.
17. Tipo de identificación fijo `C`. Ahora se homologa desde
    `TipoIdentificacionCliente`.

## 10. Pendientes

- La UI nueva (Frontend) con este contrato. Mientras tanto, la pantalla actual
  queda sin acción (404 en las rutas viejas).
- Validar en el ambiente de pruebas de SIESA los supuestos de la §8.3.
- Homologar en SIESA los tipos TI (3) y RUT (5) si se necesitan.
- Cargar cuentas bancarias reales en `CuentasTerceros` si se quieren crear los
  pagos electrónicos.
- `Terceros::ValidarTerceros`, `ValidarCliente` y `ValidarProveedor` quedaron
  sin uso; no se eliminaron para no salir del alcance.
