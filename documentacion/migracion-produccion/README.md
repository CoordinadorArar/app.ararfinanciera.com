# Migración a producción: ArarFinanciera_PRUEBAS → ArarFinanciera

Guía para el DBA o desarrollador que ejecutará la migración. Motor: **SQL Server 2014** (sin `CREATE OR ALTER`, `DROP ... IF EXISTS` ni JSON; los scripts respetan esa restricción).

## 1. Objetivo

Llevar a la base de producción `ArarFinanciera` el **esquema** y la **configuración mínima** que se construyeron y validaron en `ArarFinanciera_PRUEBAS` para:

- **Deterioro de Cartera**: fases 1 a 7b, cuotas repetidas, soporte adjunto, atribución por notas, prórroga y vencido SIESA.
- **Crédito**: fases 1 a 4 (las fases 5 y 6 no tienen tablas nuevas).

Todo en archivos `.sql` numerados que se ejecutan en orden. Son idempotentes (se pueden repetir) y transaccionales (si fallan, no dejan nada a medias). Cada uno aborta si la base no es `ArarFinanciera`.

## 2. Alcance

### Qué se migra

| Grupo | Contenido |
|---|---|
| Esquema deterioro | 30 tablas `det_*` (14 de la fase 1 y 16 nuevas) y columnas nuevas en las existentes. Ninguna tiene llaves foráneas. |
| Semillas deterioro | Paramétricas (rangos de mora, productos, interés de mora, convención, fiscal), 5 causales de suspensión y 4 causales de salida. |
| Esquema crédito | Columnas en `Pagadurias`, `Procesos` y `TratamientoDatos`; índice único en `Terceros`; tablas `PagaduriasReglasEdad`, `ConfiguracionAuditoria`, `MotivosRechazo`, `ProcesosHistorial`, `ProcesosDocumentos`, `ConsultasCentrales` y `ConfiguracionCentrales`, con sus FKs. |
| Datos derivados crédito | Backfills que ya hacen las migraciones: `TratamientoDatos.IdProceso`, historial inicial por proceso, documentos por proceso, reglas de edad por pagaduría, 6 motivos de rechazo y 4 claves de centrales. |
| Menú y permisos | Menú *Deterioro Cartera*, 18 submenús y 52 permisos (roles 1 Administrador, 2 Gerente y 7 Contador). Solo **Cortes** queda visible. |
| Registro Laravel | Las 19 migraciones de 2026 se registran en `migrations` para que un `php artisan migrate` futuro no intente recrearlas. |

### Qué NO se migra

- **Datos operativos de pruebas**: cortes, snapshots, resultados, cuadres, cierres, conciliaciones y sus explicaciones, clasificación de bajas, bitácora, cifras de validación del Excel y las marcas de suspensión cargadas en pruebas (259 del cargue inicial y 21 sin facturación). Producción genera los suyos.
- `deterioro-marcas-sin-facturacion-siesa-agosto-2026.sql`: es una carga operativa con guarda de pruebas (ver sección 9).
- `SQL_CONSULTA_SALDOS_SIESA.sql`: es una consulta de solo lectura contra SIESA (UNOEEARAR), no esquema.
- Valores **ajustados a mano** en pruebas (por ejemplo, un parámetro o una regla de edad editados por pantalla). Se siembran los valores del repositorio; `98-comparar-configuracion-con-pruebas.sql` lista las diferencias para que negocio decida.
- `Modulos_Faico` y `UNOEEARAR`: ningún script escribe en ellas. La aplicación las **lee** en tiempo de ejecución (sección 8).
- El código de la aplicación, que se despliega aparte (sección 8).

### Estado de partida conocido de producción

Según la documentación de cada fase, se espera lo siguiente; el paso 00 lo confirma antes de empezar:

