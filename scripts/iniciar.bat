@echo off
title Gestor de Presupuesto Personal
echo ====================================================
echo   Iniciando Gestor de Presupuesto Personal
echo ====================================================
echo.
echo [INFO] Recuerda tener activo el modulo MySQL en el panel de XAMPP.
echo.

:: Detect if php is in PATH or use XAMPP default path
where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set PHP_BIN=php
) else (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else (
        echo [ERROR] No se encontro el ejecutable de PHP.
        echo Asegurate de tener XAMPP instalado o PHP en las variables de entorno.
        pause
        exit /b 1
    )
)

:: Switch to project root directory
cd /d "%~dp0.."

echo Servidor local corriendo en: http://localhost:8000
echo Presiona Ctrl + C en esta ventana para detener el servidor.
echo.

:: Open browser automatically
start http://localhost:8000

:: Start PHP built-in server in project root folder
"%PHP_BIN%" -S localhost:8000
