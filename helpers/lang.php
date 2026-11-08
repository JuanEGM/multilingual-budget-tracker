<?php
/**
 * Internationalization (i18n) & Helper Utilities
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

// Ensure PHP session is active for tracking language & flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Supported language codes:
 * - en: English
 * - es: Spanish
 * - fr: French
 * - pt: Portuguese
 */
const SUPPORTED_LANGS = ['en', 'es', 'fr', 'pt'];
const DEFAULT_LANG = 'es';

// Fallback to default if no session language is set
if (!isset($_SESSION['lang']) || !in_array($_SESSION['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['lang'] = DEFAULT_LANG;
}

/**
 * Retrieve current active language code ('en', 'es', 'fr', 'pt')
 * 
 * @return string
 */
function current_lang(): string
{
    return $_SESSION['lang'] ?? DEFAULT_LANG;
}

/**
 * Load dictionary for a specific language with static caching
 * 
 * @param string $lang
 * @return array
 */
function load_language_dict(string $lang): array
{
    static $dictionaries = [];

    if (!isset($dictionaries[$lang])) {
        $filePath = dirname(__DIR__) . "/lang/{$lang}.php";
        if (file_exists($filePath)) {
            $dictionaries[$lang] = require $filePath;
        } else {
            $dictionaries[$lang] = [];
        }
    }

    return $dictionaries[$lang];
}

/**
 * Translate a key into the active language
 * 
 * @param string $key Dictionary key
 * @param array $replacements Key-value pairs for placeholders (e.g. [':day' => 15])
 * @return string Translated string or key name if missing
 */
function __(string $key, array $replacements = []): string
{
    $current = current_lang();
    $dict = load_language_dict($current);

    // Fallback to English dictionary if key is not found in current language
    if (!isset($dict[$key]) && $current !== 'en') {
        $fallbackDict = load_language_dict('en');
        $text = $fallbackDict[$key] ?? $key;
    } else {
        $text = $dict[$key] ?? $key;
    }

    // Replace optional placeholders
    if (!empty($replacements)) {
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace($placeholder, (string)$value, $text);
        }
    }

    return $text;
}

/**
 * Formats a decimal amount into a currency string
 * 
 * @param float $amount
 * @param string|null $currencySymbol
 * @return string
 */
function format_currency(float $amount, string $currencySymbol = '$'): string
{
    return $currencySymbol . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Formats a YYYY-MM-DD date string according to active language
 * 
 * @param string $dateStr
 * @return string
 */
function format_date(string $dateStr): string
{
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return htmlspecialchars($dateStr);
    }

    $lang = current_lang();
    if ($lang === 'es' || $lang === 'fr' || $lang === 'pt') {
        // e.g. 21/10/2026
        return date('d/m/Y', $timestamp);
    }

    // Default English e.g. Oct 21, 2026
    return date('M d, Y', $timestamp);
}

/**
 * Flash message helper: set message
 * 
 * @param string $type ('success' | 'danger' | 'warning' | 'info')
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Flash message helper: retrieve and consume message
 * 
 * @return array|null
 */
function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generates an appropriate relative URL to a resource from anywhere in the application
 * (Works cleanly both from root scripts like index.php and from subfolders like pages/ or controllers/)
 * 
 * @param string $path Target path relative to project root (e.g. 'pages/cuentas.php', 'index.php')
 * @return string
 */
function app_url(string $path = ''): string
{
    $currentScript = $_SERVER['PHP_SELF'] ?? '/index.php';
    $dirName = basename(dirname($currentScript));
    $prefix = ($dirName === 'pages' || $dirName === 'controllers') ? '../' : '';
    return $prefix . ltrim($path, '/');
}