- Las 14 tablas de la **fase 1** de deterioro existirían ya en `ArarFinanciera`, posiblemente con cortes calculados (fuente: `documentacion/deterioro-fase1-validacion.md:151`). En ese caso los pasos 01 y 02 las encontrarán y no harán nada.
- El seeder de menú habría corrido ya en producción en la fase 7a, dejando 17 submenús y 49 permisos (fuente: `documentacion/deterioro-fase7-validacion.md:136`). En ese caso el paso 22 solo agregará lo que falte: se espera `/deterioro-accion-exportar` y sus 3 permisos.
- No debería haber nada de las fases 2 a 7b, de las mejoras posteriores ni del módulo de crédito.

## 3. Prerrequisitos

1. **Respaldo completo de `ArarFinanciera`** inmediatamente antes de empezar, y verificado:

   ```sql
   -- <RUTA_RESPALDO> es un ejemplo: una carpeta con espacio en el servidor SQL
   -- (la ruta es local al servidor de base de datos, no al equipo desde el que ejecuta).
   BACKUP DATABASE [ArarFinanciera]
   TO DISK = N'<RUTA_RESPALDO>\ArarFinanciera_pre_migracion_AAAAMMDD.bak'
   WITH COPY_ONLY, INIT, CHECKSUM, STATS = 10,
        NAME = N'ArarFinanciera antes de migracion deterioro y credito';

   RESTORE VERIFYONLY
   FROM DISK = N'<RUTA_RESPALDO>\ArarFinanciera_pre_migracion_AAAAMMDD.bak'
   WITH CHECKSUM;
   ```

   `COPY_ONLY` evita alterar la cadena de respaldos diferenciales existente. Reemplace `<RUTA_RESPALDO>` por la ruta de respaldo del servidor SQL y `AAAAMMDD` por la fecha.

2. **Ventana de mantenimiento** con la aplicación detenida (o en modo mantenimiento: `php artisan down`). Los pasos 18 a 21 alteran `Pagadurias`, `Procesos`, `TratamientoDatos` y `Terceros`, que la aplicación usa todo el tiempo, y toman bloqueo de esquema sobre ellas.

3. **Usuario con permisos DDL** en `ArarFinanciera` (`db_owner` o `db_ddladmin` + `db_datawriter` + `db_datareader`). **No use el usuario de la aplicación (`consultaweb`)**: no debe tener DDL (pendiente de seguridad de la fase 1). El paso 00 muestra los permisos del usuario que ejecuta.

4. **Permisos del usuario de la aplicación sobre las tablas nuevas.** Si `consultaweb` tiene `db_datareader`/`db_datawriter` (o permisos a nivel de esquema `dbo`), no hay que hacer nada. Si sus permisos son por tabla, hay que concederle `SELECT, INSERT, UPDATE, DELETE` sobre las tablas nuevas de la sección 2.

5. **Herramienta**: SSMS o `sqlcmd` 13+. Con `sqlcmd` son obligatorias las opciones `-I` (activa `QUOTED_IDENTIFIER`, necesaria para el índice filtrado del paso 06) y `-f 65001` (los archivos con tildes están en UTF-8 con BOM). Todos los scripts fijan además las opciones `SET` necesarias al inicio.

6. El código de la rama a desplegar, listo para subir en la misma ventana.

## 4. Orden de ejecución

