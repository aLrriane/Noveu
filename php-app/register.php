<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$username = ''; $email = ''; 
$popupScript = ""; // Variable to hold the JS popup trigger

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $popupScript = "showPopup('error', 'Missing Fields', 'Please fill in all required fields.');";
    } elseif ($password !== $confirm_password) {
        $popupScript = "showPopup('error', 'Mismatch', 'Passwords do not match.');";
    } else {
        // Check Duplicate
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $popupScript = "showPopup('warning', 'Taken', 'Username or Email is already registered.');";
        } else {
            // Try Insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $v_code = rand(100000, 999999);

            try {
                $sql = "INSERT INTO users (username, email, password, verification_code, is_verified) VALUES (?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $email, $hashed_password, $v_code]);

                // Database success! Now show popup.
                $popupScript = "showPopup('success', 'Welcome!', 'Registration Successful. Please login.', 'login.php');";

            } catch (PDOException $e) {
                $popupScript = "showPopup('error', 'Error', 'Database error occurred.');";
            }
        }
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width: 400px;">
        <h2 style="text-align:center; margin-bottom:20px;">Create Account</h2>
        
        <form method="POST">
            <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            <button type="submit" class="btn" style="width:100%;">Register</button>
            <p style="margin-top:15px; text-align:center;">Already have an account? <a href="login.php" style="color:var(--primary-color);">Login here</a></p>
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