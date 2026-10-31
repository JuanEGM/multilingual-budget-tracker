<?php
/**
 * Database Connection via PDO
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 1+
 * 
 * Provides a secure, persistent connection instance configured
 * with strict error modes and prepared statements.
 */

declare(strict_types=1);

// Database configuration settings
// Default values work out-of-the-box with standard XAMPP / WAMP / Laragon setups
$dbHost    = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort    = getenv('DB_PORT') ?: '3306';
$dbName    = getenv('DB_NAME') ?: 'gestor_financiero';
$dbUser    = getenv('DB_USER') ?: 'root';
$dbPass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbCharset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset} COLLATE utf8mb4_unicode_ci"
];

// Try preferred host (127.0.0.1) and fallback to 'localhost' if needed
$hostsToTry = array_unique([$dbHost, '127.0.0.1', 'localhost']);
$pdo = null;
$lastException = null;

foreach ($hostsToTry as $currentHost) {
    try {
        $dsn = "mysql:host={$currentHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        break; // Connected successfully
    } catch (PDOException $e) {
        $lastException = $e;
    }
}

if ($pdo !== null) {
    require_once __DIR__ . '/migrate.php';
    runAutoMigrations($pdo);
}

if ($pdo === null) {
    // Log the actual error securely
    error_log("Database Connection Error: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));

    // Display a user-friendly message
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-6">
                    <div class="card shadow-sm border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Database Connection Failed</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                Unable to establish a connection with MySQL/MariaDB.
                            </p>
                            <div class="alert alert-warning">
                                <strong>Tip:</strong> Ensure your MySQL service (e.g. XAMPP) is running and that you have imported <code>schema.sql</code>.
                            </div>
                            <details class="text-muted small">
                                <summary>Technical Details</summary>
                                <pre class="mt-2 p-2 bg-light border rounded"><?= htmlspecialchars($lastException ? $lastException->getMessage() : '') ?></pre>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