| # | Archivo | Qué hace | Origen | Tiempo / impacto | Cómo verificar |
|---|---|---|---|---|---|
| 00 | `00-verificacion-previa.sql` | Solo lectura: entorno, permisos, respaldo, tablas base, objetos que ya existen, `migrations`, menú, bases externas | Nuevo | Segundos, sin bloqueos | Criterios de la sección 5 |
| 01 | `01-deterioro-fase1-esquema.sql` | 14 tablas de la fase 1 (solo si faltan) | **Generado** de las 3 migraciones `2026_08_31_*` | Nulo si ya existen | `PRINT` final; paso 99 |
| 02 | `02-deterioro-fase1-semilla-parametros.sql` | Rangos, productos, interés de mora y convención (solo si la tabla está vacía) | **Generado** de `DeterioroParametrosSeeder` | Nulo si ya hay filas | Paso 99, tipo SEMILLA |
| 03 | `03-deterioro-fase2-fiscal.sql` | Fiscal: 5 tablas, 6 columnas, amplía `det_corte_cuadre.codigo` a 20 (recrea su PK) y siembra el método fiscal | `deterioro-fase2-ddl.sql` | Segundos; reconstruye la PK de `det_corte_cuadre` (tabla pequeña) | `PRINT 'Fase 2 aplicada'` |
| 04 | `04-deterioro-fase3-diferido.sql` | 4 columnas de impuesto diferido | `deterioro-fase3-ddl.sql` | Inmediato (columnas nulas) | `PRINT 'Fase 3 aplicada'` |
| 05 | `05-deterioro-fase4-historico.sql` | 3 columnas de movimiento; `motivo` e `informativo` en cuadres | `deterioro-fase4-ddl.sql` | Segundos | `PRINT 'Fase 4 aplicada'` |
| 06 | `06-deterioro-fase5-suspension.sql` | Causales, marcas de suspensión (con índice único filtrado) y congelamiento | `deterioro-fase5-ddl.sql` | Segundos | `PRINT 'Fase 5a aplicada'` |
| 07 | `07-deterioro-fase5-semilla-causales-suspension.sql` | Causales FALLECIMIENTO, INSOLVENCIA y COBRO_JURIDICO | **Generado** de `DeterioroCausalSuspensionSeeder` | Inmediato | `PRINT 'Paso 07'` |
| 08 | `08-deterioro-causal-fin-cuotas.sql` | Causales FIN_CUOTAS y SIN_DETERMINAR (inactiva) | `deterioro-causal-fin-cuotas-ddl.sql` | Inmediato | Sus dos consultas finales: 5 causales; 0 marcas huérfanas |
| 09 | `09-deterioro-fase6-siesa.sql` | Snapshot SIESA, conciliación y `origen_base` | `deterioro-fase6-ddl.sql` | Segundos | `PRINT 'Fase 6a aplicada'` |
| 10 | `10-deterioro-fase7-cierre.sql` | Causales de salida, salidas y estado de cierre del corte | `deterioro-fase7-ddl.sql` | Segundos | `PRINT 'Fase 7a aplicada'` |
| 11 | `11-deterioro-fase7-semilla-causales-salida.sql` | 4 causales de salida | **Generado** de `DeterioroCausalSalidaSeeder` | Inmediato | `PRINT 'Paso 11'` |
| 12 | `12-deterioro-fase7b-cuentas-contables.sql` | Cuentas del asiento (vacías) y `cuentas_congeladas` | `deterioro-fase7b-ddl.sql` | Segundos | `PRINT 'Fase 7b aplicada'` |
| 13 | `13-deterioro-soportes-suspension.sql` | `soporte_nombre` y `soporte_tipo` | `deterioro-soportes-ddl.sql` | Inmediato | `PRINT 'Soportes...'` |
| 14 | `14-deterioro-cuotas-duplicadas.sql` | `duplicada_de` en el detalle de cuotas | `deterioro-cuotas-duplicadas-ddl.sql` | Inmediato | `PRINT 'Cuotas repetidas...'` |
| 15 | `15-deterioro-atribucion-nota-siesa.sql` | Atribución SIESA por notas: 5 columnas y 1 índice | **Generado** de `2026_09_18_100000_add_atribucion_nota_a_det_siesa` | Inmediato | `PRINT 'Paso 15'` |
| 16 | `16-deterioro-prorroga.sql` | `interes_prorroga_siesa` | `deterioro-prorroga-ddl.sql` | Inmediato | Sus 4 consultas finales (verificación 1: la columna existe) |
| 17 | `17-deterioro-vencido-siesa.sql` | Vencido según SIESA (informativo) | `deterioro-vencido-siesa-ddl.sql` | Inmediato | Su consulta final: 3 columnas |
| 18 | `18-credito-fase1-pagadurias.sql` | Regla SMMLV en `Pagadurias`, reglas de edad, auditoría de configuración | `credito-fase1-ddl.sql` | Segundos; **bloquea `Pagadurias`** | Paso 99 |
| 19 | `19-credito-fase2-registro.sql` | Correo de tratamiento en `Procesos`, `TratamientoDatos.IdProceso` (+ backfill), índice único de `Terceros.DocumentoTercero` | `credito-fase2-ddl.sql` | Proporcional a `Procesos`; **bloquea `Procesos`, `TratamientoDatos` y `Terceros`** | Si imprime «Hay documentos duplicados…», ver sección 10 |
| 20 | `20-credito-fase3-procesos.sql` | Motivos de rechazo, historial y documentos por proceso (+ backfills) | `credito-fase3-ddl.sql` | Proporcional a `Procesos` | Paso 99 (BACKFILL) |
| 21 | `21-credito-fase4-centrales.sql` | Consultas y configuración de centrales | `credito-fase4-ddl.sql` | Segundos | Paso 99 |
| 22 | `22-deterioro-menu-permisos.sql` | Menú, submenús y permisos que falten | **Generado** de `DeterioroMenuSeeder` | Inmediato | Mensaje con submenús/permisos nuevos y listado final |
| 23 | `23-deterioro-menu-visibilidad.sql` | Deja visible solo *Cortes* | `deterioro-fase7-visibilidad.sql` | Inmediato | Su listado final: una sola fila con estado 1 |
| 24 | `24-registrar-migraciones-laravel.sql` | Registra en `migrations` las 19 migraciones de 2026 que falten y cuyo objeto exista | Nuevo | Inmediato | Su listado: 19 `REGISTRADA` |
| 99 | `99-verificacion-final.sql` | Solo lectura: columnas, índices, FKs, semillas, menú, registro y codificación → OK / FALTA / AVISO | Nuevo | Segundos | `MIGRACION COMPLETA` y 0 `FALTA`. Si la tabla `migrations` no existe, su comprobación sale como `AVISO` (no `FALTA`): ver sección 12, punto 4 |
| 98 | `98-comparar-configuracion-con-pruebas.sql` | Opcional, solo lectura: diferencias de configuración entre pruebas y producción | Nuevo | Segundos | Revisión por negocio |

