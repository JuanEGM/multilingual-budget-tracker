<?php
/**
 * Database Connection via PDO with Universal Multi-Environment Support
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 2
 * 
 * Out-of-the-box compatibility with:
 * - XAMPP (Windows/Linux/Mac)
 * - MAMP (macOS/Windows default port 8889 and 3306)
 * - Laragon (Windows)
 * - Docker (docker-compose)
 * - Custom configurations via .env or system environment variables
 */

declare(strict_types=1);

// -------------------------------------------------------------
// Lightweight .env file loader (Zero external dependencies)
// -------------------------------------------------------------
(function () {
    $envFiles = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__) . '/.env.local'
    ];

    foreach ($envFiles as $envFile) {
        if (!file_exists($envFile) || !is_readable($envFile)) {
            continue;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $name  = trim($parts[0]);
                $value = trim($parts[1]);

                // Strip surrounding single/double quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                if (!empty($name) && getenv($name) === false) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
})();

// Base configuration
$dbName    = getenv('DB_NAME') ?: 'gestor_financiero';
$dbCharset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset} COLLATE utf8mb4_unicode_ci"
];

// -------------------------------------------------------------
// Multi-Environment Connection Candidate Presets
// -------------------------------------------------------------
// We build an array of presets to try in order of priority.
$candidates = [];

// 1. Explicit environment variables (if provided)
if (getenv('DB_HOST') !== false || getenv('DB_PORT') !== false || getenv('DB_USER') !== false) {
    $candidates[] = [
        'env_label' => 'Environment Variables (.env / system)',
        'host'      => getenv('DB_HOST') ?: '127.0.0.1',
        'port'      => (int) (getenv('DB_PORT') ?: 3306),
        'user'      => getenv('DB_USER') ?: 'root',
        'pass'      => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : ''
    ];
}

// 2. Standard XAMPP / Laragon preset (127.0.0.1:3306, user: root, pass: empty)
$candidates[] = [
    'env_label' => 'XAMPP / Laragon (127.0.0.1:3306)',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'user'      => 'root',
    'pass'      => ''
];

// 2b. Standard XAMPP / Laragon fallback with localhost socket
$candidates[] = [
    'env_label' => 'XAMPP / Laragon (localhost:3306)',
    'host'      => 'localhost',
    'port'      => 3306,
    'user'      => 'root',
    'pass'      => ''
];

// 3. MAMP Default preset (127.0.0.1:8889, user: root, pass: root)
$candidates[] = [
    'env_label' => 'MAMP Default (127.0.0.1:8889)',
    'host'      => '127.0.0.1',
    'port'      => 8889,
    'user'      => 'root',
    'pass'      => 'root'
];

// 3b. MAMP Secondary port (127.0.0.1:3306, user: root, pass: root)
$candidates[] = [
    'env_label' => 'MAMP Port 3306 (127.0.0.1:3306)',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'user'      => 'root',
    'pass'      => 'root'
];

// 4. Docker preset (service name 'db' or 'mysql', user: root, pass: root)
$candidates[] = [
    'env_label' => 'Docker Compose Network (db:3306)',
    'host'      => 'db',
    'port'      => 3306,
    'user'      => 'root',
    'pass'      => 'root'
];

// Remove duplicate candidate configurations
$uniqueCandidates = [];
$seenKeys = [];
foreach ($candidates as $cand) {
    $key = "{$cand['host']}:{$cand['port']}:{$cand['user']}:{$cand['pass']}";
    if (!isset($seenKeys[$key])) {
        $seenKeys[$key] = true;
        $uniqueCandidates[] = $cand;
    }
}

$pdo = null;
$connectedEnvironment = null;
$lastException = null;

// Iterate candidates and attempt connection
foreach ($uniqueCandidates as $cand) {
    try {
        // Step A: Connect to MySQL server without selecting the database first,
        // so we can automatically create the database if it doesn't exist yet.
        $serverDsn = "mysql:host={$cand['host']};port={$cand['port']};charset={$dbCharset}";
        $serverPdo = new PDO($serverDsn, $cand['user'], $cand['pass'], $options);

        // Ensure database exists automatically
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        // Step B: Now connect directly to the target database
        $dbDsn = "mysql:host={$cand['host']};port={$cand['port']};dbname={$dbName};charset={$dbCharset}";
        $pdo = new PDO($dbDsn, $cand['user'], $cand['pass'], $options);

        $connectedEnvironment = $cand['env_label'];
        break; // Successfully connected!
    } catch (PDOException $e) {
        $lastException = $e;
    }
}

// If connected, execute automated schema migrations (adds any missing columns/tables gracefully)
if ($pdo !== null) {
    require_once __DIR__ . '/migrate.php';
    runAutoMigrations($pdo);
}

