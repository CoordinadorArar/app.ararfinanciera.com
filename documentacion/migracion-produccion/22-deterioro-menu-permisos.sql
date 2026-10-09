/*
 * MIGRACION A PRODUCCION - Paso 22
 * Deterioro de Cartera - Menu, submenus y permisos por rol.
 *
 * GENERADO para esta migracion: equivale a
 * `php artisan db:seed --class=DeterioroMenuSeeder`, que escribe siempre en la
 * conexion 'identidad' (= ArarFinanciera). Menus, Submenus y PermisosRoles
 * viven solo en produccion: por eso este paso NO tiene equivalente en pruebas.
 *
 * ESTADO ESPERADO: segun deterioro-fase7-validacion.md el seeder ya corrio en
 * produccion en la fase 7a (17 submenus, 49 permisos). Faltaria solo
 * '/deterioro-accion-exportar' (fase 7b) y sus 3 permisos. Este paso inserta
 * unicamente lo que falte.
 *
 * Reproduce el seeder fielmente:
 * - Menu 'Deterioro Cartera' (RutaMenu '#'): se busca por nombre; si no existe se
 *   crea SIN Orden, igual que el seeder (ver README: decision pendiente).
 * - Submenu: se busca por RutaSubmenu; si existe NO se toca ninguna columna
 *   (en particular EstadoSubmenu, que es estado operativo y lo gobierna el
 *   paso 23). Solo se inserta si falta, con el EstadoSubmenu declarado.
 * - Permiso (IdRoles, IdSubmenu): se inserta solo si falta.
 * - Roles: 1 Administrador, 2 Gerente, 7 Contador. forzarCierre y reabrir solo
 *   1 y 2.
 *
 * Prerrequisitos: tablas Menus, Submenus y PermisosRoles (paso 00). Debe ir en
 * la MISMA ventana que el despliegue del codigo: sin estos submenus, el
 * fallback de CheckDeterioroPermiso resuelve las acciones contra
 * /deterioro-cortes.
 *
 * Idempotente y transaccional.
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

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @idMenu bigint = (SELECT TOP 1 IdMenu FROM Menus WHERE NombreMenu = N'Deterioro Cartera' ORDER BY IdMenu);

    IF @idMenu IS NULL
    BEGIN
        INSERT INTO Menus (NombreMenu, RutaMenu, CodigoMenu)
        VALUES (N'Deterioro Cartera', N'#', N'<i class="fas fa-chart-line"></i>');
        SET @idMenu = SCOPE_IDENTITY();
    END

    DECLARE @submenus TABLE (orden int NOT NULL, nombre nvarchar(30) NOT NULL, ruta nvarchar(30) NOT NULL, icono nvarchar(100) NOT NULL, estado int NOT NULL, pagina bit NOT NULL);

    INSERT INTO @submenus (orden, nombre, ruta, icono, estado, pagina) VALUES
        ( 1, N'Cortes',                     N'/deterioro-cortes',              N'<i class="fas fa-calendar-check"></i>', 1, 1),
        ( 2, N'Resumen del corte',          N'/deterioro-resumen',             N'<i class="fas fa-table-cells"></i>',    0, 1),
        ( 3, N'Detalle por operación',      N'/deterioro-detalle-operaciones', N'<i class="fas fa-list-ul"></i>',        0, 1),
        ( 4, N'Contable / fiscal',          N'/deterioro-contable-fiscal',     N'<i class="fas fa-scale-balanced"></i>', 0, 1),
        ( 5, N'Evolución',                  N'/deterioro-evolucion',           N'<i class="fas fa-chart-line"></i>',     0, 1),
        ( 6, N'Suspensión de intereses',    N'/deterioro-suspensiones',        N'<i class="fas fa-pause-circle"></i>',   0, 1),
        ( 7, N'Conciliación SIESA',         N'/deterioro-conciliacion',        N'<i class="fas fa-code-compare"></i>',   0, 1),
        ( 8, N'Controles y cierre',         N'/deterioro-controles',           N'<i class="fas fa-lock"></i>',           0, 1),
        ( 9, N'Consultar deterioro',        N'/deterioro-accion-consultar',    N'', 0, 0),
        (10, N'Calcular deterioro',         N'/deterioro-accion-calcular',     N'', 0, 0),
        (11, N'Suspender intereses',        N'/deterioro-accion-suspender',    N'', 0, 0),
        (12, N'Conciliar con SIESA',        N'/deterioro-accion-conciliar',    N'', 0, 0),
        (13, N'Clasificar bajas',           N'/deterioro-accion-clasificar',   N'', 0, 0),
        (14, N'Cerrar corte',               N'/deterioro-accion-cerrar',       N'', 0, 0),
        (15, N'Forzar cierre con salvedad', N'/deterioro-accion-forzarCierre', N'', 0, 0),
        (16, N'Reabrir corte',              N'/deterioro-accion-reabrir',      N'', 0, 0),
        (17, N'Consultar bitácora',         N'/deterioro-accion-auditar',      N'', 0, 0),
        (18, N'Exportar',                   N'/deterioro-accion-exportar',     N'', 0, 0);

    DECLARE @permisos TABLE (ruta nvarchar(30) NOT NULL, rol int NOT NULL);

    INSERT INTO @permisos (ruta, rol)
    SELECT s.ruta, r.rol
    FROM @submenus s
    CROSS JOIN (VALUES (1), (2), (7)) r (rol)
    WHERE s.pagina = 1;

    INSERT INTO @permisos (ruta, rol) VALUES
        (N'/deterioro-accion-consultar', 1),    (N'/deterioro-accion-consultar', 2),    (N'/deterioro-accion-consultar', 7),
        (N'/deterioro-accion-calcular', 1),     (N'/deterioro-accion-calcular', 2),     (N'/deterioro-accion-calcular', 7),
        (N'/deterioro-accion-suspender', 1),    (N'/deterioro-accion-suspender', 2),    (N'/deterioro-accion-suspender', 7),
        (N'/deterioro-accion-conciliar', 1),    (N'/deterioro-accion-conciliar', 2),    (N'/deterioro-accion-conciliar', 7),
        (N'/deterioro-accion-clasificar', 1),   (N'/deterioro-accion-clasificar', 2),   (N'/deterioro-accion-clasificar', 7),
        (N'/deterioro-accion-cerrar', 1),       (N'/deterioro-accion-cerrar', 2),       (N'/deterioro-accion-cerrar', 7),
        (N'/deterioro-accion-forzarCierre', 1), (N'/deterioro-accion-forzarCierre', 2),
        (N'/deterioro-accion-reabrir', 1),      (N'/deterioro-accion-reabrir', 2),
        (N'/deterioro-accion-auditar', 1),      (N'/deterioro-accion-auditar', 2),      (N'/deterioro-accion-auditar', 7),
        (N'/deterioro-accion-exportar', 1),     (N'/deterioro-accion-exportar', 2),     (N'/deterioro-accion-exportar', 7);

    DECLARE @submenusNuevos int, @permisosNuevos int;

    INSERT INTO Submenus (IdMenu, NombreSubmenu, RutaSubmenu, CodigoSubmenu, EstadoSubmenu)
    SELECT @idMenu, s.nombre, s.ruta, s.icono, s.estado
    FROM @submenus s
    WHERE NOT EXISTS (SELECT 1 FROM Submenus x WHERE x.RutaSubmenu = s.ruta)
    ORDER BY s.orden;
    SET @submenusNuevos = @@ROWCOUNT;

    INSERT INTO PermisosRoles (IdRoles, IdSubmenu)
    SELECT p.rol, sm.IdSubmenu
    FROM @permisos p
    CROSS APPLY (SELECT TOP 1 x.IdSubmenu FROM Submenus x WHERE x.RutaSubmenu = p.ruta ORDER BY x.IdSubmenu) sm
    WHERE NOT EXISTS (SELECT 1 FROM PermisosRoles pr WHERE pr.IdRoles = p.rol AND pr.IdSubmenu = sm.IdSubmenu);
    SET @permisosNuevos = @@ROWCOUNT;

    COMMIT TRANSACTION;
    PRINT 'Paso 22 (menu y permisos de deterioro) aplicado sobre ' + DB_NAME()
        + '. Submenus nuevos: ' + CAST(@submenusNuevos AS varchar(10))
        + ' - permisos nuevos: ' + CAST(@permisosNuevos AS varchar(10)) + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 22 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO

SELECT s.IdSubmenu, s.IdMenu, s.NombreSubmenu, s.RutaSubmenu, s.EstadoSubmenu,
       roles = STUFF((SELECT ',' + CAST(p.IdRoles AS varchar(10))
                      FROM PermisosRoles p WHERE p.IdSubmenu = s.IdSubmenu
                      ORDER BY p.IdRoles FOR XML PATH('')), 1, 1, '')
FROM Submenus s
WHERE s.RutaSubmenu LIKE '/deterioro%'
ORDER BY s.IdSubmenu;
GO
