#!/usr/bin/env bash
# ========================================================
# Gestor de Presupuesto Personal - macOS / Linux Launcher
# ========================================================

echo "===================================================="
echo "  Iniciando Gestor de Presupuesto Personal (macOS)"
echo "  Compatible con MAMP, Homebrew, XAMPP y Docker"
echo "===================================================="
echo ""

# Move to project root directory
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR" || exit 1

PHP_BIN=""

# 1. Check if php is in PATH
if command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
# 2. Check Apple Silicon Homebrew
elif [ -x "/opt/homebrew/bin/php" ]; then
    PHP_BIN="/opt/homebrew/bin/php"
# 3. Check Intel Homebrew
elif [ -x "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
# 4. Check MAMP for macOS (search for latest PHP 8.x)
elif compgen -G "/Applications/MAMP/bin/php/php8*/bin/php" >/dev/null; then
    PHP_BIN=$(ls -d /Applications/MAMP/bin/php/php8*/bin/php 2>/dev/null | tail -n 1)
# 5. Check XAMPP for macOS
elif [ -x "/Applications/XAMPP/xamppfiles/bin/php" ]; then
    PHP_BIN="/Applications/XAMPP/xamppfiles/bin/php"
fi

if [ -z "$PHP_BIN" ]; then
    echo "[ERROR] No se encontro el ejecutable de PHP."
    echo "Instalalo facilmente con Homebrew ejecutando:"
    echo "  brew install php"
    echo "O utiliza MAMP / XAMPP para macOS."
    echo ""
    read -r -p "Presiona Enter para salir..."
    exit 1
fi

echo "Usando PHP: $PHP_BIN ($("$PHP_BIN" -v | head -n 1))"
echo "Servidor local corriendo en: http://localhost:8000"
echo "Presiona Ctrl + C en esta ventana para detener el servidor."
echo ""

# Open default browser on macOS (or Linux fallback)
if command -v open >/dev/null 2>&1; then
    open "http://localhost:8000" &
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "http://localhost:8000" &
fi

# Start PHP built-in server
exec "$PHP_BIN" -S localhost:8000

