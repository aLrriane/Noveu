<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$popupScript = "";

// 1. UPDATE PROFILE
if (isset($_POST['update_info'])) {
    try {
        $sql = "UPDATE users SET bio = ?, social_link = ?, font_size = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([trim($_POST['bio']), trim($_POST['social_link']), $_POST['font_size'], $user_id]);
        $popupScript = "showToast('success', 'Profile settings saved!');";
    } catch (PDOException $e) {
        $popupScript = "showPopup('error', 'Error', 'Could not update profile.');";
    }
}

// 2. CHANGE PASSWORD
if (isset($_POST['change_password'])) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (password_verify($_POST['old_password'], $user['password'])) {
        $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user_id]);
        $popupScript = "showPopup('success', 'Success', 'Password changed.');";
    } else {
        $popupScript = "showPopup('error', 'Failed', 'Incorrect old password.');";
    }
}

// 3. DELETE ACCOUNT
if (isset($_POST['delete_account'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    session_destroy();
    echo "<script>alert('Account Deleted.'); window.location.href='index.php';</script>"; exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();
?>

<div class="main-content" style="padding: 20px;">
    <div class="form-container" style="max-width: 700px;">
        <h1><i class="fas fa-cog"></i> Settings</h1>
        
        <form method="POST" style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 15px; color: var(--primary-color);">Public Profile</h3>
            <div class="form-group"><label>Bio</label><textarea name="bio" rows="3"><?php echo htmlspecialchars($userData['bio'] ?? ''); ?></textarea></div>
            <div class="form-group"><label>Social Link</label><input type="text" name="social_link" value="<?php echo htmlspecialchars($userData['social_link'] ?? ''); ?>"></div>
            <div class="form-group">
                <label>Font Size</label>
                <select name="font_size">
                    <option value="small" <?php if($userData['font_size']=='small') echo 'selected'; ?>>Small</option>
                    <option value="medium" <?php if($userData['font_size']=='medium') echo 'selected'; ?>>Medium</option>
                    <option value="large" <?php if($userData['font_size']=='large') echo 'selected'; ?>>Large</option>
                </select>
            </div>
            <button type="submit" name="update_info" class="btn">Save Changes</button>
        </form>

        <form method="POST" style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 15px; color: var(--primary-color);">Security</h3>
            <div class="form-group"><label>Old Password</label><input type="password" name="old_password" required></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
            <button type="submit" name="change_password" class="btn" style="background:#e67e22;">Update Password</button>
        </form>

        <form method="POST">
            <h3 style="color: #e74c3c;">Danger Zone</h3>
            <button type="submit" name="delete_account" class="btn" style="background:darkred;">Delete Account</button>
        </form>
    </div>
</div>

<?php if($popupScript): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { <?php echo $popupScript; ?> });</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>