/*
 * Deterioro de Cartera - Cuotas repetidas del origen.
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_16_100000_add_duplicada_de_to_det_corte_detalle_cuota.php. El DDL
 * reproduce el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar
 * (minusculas, identificadores delimitados, modificadores en el orden
 * Increment > Nullable > Default) para que lo validado aqui sea exactamente lo
 * que despues corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: va DESPUES de la fase 1, que es la que crea
 * det_corte_detalle_cuota. Es independiente de todas las demas fases: se puede
 * aplicar antes o despues de ellas.
 *
 * EL HECHO. Modulos_Faico.dbo.ResumenVigentesClientes entrega cuotas que son
 * duplicados exactos de otras de la misma operacion: misma FecInicialCorriente,
 * misma FecFinalCorriente, mismo SaldoCapital y mismo SaldoIntereses, con
 * distinto IdCuota e IdDetalleOperacion. El caso medido es la operacion 8267,
 * cuyas cuotas 69-72 repiten las 33-36. El duplicado viene del origen; el
 * modulo no lo genera.
 *
 * MEDIDO sobre los tres cortes de PRUEBAS (426.264 filas de detalle): 4 filas y
 * 1 operacion por corte, todas LIBRANZAS, 737.805,00 de capital y 35.563,00 de
 * interes. Base de deterioro excluida 0,00, porque ninguna de las cuatro esta
 * vencida. Ninguna fila del detalle carece de FecFinalCorriente.
 *
 * QUE CAMBIA. Una sola columna nulable, `duplicada_de`. Nula significa que la
 * cuota cuenta para el calculo; con valor guarda el id_cuota de la cuota de su
 * mismo grupo que si cuenta -el menor id_cuota, por convencion-. Guarda el id
 * del superviviente y no una bandera porque la pantalla tiene que poder decir
 * de cual es copia cada fila.
 *
 * LA EXTRACCION NO CAMBIA. Sigue siendo un INSERT ... SELECT sin DISTINCT y sin
 * WHERE: el detalle es copia fiel del origen fila por fila y es la prueba de
 * que entrego factoring. Ninguna fila se borra, se edita ni se compensa. La
 * marca se escribe despues de extraer y antes de derivar, y de ella dependen la
 * consolidacion por operacion y los cuadres del detalle.
 *
 * NO HAY INDICE UNICO sobre la tupla de negocio, y es deliberado: un duplicado
 * exacto puede ser legitimo en FACTORING -dos desembolsos del mismo valor en la
 * misma fecha- y una restriccion abortaria el corte entero en el primer caso.
 * La llave primaria (id_corte, id_operacion, id_cuota) se queda como esta.
 *
 * NO HAY NADA QUE MIGRAR. La columna nace nula en las 426.264 filas existentes,
 * que es exactamente lo que significa "este corte no tiene la marca evaluada".
 * Los cortes ya calculados NO se recalculan con este script: hasta que se
 * recalculen, su cuadre C-DUPLICADAS no existe y la pantalla muestra un guion
 * en vez de un cero, porque un cero seria una afirmacion falsa.
 *
 * LAS CIFRAS DEL CORTE NO CAMBIAN al recalcular: las cuatro filas excluidas no
 * estan vencidas, de modo que su base de deterioro es cero y el deterioro total
 * es el mismo. Lo que cambia es el saldo de capital y de interes de la
 * operacion 8267, que dejan de contar cuatro cuotas repetidas.
 *
 * suma_capital_origen, suma_interes_origen y hash_datos de det_corte siguen
 * contando TODAS las filas, marcadas incluidas: son la huella de lo que
 * entrego el origen, no un resultado del calculo.
 *
 * Sobre SIESA (UNOEEARAR) este script no hace absolutamente nada, y sobre
 * Modulos_Faico tampoco: el modulo solo lee del sistema de factoring.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a
 * mitad no deja el esquema partido.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID(N'dbo.det_corte_detalle_cuota', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_corte_detalle_cuota: aplique antes la fase 1.', 1;
    END

    -- ------------------------------------------------------------------
    -- 1. Marca de cuota repetida del origen
    -- ------------------------------------------------------------------

    -- NULL = la cuota cuenta. Con valor = es copia de la cuota cuyo id_cuota
    -- se guarda aqui, y queda fuera de la consolidacion y del lado detalle de
    -- los cuadres.
    IF COL_LENGTH(N'dbo.det_corte_detalle_cuota', N'duplicada_de') IS NULL
        alter table [det_corte_detalle_cuota] add [duplicada_de] int null;

    COMMIT TRANSACTION;
    PRINT 'Cuotas repetidas aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Cuotas repetidas revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: al soltar la columna, los cortes ya recalculados con la marca
 * quedan con sus cuadres C-DUPLICADAS y C-DUPLICADAS-BASE sin respaldo, y su
 * consolidacion por operacion vuelve a contar las cuotas repetidas en cuanto se
 * recalculen. Hay que recalcularlos para que detalle y consolidado vuelvan a
 * cuadrar entre si.
 *
 * Ninguna fila del detalle se pierde: la reversion solo suelta la marca, nunca
 * las filas, que siguen siendo copia fiel del origen.
 *
 * La columna es nulable y sin default, de modo que no hay restriccion de
 * default que soltar antes. Tampoco hay indice que la referencie.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    ALTER TABLE det_corte_detalle_cuota DROP COLUMN duplicada_de;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
