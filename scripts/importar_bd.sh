#!/usr/bin/env bash
# ========================================================
# Importador de Base de Datos - macOS / Linux
# ========================================================

echo "===================================================="
echo "  Importando Base de Datos en MySQL / MariaDB (macOS)"
echo "  Compatible con MAMP, Homebrew, XAMPP y Docker"
echo "===================================================="
echo ""

# Move to project root directory
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR" || exit 1

MYSQL_BIN=""

# 1. Check if mysql is in PATH
if command -v mysql >/dev/null 2>&1; then
    MYSQL_BIN="$(command -v mysql)"
# 2. Check Apple Silicon Homebrew
elif [ -x "/opt/homebrew/bin/mysql" ]; then
    MYSQL_BIN="/opt/homebrew/bin/mysql"
# 3. Check Intel Homebrew
elif [ -x "/usr/local/bin/mysql" ]; then
    MYSQL_BIN="/usr/local/bin/mysql"
# 4. Check MAMP for macOS
elif [ -x "/Applications/MAMP/Library/bin/mysql" ]; then
    MYSQL_BIN="/Applications/MAMP/Library/bin/mysql"
# 5. Check XAMPP for macOS
elif [ -x "/Applications/XAMPP/xamppfiles/bin/mysql" ]; then
    MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
fi

if [ -z "$MYSQL_BIN" ]; then
    echo "[ERROR] No se encontro el cliente MySQL."
    echo "Instalalo con Homebrew: brew install mysql-client"
    echo "O asegurate de tener MAMP / XAMPP en /Applications."
    echo ""
    read -r -p "Presiona Enter para salir..."
    exit 1
fi

echo "Usando cliente MySQL: $MYSQL_BIN"
echo "Ejecutando importacion de database/schema.sql..."
echo ""

# Try standard connection (127.0.0.1:3306, user: root, without password)
if "$MYSQL_BIN" -h 127.0.0.1 -P 3306 -u root < "$DIR/database/schema.sql" 2>/dev/null; then
    IMPORT_SUCCESS=1
# Try MAMP default (127.0.0.1:8889, user: root, pass: root)
elif "$MYSQL_BIN" -h 127.0.0.1 -P 8889 -u root -proot < "$DIR/database/schema.sql" 2>/dev/null; then
    IMPORT_SUCCESS=1
# Try MAMP port 3306 with password root
elif "$MYSQL_BIN" -h 127.0.0.1 -P 3306 -u root -proot < "$DIR/database/schema.sql" 2>/dev/null; then
    IMPORT_SUCCESS=1
# Try socket connection fallback
elif "$MYSQL_BIN" -u root < "$DIR/database/schema.sql" 2>/dev/null; then
    IMPORT_SUCCESS=1
else
    IMPORT_SUCCESS=0
fi

if [ "$IMPORT_SUCCESS" -eq 1 ]; then
    echo "===================================================="
    echo "  [EXITO] Base de datos importada correctamente!"
    echo "  - Base de datos: gestor_financiero"
    echo "  - Tablas: cuentas, transacciones_fijas, transacciones"
    echo "===================================================="
else
    echo "===================================================="
    echo "  [ERROR] No se pudo importar la base de datos."
    echo "  Verifica que tu servidor MySQL este iniciado (MAMP, Homebrew, etc.)"
    echo "  Comando manual sugerido:"
    echo "    $MYSQL_BIN -u root < database/schema.sql"
    echo "  O con MAMP:"
    echo "    $MYSQL_BIN -h 127.0.0.1 -P 8889 -u root -proot < database/schema.sql"
    echo "===================================================="
fi

echo ""
read -r -p "Presiona Enter para continuar..."
exit 0

