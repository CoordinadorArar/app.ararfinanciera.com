@echo off
setlocal

rem Ejecuta los pasos 01 a 24 y 99 en orden con sqlcmd y se detiene en el primero que falle.
rem Uso: ejecutar-migracion.bat SERVIDOR [usuario clave]
rem Sin usuario y clave usa autenticacion de Windows (-E).
rem Ejecutar el paso 00 antes, por separado, y revisar su salida (README, seccion 5).

if "%~1"=="" (
    echo Uso: %~nx0 SERVIDOR [usuario clave]
    exit /b 1
)

set "SERVIDOR=%~1"
if "%~2"=="" (
    set "AUTH=-E"
) else (
    set "AUTH=-U %~2 -P %~3"
)

cd /d "%~dp0"

for %%f in (01 02 03 04 05 06 07 08 09 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 99) do (
    for %%a in (%%f-*.sql) do (
        echo ==== Paso %%f: %%a
        sqlcmd -S "%SERVIDOR%" -d ArarFinanciera %AUTH% -I -b -f 65001 -i "%%a" -o "%%~na-salida.txt"
        if errorlevel 1 (
            echo.
            echo FALLO el paso %%f ^(%%a^). Revise %%~na-salida.txt. No se ejecuto ningun paso posterior.
            exit /b 1
        )
    )
)

echo.
echo Pasos 01 a 24 y 99 ejecutados sin error. Revise 99-verificacion-final-salida.txt.
exit /b 0
