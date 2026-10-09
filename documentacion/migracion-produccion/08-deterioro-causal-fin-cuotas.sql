/*
 * MIGRACION A PRODUCCION - Paso 08
 * Copia de documentacion/deterioro-causal-fin-cuotas-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: db:seed DeterioroCausalSuspensionSeeder (filas FIN_CUOTAS y SIN_DETERMINAR; las otras tres van en el paso 07)
 * Prerrequisitos: pasos 06 y 07.
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
 * Deterioro de Cartera - Cuarta causal de suspension de intereses (FIN_CUOTAS).
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan db:seed --class=DeterioroCausalSuspensionSeeder`. El
 * script NO altera estructura: det_param_causal_suspension ya existe desde la
 * fase 5a y esta fase solo siembra filas. codigo es nvarchar(20) y descripcion
 * nvarchar(120): los dos valores caben.
 *
 * ORDEN DE DESPLIEGUE: va DESPUES de la fase 5a, que es la que crea la tabla.
 * Es independiente de las demas fases.
 *
 * LA CAUSAL. Cuando la operacion agota su numero de cuotas disponibles,
 * factoring deja de generar la facturacion automatica de intereses. No es un
 * evento del deudor -como el fallecimiento, la insolvencia o el paso a cobro
 * juridico- sino de la operacion, pero el efecto sobre el modulo es el mismo:
 * los intereses dejan de causarse y hay que congelarlos al corte.
 *
 * vigente_desde es 2016-01-01, igual que las otras tres, y no la fecha de hoy:
 * la condicion pudo ocurrir en cualquier momento y Contabilidad tiene que poder
 * clasificar con ella eventos antiguos. Una vigencia que arrancara hoy haria
 * que marcarSuspension() rechazara la causal para una fecha_evento anterior.
 *
 * EL CODIGO NO CAMBIA. marcarSuspension() valida la causal por consulta a la
 * parametrica y no contra una lista fija; congelarParametros() copia al corte
 * las causales vigentes a la fecha del corte, tambien sin lista fija; la
 * pantalla de suspensiones pinta lo que devuelve causalesSuspension(). Por eso
 * esta causal es un dato y no un despliegue de codigo.
 *
 * EFECTO EN LOS CORTES YA CERRADOS: ninguno. Su copia congelada
 * (det_corte_param_causal_suspension) se escribio cuando se calcularon y este
 * script no la toca. Un corte que se recalcule despues de aplicar esto si
 * congelara la causal nueva, sin que cambie ninguna cifra: la causal solo
 * clasifica marcas, no interviene en ninguna formula.
 *
 * Sobre SIESA (UNOEEARAR) y sobre Modulos_Faico este script no hace nada.
 *
 * Es idempotente y transaccional: cada insercion esta guardada por codigo, de
 * modo que se puede repetir sin duplicar filas, y un fallo a mitad no deja la
 * parametrica a medias.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID(N'dbo.det_param_causal_suspension', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_param_causal_suspension: aplique antes la fase 5a.', 1;
    END

    -- Formato ISO 8601 con T: el login del servidor usa idioma espanol, en el
    -- que un datetime escrito 'aaaa-mm-dd hh:mm:ss' se interpreta como
    -- dd/mm/aaaa y falla. El estilo 126 de CONVERT es el equivalente exacto del
    -- date('Y-m-d\TH:i:s') del seeder, e id_usuario 0 es el mismo que usa.
    DECLARE @fecha_registro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
    DECLARE @vigente_desde date = '2016-01-01';
    DECLARE @id_usuario int = 0;

    -- ------------------------------------------------------------------
    -- 1. Causal nueva: FIN_CUOTAS
    -- ------------------------------------------------------------------

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_suspension WHERE codigo = N'FIN_CUOTAS')
    BEGIN
        INSERT INTO det_param_causal_suspension
            (codigo, descripcion, activa, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'FIN_CUOTAS', N'Finalización de las cuotas disponibles: deja de generar facturación automática', 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    -- ------------------------------------------------------------------
    -- 2. APARTE: alineacion con el seeder (SIN_DETERMINAR). NO es alcance nuevo
    -- ------------------------------------------------------------------

    -- El seeder del repositorio ya contempla SIN_DETERMINAR, pero la base no la
    -- tiene. Hoy las 259 marcas del cargue inicial (D-14) apuntan a una causal
    -- que no existe en la parametrica: el LEFT JOIN de suspensiones() no
    -- encuentra fila y la pantalla muestra la descripcion vacia. Esta insercion
    -- solo pone la base al dia con el codigo ya versionado; no crea ninguna
    -- clasificacion que el modulo no usara ya.
    --
    -- Entra con activa = 0 a proposito. causalesSuspension() -que alimenta el
    -- desplegable de marcacion manual- filtra por activa = 1, de modo que la
    -- causal queda excluida de las opciones que se ofrecen al usuario, y
    -- "pendiente de clasificar" contradice una marca de origen MANUAL. Si
    -- llegara por otra via, marcarSuspension() tambien la rechaza -exige
    -- activa = 1- mientras los LEFT JOIN de suspensiones() y
    -- suspensionesDelCorte() siguen resolviendo su descripcion, que es el
    -- objetivo del bloque.
    IF NOT EXISTS (SELECT 1 FROM det_param_causal_suspension WHERE codigo = N'SIN_DETERMINAR')
    BEGIN
        INSERT INTO det_param_causal_suspension
            (codigo, descripcion, activa, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'SIN_DETERMINAR', N'Proviene del cargue inicial (D-14) y está pendiente de clasificar', 0, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    COMMIT TRANSACTION;
    PRINT 'Causal FIN_CUOTAS aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Causal FIN_CUOTAS revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION. Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: borrar la fila NO borra las marcas que ya la usen. Una marca de
 * det_suspension_interes con causal_codigo = 'FIN_CUOTAS' quedaria huerfana y
 * su descripcion se veria vacia en la pantalla, que es exactamente el problema
 * que el bloque 2 viene a corregir para SIN_DETERMINAR. Si hay marcas
 * registradas con la causal, lo correcto es desactivarla (activa = 0) o
 * cerrarle la vigencia (vigente_hasta), no borrarla.
 *
 * La copia congelada de los cortes ya calculados no se toca: esos cortes
 * conservan la causal aunque aqui se borre.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

DELETE FROM det_param_causal_suspension
WHERE codigo = N'FIN_CUOTAS'
  AND NOT EXISTS (SELECT 1 FROM det_suspension_interes WHERE causal_codigo = N'FIN_CUOTAS');

 */


-- ----------------------------------------------------------------------
-- VERIFICACION. Causales resultantes y marcas que cuelgan de cada una.
-- ----------------------------------------------------------------------

SELECT c.id_param,
       c.codigo,
       c.descripcion,
       c.activa,
       c.vigente_desde,
       c.vigente_hasta,
       COUNT(s.id_suspension) AS marcas
FROM det_param_causal_suspension c
LEFT JOIN det_suspension_interes s ON s.causal_codigo = c.codigo
GROUP BY c.id_param, c.codigo, c.descripcion, c.activa, c.vigente_desde, c.vigente_hasta
ORDER BY c.id_param;

-- Marcas cuya causal NO existe en la parametrica: despues de aplicar el script
-- esta consulta debe devolver cero filas.
SELECT s.causal_codigo, COUNT(*) AS marcas
FROM det_suspension_interes s
WHERE NOT EXISTS (SELECT 1 FROM det_param_causal_suspension c WHERE c.codigo = s.causal_codigo)
GROUP BY s.causal_codigo;
GO
