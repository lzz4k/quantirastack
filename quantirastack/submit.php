<?php
session_start();
require_once __DIR__ . '/config/install.php';
ensureDatabase();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

$errors = [];

if (empty($full_name) || strlen($full_name) > 255) {
    $errors[] = 'Please enter a valid full name.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($subject)) {
    $errors[] = 'Please select a subject.';
}

if (empty($message) || strlen($message) > 5000) {
    $errors[] = 'Please enter a message (max 5000 characters).';
}

if (empty($errors)) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE email = ? AND submitted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$email]);
        $count = $stmt->fetchColumn();
        if ($count >= 3) {
            $errors[] = 'Too many submissions. Please try again later.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Service temporarily unavailable. Please try again.';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO contacts (full_name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $full_name,
        $email,
        $subject,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    unset($_SESSION['csrf_token']);

    echo json_encode(['success' => true, 'message' => "Message sent successfully! We'll get back to you soon."]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    exit;
}
