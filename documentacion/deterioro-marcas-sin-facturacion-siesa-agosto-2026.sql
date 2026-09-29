/*
 * Marcas de suspension de intereses para las 21 operaciones sin facturacion de
 * interes en SIESA, con fecha de evento del 31 de agosto de 2026.
 *
 * Equivale a `php artisan deterioro:cargar-suspensiones <csv> --ambiente=demo`
 * sobre documentacion/suspensiones-sin-facturacion-siesa-agosto-2026.csv, y
 * existe porque en la maquina de desarrollo artisan no corre: el driver
 * pdo_sqlsrv instalado es 5.11.0-beta1 y rechaza PDO::ATTR_STRINGIFY_FETCHES,
 * que Laravel pone siempre en SqlServerConnector. Cuando el driver se
 * reemplace por uno estable, el camino normal es el comando, no este script.
 *
 * QUE ESCRIBE, campo por campo, igual que registrarSuspensionCargueInicial():
 *   causal_codigo         SIN_DETERMINAR — la columna es NOT NULL y no admite
 *                         vacio; SIN_DETERMINAR es la causal que el modulo
 *                         siembra para 'pendiente de clasificar', con activa=0
 *                         para que no se ofrezca en la marcacion manual.
 *   fecha_evento          2026-08-31
 *   observacion           el texto que definio Contabilidad
 *   soporte               NULL, porque el CSV no trae referencia de archivo
 *   origen                CARGUE_INICIAL, no MANUAL
 *   existe_en_factoring   se resuelve contra el corte mas reciente por fecha,
 *                         que es lo que hace el comando
 *   interes_congelado     NULL, no cero. Son cosas distintas: cero afirmaria
 *                         que el interes estaba en cero al evento, y NULL dice
 *                         que no se pudo establecer. De eso depende que
 *                         aplicarSuspensiones() no toque la operacion y que
 *                         C-MARCAS la reporte.
 *   id_usuario            0, el mismo que usa el cargue
 *
 * LA VERIFICACION PREVIA NO ES ADORNO. El comando calcula interes_congelado en
 * el momento; este script lo fija en NULL porque se midio que ninguna de las 21
 * tiene documentos de interes en SIESA. Si eso cambiara —una factura nueva, una
 * nota corregida— el NULL seria falso y la observacion tambien. Por eso el
 * bloque de abajo aborta si encuentra facturacion para cualquiera de ellas, en
 * vez de escribir una marca que mentiria en silencio.
 *
 * EFECTO EN EL CORTE: ninguno hasta recalcular. aplicarSuspensiones() corre
 * dentro del calculo del corte, no al marcar. Y cuando se recalcule, estas 21
 * NO cambian el deterioro —sin interes congelado no se congela nada— pero
 * suman 420.776.769 de base a C-MARCAS, que pasa de 18.560.054 a unos 439,3
 * millones y deja el cuadre en falla. Esta previsto y aceptado.
 *
 * Es idempotente: reproduce el upsert del modulo. Si la operacion ya tiene
 * marca activa actualiza sus campos en sitio, y respeta el soporte adjunto
 * cuando lo hay, igual que el codigo. El indice unico filtrado
 * ux_det_suspension_operacion_activa impide en cualquier caso una segunda
 * marca activa para la misma operacion.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- Obligatorias, no decorativas: det_suspension_interes tiene el indice unico
-- filtrado ux_det_suspension_operacion_activa, y SQL Server rechaza cualquier
-- INSERT o UPDATE sobre una tabla con indice filtrado si QUOTED_IDENTIFIER o
-- ANSI_NULLS estan en OFF. sqlcmd los trae apagados por defecto, de modo que
-- sin estas dos lineas el script falla con el error 1934 en vez de escribir.
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

DECLARE @cia smallint = 7;
DECLARE @fechaEvento date = '2026-08-31';
DECLARE @observacion nvarchar(500) = N'Excluidas debido a que no cuentan con registros de facturacion de interes en SIESA';
-- Formato ISO 8601 con T: el login del servidor usa idioma espanol, en el que
-- un datetime escrito 'aaaa-mm-dd hh:mm:ss' se interpreta como dd/mm/aaaa y
-- falla. El estilo 126 es el equivalente del date('Y-m-d\TH:i:s') del modulo.
DECLARE @fechaRegistro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
DECLARE @idUsuario int = 0;

DECLARE @ops TABLE (id_operacion int PRIMARY KEY);
INSERT INTO @ops (id_operacion) VALUES
    (2316),(1935),(1162),(2040),(5839),(5480),(2147),(3629),(2159),(5483),
    (5208),(5904),(3635),(4326),(6421),(4878),(6235),(4477),(2060),(2073),(2121);

IF (SELECT COUNT(*) FROM @ops) <> 21
BEGIN
    THROW 50000, 'La lista de operaciones no tiene las 21 esperadas.', 1;
END

-- ----------------------------------------------------------------------
-- VERIFICACION PREVIA · ninguna de las 21 puede tener facturacion de interes
-- ----------------------------------------------------------------------
-- Replica el parseo de interesALaFechaDelEventoEnLote(): cuatro anclas para la
-- operacion y el mes de corte de la nota, sobre CC y FAT de la cuenta de
-- interes 13451001.
DECLARE @conFacturacion int;

SELECT @conFacturacion = COUNT(DISTINCT k.id_operacion)
FROM @ops k
INNER JOIN (
    SELECT operacion = TRY_CONVERT(int, LEFT(a.resto, NULLIF(PATINDEX('%[^0-9]%', a.resto + 'x'), 0) - 1)),
           mes = COALESCE(TRY_CONVERT(date, b.fecha, 103), TRY_CONVERT(date, b.fecha, 3))
    FROM UNOEEARAR.dbo.t353_co_saldo_abierto s
    LEFT JOIN UNOEEARAR.dbo.t253_co_auxiliares aux ON aux.f253_rowid = s.f353_rowid_auxiliar
    CROSS APPLY (SELECT resto = CASE
        WHEN CHARINDEX('OPE: ', s.f353_notas) > 0
            THEN SUBSTRING(s.f353_notas, CHARINDEX('OPE: ', s.f353_notas) + 5, 12)
        WHEN PATINDEX('%OPE-1-[0-9]%', s.f353_notas) > 0
            THEN SUBSTRING(s.f353_notas, PATINDEX('%OPE-1-[0-9]%', s.f353_notas) + 6, 12)
        WHEN PATINDEX('%OPE [0-9]%', s.f353_notas) > 0
            THEN SUBSTRING(s.f353_notas, PATINDEX('%OPE [0-9]%', s.f353_notas) + 4, 12)
        WHEN PATINDEX('%OPE[0-9]%', s.f353_notas) > 0
            THEN SUBSTRING(s.f353_notas, PATINDEX('%OPE[0-9]%', s.f353_notas) + 3, 12) END,
        corte = LTRIM(REPLACE(SUBSTRING(s.f353_notas, NULLIF(CHARINDEX('CORTE', s.f353_notas), 0) + 5, 14), ':', ' '))) a
    CROSS APPLY (SELECT fecha = NULLIF(LEFT(a.corte, CHARINDEX(' ', a.corte + ' ') - 1), '')) b
    WHERE s.f353_id_cia = @cia
      AND s.f353_id_tipo_docto_cruce IN ('CC', 'FAT')
      AND aux.f253_id = '13451001') f
    ON f.operacion = k.id_operacion AND f.mes <= EOMONTH(@fechaEvento);

IF @conFacturacion > 0
BEGIN
    DECLARE @msg nvarchar(200) = CONCAT(
        'Abortado: ', @conFacturacion,
        ' de las 21 operaciones SI tienen facturacion de interes en SIESA. ',
        'Deben congelar y la observacion no les aplica; use el comando.');
    THROW 50000, @msg, 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- Corte mas reciente por fecha, que es contra el que el comando resuelve
    -- existe_en_factoring.
    DECLARE @idCorte int = (SELECT TOP 1 id_corte FROM det_corte ORDER BY fecha_corte DESC);

    -- ------------------------------------------------------------------
    -- 1. Marcas que ya estan activas: actualizacion en sitio
    -- ------------------------------------------------------------------
    -- `soporte` se conserva cuando la marca trae adjunto: un cargue masivo no
    -- puede pisar la ruta autenticada de un archivo que alguien subio a mano,
    -- porque dejaria soporte_nombre apuntando a algo que no es una URL y el
    -- archivo huerfano en disco.
    UPDATE s SET
        s.causal_codigo = N'SIN_DETERMINAR',
        s.fecha_evento = @fechaEvento,
        s.observacion = @observacion,
        s.soporte = CASE WHEN s.soporte_nombre IS NULL THEN NULL ELSE s.soporte END,
        s.origen = N'CARGUE_INICIAL',
        s.existe_en_factoring = CASE WHEN EXISTS (
            SELECT 1 FROM det_deterioro_operacion o
            WHERE o.id_corte = @idCorte AND o.id_operacion = s.id_operacion) THEN 1 ELSE 0 END,
        s.interes_congelado = NULL,
        s.id_corte_congelado = NULL
    FROM det_suspension_interes s
    INNER JOIN @ops k ON k.id_operacion = s.id_operacion
    WHERE s.fecha_reactivacion IS NULL;

    DECLARE @actualizadas int = @@ROWCOUNT;

    -- ------------------------------------------------------------------
    -- 2. Marcas nuevas
    -- ------------------------------------------------------------------
    -- marca_factoring_aplicada va explicito en 0 aunque sea el default de la
    -- columna: esta marca la escribe el paso que lleva la suspension a
    -- factoring, y una marca recien creada no ha pasado por el.
    INSERT INTO det_suspension_interes (
        id_operacion, causal_codigo, fecha_evento, observacion, soporte, origen,
        existe_en_factoring, interes_congelado, id_corte_congelado,
        marca_factoring_aplicada, soporte_nombre, soporte_tipo,
        id_usuario, fecha_registro)
    SELECT k.id_operacion, N'SIN_DETERMINAR', @fechaEvento, @observacion, NULL, N'CARGUE_INICIAL',
           CASE WHEN EXISTS (SELECT 1 FROM det_deterioro_operacion o
                             WHERE o.id_corte = @idCorte AND o.id_operacion = k.id_operacion) THEN 1 ELSE 0 END,
           NULL, NULL,
           0, NULL, NULL,
           @idUsuario, @fechaRegistro
    FROM @ops k
    WHERE NOT EXISTS (SELECT 1 FROM det_suspension_interes s
                      WHERE s.id_operacion = k.id_operacion AND s.fecha_reactivacion IS NULL);

    DECLARE @creadas int = @@ROWCOUNT;

    COMMIT TRANSACTION;

    PRINT CONCAT('Marcas creadas: ', @creadas, ' · actualizadas: ', @actualizadas,
                 ' · corte de referencia: ', @idCorte, ' · base ', DB_NAME(), '.');
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Revertido: no se escribio ninguna marca.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION. Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * Borra unicamente las marcas que este script creo, reconocibles por origen,
 * causal, fecha de evento y observacion, y solo si siguen sin congelar y sin
 * soporte adjunto. Una marca que alguien ya clasifico, congelo o documento
 * dejo de ser lo que este script escribio y no le corresponde a esta reversion
 * borrarla.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

DELETE FROM det_suspension_interes
WHERE origen = N'CARGUE_INICIAL'
  AND causal_codigo = N'SIN_DETERMINAR'
  AND fecha_evento = '2026-08-31'
  AND observacion = N'Excluidas debido a que no cuentan con registros de facturacion de interes en SIESA'
  AND interes_congelado IS NULL
  AND soporte_nombre IS NULL
  AND fecha_reactivacion IS NULL
  AND id_operacion IN (2316,1935,1162,2040,5839,5480,2147,3629,2159,5483,
                       5208,5904,3635,4326,6421,4878,6235,4477,2060,2073,2121);

 */