// If all connection attempts failed, render diagnostic dashboard
if ($pdo === null) {
    error_log("Universal Database Connection Failed: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));

    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Conexión — Gestor Financiero</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body { background: #0f172a; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
            .card-err { background: #1e293b; border: 1px solid #334155; border-radius: 1rem; }
            .badge-env { font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 9999px; }
        </style>
    </head>
    <body class="d-flex align-items-center min-vh-100 py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card card-err shadow-lg p-4 p-md-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; font-size: 1.6rem;">
                                <i class="bi bi-database-x"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-1">No se pudo conectar con MySQL / MariaDB</h3>
                                <p class="text-secondary mb-0 small">Compatible automáticamente con XAMPP, MAMP, Laragon y Docker</p>
                            </div>
                        </div>

                        <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis rounded-3 mb-4">
                            <strong><i class="bi bi-lightbulb-fill me-1"></i> Diagnóstico:</strong> 
                            El sistema intentó conectarse con los puertos y credenciales habituales pero el servicio de base de datos no está respondiendo.
                        </div>

                        <h6 class="fw-bold text-uppercase text-secondary small mb-3">Solución según tu entorno local:</h6>
                        
                        <div class="accordion mb-4" id="envHelpAccordion">
                            <!-- XAMPP -->
                            <div class="accordion-item bg-dark border-secondary">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-dark text-white shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#helpXampp">
                                        <i class="bi bi-hdd-network me-2 text-warning"></i> Si usas <strong>XAMPP</strong>
                                    </button>
                                </h2>
                                <div id="helpXampp" class="accordion-collapse collapse" data-bs-parent="#envHelpAccordion">
                                    <div class="accordion-body small text-secondary">
                                        Abre el panel de control de <strong>XAMPP</strong> y haz clic en <strong>Start</strong> en el módulo <strong>MySQL</strong>. Debe ponerse en verde (puerto 3306).
                                    </div>
                                </div>
                            </div>

                            <!-- MAMP -->
                            <div class="accordion-item bg-dark border-secondary">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-dark text-white shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#helpMamp">
                                        <i class="bi bi-apple me-2 text-info"></i> Si usas <strong>MAMP</strong>
                                    </button>
                                </h2>
                                <div id="helpMamp" class="accordion-collapse collapse" data-bs-parent="#envHelpAccordion">
                                    <div class="accordion-body small text-secondary">
                                        Inicia los servidores en MAMP. Si tu puerto de MySQL es <strong>8889</strong> con usuario <code>root</code> y contraseña <code>root</code>, el gestor lo detectará de forma automática. También puedes crear un archivo <code>.env</code> copiando <code>.env.example</code>.
                                    </div>
                                </div>
                            </div>

                            <!-- Laragon -->
                            <div class="accordion-item bg-dark border-secondary">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-dark text-white shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#helpLaragon">
                                        <i class="bi bi-layers me-2 text-primary"></i> Si usas <strong>Laragon</strong>
                                    </button>
                                </h2>
                                <div id="helpLaragon" class="accordion-collapse collapse" data-bs-parent="#envHelpAccordion">
                                    <div class="accordion-body small text-secondary">
                                        Haz clic en <strong>Start All</strong> en Laragon. MySQL corre normalmente en <code>127.0.0.1:3306</code> con usuario <code>root</code> y sin contraseña.
                                    </div>
                                </div>
                            </div>

                            <!-- Docker -->
                            <div class="accordion-item bg-dark border-secondary">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-dark text-white shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#helpDocker">
                                        <i class="bi bi-box-seam me-2 text-info"></i> Si usas <strong>Docker</strong>
                                    </button>
                                </h2>
                                <div id="helpDocker" class="accordion-collapse collapse" data-bs-parent="#envHelpAccordion">
                                    <div class="accordion-body small text-secondary">
                                        Ejecuta en tu terminal dentro de la carpeta del proyecto:
                                        <pre class="bg-black p-2 rounded text-light mt-2 mb-0"><code>docker compose up -d</code></pre>
                                        Esto levantará Apache + PHP y MariaDB con persistencia y configuración lista.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <details class="text-secondary small">
                            <summary class="cursor-pointer">Detalles técnicos del error</summary>
                            <pre class="mt-2 p-3 bg-black border border-secondary rounded text-danger-emphasis"><?= htmlspecialchars($lastException ? $lastException->getMessage() : 'Desconocido') ?></pre>
                        </details>

                        <div class="mt-4 text-center">
                            <button onclick="window.location.reload();" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reintentar Conexión
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Global helper to access the PDO instance
 * 
 * @return PDO
 */
function getDB(): PDO
{
    global $pdo;
    return $pdo;
}

/**
 * Returns diagnostic label of active connection environment
 * 
 * @return string
 */
function getDBEnvironmentLabel(): string
{
    global $connectedEnvironment;
    return $connectedEnvironment ?? 'MySQL Local';
}
