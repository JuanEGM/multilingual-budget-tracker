@echo off
title Gestor de Presupuesto Personal - Multi-Entorno
echo ====================================================
echo   Iniciando Gestor de Presupuesto Personal
echo   Compatible con XAMPP, MAMP, Laragon y Docker
echo ====================================================
echo.
echo [INFO] Asegurate de tener activo tu motor de base de datos MySQL/MariaDB.
echo.

set PHP_BIN=

:: 1. Check if php is already in PATH
where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set PHP_BIN=php
    goto php_found
)

:: 2. Check standard XAMPP path
if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
    goto php_found
)

:: 3. Check standard Laragon path (find highest available PHP version)
for /d %%D in ("C:\laragon\bin\php\php-*") do (
    if exist "%%D\php.exe" (
        set PHP_BIN=%%D\php.exe
    )
)
if defined PHP_BIN goto php_found

:: 4. Check standard MAMP path
if exist "C:\MAMP\bin\php\php.exe" (
    set PHP_BIN=C:\MAMP\bin\php\php.exe
    goto php_found
)

:php_not_found
echo [ERROR] No se encontro el ejecutable de PHP.
echo Asegurate de tener PHP instalado en PATH, XAMPP (C:\xampp), Laragon (C:\laragon) o MAMP.
echo.
pause
exit /b 1

:php_found
:: Switch to project root directory
cd /d "%~dp0.."

echo Servidor local corriendo en: http://localhost:8000
echo Presiona Ctrl + C en esta ventana para detener el servidor.
echo.

:: Open browser automatically
start http://localhost:8000

:: Start PHP built-in server in project root folder
"%PHP_BIN%" -S localhost:8000
