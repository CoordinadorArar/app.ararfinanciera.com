/*
 * MIGRACION A PRODUCCION - Paso 16
 * Copia de documentacion/deterioro-prorroga-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_09_23_100000_add_prorroga_a_det_deterioro
 * Prerrequisitos: paso 15 (atribucion por notas). Las consultas de verificacion del final son de solo lectura.
 * La reversion para produccion esta en rollback/ (no descomentar el bloque de
 * abajo).
 */

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/*
 * Deterioro de Cartera - La prorroga vencida de SIESA entra en la base.
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_23_100000_add_prorroga_a_det_deterioro.php. El DDL reproduce el texto
 * que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar (minusculas,
 * identificadores delimitados, modificadores en el orden Increment > Nullable >
 * Default) para que lo validado aqui sea exactamente lo que despues corra en
 * produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: va DESPUES de la atribucion por notas
 * (2026_09_18_100000), que es la que crea det_corte_saldo_siesa.componente y
 * det_deterioro_operacion.valor_nominal_siesa. Sin ella no habria quien llenara
 * la columna que este script agrega.
 *
 * LA POLITICA. Contabilidad definio que el saldo de PRORROGA ya VENCIDO es
 * interes de la obligacion y entra en la base de deterioro. Hasta hoy la
 * prorroga se clasificaba aparte y solo sumaba a valor_nominal_siesa: son 56
 * filas por 82.170.273 en el corte de agosto de 2026, repartidas en cinco
 * operaciones -2133, 2316, 1276, 1238 y 2136-.
 *
 * LO QUE NO CAMBIA. saldo_siesa sigue significando CAPITAL y la prorroga no
 * entra ahi: la conciliacion C-3 lo compara contra el capital de factoring y no
 * se toca, ni una partida ni un peso. valor_nominal_siesa tampoco cambia, porque
 * ya sumaba la prorroga por las dos vias. El componente PRORROGA se conserva
 * como etiqueta propia y NO se renombra a INTERES: lo que cambia es el
 * tratamiento, no el origen del dato, y perder la etiqueta seria perder la
 * trazabilidad de donde salio la cifra.
 *
 * LA PRORROGA NO VENCIDA queda en el componente nuevo PRORROGA_NV y sigue fuera
 * de la base, igual que el interes corriente por D-03. La que no trae fecha de
 * vencimiento se trata como no vencida. componente es nvarchar(12) desde la
 * atribucion por notas, de modo que 'PRORROGA_NV' -11 caracteres- cabe y este
 * script NO tiene que ampliar la columna. Hoy las 57 filas de prorroga de la
 * compania 7 estan todas vencidas y ninguna tiene la fecha nula: el snapshot del
 * corte de agosto no cambia una sola fila por este reparto.
 *
 * DESDE CUANDO APLICA: agosto de 2026 en adelante. No hay ninguna compuerta por
 * fecha, ni en el codigo ni aqui, y no hace falta: el corte de julio esta
 * CERRADO y no se recalcula. Un corte cerrado conserva sus cifras aunque este
 * script se aplique.
 *
 * ESTE SCRIPT NO APLICA LA POLITICA POR SI SOLO: solo agrega la columna, vacia.
 * Lo que mueve las cifras es RECALCULAR el corte con el codigo nuevo. Es
 * enlazarSaldoSiesa() quien llena interes_prorroga_siesa desde el snapshot y
 * quien la suma a base_deterioro, y aplicarSuspensiones() quien la suma a
 * base_congelada de las operaciones suspendidas (D-06). Mientras el corte no se
 * recalcule, la columna queda en NULL y la base es la de antes.
 *
 * EFECTO DEL RECALCULO sobre el corte de agosto de 2026: base_deterioro sube
 * 82.170.273 repartidos en cinco operaciones, y con ella el deterioro contable,
 * el fiscal y el diferido de esas cinco. C-BASE pasa a comparar contra el
 * detalle de cuotas MAS la prorroga del snapshot -la prorroga no viene de
 * factoring y en el detalle de cuotas no existe-, y el control nuevo
 * C-SIESA-PRORROGA publica cuanta entro. Los dos lados de C-SUSPENSION llevan la
 * prorroga, de modo que su diferencia sigue siendo el interes vencido menos el
 * congelado.
 *
 * Sobre SIESA (UNOEEARAR) este script no hace absolutamente nada: el motor solo
 * lee de alli, y f353_fecha_vcto ya existe en t353_co_saldo_abierto.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a mitad
 * no deja el esquema partido.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF COL_LENGTH(N'dbo.det_corte_saldo_siesa', N'componente') IS NULL
    BEGIN
        THROW 50000, 'Falta det_corte_saldo_siesa.componente: aplique antes la atribucion por notas.', 1;
    END

    -- ------------------------------------------------------------------
    -- 1. Prorroga vencida atribuida a la operacion
    -- ------------------------------------------------------------------

    -- Nulable a proposito, y el nulo significa UNA sola cosa: que el corte se
    -- calculo antes de esta politica. Un corte recalculado con ella deja CERO en
    -- las operaciones sin prorroga -el enlace recorre todas las del corte y
    -- escribe ISNULL(...)-, de modo que "medido y no hay prorroga" y "no
    -- evaluado" son valores distintos y las pantallas pueden separarlos.
    --
    -- Por eso la columna NO se rellena con cero en los cortes existentes: el
    -- NULL del corte de julio, que esta cerrado y no se recalcula, es correcto y
    -- es justamente la marca de que se calculo con la politica anterior.
    -- Rellenarlo con cero diria que se midio, y no se midio.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_prorroga_siesa') IS NULL
        alter table [det_deterioro_operacion] add [interes_prorroga_siesa] decimal(19, 4) null;

    COMMIT TRANSACTION;
    PRINT 'Prorroga en la base de deterioro aplicada sobre ' + DB_NAME() + '.';
    PRINT 'RECUERDE: la columna queda vacia. La politica se aplica al RECALCULAR el corte.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Prorroga en la base de deterioro revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: soltar la columna NO devuelve las cifras. Un corte ya recalculado
 * con la politica nueva conserva la prorroga dentro de base_deterioro y de
 * base_congelada, y sin la columna deja de verse de donde salio; C-BASE, que
 * suma la prorroga al lado del detalle desde el snapshot, seguiria cuadrando,
 * pero C-SIESA-PRORROGA desaparece y con el la cifra que la hacia visible. Para
 * volver de verdad a la politica anterior hay que revertir tambien el codigo y
 * recalcular los cortes abiertos.
 *
 * Se borra ademas la fila de C-SIESA-PRORROGA de det_corte_cuadre, que es lo
 * mismo que hace el down() de la migracion: el control deja de existir y su fila
 * quedaria huerfana en la pantalla de cuadres.
 *
 * El componente PRORROGA_NV del snapshot no se toca: lo reescribe la extraccion
 * la proxima vez que se recalcule el corte.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre WHERE codigo = N'C-SIESA-PRORROGA';

    ALTER TABLE det_deterioro_operacion DROP COLUMN interes_prorroga_siesa;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */


