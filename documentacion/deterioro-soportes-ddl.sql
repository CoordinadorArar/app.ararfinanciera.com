/*
 * Deterioro de Cartera - Soporte de la marca de suspension como adjunto real.
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_15_100000_add_soporte_adjunto_to_det_suspension.php. El DDL reproduce
 * el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar
 * (minusculas, identificadores delimitados, modificadores en el orden
 * Increment > Nullable > Default) para que lo validado aqui sea exactamente lo
 * que despues corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: va DESPUES de la fase 5a, que es la que crea
 * det_suspension_interes. Es independiente de las fases 6 y 7: se puede aplicar
 * antes o despues de ellas.
 *
 * QUE CAMBIA. El campo Soporte de la pantalla de suspensiones deja de ser texto
 * libre y pasa a ser un archivo adjunto (PDF o imagen escaneada, hasta 20 MB,
 * uno por marca y OPCIONAL, que es como estaba antes). La columna `soporte` no
 * cambia de tipo ni de tamano: pasa a guardar la REFERENCIA al archivo, es
 * decir la ruta autenticada /deterioro-soporte-suspension/{idSuspension}, nunca
 * la ruta fisica. Es el mismo criterio de Gestion Documental con los folios.
 *
 * NO HAY NADA QUE MIGRAR. Las 259 marcas existentes, todas de origen
 * CARGUE_INICIAL, tienen `soporte` vacio (verificado antes de escribir esto).
 * Este script no toca ni una fila de datos: solo agrega dos columnas nulables.
 *
 * EL CARGUE INICIAL (D-14) sigue escribiendo texto en `soporte` y sigue sin
 * adjunto. Lo que distingue "esto es un adjunto" de "esto es una referencia
 * escrita a mano" es soporte_nombre, que solo puebla la marcacion manual con
 * archivo. Por eso el listado expone tiene_soporte y soporte_url calculados
 * sobre soporte_nombre y no sobre `soporte`: una marca del cargue con texto en
 * `soporte` no dibuja ningun enlace.
 *
 * Con una sola excepcion, que sostiene la invariante: si la marca activa que el
 * cargue va a actualizar YA TIENE adjunto, el upsert no pisa `soporte`. El
 * adjunto es evidencia de un acto manual con autor y fecha, y un cargue masivo
 * no puede destruirlo ni dejarlo huerfano en disco con soporte_nombre apuntando
 * a un texto. Los demas campos -causal, fecha del evento, observacion- si se
 * actualizan como siempre.
 *
 * POR QUE DOS COLUMNAS Y NO UNA. El nombre del archivo en disco se deriva del
 * id de la marca ({id_suspension}.pdf), nunca del nombre que venga del cliente,
 * que es entrada no confiable y decidiria donde se escribe. Sin guardar el
 * nombre original, dentro de un ano nadie sabria que es 12.pdf: eso es
 * soporte_nombre. soporte_tipo guarda el tipo MIME y sirve para dos cosas, el
 * Content-Type con que se sirve el archivo y reconstruir la extension del
 * archivo en disco sin tener que buscarlo en la carpeta.
 *
 * FUERA DE LA BASE, y es parte del despliegue: la carpeta project/soportes-suspension/
 * con su .htaccess de bloqueo (ya versionada). Los archivos viven ahi, fuera de
 * public/ y sin URL adivinable: un soporte de suspension es un acta de
 * defuncion, un auto de admision a insolvencia o una demanda. El unico camino a
 * ellos es la ruta autenticada, que ademas exige el permiso 'consultar' del
 * modulo.
 *
 * Los soportes NO se borran al recalcular un corte ni al levantar la marca:
 * ninguna tabla de corte los referencia y limpiarCorte() no los conoce. Son
 * evidencia contable y sobreviven a la reactivacion.
 *
 * Sobre SIESA (UNOEEARAR) este script no hace absolutamente nada.
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

    IF OBJECT_ID(N'dbo.det_suspension_interes', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_suspension_interes: aplique antes la fase 5a.', 1;
    END

    -- ------------------------------------------------------------------
    -- 1. Nombre original del archivo subido
    -- ------------------------------------------------------------------

    -- Tambien es la bandera que dice que la marca trae adjunto: nula = sin
    -- adjunto (las del cargue inicial, que escriben texto en `soporte`).
    IF COL_LENGTH(N'dbo.det_suspension_interes', N'soporte_nombre') IS NULL
        alter table [det_suspension_interes] add [soporte_nombre] nvarchar(255) null;

    -- ------------------------------------------------------------------
    -- 2. Tipo MIME del archivo
    -- ------------------------------------------------------------------

    -- Content-Type con que se sirve, y de el se reconstruye la extension del
    -- archivo en disco: application/pdf -> {id}.pdf, image/jpeg -> {id}.jpg,
    -- image/png -> {id}.png.
    IF COL_LENGTH(N'dbo.det_suspension_interes', N'soporte_tipo') IS NULL
        alter table [det_suspension_interes] add [soporte_tipo] nvarchar(100) null;

    COMMIT TRANSACTION;
    PRINT 'Soportes de suspension aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Soportes de suspension revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: al soltar estas dos columnas se pierde el nombre original y el tipo
 * de cada adjunto. Los archivos siguen en project/soportes-suspension/ -esta
 * reversion no borra ninguno, son evidencia contable-, pero quedan como
 * {id_suspension}.{ext} sin el nombre con que se subieron, y el enlace del
 * listado deja de dibujarse porque depende de soporte_nombre.
 *
 * La columna `soporte` NO se toca: conserva la ruta autenticada de cada marca,
 * con la que se puede reconstruir a que marca pertenece cada archivo.
 *
 * Ningun corte pierde cifras: el adjunto no entra en ningun calculo.
 *
 * Las dos columnas son nulables y sin default, de modo que no hay restriccion
 * de default que soltar antes.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    ALTER TABLE det_suspension_interes DROP COLUMN soporte_tipo;
    ALTER TABLE det_suspension_interes DROP COLUMN soporte_nombre;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
