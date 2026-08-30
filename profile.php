<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// 1. Determine which profile to show
$view_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : ($_SESSION['user_id'] ?? 0);
if ($view_user_id == 0) { header("Location: login.php"); exit; }

$current_user = $_SESSION['user_id'] ?? 0;

// 2. Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$view_user_id]);
$user = $stmt->fetch();
if (!$user) die("<div class='main-content'>User not found.</div>");

// 3. FOLLOW LOGIC & LISTS
$is_following = false;

// A. Handle Toggle Follow
if ($current_user && isset($_POST['toggle_follow']) && $current_user != $view_user_id) {
    // Check state before toggling
    $chk = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    $chk->execute([$current_user, $view_user_id]);
    
    if ($chk->rowCount() > 0) {
        // Unfollow
        $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?")
            ->execute([$current_user, $view_user_id]);
    } else {
        // Follow & Notify
        $pdo->prepare("INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)")
            ->execute([$current_user, $view_user_id]);
            
        // Notify (Prevent Duplicate)
        $chkNotif = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND actor_id = ? AND type = 'follow_user'");
        $chkNotif->execute([$view_user_id, $current_user]);
        if ($chkNotif->rowCount() == 0) {
            $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, 'follow_user', ?)")
                ->execute([$view_user_id, $current_user, $current_user]);
        }
    }
    header("Location: profile.php?user_id=$view_user_id"); exit;
}

// B. Check if currently following (for button state)
if ($current_user) {
    $chk = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    $chk->execute([$current_user, $view_user_id]);
    $is_following = $chk->rowCount() > 0;
}

// C. Fetch Lists (Followers & Following)
// Get Followers (People who follow THIS user)
$followersStmt = $pdo->prepare("SELECT u.id, u.username, u.avatar FROM user_follows f JOIN users u ON f.follower_id = u.id WHERE f.followed_id = ?");
$followersStmt->execute([$view_user_id]);
$followers_list = $followersStmt->fetchAll();
$follower_count = count($followers_list);

// Get Following (People THIS user follows)
$followingStmt = $pdo->prepare("SELECT u.id, u.username, u.avatar FROM user_follows f JOIN users u ON f.followed_id = u.id WHERE f.follower_id = ?");
$followingStmt->execute([$view_user_id]);
$following_list = $followingStmt->fetchAll();
$following_count = count($following_list);

// 4. Fetch Profile Content
$uploads = $pdo->prepare("SELECT * FROM novels WHERE uploaded_by = ? ORDER BY upload_date DESC");
$uploads->execute([$view_user_id]);
$my_uploads = $uploads->fetchAll();

$hist = $pdo->prepare("SELECT n.*, rh.updated_at FROM reading_history rh JOIN novels n ON rh.novel_id = n.id WHERE rh.user_id = ? ORDER BY rh.updated_at DESC LIMIT 5");
$hist->execute([$view_user_id]);
$history = $hist->fetchAll();

$colStmt = $pdo->prepare("SELECT * FROM custom_lists WHERE user_id = ? AND (is_public = 1 OR user_id = ?) ORDER BY created_at DESC LIMIT 5");
$colStmt->execute([$view_user_id, $current_user]);
$collections = $colStmt->fetchAll();

// Banner Style
$bannerUrl = !empty($user['banner_image']) ? $user['banner_image'] : ''; 
$bannerStyle = $bannerUrl ? "background-image: url('$bannerUrl');" : "background: linear-gradient(to right, #2c3e50, #4ca1af);";
?>