Tiempo total esperado: menos de 10 minutos de base de datos. Las columnas nuevas son nulas o con default y las tablas `det_*` son pequeñas (miles de filas por corte). El orden sigue la fecha de las migraciones y respeta las dependencias: 03 requiere 01; 06 antes de 07, 08 y 13; 09 antes de 15, 15 antes de 16 y 16 antes de 17; 10 antes de 11; 19 antes de 20; las FKs de 18, 20 y 21 apuntan a tablas base que ya existen o se crean en el mismo paso.

## 5. Paso 00: criterios para continuar

No siga si alguno falla:

- Sección 1: `base = ArarFinanciera`, versión 12.x (SQL Server 2014) y el usuario con `es_db_owner = 1` o `es_db_ddladmin = 1`.
- Sección 2: hay un respaldo completo de hoy (o confírmelo por otro medio si `msdb` no es legible).
- Sección 3: todas las tablas base `EXISTE`. La única excepción admitida es `migrations`: si falta, se puede continuar, pero el paso 24 no registrará nada y el paso 99 lo mostrará como `AVISO`; decídalo antes (sección 12, punto 4).
- Sección 4: `Pagadurias.IdPagaduria`, `Procesos.IdProceso`, `Terceros.IdTercero` y `DocumentosSolicitados.IdDocumentoSolicitado` son `bigint` y PK/únicas (`es_pk_o_unica = 1`). Si no, las FKs de 18, 20 y 21 fallan.
- Sección 5: las pagadurías **6 y 8** son FIDUPREVISORA y FUERZA AÉREA (el paso 18 les activa la regla SMMLV por ID). Anote si hay `DocumentoTercero` duplicados.
- Sección 6: anote qué tablas ya existen. Lo esperado: las 14 de la fase 1 y nada más. Si aparecen tablas de fases posteriores, alguien aplicó algo antes: los scripts lo toleran, pero repórtelo.
- Sección 9: `Modulos_Faico` existe y tiene `ResumenVigentesClientes`.

