<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'hackcraft_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Конфигурация сайта
define('SITE_NAME', 'HackCraft');
define('SITE_URL', 'http://localhost/hackcraft');
define('SESSION_TIMEOUT', 10800); // 3 часа в секундах

// Шифрование для статей
define('ENCRYPTION_KEY', 'hackcraft_secret_key_2026');

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Функция для шифрования текста
function encrypt_text($text) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($text, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

// Функция для дешифрования текста
function decrypt_text($text) {
    list($encrypted_data, $iv) = explode('::', base64_decode($text), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
}

// Старт сессии
session_start();

// Проверка таймаута сессии
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();
?>