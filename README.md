# Gestor de Presupuesto Personal y Control de Gastos — Fase 2

Una aplicación web fintech moderna, modular y ligera para la gestión financiera personal, desarrollada con **PHP 8.x nativo**, **MySQL / MariaDB (PDO)**, **Bootstrap 5**, arquitectura **multi-entorno** (XAMPP, MAMP, Laragon, Docker) y soporte **multilingüe completo en 4 idiomas** (Español, Inglés, Francés y Portugués).

---

## 🚀 Nuevas Funcionalidades y Mejoras

### 1. 🔄 Transacciones y Transferencias entre Cuentas y Tarjetas
- **Transferencias Directas**: Mueve dinero entre cuentas bancarias, de tarjeta a cuenta, de cuenta a tarjeta o entre billeteras de efectivo.
- **Transaccionalidad Atómica (ACID)**: Descuento en la cuenta de origen y abono en la cuenta de destino ejecutados mediante transacciones seguras de base de datos (`beginTransaction` / `commit`).
- **Conservación Patrimonial**: Las transferencias internas no alteran el balance de gastos ni ingresos netos del patrimonio global, reflejándose con exactitud en los saldos de cada tarjeta o cuenta.
- **Reversión Automática**: Al eliminar una transacción de transferencia, el sistema devuelve automáticamente los fondos a la cuenta de origen y los descuenta de la cuenta de destino.
- **Botón Swap**: Inversión rápida de origen y destino con un solo clic.

### 2. 📅 Suscripciones Quincenales, Anuales y Mensuales
- **Soporte de Periodicidades**:
  - **Mensual**: Cobro o pago en un día específico del mes (ej. día 5).
  - **Quincenal**: Pagos divididos en dos días del mes (ej. días 15 y 30 o quincenas laborales).
  - **Anual**: Pagos o renovaciones de periodicidad anual con selección de mes y día (ej. Amazon Prime, seguros, dominios).
- **Cálculo de Equivalente Mensual**: El sistema pondera automáticamente las suscripciones quincenales (`monto × 2`) y anuales (`monto ÷ 12`) para proyectar el flujo de caja mensual y el saldo restante proyectado en cada tarjeta de crédito o débito.
- **Aplicación por Ciclos**: Registro en el libro diario de compromisos mensuales, quincenales o anuales con prevención de duplicados por ciclo.

### 3. 🔌 Conexión Multi-Entorno (XAMPP, MAMP, Laragon, Docker)
- **Auto-Detección Inteligente**: El sistema intenta conectarse de forma secuencial y transparente a los entornos locales más comunes:
  1. Variables de entorno en `.env` o del sistema operativo.
  2. **XAMPP / Laragon**: `127.0.0.1:3306` con usuario `root` y sin contraseña.
  3. **MAMP**: `127.0.0.1:8889` y `127.0.0.1:3306` con usuario `root` y contraseña `root`.
  4. **Docker**: Servicio de red `db:3306`.
- **Auto-Creación de Base de Datos**: Si el servidor MySQL está activo pero la base de datos `gestor_financiero` aún no ha sido creada, el script de conexión la crea automáticamente (`CREATE DATABASE IF NOT EXISTS`) y aplica las migraciones estructurales sin requerir comandos manuales.
- **Soporte Docker Oficial**: Incluye `Dockerfile` y `docker-compose.yml` listos para producción y desarrollo local con un solo comando.
- **Soporte para archivo `.env`**: Plantilla `.env.example` lista para personalizar credenciales sin dependencias externas.

### 4. 🌍 Soporte Multilingüe (i18n): Español, Inglés, Francés y Portugués
- **4 Idiomas Nativos**:
  - 🇪🇸 **Español** (`es`)
  - 🇺🇸 **English** (`en`)
  - 🇫🇷 **Français** (`fr`)
  - 🇵🇹 **Português** (`pt`)
- **URLs Limpias**: Cambio instantáneo de idioma desde el menú lateral persistido en sesión PHP sin ensuciar la barra de direcciones con parámetros `?lang=`.
- **Formateo Regional de Fechas**: Fechas adaptadas automáticamente según el estándar lingüístico de cada idioma.

---

## 💻 Guía de Inicio Rápido por Sistema Operativo

Selecciona las instrucciones según tu sistema operativo:

```
├── 🪟 Windows
├── 🍎 macOS
├── 🐧 Linux
└── 🐳 Docker (Multiplataforma)
```

---

### 🪟 1. Guía para Windows

#### Método 1: Inicio Rápido con 1 Clic (Recomendado)
1. Asegúrate de tener tu servidor MySQL activo (por ejemplo, el módulo **MySQL en verde** en XAMPP o Laragon).
2. Haz **doble clic en `iniciar.bat`** en la carpeta principal del proyecto.
3. El script detectará tu instalación de PHP, iniciará el servidor local y abrirá tu navegador automáticamente en:
   ```text
   http://localhost:8000
   ```

