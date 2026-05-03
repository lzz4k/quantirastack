<?php
session_start();
require_once __DIR__ . '/../config/install.php';
ensureDatabase();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDBConnection();
$flash = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_role' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if ($user) {
            $newRole = $user['role'] === 'admin' ? 'user' : 'admin';
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $uid]);
            $flash = $newRole === 'admin' ? 'User promoted to admin.' : 'Admin demoted to user.';
        }
    }

    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $flash = 'User deleted.';
        } else {
            $flash = 'You cannot delete your own account.';
        }
    }

    if ($action === 'update_status' && isset($_POST['contact_id'], $_POST['status'])) {
        $cid = (int)$_POST['contact_id'];
        $status = $_POST['status'];
        if (in_array($status, ['new', 'read', 'replied', 'archived'])) {
            $stmt = $pdo->prepare("UPDATE contacts SET status = ? WHERE id = ?");
            $stmt->execute([$status, $cid]);
            $flash = 'Message status updated.';
        }
    }

    if ($action === 'delete_message' && isset($_POST['contact_id'])) {
        $cid = (int)$_POST['contact_id'];
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$cid]);
        $flash = 'Message deleted.';
    }
}

// Fetch data
$users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$messages = $pdo->query("SELECT * FROM contacts ORDER BY submitted_at DESC")->fetchAll();

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalMessages = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
$totalPageViews = $pdo->query("SELECT COUNT(*) FROM page_views")->fetchColumn();
$newMessages = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'new'")->fetchColumn();

$messagesByStatus = $pdo->query("SELECT status, COUNT(*) as cnt FROM contacts GROUP BY status")->fetchAll();
$statusCounts = ['new' => 0, 'read' => 0, 'replied' => 0, 'archived' => 0];
foreach ($messagesByStatus as $row) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
}