## 6. Cómo ejecutar

### Con SSMS

Conéctese con el usuario DDL, abra cada archivo en orden, confirme que la base activa es `ArarFinanciera` y ejecute (F5). Revise la pestaña *Mensajes*: debe terminar sin errores rojos y con el `PRINT` de éxito. **SSMS sigue ejecutando los lotes siguientes aunque uno falle**: si aparece un error, deténgase y no abra el siguiente archivo.

### Con sqlcmd (recomendado: deja registro y se detiene ante el primer error)

Copie la carpeta `migracion-produccion` completa al equipo Windows desde el que ejecutará `sqlcmd` (con acceso al servidor SQL). En los ejemplos, `<CARPETA_SCRIPTS>` es esa carpeta.

1. Paso 00, por separado:

   ```bat
   cd /d <CARPETA_SCRIPTS>
   sqlcmd -S SERVIDOR -d ArarFinanciera -E -I -b -f 65001 -i 00-verificacion-previa.sql -o 00-salida.txt
   ```

   Revise `00-salida.txt` con los criterios de la sección 5.

2. Pasos 01 a 24 y 99: **ejecute el archivo `ejecutar-migracion.bat`** de la misma carpeta. No pegue el bucle en la consola.

   ```bat
   cd /d <CARPETA_SCRIPTS>
   ejecutar-migracion.bat SERVIDOR
   rem con autenticacion SQL:
   ejecutar-migracion.bat SERVIDOR usuario clave
   ```

   El `.bat` ejecuta cada paso con `sqlcmd -I -b -f 65001`, guarda la salida en `<paso>-salida.txt` y, si un paso termina con código de error distinto de 0, **se detiene**, indica qué paso falló y no ejecuta ninguno posterior. Termina con código 0 solo si todos los pasos terminaron sin error.

- `-b` hace que `sqlcmd` devuelva código de error ante cualquier error del script: es lo que permite al `.bat` detenerse en el paso que falla.
- `-I` y `-f 65001` son obligatorias (sección 3).
- Guarde todos los `*-salida.txt` como evidencia.

## 7. Si falla un paso

1. **No continúe con el siguiente.** Cada paso es una transacción: si falló, no dejó cambios. La mayoría de los pasos lo indican con el mensaje «revertido: no se aplicó ningún cambio». Los pasos 17, 18 a 21 (crédito) y 23 no imprimen ese mensaje y solo muestran el error original (`THROW`), pero también revierten su transacción completa.
2. Lea el error del archivo de salida. Causas probables:
   - Base equivocada (guarda): conéctese a `ArarFinanciera`.
   - «Falta …: aplique antes el paso NN»: se saltó un paso.
   - Error 1934 (opciones SET): faltó `-I` en `sqlcmd`.
   - Permisos: el usuario no tiene DDL.
   - FK que no se puede crear: tipo o PK de la tabla base distintos de lo esperado (paso 00, sección 4).
3. Corrija la causa y **vuelva a ejecutar el mismo paso**: son idempotentes.
4. Si no se puede corregir dentro de la ventana: ejecute los rollback de los pasos ya aplicados (sección 11) o restaure el respaldo, y deje la aplicación con el código anterior.

## 8. Pasos posteriores en la aplicación

En la misma ventana, después del paso 99 sin `FALTA`:

