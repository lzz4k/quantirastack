<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'quantira_stack');
define('DB_USER', 'root');
define('DB_PASS', '');
define('AES_KEY', 'Qs_8kX2!vN7zR4wY');

function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

function encryptPassword($password) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-128-CBC', AES_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptPassword($encrypted_password) {
    $data = base64_decode($encrypted_password);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-128-CBC', AES_KEY, 0, $iv);
}

function verifyPassword($input_password, $stored_password) {
    if (preg_match('/^\$2y\$/', $stored_password)) {
        return password_verify($input_password, $stored_password);
    }
    return decryptPassword($stored_password) === $input_password;
}
