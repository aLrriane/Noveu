<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$popupScript = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $popupScript = "showPopup('error', 'Missing Info', 'Please fill in all fields.');";
    } else {
        // 1. Fetch user from DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 2. Verify User AND Password
        if ($user && password_verify($password, $user['password'])) {
            
            // 3. Check Verification Status
            if ($user['is_verified'] == 0) {
                // User exists but email isn't verified
                $emailEncoded = urlencode($user['email']);
                $popupScript = "
                    Swal.fire({
                        icon: 'warning',
                        title: 'Not Verified',
                        html: 'Please verify your email first. <br><a href=\"verify.php?email=$emailEncoded\" style=\"color:#f47521\">Click here to verify</a>',
                        confirmButtonColor: '#f47521'
                    });
                ";
            } else {
                // SUCCESS: Log them in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Optional: Redirect with a small toast or direct
                header("Location: index.php");
                exit;
            }
        } else {
            // FAILURE: User not found OR Password wrong
            $popupScript = "showPopup('error', 'Login Failed', 'Invalid username or password.');";
        }
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width: 400px;">
        <h2 style="text-align:center; margin-bottom:20px;">Login to NoveU</h2>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
                <a href="forgot_password.php" style="font-size:0.8rem; color:var(--text-light); float:right; margin-top:5px;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn" style="width:100%; margin-top:10px;">Login</button>
            
            <p style="margin-top:15px; text-align:center;">
                No account? <a href="register.php" style="color:var(--primary-color);">Register here</a>
            </p>
        </form>
    </div>
</div>

<?php if($popupScript): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php echo $popupScript; ?>
        });
    </script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>