$recentUsers = $pdo->query("SELECT username, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentViews = $pdo->query("SELECT DATE(viewed_at) as d, COUNT(*) as cnt FROM page_views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(viewed_at) ORDER BY d")->fetchAll();

$activeTab = $_GET['tab'] ?? 'users';
if (!in_array($activeTab, ['users', 'messages', 'statistics'])) {
    $activeTab = 'users';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - QUANTIRA STACK</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            background: var(--bg-primary);
            min-height: 100vh;
        }

        .admin-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(12, 10, 20, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 2px solid var(--border-color);
            height: 64px;
        }

        .admin-nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .admin-nav-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-nav-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .admin-nav-logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 10px;
        }

        .admin-nav-logo span {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .admin-nav-logo .accent {
            color: var(--accent);
        }

        .admin-nav-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color);
        }

        .admin-nav-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-nav-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .admin-nav-back:hover {
            color: var(--text-primary);
            border-color: var(--accent);
            background: var(--accent-glow);
        }

        .admin-nav-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .admin-nav-user i {
            color: var(--accent);
        }

        .admin-nav-logout {
            color: var(--text-muted) !important;
            padding: 0.3rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .admin-nav-logout:hover {
            color: var(--accent-red) !important;
        }

        .admin-main {
            padding-top: 64px;
            min-height: 100vh;
        }

        .admin-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .admin-flash {
            background: rgba(52, 211, 153, 0.1);
            border: 1px solid rgba(52, 211, 153, 0.25);
            color: var(--accent-green);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: fadeInUp 0.4s ease;
        }

        .admin-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }

        .admin-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            border: 2px solid transparent;
            border-bottom: none;
            transition: all 0.3s ease;
            position: relative;
            bottom: -2px;
        }

        .admin-tab:hover {
            color: var(--text-secondary);
            background: rgba(168, 85, 247, 0.05);
        }

        .admin-tab.active {
            color: var(--accent);
            background: var(--bg-card);
            border-color: var(--border-color);
        }

        .admin-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--bg-card);
        }

        .admin-tab i {
            font-size: 0.85rem;
        }

        .tab-badge {
            background: var(--accent);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.45rem;
            border-radius: 50px;
            min-width: 18px;
            text-align: center;
        }

        .tab-badge.new {
            background: var(--accent-red);
        }

        .admin-panel {
            display: none;
            animation: fadeInUp 0.3s ease;
        }

        .admin-panel.active {
            display: block;
        }

        .admin-table-wrapper {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .admin-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .admin-table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-table-header h3 i {
            color: var(--accent);
        }

        .admin-table-header .count {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .admin-table-scroll {
            overflow-x: auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: all var(--transition);
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-accent);
        }

        .stat-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 8px 0 rgba(109, 40, 217, 0.3), 0 20px 50px rgba(168, 85, 247, 0.2);
        }

        .stat-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--gradient-accent);
            border-radius: 14px;
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            box-shadow: 0 3px 0 rgba(109, 40, 217, 0.5), 0 6px 15px rgba(168, 85, 247, 0.3);
        }

        .stat-card-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .stat-card-value {
            display: block;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-card-sub {
            display: block;
            font-size: 0.8rem;
            color: var(--accent);
            font-weight: 500;
            margin-top: 0.25rem;
        }

        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-section-title i {
            color: var(--accent);
        }

        .status-bar-chart {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .status-bar-item {
            flex: 1;
            min-width: 140px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            text-align: center;
        }

        .status-bar-item .bar {
            height: 6px;
            border-radius: 3px;
            margin: 0.75rem auto 0;
            max-width: 100px;
        }

        .status-bar-item .bar.new { background: var(--accent-red); }
        .status-bar-item .bar.read { background: var(--accent); }
        .status-bar-item .bar.replied { background: var(--accent-green); }
        .status-bar-item .bar.archived { background: var(--text-muted); }

        .status-bar-item .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-bar-item .value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-top: 0.25rem;
        }

        .recent-list {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .recent-list-title {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recent-list-title i {
            color: var(--accent);
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.5rem;
            border-bottom: 1px solid rgba(46, 36, 72, 0.5);
            transition: background 0.2s ease;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item:hover {
            background: rgba(168, 85, 247, 0.03);
        }

        .recent-item-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .recent-item-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .recent-item-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .recent-item-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .views-chart {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
        }

        .views-chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            height: 160px;
            padding-top: 1rem;
        }

        .views-chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            height: 100%;
            justify-content: flex-end;
        }

        .views-chart-bar {
            width: 100%;
            max-width: 50px;
            background: var(--gradient-accent);
            border-radius: 6px 6px 0 0;
            min-height: 4px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(168, 85, 247, 0.3);
        }

        .views-chart-bar:hover {
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.5);
            transform: scaleY(1.05);
            transform-origin: bottom;
        }

        .views-chart-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .views-chart-value {
            font-size: 0.7rem;
            color: var(--accent);
            font-weight: 600;
        }

        .msg-subject {
            color: var(--text-primary);
            font-weight: 600;
        }

        .msg-preview {
            color: var(--text-muted);
            font-size: 0.8rem;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .status-badge.new {
            background: rgba(244, 63, 94, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .status-badge.read {
            background: rgba(168, 85, 247, 0.15);
            color: var(--accent);
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        .status-badge.replied {
            background: rgba(52, 211, 153, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(52, 211, 153, 0.3);
        }

        .status-badge.archived {
            background: rgba(122, 106, 144, 0.15);
            color: var(--text-muted);
            border: 1px solid rgba(122, 106, 144, 0.3);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .role-badge.admin {
            background: rgba(168, 85, 247, 0.15);
            color: var(--accent);
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        .role-badge.user {
            background: rgba(52, 211, 153, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(52, 211, 153, 0.2);
        }

        .admin-actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: nowrap;
        }

        .admin-btn-sm {
            border: 1px solid;
            border-radius: 6px;
            padding: 0.25rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 600;
            font-family: var(--font-family);
            cursor: pointer;
            transition: all 0.2s ease;
            background: transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }

        .admin-btn-sm.purple {
            color: var(--accent);
            border-color: rgba(168, 85, 247, 0.3);
            background: rgba(168, 85, 247, 0.08);
        }

        .admin-btn-sm.purple:hover {
            background: rgba(168, 85, 247, 0.2);
        }

        .admin-btn-sm.green {
            color: var(--accent-green);
            border-color: rgba(52, 211, 153, 0.3);
            background: rgba(52, 211, 153, 0.08);
        }

        .admin-btn-sm.green:hover {
            background: rgba(52, 211, 153, 0.2);
        }

        .admin-btn-sm.red {
            color: var(--accent-red);
            border-color: rgba(244, 63, 94, 0.3);
            background: rgba(244, 63, 94, 0.08);
        }

        .admin-btn-sm.red:hover {
            background: rgba(244, 63, 94, 0.2);
        }

        .admin-btn-sm.orange {
            color: var(--accent-orange);
            border-color: rgba(245, 158, 11, 0.3);
            background: rgba(245, 158, 11, 0.08);
        }

        .admin-btn-sm.orange:hover {
            background: rgba(245, 158, 11, 0.2);
        }

        .admin-status-select {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
            font-family: var(--font-family);
            cursor: pointer;
        }

        .admin-status-select:focus {
            border-color: var(--accent);
            outline: none;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            margin-bottom: 1rem;
            display: block;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        .confirm-delete {
            display: none;
        }

        .confirm-delete.show {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        @media (max-width: 768px) {
            .admin-nav-inner {
                flex-wrap: wrap;
                height: auto;
                padding: 0.75rem 1rem;
                gap: 0.5rem;
            }

            .admin-nav-divider {
                display: none;
            }

            .admin-nav-title {
                font-size: 0.8rem;
            }

            .admin-main {
                padding-top: 100px;
            }

            .admin-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .admin-tab {
                font-size: 0.8rem;
                padding: 0.7rem 1rem;
                white-space: nowrap;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .status-bar-chart {
                flex-direction: column;
            }

            .status-bar-item {
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="admin-navbar">
        <div class="admin-nav-inner">
            <div class="admin-nav-left">
                <a href="../index.php" class="admin-nav-logo">
                    <img src="../assets/logo.png" alt="Quantira Stack">
                    <span>QUANTIRA<span class="accent">-S</span></span>
                </a>
                <div class="admin-nav-divider"></div>
                <div class="admin-nav-title">
                    <i class="fas fa-shield-halved"></i> Admin Dashboard
                </div>
            </div>
            <div class="admin-nav-right">
                <a href="../index.php" class="admin-nav-back">
                    <i class="fas fa-arrow-left"></i> Back to Site
                </a>
                <div class="admin-nav-user">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../logout.php" class="admin-nav-logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="admin-main">
        <div class="admin-content">
            <?php if ($flash): ?>
                <div class="admin-flash">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flash); ?>
                </div>
            <?php endif; ?>

            <div class="admin-tabs">
                <a href="?tab=users" class="admin-tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Users
                    <span class="tab-badge"><?php echo $totalUsers; ?></span>
                </a>
                <a href="?tab=messages" class="admin-tab <?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if ($newMessages > 0): ?>
                        <span class="tab-badge new"><?php echo $newMessages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=statistics" class="admin-tab <?php echo $activeTab === 'statistics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Statistics
                </a>
            </div>

            <!-- USERS TAB -->
            <div class="admin-panel <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="panel-users">
                <div class="admin-table-wrapper">
                    <div class="admin-table-header">
                        <h3><i class="fas fa-users"></i> All Users</h3>
                        <span class="count"><?php echo $totalUsers; ?> total &middot; <?php echo $totalAdmins; ?> admins</span>
                    </div>
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>No users found.</p>
                        </div>
                    <?php else: ?>
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="muted"><?php echo $u['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                            <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                                <span style="font-size:0.7rem;color:var(--accent);margin-left:0.3rem;">(you)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="purple"><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td>
                                            <span class="role-badge <?php echo $u['role']; ?>">
                                                <i class="fas fa-<?php echo $u['role'] === 'admin' ? 'shield-halved' : 'user'; ?>"></i>
                                                <?php echo ucfirst($u['role']); ?>
                                            </span>
                                        </td>
                                        <td class="muted"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <form method="POST" style="display:inline;" onsubmit="return confirmToggleRole(this, '<?php echo htmlspecialchars($u['username']); ?>', '<?php echo $u['role']; ?>')">
                                                    <input type="hidden" name="action" value="toggle_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="admin-btn-sm <?php echo $u['role'] === 'admin' ? 'orange' : 'purple'; ?>">
                                                        <i class="fas fa-<?php echo $u['role'] === 'admin' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                                        <?php echo $u['role'] === 'admin' ? 'Demote' : 'Make Admin'; ?>
                                                    </button>
                                                </form>
                                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($u['username']); ?>? This cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="admin-btn-sm red">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MESSAGES TAB -->
            <div class="admin-panel <?php echo $activeTab === 'messages' ? 'active' : ''; ?>" id="panel-messages">
                <div class="admin-table-wrapper">
                    <div class="admin-table-header">
                        <h3><i class="fas fa-envelope"></i> Contact Messages</h3>
                        <span class="count"><?php echo $totalMessages; ?> total &middot; <?php echo $newMessages; ?> new</span>
                    </div>
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>From</th>
                                        <th>Email</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $m): ?>
                                    <tr>
                                        <td class="muted"><?php echo $m['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($m['full_name']); ?></strong></td>
                                        <td class="purple"><?php echo htmlspecialchars($m['email']); ?></td>
                                        <td class="msg-subject"><?php echo htmlspecialchars($m['subject'] ?? 'N/A'); ?></td>
                                        <td class="msg-preview" title="<?php echo htmlspecialchars($m['message']); ?>"><?php echo htmlspecialchars($m['message']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $m['status']; ?>">
                                                <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                                                <?php echo ucfirst($m['status']); ?>
                                            </span>
                                        </td>
                                        <td class="muted"><?php echo date('M d, Y H:i', strtotime($m['submitted_at'])); ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="contact_id" value="<?php echo $m['id']; ?>">
                                                    <select name="status" class="admin-status-select" onchange="this.form.submit()">
                                                        <option value="new" <?php echo $m['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                        <option value="read" <?php echo $m['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                                        <option value="replied" <?php echo $m['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                                        <option value="archived" <?php echo $m['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                                    </select>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?')">
                                                    <input type="hidden" name="action" value="delete_message">
                                                    <input type="hidden" name="contact_id" value="<?php echo $m['id']; ?>">
                                                    <button type="submit" class="admin-btn-sm red">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STATISTICS TAB -->
            <div class="admin-panel <?php echo $activeTab === 'statistics' ? 'active' : ''; ?>" id="panel-statistics">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                        <span class="stat-card-label">Total Users</span>
                        <span class="stat-card-value"><?php echo $totalUsers; ?></span>
                        <span class="stat-card-sub"><?php echo $totalAdmins; ?> admin<?php echo $totalAdmins != 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-envelope"></i></div>
                        <span class="stat-card-label">Total Messages</span>
                        <span class="stat-card-value"><?php echo $totalMessages; ?></span>
                        <span class="stat-card-sub"><?php echo $newMessages; ?> new</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-eye"></i></div>
                        <span class="stat-card-label">Page Views</span>
                        <span class="stat-card-value"><?php echo $totalPageViews; ?></span>
                        <span class="stat-card-sub">All time</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-user-plus"></i></div>
                        <span class="stat-card-label">Users Today</span>
                        <span class="stat-card-value"><?php
                            $todayUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                            echo $todayUsers;
                        ?></span>
                        <span class="stat-card-sub">Registered today</span>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="stats-section-title"><i class="fas fa-chart-pie"></i> Messages by Status</div>
                    <div class="status-bar-chart">
                        <?php
                        $maxStatus = max(1, max($statusCounts));
                        foreach (['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'] as $key => $label):
                            $pct = $totalMessages > 0 ? round(($statusCounts[$key] / $totalMessages) * 100) : 0;
                        ?>
                        <div class="status-bar-item">
                            <div class="label"><?php echo $label; ?></div>
                            <div class="value"><?php echo $statusCounts[$key]; ?></div>
                            <div class="bar <?php echo $key; ?>" style="width: <?php echo max(10, $pct); ?>%;"></div>
                            <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;"><?php echo $pct; ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="stats-section-title"><i class="fas fa-chart-line"></i> Page Views (Last 7 Days)</div>
                    <div class="views-chart">
                        <div class="views-chart-bars">
                            <?php
                            $maxViews = 1;
                            foreach ($recentViews as $rv) {
                                if ((int)$rv['cnt'] > $maxViews) $maxViews = (int)$rv['cnt'];
                            }
                            $daysMap = [];
                            foreach ($recentViews as $rv) {
                                $daysMap[$rv['d']] = (int)$rv['cnt'];
                            }
                            for ($i = 6; $i >= 0; $i--):
                                $date = date('Y-m-d', strtotime("-$i days"));
                                $cnt = $daysMap[$date] ?? 0;
                                $heightPct = $maxViews > 0 ? round(($cnt / $maxViews) * 100) : 0;
                                $heightPct = max(3, $heightPct);
                            ?>
                            <div class="views-chart-bar-wrap">
                                <span class="views-chart-value"><?php echo $cnt; ?></span>
                                <div class="views-chart-bar" style="height: <?php echo $heightPct; ?>%;"></div>
                                <span class="views-chart-label"><?php echo date('D', strtotime($date)); ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="stats-section-title"><i class="fas fa-user-clock"></i> Recent Users</div>
                    <div class="recent-list">
                        <div class="recent-list-title"><i class="fas fa-clock"></i> Latest Signups</div>
                        <?php if (empty($recentUsers)): ?>
                            <div class="empty-state" style="padding:1.5rem;">
                                <p>No users yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $ru): ?>
                            <div class="recent-item">
                                <div class="recent-item-left">
                                    <div class="recent-item-avatar"><?php echo strtoupper(substr($ru['username'], 0, 2)); ?></div>
                                    <div>
                                        <div class="recent-item-name"><?php echo htmlspecialchars($ru['username']); ?></div>
                                        <div class="recent-item-date"><?php echo date('M d, Y H:i', strtotime($ru['created_at'])); ?></div>
                                    </div>
                                </div>
                                <span class="role-badge <?php echo $ru['role']; ?>">
                                    <i class="fas fa-<?php echo $ru['role'] === 'admin' ? 'shield-halved' : 'user'; ?>"></i>
                                    <?php echo ucfirst($ru['role']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function confirmToggleRole(form, username, currentRole) {
        var action = currentRole === 'admin' ? 'demote' : 'promote';
        var msg = action === 'promote'
            ? 'Make ' + username + ' an admin? They will have full access.'
            : 'Demote ' + username + ' from admin to regular user?';
        return confirm(msg);
    }
    </script>
</body>
</html>
