@echo off
title Importador de Base de Datos - Gestor Financiero
echo ====================================================
echo   Importando Base de Datos en MySQL / MariaDB
echo   Compatible con XAMPP, Laragon, MAMP y Docker
echo ====================================================
echo.

set MYSQL_BIN=

:: 1. Check if mysql is in PATH
where mysql >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set MYSQL_BIN=mysql
    goto run_import
)

:: 2. Check XAMPP
if exist "C:\xampp\mysql\bin\mysql.exe" (
    set MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe
    goto run_import
)

:: 3. Check Laragon
for /d %%D in ("C:\laragon\bin\mysql\mysql-*") do (
    if exist "%%D\bin\mysql.exe" (
        set MYSQL_BIN=%%D\bin\mysql.exe
    )
)
if defined MYSQL_BIN goto run_import

:: 4. Check MAMP
if exist "C:\MAMP\bin\mysql\bin\mysql.exe" (
    set MYSQL_BIN=C:\MAMP\bin\mysql\bin\mysql.exe
    goto run_import
)

if "%MYSQL_BIN%"=="" (
    echo [ERROR] No se encontro el cliente MySQL.
    echo Asegurate de tener MySQL en PATH, o XAMPP / Laragon / MAMP instalados.
    echo.
    pause
    exit /b 1
)

:run_import
echo Usando MySQL: %MYSQL_BIN%
echo Ejecutando importacion de database\schema.sql...
echo.

"%MYSQL_BIN%" -u root < "%~dp0..\database\schema.sql"
if errorlevel 1 goto error_import

:success_import
echo ====================================================
echo   [EXITO] Base de datos importada correctamente!
echo   - Base de datos: gestor_financiero
echo   - Tablas actualizadas con soporte de transferencias,
echo     suscripciones anuales, quincenales y mensuales.
echo ====================================================
echo.
pause
exit /b 0

:error_import
echo ====================================================
echo   [ERROR] No se pudo importar la base de datos.
echo   Verifica que el servicio MySQL este encendido.
echo   Si tu MySQL requiere contrasena (como en MAMP con "root"),
echo   puedes ejecutar: "%MYSQL_BIN%" -u root -proot ^< "%~dp0..\database\schema.sql"
echo ====================================================
echo.
pause
exit /b 1