<div class="main-content">
    
    <div class="content-box" style="padding: 0; overflow: hidden; text-align:center;">
        <div class="profile-banner" style="<?php echo $bannerStyle; ?>"></div>
        
        <div class="profile-header-content">
            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" class="profile-avatar-large">
            <h1 style="margin: 10px 0 5px 0;"><?php echo htmlspecialchars($user['username']); ?></h1>
            
            <div style="display:flex; justify-content:center; gap:20px; margin-bottom:15px; color:var(--text-light);">
                <a href="javascript:void(0)" onclick="openModal('followersModal')" style="text-decoration:none; color:inherit; cursor:pointer;">
                    <span style="font-weight:bold; color:var(--text-color);"><?php echo $follower_count; ?></span> Followers
                </a>
                <a href="javascript:void(0)" onclick="openModal('followingModal')" style="text-decoration:none; color:inherit; cursor:pointer;">
                    <span style="font-weight:bold; color:var(--text-color);"><?php echo $following_count; ?></span> Following
                </a>
            </div>

            <?php if(!empty($user['bio'])): ?>
                <p style="color: var(--text-light); max-width: 600px; margin: 0 auto 15px; font-style: italic;">"<?php echo nl2br(htmlspecialchars($user['bio'])); ?>"</p>
            <?php endif; ?>
            
            <div class="action-bar" style="display:flex; justify-content: center; gap: 10px;">
                <?php if ($current_user == $view_user_id): ?>
                    <a href="edit_profile.php" class="btn btn-outline"><i class="fas fa-camera"></i> Edit Profile</a>
                    <a href="create_list.php" class="btn"><i class="fas fa-plus"></i> New List</a>
                <?php elseif ($current_user): ?>
                    <form method="POST">
                        <button type="submit" name="toggle_follow" class="btn" style="<?php echo $is_following ? 'background:gray;' : ''; ?>">
                            <?php if($is_following): ?>
                                <i class="fas fa-user-minus"></i> Unfollow
                            <?php else: ?>
                                <i class="fas fa-user-plus"></i> Follow
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="content-box">
            <h3>Recently Read</h3>
            <?php if($history): ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach($history as $nov): ?>
                        <a href="novel.php?id=<?php echo $nov['id']; ?>" style="display:flex; gap:10px; align-items:center; text-decoration:none; color:var(--text-color);">
                            <img src="<?php echo $nov['cover_image']; ?>" style="width:40px; height:60px; object-fit:cover; border-radius:4px;">
                            <div><span style="font-weight:bold; display:block;"><?php echo htmlspecialchars($nov['title']); ?></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: echo "<p class='text-light'>No history visible.</p>"; endif; ?>
        </div>
        <div class="content-box">
            <h3>Collections</h3>
            <?php if($collections): foreach($collections as $list): ?>
                <div style="padding:10px; border:1px solid var(--border-color); margin-bottom:5px; border-radius:4px; cursor:pointer;" onclick="window.location.href='view_list.php?id=<?php echo $list['id']; ?>'">
                    <strong><?php echo htmlspecialchars($list['title']); ?></strong>
                </div>
            <?php endforeach; else: echo "<p class='text-light'>No collections.</p>"; endif; ?>
        </div>
    </div>

    <div class="content-box">
        <h3>Uploaded Works</h3>
        <?php if($my_uploads): ?>
            <div class="novel-grid">
                <?php 
                $first_six = array_slice($my_uploads, 0, 8); 
                foreach($first_six as $novel) include 'includes/card_template.php';
                ?>
            </div>
        <?php else: echo "<p>No uploads.</p>"; endif; ?>
    </div>
</div>

<div id="followersModal" class="modal-overlay" onclick="closeModal(event, 'followersModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Followers</h3>
            <button onclick="document.getElementById('followersModal').style.display='none'" style="background:none; border:none; color:var(--text-color); font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <?php if(empty($followers_list)): ?>
                <p style="padding:10px; color:var(--text-light); text-align:center;">No followers yet.</p>
            <?php else: foreach($followers_list as $f): ?>
                <a href="profile.php?user_id=<?php echo $f['id']; ?>" class="user-list-item">
                    <img src="<?php echo htmlspecialchars($f['avatar']); ?>" alt="Avatar">
                    <span><?php echo htmlspecialchars($f['username']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div id="followingModal" class="modal-overlay" onclick="closeModal(event, 'followingModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Following</h3>
            <button onclick="document.getElementById('followingModal').style.display='none'" style="background:none; border:none; color:var(--text-color); font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <?php if(empty($following_list)): ?>
                <p style="padding:10px; color:var(--text-light); text-align:center;">Not following anyone.</p>
            <?php else: foreach($following_list as $f): ?>
                <a href="profile.php?user_id=<?php echo $f['id']; ?>" class="user-list-item">
                    <img src="<?php echo htmlspecialchars($f['avatar']); ?>" alt="Avatar">
                    <span><?php echo htmlspecialchars($f['username']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'block';
}
function closeModal(event, id) {
    if (event.target === document.getElementById(id)) {
        document.getElementById(id).style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>