@echo off
title Importador de Base de Datos - Gestor Financiero
echo ====================================================
echo   Importando Base de Datos en MySQL - XAMPP
echo ====================================================
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
    echo Asegurate de tener XAMPP instalado en C:\xampp
    echo.
    pause
    exit /b 1
)

echo Ejecutando importacion de database\schema.sql...
echo.

"%MYSQL_BIN%" -u root < "%~dp0..\database\schema.sql"
if errorlevel 1 goto error_import

:success_import
echo ====================================================
echo   [EXITO] Base de datos importada correctamente!
echo   - Base de datos: gestor_financiero
echo   - Tablas: transacciones, transacciones_fijas y cuentas
echo   - Base limpia y lista para registrar tus datos
echo ====================================================
echo.
pause
exit /b 0

:error_import
echo ====================================================
echo   [ERROR] No se pudo importar la base de datos.
echo   Verifica que el servicio MySQL este encendido (verde) en XAMPP.
echo ====================================================
echo.
pause
exit /b 1
