<?php
require_once 'config/db.php';
// Mail login lives in config/secrets.php (not on GitHub). See config/secrets.example.php
if (file_exists(__DIR__ . '/config/secrets.php')) { require_once __DIR__ . '/config/secrets.php'; }
if (!defined('MAIL_USERNAME')) { define('MAIL_USERNAME', ''); }
if (!defined('MAIL_APP_PASSWORD')) { define('MAIL_APP_PASSWORD', ''); }
require_once 'includes/header.php';

// Load PHPMailer
require 'libs/PHPMailer/Exception.php';
require 'libs/PHPMailer/PHPMailer.php';
require 'libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // 1. Check if email exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate Token (Secure random string)
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour")); // Valid for 1 hour

        // 3. Save Token to DB
        // First, delete any old tokens for this email to keep it clean
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$email, $token, $expires]);

        // 4. Send Email
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_APP_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('noreply@noveu.com', 'NoveU Security');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body    = "<h3>Hi {$user['username']},</h3>
                              <p>Click the link below to reset your password:</p>
                              <p><a href='$resetLink'>$resetLink</a></p>
                              <p>This link expires in 1 hour.</p>";

            $mail->send();
            $msg = "Reset link sent! Check your email (spam folder).";
        } catch (Exception $e) {
            $error = "Could not send email. Error: {$mail->ErrorInfo}";
        }
    } else {
        // Generic message for security (don't tell them if email doesn't exist)
        $msg = "If that email exists, we sent a reset link.";
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width:400px;">
        <h2 style="text-align:center;">Forgot Password</h2>
        <p style="text-align:center; color:var(--text-light); margin-bottom:20px;">Enter your email to receive a reset link.</p>
        
        <?php if($msg) echo "<div class='alert' style='background:#d4edda; color:#155724;'>$msg</div>"; ?>
        <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Send Link</button>
            
            <div style="text-align:center; margin-top:15px;">
                <a href="login.php" style="color:var(--primary-color);">Back to Login</a>
            </div>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>