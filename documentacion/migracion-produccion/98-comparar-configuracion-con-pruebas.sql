/*
 * MIGRACION A PRODUCCION - Paso 98 (OPCIONAL, recomendado)
 * Comparacion de parametricas y configuracion entre ArarFinanciera_PRUEBAS y
 * ArarFinanciera. SOLO LECTURA: no escribe en ninguna de las dos bases.
 *
 * Para que sirve: los pasos 02, 03, 07, 08, 11, 18, 20 y 21 siembran los valores
 * del repositorio, NO los que negocio haya ajustado en pruebas. Este script
 * lista, tabla por tabla, las filas que solo estan en un lado, para que negocio
 * decida si replica un ajuste en produccion (por la pantalla de administracion
 * o por la parametrica, con vigencia), nunca copiando a ciegas.
 *
 * Cuando correrlo: despues del paso 99 (las tablas nuevas ya existen en los
 * dos lados). Puede correrse tambien antes; las tablas que falten se informan.
 *
 * Requisitos: ArarFinanciera_PRUEBAS en la misma instancia y permiso de lectura
 * sobre ella. Se compara sin id_param/id_usuario/fecha_registro, que difieren
 * por construccion. PagaduriasReglasEdad se compara por IdPagaduria, que es el
 * mismo en las dos bases solo si pruebas es copia de produccion.
 *
 * Salida: un resumen (tabla, solo_en_pruebas, solo_en_produccion, estado) y,
 * por cada tabla con diferencias, el detalle de las filas.
 */

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

SET NOCOUNT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

IF DB_ID(N'ArarFinanciera_PRUEBAS') IS NULL
BEGIN
    PRINT 'ArarFinanciera_PRUEBAS no existe en esta instancia: no hay nada que comparar.';
    RETURN;
END

DECLARE @t TABLE (orden int IDENTITY, tabla sysname NOT NULL, columnas nvarchar(1000) NOT NULL);

INSERT INTO @t (tabla, columnas) VALUES
    (N'det_param_rango_mora',        N'codigo, dias_desde, dias_hasta, etiqueta, pct_deterioro_contable, orden, vigente_desde, vigente_hasta'),
    (N'det_param_producto',          N'nom_operacion, producto, vigente_desde, vigente_hasta'),
    (N'det_param_interes',           N'producto, tasa_mora_mensual, aplica_mora, sigue_calculando_suspendido, vigente_desde, vigente_hasta'),
    (N'det_param_convencion',        N'base_dias, origen_mora, base_incluye_interes, siesa_manda_sobre_base, tarifa_renta, vigente_desde, vigente_hasta'),
    (N'det_param_fiscal',            N'metodo, pct_anual, dias_minimos_mora, activo, vigente_desde, vigente_hasta'),
    (N'det_param_fiscal_rango',      N'metodo, rango_codigo, pct, vigente_desde, vigente_hasta'),
    (N'det_param_causal_suspension', N'codigo, descripcion, activa, vigente_desde, vigente_hasta'),
    (N'det_param_causal_salida',     N'codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden, vigente_desde, vigente_hasta'),
    (N'det_param_cuenta_contable',   N'concepto, producto, cuenta_debito, cuenta_credito, descripcion, vigente_desde, vigente_hasta'),
    (N'Pagadurias',                  N'IdPagaduria, NombrePagaduria, EstadoPagaduria, UsaReglaSMMLV, UmbralSMMLV'),
    (N'PagaduriasReglasEdad',        N'IdPagaduria, EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro'),
    (N'ConfiguracionCentrales',      N'Clave, Valor'),
    (N'MotivosRechazo',              N'IdMotivo, NombreMotivo, EstadoMotivo'),
    (N'ValoresVariables',            N'*'),
    (N'CuposConfigCalculos',         N'*');

DECLARE @res TABLE (tabla sysname NOT NULL, solo_en_pruebas int NULL, solo_en_produccion int NULL, estado nvarchar(300) NOT NULL);

DECLARE @i int = 1, @max int = (SELECT MAX(orden) FROM @t);
DECLARE @tabla sysname, @cols nvarchar(1000), @sql nvarchar(max), @a int, @b int;

WHILE @i <= @max
BEGIN
    SELECT @tabla = tabla, @cols = columnas FROM @t WHERE orden = @i;
    SELECT @a = NULL, @b = NULL;

    IF OBJECT_ID(N'dbo.' + @tabla, N'U') IS NULL OR OBJECT_ID(N'ArarFinanciera_PRUEBAS.dbo.' + @tabla, N'U') IS NULL
    BEGIN
        INSERT INTO @res VALUES (@tabla, NULL, NULL, N'No existe en uno de los dos lados');
    END
    ELSE
    BEGIN
        BEGIN TRY
            SET @sql = N'SELECT @a = COUNT(*) FROM (SELECT ' + @cols + N' FROM ArarFinanciera_PRUEBAS.dbo.' + QUOTENAME(@tabla)
                     + N' EXCEPT SELECT ' + @cols + N' FROM dbo.' + QUOTENAME(@tabla) + N') x;'
                     + N' SELECT @b = COUNT(*) FROM (SELECT ' + @cols + N' FROM dbo.' + QUOTENAME(@tabla)
                     + N' EXCEPT SELECT ' + @cols + N' FROM ArarFinanciera_PRUEBAS.dbo.' + QUOTENAME(@tabla) + N') y;';
            EXEC sp_executesql @sql, N'@a int OUTPUT, @b int OUTPUT', @a = @a OUTPUT, @b = @b OUTPUT;

            INSERT INTO @res VALUES (@tabla, @a, @b, CASE WHEN @a = 0 AND @b = 0 THEN N'IGUAL' ELSE N'DIFIERE: revisar detalle' END);

            IF @a > 0 OR @b > 0
            BEGIN
                SET @sql = N'SELECT tabla = N''' + @tabla + N''', lado = ''SOLO_EN_PRUEBAS'', x.* FROM (SELECT ' + @cols + N' FROM ArarFinanciera_PRUEBAS.dbo.' + QUOTENAME(@tabla)
                         + N' EXCEPT SELECT ' + @cols + N' FROM dbo.' + QUOTENAME(@tabla) + N') x'
                         + N' UNION ALL SELECT tabla = N''' + @tabla + N''', lado = ''SOLO_EN_PRODUCCION'', y.* FROM (SELECT ' + @cols + N' FROM dbo.' + QUOTENAME(@tabla)
                         + N' EXCEPT SELECT ' + @cols + N' FROM ArarFinanciera_PRUEBAS.dbo.' + QUOTENAME(@tabla) + N') y;';
                EXEC sp_executesql @sql;
            END
        END TRY
        BEGIN CATCH
            INSERT INTO @res VALUES (@tabla, NULL, NULL, LEFT(N'No se pudo comparar: ' + ERROR_MESSAGE(), 300));
        END CATCH
    END

    SET @i += 1;
END

SELECT tabla, solo_en_pruebas, solo_en_produccion, estado FROM @res ORDER BY estado, tabla;
GO