1. **Desplegar el código** de la rama aprobada. Orden obligatorio: primero esquema y después código. Con el código nuevo y el esquema viejo, las rutas fallan con error SQL.
2. **`.env` de producción** (no copiar el de desarrollo):
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.ararfinanciera.com`.
   - `DB_DATABASE=ArarFinanciera` y `DB_FAICO_DATABASE=Modulos_Faico`. No usar `ArarFinanciera_PRUEBAS` ni `Modulos_Faico_prueba` en estas dos variables: esas bases solo deben venir de `DB_DATABASE_DEMO` y `DB_FAICO_DEMO_DATABASE`.
   - `config/database.php` deja `'ambiente' => 'produccion'` por defecto. El ambiente demo es una conmutación **por sesión** que solo pueden hacer los roles de `roles_ambiente` (hoy el 1). Al terminar, verifique en la aplicación que la sesión de cada administrador esté en Producción.
   - `SIESA_ENVIO_HABILITADO=false` hasta tener la autorización escrita (crédito fase 5).
   - `CENTRALES_SIMULADO` vacío o `false`. Las credenciales de TransUnion y DataCrédito van solo en `.env` (crédito fase 4).
   - Variables de las conexiones `unoeearar` (SIESA, solo lectura) y `faico`.
   - **Verificación obligatoria** en el servidor de la aplicación, antes de abrirla:

     ```bash
     cd /var/www/app.ararfinanciera.com/www/project
     grep -E '^(DB_DATABASE|DB_FAICO_DATABASE|APP_ENV|APP_DEBUG)=' .env
     ```

     Debe mostrar `DB_DATABASE=ArarFinanciera` y `DB_FAICO_DATABASE=Modulos_Faico` (nunca `ArarFinanciera_PRUEBAS` ni `Modulos_Faico_prueba`), `APP_ENV=production` y `APP_DEBUG=false`. Confirme además que `config/database.php` desplegado conserva `'ambiente' => 'produccion'`, es decir, que la aplicación no arranca en ambiente demo.
3. **Limpiar cachés.** El servidor de la aplicación es Ubuntu + nginx + PHP-FPM; la aplicación está en `/var/www/app.ararfinanciera.com/www/project`, los archivos son del usuario de despliegue `web` y PHP-FPM corre con el grupo `www-data`. Ejecute `artisan` **como el dueño de los archivos** (`web`), nunca como `root`, para que lo que se regenere en `storage/framework` y `bootstrap/cache` no quede con otro dueño:

   ```bash
   cd /var/www/app.ararfinanciera.com/www/project
   sudo -u web php artisan config:clear
   sudo -u web php artisan view:clear
   ```

   (Si ya está conectado como `web`, omita `sudo -u web`.) Si el servidor usa `config:cache` o `route:cache`, regenérelos después con el mismo usuario.
4. **Permisos de escritura del grupo `www-data`** sobre `storage/` y `bootstrap/cache/`. Es el procedimiento probado el 2026-10-08 tras un error 500 causado por vistas compiladas sin permiso de escritura:

   ```bash
   cd /var/www/app.ararfinanciera.com/www/project
   chmod -R g+rwX storage bootstrap/cache
   find storage bootstrap/cache -type d -exec chmod g+s {} +
   ```

   Lo mismo aplica a `project/soportes-suspension/`, donde PHP guarda los adjuntos de suspensión. Esas carpetas deben tener grupo `www-data`. En nginx el `.htaccess` no tiene efecto: **verifique en la configuración del sitio de nginx** que la raíz pública no exponga `project/` (incluidas `project/soportes-suspension/` y `project/storage/`) ni el archivo `.env`. Por ejemplo, `curl -I https://app.ararfinanciera.com/project/.env` debe responder 403 o 404, nunca 200.
5. **Extensión SOAP** (centrales TransUnion): instale el paquete `php-soap` de la versión de PHP instalada y reinicie PHP-FPM:

   ```bash
   php -v                                   # identifica la versión X.Y
   sudo apt-get install phpX.Y-soap
   sudo systemctl restart phpX.Y-fpm
   php -m | grep -i soap                    # debe mostrar "soap"
   ```
6. `sudo -u web php artisan up` y prueba de humo: menú *Deterioro Cartera → Cortes* con un usuario de rol 1, 2 o 7; un usuario de otro rol debe recibir 403; listado de procesos de crédito; Administración del sitio → configuración de pagadurías y centrales.
7. **Nunca ejecutar `php artisan migrate:rollback`** en producción: el paso 24 registra las migraciones en un solo batch, y `rollback` ejecutaría todos sus `down()`, que son destructivos.

