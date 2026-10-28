<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$error = "";
$success = "";
$token = $_GET['token'] ?? '';

if (!$token) {
    die("<div class='main-content'><div class='content-box'>Invalid request.</div></div>");
}

// 1. Verify Token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$resetRequest = $stmt->fetch();

if (!$resetRequest) {
    $error = "Invalid or expired token.";
}

// 2. Handle New Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Update User Password
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hashed, $resetRequest['email']]);
        
        // Delete Token (One-time use)
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetRequest['email']]);
        
        echo "<script>alert('Password updated! Login now.'); window.location.href='login.php';</script>";
        exit;
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width:400px;">
        <h2 style="text-align:center;">Reset Password</h2>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php if($error == "Invalid or expired token."): ?>
                <a href="forgot_password.php" class="btn" style="width:100%; text-align:center;">Request New Link</a>
            <?php endif; ?>
        <?php else: ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn" style="width:100%;">Update Password</button>
            </form>
            
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>