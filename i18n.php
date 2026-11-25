<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

$supportedLanguages = ['en', 'fr'];
$requestedLanguage = $_GET['lang'] ?? null;
if (is_string($requestedLanguage) && in_array($requestedLanguage, $supportedLanguages, true)) {
    $_SESSION['language'] = $requestedLanguage;
    $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('lumidachris_language', $requestedLanguage, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => false,
        'samesite' => 'Strict',
    ]);
} elseif (!isset($_SESSION['language'])) {
    $cookieLanguage = $_COOKIE['lumidachris_language'] ?? '';
    $_SESSION['language'] = is_string($cookieLanguage) && in_array($cookieLanguage, $supportedLanguages, true)
        ? $cookieLanguage
        : 'en';
}

$currentLanguage = $_SESSION['language'] === 'fr' ? 'fr' : 'en';
$languageFile = __DIR__ . '/languages/' . $currentLanguage . '.php';
$fallbackFile = __DIR__ . '/languages/en.php';
$translations = is_file($languageFile) ? require $languageFile : [];
$fallbackTranslations = is_file($fallbackFile) ? require $fallbackFile : [];

if (!is_array($translations)) {
    $translations = [];
}
if (!is_array($fallbackTranslations)) {
    $fallbackTranslations = [];
}

function t(string $key, array $parameters = []): string
{
    global $translations, $fallbackTranslations;
    $value = $translations[$key] ?? $fallbackTranslations[$key] ?? $key;
    foreach ($parameters as $name => $parameter) {
        $value = str_replace('{' . $name . '}', (string) $parameter, $value);
    }
    return $value;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
