<?php
session_start();
require_once __DIR__ . '/../config/install.php';
ensureDatabase();

$current_script = basename($_SERVER['SCRIPT_NAME']);
$public_pages = ['login.php', 'register.php', 'logout.php'];
if (!in_array($current_script, $public_pages) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function trackPageView() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO page_views (page_url, visitor_ip, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([
            $_SERVER['REQUEST_URI'] ?? '/',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
    }
}
trackPageView();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'QUANTIRA STACK - Cloud & Software Solutions'; ?></title>
    <meta name="description" content="Cloud & software solutions that ship faster, scale smarter, and stay secure.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="index.php" class="nav-logo">
                <img src="assets/logo.png" alt="Quantira Stack Logo" class="logo-img">
                <span class="logo-text">QUANTIRA<span class="logo-accent">-S</span></span>
            </a>
            <ul class="nav-links" id="navLinks">
                <li><a href="#services">Services</a></li>
                <li><a href="#solutions">Solutions</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact" class="nav-cta">Get Started</a></li>
                <?php
                $rootPath = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'admin') ? '../' : '';
                ?>
                <li class="nav-user" id="navUser">
                    <div class="nav-user-toggle" onclick="document.getElementById('navUser').classList.toggle('open')">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        <i class="fas fa-chevron-down" style="font-size:0.6rem;margin-left:0.2rem;"></i>
                    </div>
                    <div class="nav-user-dropdown">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="<?php echo $rootPath; ?>admin/index.php" target="_blank"><i class="fas fa-shield-halved"></i> Admin Panel</a>
                        <?php endif; ?>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                    </div>
                </li>
            </ul>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