## 9. Cargas operativas posteriores (opcionales, fuera de la ventana de esquema)

| Carga | Cómo | Prerrequisito |
|---|---|---|
| **Acumulado fiscal 1399** (obligatorio antes de calcular el primer corte con el motor nuevo) | `php artisan deterioro:cargar-acumulado-fiscal <csv> --origen=EXCEL_1399 --ambiente=produccion` con el CSV del bloque A (en pruebas: `1399-bloqueA-2025.csv`, 238 filas) | Paso 03. Sin él, el tope fiscal opera «por vacuidad» y el diferido sale sobrestimado (deterioro-fase2/3-validacion.md). El paso 99 lo marca como AVISO mientras falte. |
| Marcas de suspensión del cargue inicial (D-14) | `php artisan deterioro:cargar-suspensiones <csv> --ambiente=produccion [--simular]` con la plantilla (`plantilla-suspensiones-intereses.csv`, ver su instructivo) | Pasos 06 a 08 y al menos un corte calculado en producción (de él se resuelve `existe_en_factoring`). |
| 21 marcas sin facturación SIESA (agosto 2026) | El script `deterioro-marcas-sin-facturacion-siesa-agosto-2026.sql` tiene guarda de **pruebas** y no debe adaptarse a ciegas: aborta si alguna operación ya tiene facturación. El camino normal es el comando anterior con su CSV. **El CSV que cita (`suspensiones-sin-facturacion-siesa-agosto-2026.csv`) no está en `documentacion/`**. | Lo mismo que la fila anterior, y revalidar en SIESA que las 21 siguen sin facturación. |
| Recongelar suspensiones | `php artisan deterioro:recongelar-suspensiones --ambiente=produccion --simular` y luego sin `--simular` | Marcas cargadas. |
| Cifras del libro para validación | `php artisan deterioro:cargar-validacion-excel {idCorte} <archivo> --hoja=DETERIORO --ambiente=produccion` | Solo si se quiere repetir la validación contra el Excel en producción. |
| Recalcular cortes | Desde la pantalla *Cortes* | Los cortes de producción se calcularon con el motor de la fase 1: hasta recalcularlos no tienen cifras fiscales, diferido, SIESA ni cuadres nuevos. Un corte que se marque CERRADO ya no se recalcula. |
| `SQL_CONSULTA_SALDOS_SIESA.sql` | Consulta de solo lectura contra UNOEEARAR | Ninguno. No es parte de la migración. |

Los comandos `artisan` usan la conexión de la aplicación: ejecútelos en el servidor de producción con el `.env` de producción y `--ambiente=produccion`.

## 10. Datos de configuración que negocio debe revisar

Antes de habilitar la aplicación (o inmediatamente después, antes del primer uso), ejecute `98-comparar-configuracion-con-pruebas.sql` y revise con negocio:

| Tema | Valor sembrado | Quién decide |
|---|---|---|
| Rangos de mora y % contable | A 0 % · B 8 % · C 23 % · D 53 % · E 78 % · F 100 % (vigentes desde 2016-01-01) | Contabilidad |
| Interés de mora | 2,33 % mensual; no aplica a FACTORING | Contabilidad |
| Convención | Base 360 días, mora desde `FEC_INICIAL_MORA`, base con interés, SIESA no manda sobre la base, tarifa de renta 35 % | Contabilidad |
| Fiscal | Individual 33 % anual desde 361 días (activo); general 5/10/15 % (inactivo) | Contabilidad / Tributaria |
| Cuentas del asiento | **Vacía por diseño**: el exportable del asiento se rechaza hasta que Contabilidad defina las cuentas (los otros exportables funcionan) | Contabilidad |
| Causales | 5 de suspensión (SIN_DETERMINAR inactiva) y 4 de salida | Contabilidad |
| **Reglas de edad por pagaduría** | 18–70 → 120 meses y seguro 0,003; 71–74 → 48 meses y 0,003; 75–99 → 48 meses y 0,005625, para **todas** las pagadurías que no tengan reglas. **El simulador anterior usaba 96/60: negocio debe confirmar 120/48 antes de originar créditos.** | Crédito / Riesgo |
| Regla SMMLV | `UsaReglaSMMLV = 1` solo en las pagadurías 6 y 8; umbral 2 SMMLV en todas. **Es solo un valor inicial** (los IDs fijos del código anterior): negocio debe confirmarlo, o corregirlo, en la pantalla de pagadurías | Crédito |
| Centrales | Predeterminado TransUnion, vigencia 30 días, ambos proveedores habilitados | Crédito |
| Motivos de rechazo | Los 6 del formulario anterior; el 1 («No tiene cupo») es el del rechazo automático | Crédito |
| Permisos de deterioro | Roles 1, 2 y 7; forzar cierre y reabrir solo 1 y 2 | Gerencia |

