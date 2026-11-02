<?php
if (ob_get_level() === 0) {
    ob_start();
}

// Prevent PHP version leakage in HTTP headers
@ini_set('expose_php', 'off');
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

// Set secure session cookie parameters before starting the session.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';