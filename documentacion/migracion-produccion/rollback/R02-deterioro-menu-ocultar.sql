/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R02
 *  Revierte: pasos 22 y 23 (menu y visibilidad de Deterioro)
 * ======================================================================
 *
 *  !!! ADVERTENCIA: SCRIPT DESTRUCTIVO SOBRE LA BASE DE PRODUCCION !!!
 *
 *  - Ejecutar SOLO si la migracion fallo o se decidio revertirla, con la
 *    aplicacion detenida y DESPUES de confirmar que existe el respaldo
 *    completo tomado antes de empezar (ver README).
 *  - Los rollback van en orden: R01, R02, ... R19. Ejecute solo los de los
 *    pasos que SI se aplicaron (salida de los pasos 00 y 99). Un rollback de
 *    un paso no aplicado falla dentro de su transaccion y no cambia nada.
 *  - Si hay duda, restaurar el respaldo es mas seguro que este script.
 *  - Para ejecutarlo hay que editar @confirmo = 1 abajo: es a proposito.
 *
 *  NO borra submenus ni permisos: la mayoria ya existian en produccion antes
 *  de esta migracion (el seeder corrio en la fase 7a) y no hay forma segura de
 *  distinguir cuales agrego el paso 22. Lo que hace es OCULTAR todo el modulo
 *  del menu lateral (EstadoSubmenu = 0 en todas las rutas /deterioro%).
 *  EstadoSubmenu no da ni quita acceso: los permisos siguen en PermisosRoles.
 *  Para retirar permisos use la pantalla de gestion del sitio.
 *  Para volver a mostrar Cortes, ejecute de nuevo el paso 23.
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
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

DECLARE @confirmo bit = 0;

IF @confirmo = 0
BEGIN
    THROW 50003, 'Reversion NO confirmada: lea la advertencia y cambie @confirmo a 1 en este archivo.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    UPDATE Submenus SET EstadoSubmenu = 0
    WHERE RutaSubmenu LIKE '/deterioro%' AND EstadoSubmenu <> 0;

    PRINT CAST(@@ROWCOUNT AS varchar(10)) + ' submenus de deterioro ocultados.';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
