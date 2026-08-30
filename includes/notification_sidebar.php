<?php
// includes/notification_sidebar.php

if(!isset($_SESSION['user_id'])) return;

$uid = $_SESSION['user_id'];

// 1. Mark as Read
if(isset($_POST['mark_read_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    echo "<script>window.location.href=window.location.href;</script>";
}

// 2. Fetch Notifications (Updated for Specific Details)
// We use SQL Joins to get the Novel Title + Chapter Number combined
$sql = "SELECT n.*, u.username, u.avatar,
        CASE 
            -- REVIEWS: Get Novel Title
            WHEN n.type IN ('new_review', 'like_review') THEN 
                (SELECT title FROM novels WHERE id = n.reference_id)
            
            -- LISTS: Get Collection Title
            WHEN n.type IN ('like_list', 'follow_list', 'comment_list') THEN 
                (SELECT title FROM custom_lists WHERE id = n.reference_id)
            
            -- CHAPTERS: Get 'Novel Title - Ch. X'
            WHEN n.type IN ('comment_chapter', 'reply_chapter', 'like_chapter', 'reply_comment', 'like_comment') THEN (
                SELECT CONCAT(nov.title, ' - Ch. ', c.chapter_number)
                FROM chapters c 
                JOIN novels nov ON c.novel_id = nov.id 
                WHERE c.id = n.reference_id
            )
        END as source_title
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$notifs = $stmt->fetchAll();

// 3. Categorize
$cats = [
    'all' => [],
    'interactions' => [],
    'follows' => [],
    'reviews' => []
];

foreach($notifs as $n) {
    $msg = "interacted with your content"; // Fallback
    $link = "#";
    $title = htmlspecialchars($n['source_title'] ?? 'content'); // The result of our smart SQL above
    
    // Determine Message & Link based on Type
    switch($n['type']) {
        // --- USER FOLLOWS ---
        case 'follow_user':
            $msg = "started following you";
            $link = "profile.php?user_id={$n['actor_id']}";
            $cats['follows'][] = $n; 
            break;

        // --- LISTS ---
        case 'follow_list': 
            $msg = "followed your collection <strong>$title</strong>"; 
            $link = "view_list.php?id={$n['reference_id']}";
            $cats['follows'][] = $n; 
            break;
        case 'like_list': 
            $msg = "liked your collection <strong>$title</strong>"; 
            $link = "view_list.php?id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
        case 'comment_list': 
            $msg = "commented on <strong>$title</strong>"; 
            $link = "view_list.php?id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
        
        // --- REVIEWS ---
        case 'new_review': 
            $msg = "reviewed <strong>$title</strong>"; 
            $link = "novel.php?id={$n['reference_id']}"; 
            $cats['reviews'][] = $n; 
            break;
        case 'like_review': 
            $msg = "liked your review on <strong>$title</strong>"; 
            $link = "novel.php?id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
        
        // --- CHAPTERS ---
        case 'comment_chapter': 
            $msg = "commented on <strong>$title</strong>"; 
            $link = "read.php?novel_id=" . getNovelIdFromChap($pdo, $n['reference_id']) . "&chapter_id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
        case 'reply_chapter': 
            $msg = "replied to you on <strong>$title</strong>"; 
            $link = "read.php?novel_id=" . getNovelIdFromChap($pdo, $n['reference_id']) . "&chapter_id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
        case 'like_chapter': 
            $msg = "liked your comment on <strong>$title</strong>"; 
            $link = "read.php?novel_id=" . getNovelIdFromChap($pdo, $n['reference_id']) . "&chapter_id={$n['reference_id']}"; 
            $cats['interactions'][] = $n; 
            break;
    }
    
    // Create formatted Item
    $formatted_n = $n; 
    $formatted_n['html_msg'] = $msg;
    $formatted_n['html_link'] = $link;
    
    // Add to 'All'
    $cats['all'][] = $formatted_n;
    
    // Replace raw data in categories with formatted data
    if (in_array($n, $cats['interactions'])) { array_pop($cats['interactions']); $cats['interactions'][] = $formatted_n; }
    if (in_array($n, $cats['follows'])) { array_pop($cats['follows']); $cats['follows'][] = $formatted_n; }
    if (in_array($n, $cats['reviews'])) { array_pop($cats['reviews']); $cats['reviews'][] = $formatted_n; }
}

// Helper to get Novel ID for chapter links
function getNovelIdFromChap($pdo, $chapId) {
    static $cache = [];
    if(isset($cache[$chapId])) return $cache[$chapId];
    $nid = $pdo->query("SELECT novel_id FROM chapters WHERE id=$chapId")->fetchColumn();
    $cache[$chapId] = $nid;
    return $nid;
}
?>

<div id="notifSidebar" class="notif-sidebar">
    <div class="notif-header">
        <h3>Notifications</h3>
        <button onclick="closeNotif()" class="close-btn">&times;</button>
    </div>

    <div class="notif-tabs">
        <button class="tab-btn active" onclick="switchTab('all')">All</button>
        <button class="tab-btn" onclick="switchTab('interactions')">Likes</button>
        <button class="tab-btn" onclick="switchTab('reviews')">Reviews</button>
        <button class="tab-btn" onclick="switchTab('follows')">Follows</button>
    </div>

    <div class="notif-content">
        <?php foreach($cats as $key => $list): ?>
            <div id="tab-<?php echo $key; ?>" class="notif-list <?php echo $key=='all'?'active':''; ?>">
                <?php if(empty($list)): ?>
                    <p class="empty-msg">No new notifications.</p>
                <?php else: ?>
                    <?php foreach($list as $item): ?>
                        <a href="<?php echo $item['html_link']; ?>" class="notif-item <?php echo $item['is_read']?'read':'unread'; ?>">
                            <img src="<?php echo htmlspecialchars($item['avatar']); ?>" class="notif-avatar">
                            <div class="notif-text">
                                <p>
                                    <span class="actor-name"><?php echo htmlspecialchars($item['username']); ?></span>
                                    <?php echo $item['html_msg']; ?>
                                </p>
                                <small><?php echo date('M d, h:i A', strtotime($item['created_at'])); ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="notif-footer">
        <form method="POST">
            <button type="submit" name="mark_read_all" class="btn btn-outline" style="width:100%; font-size:0.8rem;">Mark all as read</button>
        </form>
    </div>
</div>
<div id="notifOverlay" class="notif-overlay" onclick="closeNotif()"></div>