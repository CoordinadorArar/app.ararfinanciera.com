/*
 * MIGRACION A PRODUCCION - Paso 23
 * Copia literal de documentacion/deterioro-fase7-visibilidad.sql (ya tenia la
 * guarda de produccion). Solo se agrega esta cabecera y el bloque SET inicial.
 * No equivale a ninguna migracion ni seeder: gobierna EstadoSubmenu, que el
 * seeder deliberadamente no reconcilia.
 * Deja el menu del modulo de deterioro con una sola entrada visible (Cortes).
 * Prerrequisitos: paso 22 (exige que exista /deterioro-cortes en Submenus).
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
 * Deterioro de Cartera - Visibilidad del modulo en el menu lateral.
 *
 * ESTE SCRIPT CORRE CONTRA PRODUCCION (ArarFinanciera), no contra PRUEBAS.
 * Es el unico del modulo que lo hace, y por eso su guarda es la inversa de la de
 * los DDL: aborta si NO esta en produccion.
 *
 * La razon es que menus, submenus, roles y permisos viven siempre en la base de
 * identidad, que Ambiente::aplicar() no conmuta a proposito porque la identidad
 * es unica. Sembrarlos en ArarFinanciera_PRUEBAS no da menu ni permiso a nadie.
 *
 * QUE HACE
 *
 * Deja el menu del modulo con UNA sola entrada: Cortes. Las otras siete paginas
 * y las diez acciones quedan con EstadoSubmenu = 0.
 *
 * POR QUE SOLO CORTES
 *
 * Las siete paginas restantes son el detalle de un corte: necesitan uno en la
 * URL y, al entrar sin el, su JS redirige a Cortes. Dibujarlas en el menu daria
 * ocho entradas que llevan todas al mismo sitio. Se entra a ellas desde la fila
 * del corte, en la pantalla de Cortes, que es como el modulo ya funciona; y asi
 * nunca hay duda de que mes se esta mirando, porque el corte se eligio antes de
 * entrar. En un modulo contable esa ambiguedad no es un detalle menor.
 *
 * HISTORIA DE ESTE ARCHIVO, para que nadie se confunda con versiones viejas
 *
 * Nacio el 14 de septiembre de 2026 haciendo lo contrario: ENCENDER las cinco
 * paginas que se habian apagado porque su codigo aun no estaba desplegado. El
 * 15 de septiembre se decidio que el menu muestre solo Cortes, de modo que
 * aquel encendido ya no debe ejecutarse nunca. Si alguien conserva una copia de
 * la version anterior, esta la reemplaza.
 *
 * QUE NO TOCA
 *
 * Ningun permiso. Las filas de Submenus y PermisosRoles siguen existiendo tal
 * cual: EstadoSubmenu solo gobierna si el submenu se DIBUJA. Ni
 * CheckSubmenuPermission ni CheckDeterioroPermiso miran esa columna -buscan por
 * RutaSubmenu-, asi que apagar una pagina no debilita ni fortalece el acceso.
 * Su unico lector en todo el arbol es Admin::obtenerSubMenus().
 *
 * Y al reves: encender no es lo que da acceso. Con el codigo desplegado, las
 * rutas son alcanzables escribiendo la URL aunque el menu no las dibuje.
 *
 * No hace falta cerrar sesion ni limpiar el navegador: el menu se pide en cada
 * carga y el cambio se ve en la siguiente navegacion.
 *
 * Es idempotente y transaccional.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- La unica entrada que se dibuja.
    DECLARE @visible nvarchar(30) = N'/deterioro-cortes';

    -- Cortes tiene que existir: si no, el seeder no se ha corrido en esta base
    -- y lo que hace falta es sembrar, no apagar.
    IF NOT EXISTS (SELECT 1 FROM Submenus WHERE RutaSubmenu = @visible)
        THROW 50001, 'No existe /deterioro-cortes en Submenus: corra antes DeterioroMenuSeeder.', 1;

    UPDATE Submenus SET EstadoSubmenu = 1
    WHERE RutaSubmenu = @visible AND EstadoSubmenu <> 1;

    UPDATE Submenus SET EstadoSubmenu = 0
    WHERE RutaSubmenu LIKE '/deterioro%' AND RutaSubmenu <> @visible AND EstadoSubmenu <> 0;

    -- Debe quedar exactamente una entrada dibujada del modulo.
    DECLARE @dibujadas int = (
        SELECT COUNT(*) FROM Submenus
        WHERE RutaSubmenu LIKE '/deterioro%' AND EstadoSubmenu = 1);

    IF @dibujadas <> 1
        THROW 50002, 'El menu del modulo no quedo con una sola entrada visible.', 1;

    COMMIT TRANSACTION;

    SELECT RutaSubmenu, NombreSubmenu, EstadoSubmenu
    FROM Submenus WHERE RutaSubmenu LIKE '/deterioro%'
    ORDER BY EstadoSubmenu DESC, RutaSubmenu;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO

/*
 * PARA VOLVER A DIBUJAR TODAS LAS PAGINAS, si alguna vez se revierte la
 * decision de navegacion. Las acciones nunca se dibujan.
 *
UPDATE Submenus SET EstadoSubmenu = 1
WHERE RutaSubmenu LIKE '/deterioro%' AND RutaSubmenu NOT LIKE '/deterioro-accion-%';
 */
