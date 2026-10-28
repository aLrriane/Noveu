<?php
require_once 'config/db.php';
// Mail login lives in config/secrets.php (not on GitHub). See config/secrets.example.php
if (file_exists(__DIR__ . '/config/secrets.php')) { require_once __DIR__ . '/config/secrets.php'; }
if (!defined('MAIL_USERNAME')) { define('MAIL_USERNAME', ''); }
if (!defined('MAIL_APP_PASSWORD')) { define('MAIL_APP_PASSWORD', ''); }
require_once 'includes/header.php';

// Load PHPMailer (Adjust path if your libs folder is different)
require 'libs/PHPMailer/Exception.php';
require 'libs/PHPMailer/PHPMailer.php';
require 'libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = "";
$error = "";
$debug_log = ""; // To show email errors

// 1. Get Email from URL
if (isset($_GET['email'])) {
    $email = $_GET['email'];
} else {
    header("Location: login.php"); exit;
}

// 2. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- OPTION A: VERIFY CODE ---
    if (isset($_POST['verify_code'])) {
        $code_input = trim($_POST['code']);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ?");
        $stmt->execute([$email, $code_input]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?")->execute([$user['id']]);
            echo "<script>alert('Account Verified! Login now.'); window.location.href='login.php';</script>";
            exit;
        } else {
            $error = "Invalid Verification Code.";
        }
    }

    // --- OPTION B: RESEND CODE ---
    if (isset($_POST['resend_code'])) {
        // 1. Generate New Code
        $new_code = rand(100000, 999999);
        
        // 2. Update Database
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
        $stmt->execute([$new_code, $email]);
        
        // 3. Fetch Username (for the email body)
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
        $uStmt->execute([$email]);
        $uName = $uStmt->fetchColumn();

        // 4. Send Email (With Debugging)
        $mail = new PHPMailer(true);
        try {
            // SERVER SETTINGS
            $mail->SMTPDebug = 0; // Change to 2 to see errors if it fails!
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_APP_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // RECIPIENTS
            $mail->setFrom('noreply@noveu.com', 'NoveU Security');
            $mail->addAddress($email, $uName);

            // CONTENT
            $mail->isHTML(true);
            $mail->Subject = 'Resend: Your Verification Code';
            $mail->Body    = "<h3>Hello $uName!</h3><p>Your new code is: <b style='font-size:24px; color:#f47521;'>$new_code</b></p>";

            $mail->send();
            $msg = "New code sent! Check your inbox (and spam).";
        } catch (Exception $e) {
            $error = "Message could not be sent.";
            $debug_log = "Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width:400px; text-align:center;">
        <h2>Verify Email</h2>
        <p>Please enter the code sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>
        
        <?php if($msg) echo "<div class='alert' style='background:#d4edda; color:#155724;'>$msg</div>"; ?>
        <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
        
        <?php if($debug_log): ?>
            <div style="text-align:left; background:#333; color:#ff9999; padding:10px; font-size:0.8rem; margin-bottom:15px; overflow-x:auto;">
                <strong>Debug Info:</strong><br>
                <?php echo $debug_log; ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="margin-bottom:15px;">
            <div class="form-group">
                <input type="text" name="code" placeholder="6-Digit Code" required style="text-align:center; letter-spacing:5px; font-size:1.5rem;">
            </div>
            <button type="submit" name="verify_code" class="btn" style="width:100%;">Verify Account</button>
        </form>

        <hr style="border-color:var(--border-color); margin: 20px 0;">

        <form method="POST">
            <p style="font-size:0.9rem; color:var(--text-light);">Didn't receive it?</p>
            <button type="submit" name="resend_code" class="btn btn-outline" style="width:100%; margin-top:10px;">
                <i class="fas fa-sync-alt"></i> Resend Code
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>