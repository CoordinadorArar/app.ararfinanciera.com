/*
 * Deterioro de Cartera - Fase 7b (exportables: cuentas contables del asiento)
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_14_100000_create_det_cuenta_contable_tables.php. El DDL reproduce el
 * texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar
 * (minusculas, identificadores delimitados, modificadores en el orden
 * Increment > Nullable > Default) para que lo validado aqui sea exactamente lo
 * que despues corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> el 1399 (det_fiscal_acumulado) -> fase 3 ->
 * fase 4 -> fase 5a -> fase 6a -> fase 7a -> DeterioroCausalSalidaSeeder ->
 * fase 7b -> DeterioroCuentaContableSeeder -> DeterioroMenuSeeder.
 *
 * DeterioroMenuSeeder va al final y en la MISMA ventana que el codigo: sin el,
 * el permiso 'exportar' no existe en Submenus y el fallback deliberado de
 * CheckDeterioroPermiso lo resuelve contra /deterioro-cortes, con lo que
 * cualquiera que pueda abrir la pantalla de Cortes podria descargar el asiento.
 *
 * DeterioroCuentaContableSeeder existe por simetria con el resto de
 * parametricas, pero HOY NO SIEMBRA NINGUNA FILA y correrlo no cambia nada. Es
 * deliberado: la seccion 17 dice que el gasto va «contra la cuenta que defina
 * Contabilidad» y esa definicion no ha llegado. Inventar un PUC seria peor que
 * no tener ninguno. Mientras la tabla este vacia el exportable del asiento se
 * rechaza con la lista de conceptos sin cuenta; los otros tres exportables
 * -Excel de transicion, PDF del resumen y detalle por operacion- no dependen de
 * ella y funcionan desde el primer dia.
 *
 * Esta fase NO despliega los controles C-4 (sincronia de marcas con factoring)
 * ni C-5 (cobertura del cargue inicial), que siguen dependiendo de definiciones
 * externas.
 *
 * Sobre la llave conceptual. No es "una cuenta de gasto": el asiento del mes
 * cambia de forma segun el signo. Cuando el deterioro aumenta se debita gasto y
 * se acredita la 1399; cuando disminuye se debita la 1399 y se acredita una
 * cuenta de recuperacion, que normalmente es de ingreso y no la misma de gasto.
 * Por eso la parametrica es por CONCEPTO y lleva cuenta debito y cuenta credito
 * por concepto: ningun lado del asiento queda escrito en el codigo, ni siquiera
 * la 1399.
 *
 * REGLA DE RESOLUCION. `producto` es opcional. Una fila con producto resuelve
 * solo ese producto; una fila con producto nulo es la general. Al armar el
 * asiento se busca primero (concepto, producto) y, si no existe, (concepto,
 * NULL). Asi Contabilidad puede abrir cuentas distintas por producto para el
 * mayor auxiliar sin enumerar los que comparten cuenta. La copia congelada lleva
 * indice unico y no clave primaria porque `producto` es nulable: SQL Server no
 * admite nulos en una PK y un indice unico si, tratando los nulos como iguales,
 * que es justo lo que hace falta -una sola fila general por concepto y corte-.
 *
 * Los conceptos los enumera Deterioro::CONCEPTOS_ASIENTO y no una tabla de
 * catalogo: son el vocabulario del asiento que arma el modulo. Lo que es
 * politica -que cuenta lleva cada uno- vive en esta tabla.
 *
 * DEFINICION PENDIENTE que el asiento no supone: el castigo. C-2 dice que el
 * deterioro acumulado de la operacion castigada se cierra en el mismo
 * movimiento, pero contra que cuenta de cartera, y si va en el mismo comprobante
 * que el ajuste del periodo, no esta definido. El concepto existe en la
 * parametrica y el exportable emite su bloque por separado y rotulado como
 * informativo, porque ese deterioro ya esta dentro del ajuste del periodo por
 * producto y sumarlo dos veces descuadraria el comprobante.
 *
 * ADVERTIR A CONTABILIDAD: el concepto CASTIGO se resuelve SIEMPRE contra la
 * fila general, la de producto nulo, porque las bajas del periodo se agrupan por
 * causal y no por producto (descomposicionBajas). Una fila de CASTIGO con
 * producto no la usaria nadie: quedaria sembrada y sin efecto. Si Contabilidad
 * necesita cuentas de castigo distintas por producto, hay que cambiar antes el
 * agrupamiento de las bajas, que es trabajo de codigo y no de parametrica.
 *
 * La copia congelada la escribe congelarParametros() y la borra limpiarCorte(),
 * igual que el resto de las parametricas del corte, y entra en
 * hashParametros(). Los cortes calculados antes de esta fase no la tienen: para
 * esos la lectura se cae a la parametrica viva a la fecha del corte, igual que
 * hizo la fase 7a con las causales de salida.
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
    -- 1. Cuentas contables del asiento (seccion 17)
    -- ------------------------------------------------------------------

    -- Parametrica con vigencias, igual que las demas del modulo. Se crea VACIA:
    -- las cuentas las siembra Contabilidad cuando las defina, y hasta entonces
    -- el asiento se rechaza diciendo que conceptos le faltan.
    IF OBJECT_ID(N'dbo.det_param_cuenta_contable', N'U') IS NULL
    BEGIN
        create table [det_param_cuenta_contable] ([id_param] int identity primary key not null, [concepto] nvarchar(24) not null, [producto] nvarchar(20) null, [cuenta_debito] nvarchar(20) not null, [cuenta_credito] nvarchar(20) not null, [descripcion] nvarchar(120) not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_cuenta_contable_vigencia] on [det_param_cuenta_contable] ([vigente_desde], [vigente_hasta]);
    END

    -- ------------------------------------------------------------------
    -- 2. Copia congelada dentro del corte
    -- ------------------------------------------------------------------

    -- Un corte cerrado tiene que exportar exactamente el mismo asiento dentro de
    -- seis meses, aunque para entonces Contabilidad haya cambiado las cuentas.
    IF OBJECT_ID(N'dbo.det_corte_param_cuenta_contable', N'U') IS NULL
    BEGIN
        create table [det_corte_param_cuenta_contable] ([id_corte] int not null, [concepto] nvarchar(24) not null, [producto] nvarchar(20) null, [cuenta_debito] nvarchar(20) not null, [cuenta_credito] nvarchar(20) not null, [descripcion] nvarchar(120) not null);
        create unique index [ux_det_corte_param_cuenta_contable] on [det_corte_param_cuenta_contable] ([id_corte], [concepto], [producto]);
    END

    -- ------------------------------------------------------------------
    -- 3. Marca de congelamiento de las cuentas en el corte
    -- ------------------------------------------------------------------

    -- Mientras el PUC este vacio -el estado de hoy y el de los proximos meses-
    -- congelarParametros() copia cero filas, de modo que "el corte congelo sus
    -- cuentas y no habia ninguna" y "el corte es anterior a esta fase y nunca
    -- las congelo" son la misma tabla vacia. El tratamiento tiene que ser el
    -- contrario: el primero no puede volver a leer la parametrica viva y el
    -- segundo tiene que hacerlo. Preguntar por el numero de filas mandaria a la
    -- parametrica viva a todos los cortes cerrados en este periodo, y el dia que
    -- Contabilidad sembrara las cuentas un corte cerrado hace seis meses
    -- empezaria a exportar un asiento que antes no existia. Esta bandera es lo
    -- unico que los distingue.
    --
    -- Consecuencia querida: un corte que congelo cero cuentas no exporta asiento
    -- nunca mas; para incorporarlas hay que reabrirlo y recalcularlo, que es una
    -- accion con permiso propio, motivo y bitacora.
    IF COL_LENGTH(N'dbo.det_corte', N'cuentas_congeladas') IS NULL
        alter table [det_corte] add [cuentas_congeladas] bit not null default '0';

    COMMIT TRANSACTION;
    PRINT 'Fase 7b aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 7b revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: al soltar det_param_cuenta_contable se pierden las cuentas que
 * Contabilidad haya definido, que son politica contable y no se pueden
 * recalcular. Al soltar la copia congelada, los cortes ya cerrados pierden las
 * cuentas con que se exporto su asiento: si despues se vuelve a desplegar la
 * fase con cuentas distintas, el mismo corte exportaria un asiento distinto, que
 * es justo lo que la copia congelada existe para impedir.
 *
 * Ningun corte pierde cifras: los cuatro exportables se arman al vuelo sobre
 * det_deterioro_operacion y esta reversion no toca ninguna tabla de resultados.
 * Los otros tres exportables siguen funcionando sin estas dos tablas; solo el
 * asiento deja de poder generarse.
 *
 * El permiso 'exportar' vive en Submenus, en la base de identidad, y esta
 * reversion no lo toca: se retira desde la pantalla de gestion del sitio.
 *
 * cuentas_congeladas lleva restriccion de default, que hay que soltar antes de
 * la columna: es lo mismo que emite el dropColumn de la migracion.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_corte] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_corte]')
      AND [name] IN ('cuentas_congeladas') AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_corte DROP COLUMN cuentas_congeladas;

    DROP TABLE det_corte_param_cuenta_contable;
    DROP TABLE det_param_cuenta_contable;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
