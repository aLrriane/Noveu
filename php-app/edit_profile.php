<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$msg = "";

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_bio = trim($_POST['bio']);
    
    // Check Duplicates
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$new_username, $user_id]);
    
    if ($check->rowCount() > 0) {
        $msg = "<div class='alert alert-error'>Username taken.</div>";
    } else {
        // --- IMAGE HANDLING START ---
        
        // 1. BANNER CLEANUP & UPLOAD
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'png', 'jpeg'])) {
                // Delete Old
                if ($user['banner_image'] && file_exists($user['banner_image']) && strpos($user['banner_image'], 'default') === false) {
                    unlink($user['banner_image']);
                }
                // Upload New
                $name = "banner_" . $user_id . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['banner']['tmp_name'], "uploads/avatars/" . $name);
                $pdo->prepare("UPDATE users SET banner_image = ? WHERE id = ?")->execute(["uploads/avatars/" . $name, $user_id]);
            }
        }

        // 2. AVATAR CLEANUP & UPLOAD
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'png', 'jpeg'])) {
                // Delete Old
                if ($user['avatar'] && file_exists($user['avatar']) && strpos($user['avatar'], 'default') === false) {
                    unlink($user['avatar']);
                }
                // Upload New
                $name = "user_" . $user_id . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], "uploads/avatars/" . $name);
                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute(["uploads/avatars/" . $name, $user_id]);
            }
        }
        // --- IMAGE HANDLING END ---

        // Update Info
        $pdo->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?")->execute([$new_username, $new_bio, $user_id]);
        $_SESSION['username'] = $new_username;
        
        // Redirect to Profile
        header("Location: profile.php");
        exit;
    }
}
?>

<div class="main-content">
    <div class="form-container">
        <h2>Edit Profile</h2>
        <?php echo $msg; ?>
        <form method="POST" enctype="multipart/form-data">
            <div style="text-align:center; margin-bottom:20px;">
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover;">
            </div>
            
            <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></div>
            
            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-group"><label>Profile Picture</label><input type="file" name="avatar"></div>
            <div class="form-group"><label>Banner Image</label><input type="file" name="banner"></div>

            <button type="submit" class="btn">Save Changes</button>
            <a href="profile.php" class="btn" style="background:gray;">Back</a>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>