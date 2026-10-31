@echo off
title Importador de Base de Datos - Gestor Financiero
echo ====================================================
echo   Importando Base de Datos en MySQL (XAMPP)
echo ====================================================
echo.
echo Asegurate de que el modulo MySQL este en verde (Start) en XAMPP.
echo.

set MYSQL_BIN=
where mysql >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set MYSQL_BIN=mysql
) else (
    if exist "C:\xampp\mysql\bin\mysql.exe" (
        set MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe
    )
)

if "%MYSQL_BIN%"=="" (
    echo [ERROR] No se encontro el cliente MySQL.
    echo Verifica que XAMPP este instalado en C:\xampp
    pause
    exit /b 1
)

echo Importando schema.sql en MySQL...
"%MYSQL_BIN%" -u root < "%~dp0schema.sql"

if %ERRORLEVEL% equ 0 (
    echo.
    echo ====================================================
    echo   [EXITO] Base de datos importada correctamente!
    echo   - Base de datos: gestor_financiero
    echo   - Tablas: transacciones, transacciones_fijas
    echo   - Datos de prueba cargados
    echo ====================================================
) else (
    echo.
    echo [ERROR] No se pudo importar. 
    echo Asegurate de haber iniciado MySQL en el panel de XAMPP.
)

echo.
pause
