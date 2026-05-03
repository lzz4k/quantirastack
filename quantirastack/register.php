<?php
session_start();
require_once __DIR__ . '/config/install.php';
ensureDatabase();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be 3-50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already taken.';
            } else {
                $hashed = encryptPassword($password);
                $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $role = $userCount == 0 ? 'admin' : 'user';
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed, $role]);
                $success = $role === 'admin' ? 'Admin account created! You can now sign in.' : 'Account created! You can now sign in.';
            }
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - QUANTIRA STACK</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body style="background: #0c0a14; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="width: 100%; max-width: 420px; padding: 0 1.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="assets/logo.png" alt="Quantira Stack" style="width: 80px; height: 80px; object-fit: contain; border-radius: 16px; filter: drop-shadow(0 4px 15px rgba(168, 85, 247, 0.5)); margin-bottom: 1rem;">
            <h1 style="font-family: 'Inter', sans-serif; font-size: 1.6rem; font-weight: 800; color: #f0eaf8; margin-bottom: 0.25rem;">QUANTIRA <span style="background: linear-gradient(135deg, #a855f7, #6d28d9); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">STACK</span></h1>
            <p style="color: #7a6a90; font-family: 'Inter', sans-serif; font-size: 0.9rem;">Create your account</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(244,63,94,0.1); border: 2px solid rgba(244,63,94,0.3); border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; color: #f43f5e; font-family: 'Inter', sans-serif; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(52,211,153,0.1); border: 2px solid rgba(52,211,153,0.3); border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; color: #34d399; font-family: 'Inter', sans-serif; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="background: #1a1528; border: 2px solid #2e2448; border-radius: 22px; padding: 2rem; box-shadow: 0 5px 0 rgba(0,0,0,0.35), 0 10px 30px rgba(0,0,0,0.25);">
            <div style="margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #f0eaf8; margin-bottom: 0.5rem;">
                    <i class="fas fa-user" style="color: #a855f7;"></i> Username
                </label>
                <input type="text" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($username ?? ''); ?>" style="width: 100%; padding: 0.85rem 1.15rem; background: #0c0a14; border: 2px solid #2e2448; border-radius: 10px; color: #f0eaf8; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onfocus="this.style.borderColor='#a855f7';this.style.boxShadow='0 0 0 4px rgba(168,85,247,0.15)'" onblur="this.style.borderColor='#2e2448';this.style.boxShadow='none'">
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #f0eaf8; margin-bottom: 0.5rem;">
                    <i class="fas fa-envelope" style="color: #a855f7;"></i> Email
                </label>
                <input type="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($email ?? ''); ?>" style="width: 100%; padding: 0.85rem 1.15rem; background: #0c0a14; border: 2px solid #2e2448; border-radius: 10px; color: #f0eaf8; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onfocus="this.style.borderColor='#a855f7';this.style.boxShadow='0 0 0 4px rgba(168,85,247,0.15)'" onblur="this.style.borderColor='#2e2448';this.style.boxShadow='none'">
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #f0eaf8; margin-bottom: 0.5rem;">
                    <i class="fas fa-lock" style="color: #a855f7;"></i> Password
                </label>
                <input type="password" name="password" required placeholder="Create a password (min 6 chars)" style="width: 100%; padding: 0.85rem 1.15rem; background: #0c0a14; border: 2px solid #2e2448; border-radius: 10px; color: #f0eaf8; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onfocus="this.style.borderColor='#a855f7';this.style.boxShadow='0 0 0 4px rgba(168,85,247,0.15)'" onblur="this.style.borderColor='#2e2448';this.style.boxShadow='none'">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #f0eaf8; margin-bottom: 0.5rem;">
                    <i class="fas fa-lock" style="color: #a855f7;"></i> Confirm Password
                </label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password" style="width: 100%; padding: 0.85rem 1.15rem; background: #0c0a14; border: 2px solid #2e2448; border-radius: 10px; color: #f0eaf8; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onfocus="this.style.borderColor='#a855f7';this.style.boxShadow='0 0 0 4px rgba(168,85,247,0.15)'" onblur="this.style.borderColor='#2e2448';this.style.boxShadow='none'">
            </div>
            <button type="submit" style="width: 100%; padding: 0.9rem; background: linear-gradient(135deg, #a855f7, #6d28d9); color: #fff; border: none; border-radius: 50px; font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 0 rgba(109,40,217,0.6), 0 10px 30px rgba(168,85,247,0.3); transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onmouseover="this.style.transform='translateY(-3px) scale(1.03)';this.style.boxShadow='0 7px 0 rgba(109,40,217,0.5), 0 16px 40px rgba(168,85,247,0.4)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 0 rgba(109,40,217,0.6), 0 10px 30px rgba(168,85,247,0.3)'">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; font-family: 'Inter', sans-serif; font-size: 0.9rem; color: #7a6a90;">
            Already have an account? <a href="login.php" style="color: #a855f7; font-weight: 600; text-decoration: none;">Sign In</a>
        </p>
    </div>
</body>
</html>