-- ----------------------------------------------------------------------
-- VERIFICACION 1. La columna quedo creada con el tipo esperado.
-- ----------------------------------------------------------------------

SELECT c.name AS columna, t.name AS tipo, c.precision, c.scale, c.is_nullable
FROM sys.columns c
INNER JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID(N'dbo.det_deterioro_operacion')
  AND c.name = N'interes_prorroga_siesa';

-- ----------------------------------------------------------------------
-- VERIFICACION 2. Prorroga del snapshot por corte y componente. Antes de
-- recalcular no existe ninguna fila PRORROGA_NV: la escribe la extraccion.
-- ----------------------------------------------------------------------

SELECT id_corte, componente, filas = COUNT(*), saldo = SUM(saldo)
FROM det_corte_saldo_siesa
WHERE componente LIKE 'PRORROGA%'
GROUP BY id_corte, componente
ORDER BY id_corte, componente;

-- ----------------------------------------------------------------------
-- VERIFICACION 3. Los dos lados de C-SIESA-PRORROGA, por corte: lo que el motor
-- dejo en la operacion contra lo que el snapshot tiene para esas mismas
-- operaciones. Deben coincidir al peso DESPUES de recalcular; antes, el lado del
-- motor sale en cero y el del snapshot muestra la cifra pendiente de aplicar.
-- ----------------------------------------------------------------------

SELECT o.id_corte,
       motor = ISNULL(SUM(o.interes_prorroga_siesa), 0),
       snapshot = ISNULL((SELECT SUM(s.saldo)
                          FROM det_corte_saldo_siesa s
                          WHERE s.id_corte = o.id_corte
                            AND s.componente = 'PRORROGA'
                            AND s.id_operacion_nota IS NOT NULL
                            AND EXISTS (SELECT 1 FROM det_deterioro_operacion d
                                        WHERE d.id_corte = s.id_corte
                                          AND d.id_operacion = s.id_operacion_nota)), 0)
FROM det_deterioro_operacion o
GROUP BY o.id_corte
ORDER BY o.id_corte;

-- ----------------------------------------------------------------------
-- VERIFICACION 4. C-BASE rearmado a mano sobre el ultimo corte: la base
-- consolidada contra el detalle de cuotas mas la prorroga vencida del snapshot.
-- Las dos cifras deben coincidir al peso despues de recalcular.
-- ----------------------------------------------------------------------

DECLARE @idCorte int = (SELECT MAX(id_corte) FROM det_corte);

SELECT id_corte = @idCorte,
       base = (SELECT ISNULL(SUM(base_deterioro), 0) FROM det_deterioro_operacion
               WHERE id_corte = @idCorte),
       detalle = (SELECT ISNULL(SUM(capital_vencido + interes_vencido), 0)
                  FROM det_corte_detalle_cuota
                  WHERE id_corte = @idCorte AND duplicada_de IS NULL)
               + (SELECT ISNULL(SUM(s.saldo), 0)
                  FROM det_corte_saldo_siesa s
                  WHERE s.id_corte = @idCorte AND s.componente = 'PRORROGA'
                    AND s.id_operacion_nota IS NOT NULL
                    AND EXISTS (SELECT 1 FROM det_deterioro_operacion d
                                WHERE d.id_corte = s.id_corte
                                  AND d.id_operacion = s.id_operacion_nota));
GO
