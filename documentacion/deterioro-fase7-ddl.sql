/*
 * Deterioro de Cartera - Fase 7a (cierre y reapertura de corte, controles C-1 y
 * C-2, permisos y auditoria: esquema)
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_05_100000_create_det_cierre_tables.php. El DDL reproduce el texto que
 * emite Illuminate\Database\Schema\Grammars\SqlServerGrammar (minusculas,
 * identificadores delimitados, modificadores en el orden Increment > Nullable >
 * Default) para que lo validado aqui sea exactamente lo que despues corra en
 * produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> el 1399 (det_fiscal_acumulado) -> fase 3 ->
 * fase 4 -> fase 5a -> fase 6a -> fase 7a -> DeterioroCausalSalidaSeeder ->
 * DeterioroMenuSeeder.
 *
 * Los dos seeders van al final y en la MISMA ventana que el codigo, no despues:
 *
 * - Sin DeterioroCausalSalidaSeeder no hay causales de salida, ninguna baja se
 *   puede clasificar y ningun corte con bajas se puede cerrar. El esquema no las
 *   siembra a proposito: son parametrica con vigencias y el seeder es
 *   idempotente, igual que las causales de suspension de la fase 5a.
 * - Sin DeterioroMenuSeeder los cinco permisos nuevos (clasificar, cerrar,
 *   forzarCierre, reabrir, auditar) no existen en Submenus, y el fallback
 *   deliberado de CheckDeterioroPermiso los resuelve contra /deterioro-cortes:
 *   cualquiera que pueda abrir la pantalla de Cortes podria cerrar o reabrir un
 *   corte. Es el pendiente 3 de la fase 6a, ahora con acciones mucho mas
 *   delicadas colgando de el.
 *
 * Esta fase NO despliega los controles C-4 (sincronia de marcas con factoring)
 * ni C-5 (cobertura del cargue inicial), que dependen de definiciones externas,
 * ni los exportables de la seccion 16.
 *
 * Sobre las causales de salida. La lista es parametrica y tiene que poder crecer
 * sin desplegar codigo, y con ella el tratamiento: cierra_fiscal dice que esa
 * causal cierra el deterioro fiscal acumulado en el mismo movimiento -hoy solo
 * el castigo, por el tratamiento fiscal propio que le da C-2- y pide_referencia
 * dice que la captura pregunta por la operacion nueva. Sin esas dos columnas el
 * codigo tendria que preguntar si el codigo es 'CASTIGO', que es exactamente la
 * regla escrita en duro que el modulo evita desde la fase 1.
 *
 * det_salida_operacion es la tabla que la seccion 9 llama `salida_operacion` y
 * que la fase 4 dejo pendiente. No se regenera nunca: es trabajo del usuario y
 * sobrevive al recalculo del corte, igual que det_conciliacion_explicacion, de
 * modo que limpiarCorte() no la toca y eliminarCorte() si.
 *
 * referencia_operacion_nueva es informativa y se guarda como texto a proposito:
 * D-07 dice que la operacion nueva no se vincula con la cerrada, y guardarla
 * como entero invitaria a unir por ella.
 *
 * foto_salvedad congela en JSON que estaba mal en el momento de cerrar con
 * salvedades -que controles en falla y con que cifras, cuantas partidas y de que
 * tipo, cuantas bajas-. Es la unica forma de que dentro de un anio se pueda
 * reconstruir por que se cerro asi: las tablas de origen ya no daran esos
 * numeros.
 *
 * Sobre SIESA (UNOEEARAR) este script no hace absolutamente nada.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a mitad
 * no deja el esquema partido.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- ------------------------------------------------------------------
    -- 1. Causales de la salida de la base entre cortes (C-2, D-09)
    -- ------------------------------------------------------------------

    -- Parametrica con vigencias, igual que las causales de suspension de la
    -- fase 5a. Las cuatro semillas -recaudo total, castigo, cierre con apertura
    -- de una operacion nueva y otra causa- las siembra
    -- DeterioroCausalSalidaSeeder, no este script.
    IF OBJECT_ID(N'dbo.det_param_causal_salida', N'U') IS NULL
    BEGIN
        create table [det_param_causal_salida] ([id_param] int identity primary key not null, [codigo] nvarchar(24) not null, [descripcion] nvarchar(120) not null, [activa] bit not null, [cierra_fiscal] bit not null, [pide_referencia] bit not null, [orden] tinyint not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_causal_salida_vigencia] on [det_param_causal_salida] ([vigente_desde], [vigente_hasta]);
    END

    -- ------------------------------------------------------------------
    -- 2. Copia congelada dentro del corte
    -- ------------------------------------------------------------------

    -- La escribe congelarParametros() en cada ejecucion y la borra
    -- limpiarCorte(), igual que el resto de las parametricas del corte. Los
    -- cortes calculados antes de esta fase no la tienen, y para esos la lectura
    -- se cae a la parametrica viva: sin esa salida sus bajas no se podrian
    -- clasificar y quedarian bloqueando el cierre para siempre.
    IF OBJECT_ID(N'dbo.det_corte_param_causal_salida', N'U') IS NULL
    BEGIN
        create table [det_corte_param_causal_salida] ([id_corte] int not null, [codigo] nvarchar(24) not null, [descripcion] nvarchar(120) not null, [activa] bit not null, [cierra_fiscal] bit not null, [pide_referencia] bit not null, [orden] tinyint not null);
        alter table [det_corte_param_causal_salida] add constraint [pk_det_corte_param_causal_salida] primary key ([id_corte], [codigo]);
    END

    -- ------------------------------------------------------------------
    -- 3. Salidas de la base entre cortes (C-2)
    -- ------------------------------------------------------------------

    -- La llave es (id_corte, id_operacion) porque la baja pertenece al corte en
    -- que se detecta: la misma operacion puede salir, volver y salir otra vez.
    --
    -- Los tres importes se congelan al clasificar y no se recalculan al leer. El
    -- corte anterior es inmutable, asi que hoy darian lo mismo; se guardan
    -- porque en el castigo son el asiento que cierra el deterioro acumulado en
    -- el mismo movimiento, y un asiento no es una consulta que se rehace.
    -- fiscal_acumulado_cerrado queda nulo cuando la causal no cierra fiscal, de
    -- modo que un cero significa "no habia deduccion que cerrar" y el nulo
    -- "esta causal no cierra ninguna", que son cosas distintas.
    IF OBJECT_ID(N'dbo.det_salida_operacion', N'U') IS NULL
    BEGIN
        create table [det_salida_operacion] ([id_corte] int not null, [id_operacion] int not null, [clasificacion] nvarchar(24) not null, [observacion] nvarchar(500) not null, [referencia_operacion_nueva] nvarchar(30) null, [base_cerrada] decimal(19, 4) not null, [deterioro_cerrado] decimal(19, 4) not null, [fiscal_acumulado_cerrado] decimal(19, 4) null, [id_usuario] int not null, [fecha] datetime not null);
        alter table [det_salida_operacion] add constraint [pk_det_salida_operacion] primary key ([id_corte], [id_operacion]);
    END

    -- ------------------------------------------------------------------
    -- 4. Estado de cierre del corte
    -- ------------------------------------------------------------------

    -- La migracion agrega las ocho columnas en un solo ALTER; aqui van una a una
    -- para poder comprobar cada existencia. El orden de creacion es el mismo, de
    -- modo que el esquema resultante no cambia.
    IF COL_LENGTH(N'dbo.det_corte', N'fecha_cierre') IS NULL
        alter table [det_corte] add [fecha_cierre] datetime null;

    IF COL_LENGTH(N'dbo.det_corte', N'id_usuario_cierre') IS NULL
        alter table [det_corte] add [id_usuario_cierre] int null;

    -- Lo que distingue un cierre limpio de uno forzado. Sin esta columna el
    -- modulo mentiria sobre su propio estado.
    IF COL_LENGTH(N'dbo.det_corte', N'cerrado_con_salvedad') IS NULL
        alter table [det_corte] add [cerrado_con_salvedad] bit not null default '0';

    IF COL_LENGTH(N'dbo.det_corte', N'motivo_salvedad') IS NULL
        alter table [det_corte] add [motivo_salvedad] nvarchar(500) null;

    -- La enumeracion congelada de lo que estaba mal al cerrar, en JSON.
    IF COL_LENGTH(N'dbo.det_corte', N'foto_salvedad') IS NULL
        alter table [det_corte] add [foto_salvedad] nvarchar(max) null;

    IF COL_LENGTH(N'dbo.det_corte', N'fecha_reapertura') IS NULL
        alter table [det_corte] add [fecha_reapertura] datetime null;

    IF COL_LENGTH(N'dbo.det_corte', N'id_usuario_reapertura') IS NULL
        alter table [det_corte] add [id_usuario_reapertura] int null;

    IF COL_LENGTH(N'dbo.det_corte', N'motivo_reapertura') IS NULL
        alter table [det_corte] add [motivo_reapertura] nvarchar(500) null;

    COMMIT TRANSACTION;
    PRINT 'Fase 7a aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 7a revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: borra del acumulado fiscal TODO lo que escribieron los cierres de
 * diciembre (origen = 'CIERRE_DICIEMBRE'), de cualquier corte y de cualquier
 * anio gravable. Es deliberado: esas filas las produce el codigo de esta fase y
 * sin ella nadie volveria a escribirlas ni a saber de donde salieron. Se acota
 * por origen para no tocar el 1399 historico, que entro por el cargue de la
 * fase 2 con origen 'EXCEL_1399'.
 *
 * ATENCION: al soltar det_salida_operacion se pierde la clasificacion de las
 * bajas, que es trabajo del usuario y no se puede recalcular. Lo mismo vale para
 * el motivo y la foto de los cierres con salvedad, que viven en columnas de
 * det_corte. En bitacora si queda rastro de los dos.
 *
 * ATENCION: los cortes que hayan quedado en estado CERRADO lo siguen estando
 * despues de la reversion, pero sin fecha, sin autor y sin salvedad: el estado
 * vive en det_corte.estado, que es de la fase 1 y esta reversion no toca.
 * Reabrirlos exige el codigo de esta fase.
 *
 * ATENCION: borra las filas de los controles C-PRORROGA, C-SALIDAS y
 * C-CIERRE-FISCAL en det_corte_cuadre. Los cortes ya calculados pierden esos
 * tres renglones de cuadre de forma irreversible. C-CONCILIA no se borra: lo
 * emite la fase 6a y esta fase solo lo activo.
 *
 * cerrado_con_salvedad lleva restriccion de default, que hay que soltar antes de
 * la columna: es lo mismo que emite el dropColumn de la migracion.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_fiscal_acumulado WHERE origen = N'CIERRE_DICIEMBRE';

    DELETE FROM det_corte_cuadre
    WHERE codigo IN (N'C-PRORROGA', N'C-SALIDAS', N'C-CIERRE-FISCAL');

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_corte] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_corte]')
      AND [name] IN ('cerrado_con_salvedad') AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_corte DROP COLUMN fecha_cierre, id_usuario_cierre,
        cerrado_con_salvedad, motivo_salvedad, foto_salvedad,
        fecha_reapertura, id_usuario_reapertura, motivo_reapertura;

    DROP TABLE det_salida_operacion;
    DROP TABLE det_corte_param_causal_salida;
    DROP TABLE det_param_causal_salida;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