*(Opcional: Si deseas restaurar o importar manualmente la base de datos limpia, haz doble clic en `importar_bd.bat`)*.

---

#### Método 2: Con XAMPP
1. Inicia **Apache** y **MySQL** desde el **XAMPP Control Panel**.
2. Copia la carpeta del proyecto a:
   ```text
   C:\xampp\htdocs\gestor-financiero
   ```
3. Accede desde tu navegador a:
   ```text
   http://localhost/gestor-financiero/
   ```

---

#### Método 3: Con Laragon
1. Abre **Laragon** y pulsa **Start All**.
2. Coloca el proyecto en:
   ```text
   C:\laragon\www\gestor-financiero
   ```
3. Visita en tu navegador:
   ```text
   http://gestor-financiero.test
   ```

---

### 🍎 2. Guía para macOS

#### Método 1: Doble Clic en Finder (Estilo Nativo Mac)
1. Asegúrate de tener MySQL iniciado (vía **MAMP**, **Homebrew** o **XAMPP**).
2. En el **Finder** de tu Mac, ve a la carpeta del proyecto y haz **doble clic en `iniciar.command`**.
3. Se abrirá la Terminal de macOS, iniciará el servidor y se abrirá tu navegador predeterminado en:
   ```text
   http://localhost:8000
   ```

*(Opcional: Para importar o resetear la base de datos desde Finder, haz doble clic en `importar_bd.command`)*.

---

#### Método 2: Por Terminal de macOS
1. Abre la **Terminal** y navega a la carpeta del proyecto:
   ```bash
   cd "/ruta/al/proyecto/Gestor Financiero"
   ```
2. Inicia el servidor con el script ejecutable:
   ```bash
   ./iniciar.sh
   ```
3. Para importar la base de datos por terminal:
   ```bash
   ./importar_bd.sh
   ```

---

#### Método 3: Con MAMP para Mac
1. Abre **MAMP** y haz clic en **Start Servers**.
2. Por defecto MAMP utiliza el puerto `8889` con usuario `root` y contraseña `root`. **El sistema lo detecta de forma automática sin que tengas que configurar nada**.
3. Ejecuta `iniciar.command` o `./iniciar.sh`.

---

### 🐧 3. Guía para Linux

1. Abre una terminal en el directorio del proyecto:
   ```bash
   cd /ruta/al/proyecto/gestor-financiero
   ```
2. Asegúrate de tener instalado PHP (con extensión `pdo_mysql`) y MySQL/MariaDB:
   ```bash
   sudo apt update && sudo apt install php php-mysql mysql-server -y   # (Debian/Ubuntu)
   ```
3. Inicia la aplicación con el script:
   ```bash
   ./iniciar.sh
   ```
4. Abre en tu navegador `http://localhost:8000`.

---

### 🐳 4. Guía con Docker (Windows, macOS o Linux)

Si prefieres no instalar PHP ni MySQL en tu sistema y tienes **Docker** con **Docker Compose**:

1. Abre tu terminal o consola en la carpeta del proyecto.
2. Levanta los contenedores con un solo comando:
   ```bash
   docker compose up -d
   ```
3. Listo. Se levantará automáticamente:
   - **Contenedor Web**: Apache + PHP 8.2 en el puerto `8000`.
   - **Contenedor BD**: MariaDB 10.11 preconfigurado e inicializado con la base de datos `gestor_financiero`.
4. Abre en tu navegador:
   ```text
   http://localhost:8000
   ```
5. Para detener los contenedores cuando termines:
   ```bash
   docker compose down
   ```

---

## ⚙️ Configuración Personalizada (.env)

Si tu servidor de base de datos utiliza un puerto o contraseña diferente a los estándares de XAMPP/MAMP/Laragon:

1. Duplica el archivo `.env.example` y renómbralo a `.env`:
   ```bash
   cp .env.example .env
   ```
2. Ajusta tus valores:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=gestor_financiero
   DB_USER=root
   DB_PASS=tu_contraseña
   ```

---

## 🔒 Seguridad y Buenas Prácticas
- Consultas 100% preparadas mediante **PDO** con parámetros tipados para prevenir inyecciones SQL.
- Escape contextual contra XSS mediante `htmlspecialchars`.
- Transacciones de base de datos con control de rollback en transferencias y eliminaciones de registros.
- Interfaz reactiva fluida con almacenamiento local de preferencias de tema (Oscuro / Claro) y estado colapsado del menú lateral.

---
Desarrollado con ❤️ por JuanEGM. Todos los derechos reservados.