-- ----------------------------------------------------------------------
-- VERIFICACION. Debe devolver 21 filas, todas sin congelar.
-- ----------------------------------------------------------------------

SELECT s.id_suspension,
       s.id_operacion,
       s.causal_codigo,
       s.fecha_evento,
       s.origen,
       s.existe_en_factoring,
       s.interes_congelado,
       s.fecha_registro,
       base_del_corte = o.base_deterioro
FROM det_suspension_interes s
LEFT JOIN det_deterioro_operacion o
       ON o.id_operacion = s.id_operacion
      AND o.id_corte = (SELECT TOP 1 id_corte FROM det_corte ORDER BY fecha_corte DESC)
WHERE s.fecha_reactivacion IS NULL
  AND s.id_operacion IN (2316,1935,1162,2040,5839,5480,2147,3629,2159,5483,
                         5208,5904,3635,4326,6421,4878,6235,4477,2060,2073,2121)
ORDER BY s.id_operacion;

-- Totales. Marcas vigentes antes: 259, todas con causal resuelta. Despues de
-- este script deben ser 280, con 38 sin congelar (las 17 que ya lo estaban mas
-- estas 21), y la base que C-MARCAS vera crecer en 420.776.769.
SELECT marcas_vigentes = COUNT(*),
       sin_congelar = SUM(CASE WHEN interes_congelado IS NULL THEN 1 ELSE 0 END),
       del_cargue_de_hoy = SUM(CASE WHEN origen = N'CARGUE_INICIAL'
                                     AND fecha_evento = '2026-08-31' THEN 1 ELSE 0 END)
FROM det_suspension_interes
WHERE fecha_reactivacion IS NULL;

-- Ninguna marca puede quedar apuntando a una causal que no exista en la
-- parametrica: si esta consulta devuelve filas, la pantalla mostraria la
-- descripcion vacia.
SELECT s.causal_codigo, marcas = COUNT(*)
FROM det_suspension_interes s
WHERE NOT EXISTS (SELECT 1 FROM det_param_causal_suspension c WHERE c.codigo = s.causal_codigo)
GROUP BY s.causal_codigo;
GO