Cualquier ajuste se hace por la pantalla de administración o insertando una vigencia nueva en la paramétrica, nunca editando las filas sembradas con historia.

## 11. Rollback

La carpeta `rollback/` tiene los bloques de reversión de cada script, en **orden inverso** (R01 deshace el paso 24, …, R19 los pasos 01 y 02), con la guarda de producción y una advertencia en la cabecera.

- **La primera opción ante un problema serio es restaurar el respaldo** del paso de prerrequisitos (con la aplicación detenida). Es más simple y no pierde nada de lo que existía antes.
- Los rollback son destructivos: borran tablas, columnas, cuadres y datos capturados después de la migración. Cada uno exige editar `@confirmo = 1` para poder ejecutarse.
- Ejecútelos **solo para los pasos que se aplicaron** y en orden ascendente (R01, R02, …). El de un paso no aplicado falla dentro de su transacción sin cambiar nada.
- **R02 no borra** menú ni permisos (ya existían antes de esta migración): oculta el módulo del menú lateral.
- **R19 no debe ejecutarse** en este escenario: la fase 1 existía antes. El script aborta si hay cortes.
- R18 (fase 2) devuelve `det_corte_cuadre.codigo` a 10 caracteres y falla si quedan cuadres con códigos más largos de otras fases (por ejemplo `C-DUPLICADAS` y `C-DUPLICADAS-BASE`, que el rollback de cuotas repetidas no borra, igual que su `down()`).
- Después de revertir, desplegar de nuevo el código anterior y repetir la limpieza de cachés.

## 12. Puntos que requieren decisión

1. **Reglas de edad 120/48 frente al 96/60 del simulador** (sección 10).
2. **IDs 6 y 8 de la regla SMMLV**: confirmar en el paso 00 que son las mismas pagadurías en producción. `UsaReglaSMMLV = 1` para esos dos IDs es solo un valor inicial: negocio debe confirmar en la pantalla de pagadurías qué pagadurías usan la regla y con qué umbral.
3. **Duplicados en `Terceros.DocumentoTercero`**: si existen, el paso 19 no crea el índice único (no falla, solo informa) y el paso 99 lo marca como AVISO. Depurar los duplicados y repetir el paso 19.
4. **Tabla `migrations` ausente** en producción: el paso 24 no la crea. Si falta, un `php artisan migrate` intentaría ejecutar todas las migraciones del proyecto desde 2014. Decidir entre crearla y registrar todas las migraciones existentes, o prohibir `artisan migrate` en producción.
5. **Menú nuevo sin `Orden`**: si el menú *Deterioro Cartera* no existiera (no es lo esperado), el paso 22 lo crea sin `Orden`, igual que el seeder, y quedaría primero en la barra lateral. Asignarle `Orden` a mano si ocurre.
6. **Cortes existentes de producción** (fase 1): recalcularlos con el motor nuevo o empezar en el siguiente mes. Afecta cifras ya reportadas.
7. **Carga del 1399** con el CSV correcto antes del primer cálculo, y **CSV faltante** de las 21 marcas sin facturación.
8. **Ajustes hechos en pruebas** que deban replicarse (salida de 98).
