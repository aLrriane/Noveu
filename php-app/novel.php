<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit; }
$id = $_GET['id'];

// 1. Fetch Novel Data
$stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
$stmt->execute([$id]);
$novel = $stmt->fetch();
if (!$novel) die("Content not found.");

// 2. Fetch Related Data
$chapStmt = $pdo->prepare("SELECT * FROM chapters WHERE novel_id = ? ORDER BY chapter_number ASC");
$chapStmt->execute([$id]);
$chapters = $chapStmt->fetchAll();
$first_chapter = $chapters[0]['id'] ?? null;

// Fetch Genres (Multi)
$genStmt = $pdo->prepare("SELECT g.name, g.id FROM genres g JOIN novel_genres ng ON g.id = ng.genre_id WHERE ng.novel_id = ?");
$genStmt->execute([$id]);
$genres = $genStmt->fetchAll();

// Fetch Tags
$tagStmt = $pdo->prepare("SELECT t.name, t.id FROM tags t JOIN novel_tags nt ON t.id = nt.tag_id WHERE nt.novel_id = ?");
$tagStmt->execute([$id]);
$tags = $tagStmt->fetchAll();

// Fetch Reviews
$revStmt = $pdo->prepare("SELECT r.*, u.username, u.avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.novel_id = ? ORDER BY r.created_at DESC");
$revStmt->execute([$id]);
$reviews = $revStmt->fetchAll();

// Continue Reading Logic
$last_read_chap = null;
if (isset($_SESSION['user_id'])) {
    $histStmt = $pdo->prepare("SELECT last_chapter_id FROM reading_history WHERE user_id = ? AND novel_id = ?");
    $histStmt->execute([$_SESSION['user_id'], $id]);
    $last_read_chap = $histStmt->fetchColumn();
}

// User Lists
$myCustomLists = [];
$my_existing_review = null;
if (isset($_SESSION['user_id'])) {
    $l_stmt = $pdo->prepare("SELECT id, title FROM custom_lists WHERE user_id = ?");
    $l_stmt->execute([$_SESSION['user_id']]);
    $myCustomLists = $l_stmt->fetchAll();

    $myRevStmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND novel_id = ?");
    $myRevStmt->execute([$_SESSION['user_id'], $id]);
    $my_existing_review = $myRevStmt->fetch();
}

// Handle POST actions (Review, List, Vote)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];

    if (isset($_POST['add_standard'])) {
        $type = $_POST['list_type'];
        $check = $pdo->prepare("SELECT id FROM user_lists WHERE user_id=? AND novel_id=?");
        $check->execute([$uid, $id]);
        if ($check->rowCount() == 0) {
            $pdo->prepare("INSERT INTO user_lists (user_id, novel_id, list_type) VALUES (?, ?, ?)")->execute([$uid, $id, $type]);
            echo "<script>showToast('success', 'Added to $type list!');</script>";
        } else {
            $pdo->prepare("UPDATE user_lists SET list_type = ? WHERE user_id=? AND novel_id=?")->execute([$type, $uid, $id]);
            echo "<script>showToast('success', 'Updated to $type!');</script>";
        }
    } elseif (isset($_POST['add_custom'])) {
        try {
            $pdo->prepare("INSERT INTO list_items (list_id, novel_id) VALUES (?, ?)")->execute([$_POST['custom_list_id'], $id]);
            echo "<script>showToast('success', 'Added to collection!');</script>";
        } catch (Exception $e) { echo "<script>showToast('warning', 'Already in collection!');</script>"; }
    } elseif (isset($_POST['submit_review'])) {
        // Delete old review if exists
        $pdo->prepare("DELETE FROM reviews WHERE user_id=? AND novel_id=?")->execute([$uid, $id]);
        
        $ins = $pdo->prepare("INSERT INTO reviews (user_id, novel_id, rating, comment) VALUES (?, ?, ?, ?)");
        $ins->execute([$uid, $id, $_POST['rating'], $_POST['comment']]);
        
        // Notify the Author (using functions.php)
        if(isset($novel['uploaded_by'])) {
            sendNotification($pdo, $novel['uploaded_by'], 'new_review', $id);
        }
        header("Location: novel.php?id=$id"); exit;
    } 
    elseif (isset($_POST['vote_review'])) {
        $tid = $_POST['review_id'];
        $val = (int)$_POST['vote_value'];
        $chk = $pdo->prepare("SELECT id, vote FROM likes WHERE user_id=? AND target_id=? AND target_type='review'");
        $chk->execute([$uid, $tid]);
        $ex = $chk->fetch();
        if ($ex) {
            if ($ex['vote'] == $val) $pdo->prepare("DELETE FROM likes WHERE id=?")->execute([$ex['id']]);
            else $pdo->prepare("UPDATE likes SET vote=? WHERE id=?")->execute([$val, $ex['id']]);
        } else {
            $pdo->prepare("INSERT INTO likes (user_id, target_id, target_type, vote) VALUES (?, ?, 'review', ?)")->execute([$uid, $tid, $val]);
            
            // Notify Review Owner (New Feature)
            $revOwner = $pdo->query("SELECT user_id FROM reviews WHERE id=$tid")->fetchColumn();
            sendNotification($pdo, $revOwner, 'like_review', $id);
        }
        header("Location: novel.php?id=$id"); exit;
    }
}
?>

<div class="main-content">
    <div class="content-box" style="display: flex; gap: 30px; flex-wrap: wrap;">
        
        <div style="flex: 1; min-width: 280px;">
            <img src="<?php echo htmlspecialchars($novel['cover_image']); ?>" style="width: 100%; border-radius: 8px; box-shadow: var(--shadow);">
            
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px;">
                <?php if($first_chapter): ?>
                    <a href="read.php?novel_id=<?php echo $id; ?>&chapter_id=<?php echo $first_chapter; ?>" class="btn" style="text-align:center;">
                        <i class="fas fa-book-open"></i> Start Reading
                    </a>
                <?php endif; ?>

                <?php if($last_read_chap): 
                    $chNum = $pdo->query("SELECT chapter_number FROM chapters WHERE id=$last_read_chap")->fetchColumn();
                ?>
                    <a href="read.php?novel_id=<?php echo $id; ?>&chapter_id=<?php echo $last_read_chap; ?>" class="btn" style="background:#e67e22; text-align:center;">
                        <i class="fas fa-bookmark"></i> Continue Ch. <?php echo $chNum; ?>
                    </a>
                <?php endif; ?>

                <?php if(!empty($novel['external_link'])): ?>
                    <a href="<?php echo $novel['external_link']; ?>" target="_blank" class="btn" style="background:#e74c3c; text-align:center;">
                        <i class="fas fa-play-circle"></i> Watch / Source
                    </a>
                <?php endif; ?>
                
                <?php if($novel['file_path']): ?>
                    <a href="<?php echo $novel['file_path']; ?>" class="btn" style="background:#27ae60; text-align:center;" download>
                        <i class="fas fa-download"></i> Download File
                    </a>
                <?php endif; ?>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <div style="background:var(--bg-dark); padding:15px; border-radius:8px; border:1px solid var(--border-color);">
                        <form method="POST" style="margin-bottom:10px;">
                            <div style="display:flex; gap:5px;">
                                <select name="list_type" style="flex:1; padding:5px;"><option value="reading">Reading</option><option value="favorite">Favorite</option><option value="completed">Completed</option></select>
                                <button type="submit" name="add_standard" class="btn" style="padding:8px;"><i class="fas fa-save"></i></button>
                            </div>
                        </form>
                        <form method="POST">
                            <div style="display:flex; gap:5px;">
                                <?php if($myCustomLists): ?>
                                    <select name="custom_list_id" style="flex:1; padding:5px;"><?php foreach($myCustomLists as $l) echo "<option value='{$l['id']}'>".htmlspecialchars($l['title'])."</option>"; ?></select>
                                    <button type="submit" name="add_custom" class="btn" style="padding:8px;"><i class="fas fa-plus"></i></button>
                                <?php else: ?>
                                    <a href="create_list.php" class="btn" style="width:100%; font-size:0.8rem; text-align:center;">Create List</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $novel['uploaded_by'] || $_SESSION['role'] == 'admin')): ?>
                    <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border-color);">
                        <h4 style="margin-bottom:10px; color:var(--text-light);">Owner Actions</h4>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <a href="edit_novel.php?id=<?php echo $id; ?>" class="btn" style="background:gray; text-align:center;">
                                <i class="fas fa-cog"></i> Edit Novel
                            </a>
                            <a href="manage_chapters.php?novel_id=<?php echo $id; ?>" class="btn" style="background:var(--primary-color); text-align:center;">
                                <i class="fas fa-list"></i> Manage Chapters
                            </a>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
                            <a href="add_chapter.php?novel_id=<?php echo $id; ?>" class="btn btn-outline" style="text-align:center;">
                                <i class="fas fa-plus"></i> Add Chapter
                            </a>
                            
                            <?php 
                            $ext = pathinfo($novel['file_path'] ?? '', PATHINFO_EXTENSION);
                            if (strtolower($ext) === 'epub'): 
                            ?>
                                <a href="extract_chapters.php?novel_id=<?php echo $id; ?>" class="btn" style="background:#9b59b6; text-align:center;" onclick="return confirm('Extract chapters from EPUB? This will append to existing chapters.');">
                                    <i class="fas fa-magic"></i> Auto Extract
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="flex: 2; min-width: 300px;">
            <h1 style="color: var(--primary-color); margin-bottom:5px;"><?php echo htmlspecialchars($novel['title']); ?></h1>
            
            <div style="margin-bottom:15px; font-size:0.9rem; color:var(--text-light); display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($novel['author']); ?></span>
                <span class="badge" style="background:var(--accent-color);"><?php echo $novel['content_type']; ?></span>
                <span class="badge" style="background:<?php echo ($novel['status']=='Ongoing'?'#3498db':'#27ae60'); ?>"><?php echo $novel['status']; ?></span>
            </div>

            <div style="margin-bottom:15px;">
                <?php foreach($genres as $g): ?>
                    <a href="search.php?genre_id=<?php echo $g['id']; ?>" style="color:var(--text-color); margin-right:10px; text-decoration:underline;"><?php echo htmlspecialchars($g['name']); ?></a>
                <?php endforeach; ?>
            </div>

            <?php if($tags): ?>
                <div style="margin-bottom:20px;">
                    <?php foreach($tags as $t): ?>
                        <a href="search.php?tag_id=<?php echo $t['id']; ?>" style="display:inline-block; background:var(--bg-dark); padding:3px 8px; border-radius:12px; font-size:0.8rem; margin-right:5px; border:1px solid var(--border-color); text-decoration:none; color:var(--text-color);">#<?php echo htmlspecialchars($t['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="border:0; border-top:1px solid var(--border-color); margin:20px 0;">
            <p style="line-height:1.8; white-space:pre-wrap;"><?php echo htmlspecialchars($novel['description']); ?></p>
            
            <h3 style="margin-top:40px; border-bottom:2px solid var(--accent-color); display:inline-block;">Chapters</h3>
            <div style="max-height:300px; overflow-y:auto; border:1px solid var(--border-color); border-radius:8px; margin-top:10px;">
                <?php if($chapters): ?>
                    <ul style="list-style:none;">
                        <?php foreach($chapters as $chap): ?>
                            <li style="border-bottom:1px solid var(--border-color);">
                                <a href="read.php?novel_id=<?php echo $id; ?>&chapter_id=<?php echo $chap['id']; ?>" style="display:block; padding:10px 15px; text-decoration:none; color:var(--text-color);">
                                    <strong>Ch. <?php echo $chap['chapter_number']; ?>:</strong> <?php echo htmlspecialchars($chap['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="padding:20px; text-align:center; color:var(--text-light);">No chapters yet.</div>
                <?php endif; ?>
            </div>

            <h3 style="margin-top:40px; border-bottom:2px solid gold; display:inline-block;">Reviews</h3>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <div style="background:var(--bg-dark); padding:15px; border-radius:8px; margin-top:15px;">
                    <form method="POST">
                        <select name="rating" style="padding:5px; margin-bottom:10px; border-radius:4px;">
                            <option value="5">⭐⭐⭐⭐⭐</option><option value="4">⭐⭐⭐⭐</option><option value="3">⭐⭐⭐</option><option value="2">⭐⭐</option><option value="1">⭐</option>
                        </select>
                        <textarea name="comment" rows="2" placeholder="Write a review..." required style="width:100%;"></textarea>
                        <button type="submit" name="submit_review" class="btn" style="margin-top:10px; font-size:0.8rem;">Post</button>
                    </form>
                </div>
            <?php endif; ?>

            <div style="margin-top:20px;">
                <?php foreach($reviews as $r): 
                    $likes = $pdo->query("SELECT COUNT(*) FROM likes WHERE target_id={$r['id']} AND target_type='review' AND vote=1")->fetchColumn();
                    $dislikes = $pdo->query("SELECT COUNT(*) FROM likes WHERE target_id={$r['id']} AND target_type='review' AND vote=-1")->fetchColumn();
                ?>
                    <div style="border-bottom:1px solid var(--border-color); padding:15px 0;">
                        <div style="display:flex; justify-content:space-between;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <img src="<?php echo htmlspecialchars($r['avatar']); ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                                <a href="profile.php?user_id=<?php echo $r['user_id']; ?>" style="color:var(--text-color); text-decoration:none;">
                                    <strong><?php echo htmlspecialchars($r['username']); ?></strong>
                                </a>
                            </div>
                            <span style="color:gold;"><?php echo str_repeat('★', $r['rating']); ?></span>
                        </div>
                        <p style="margin:10px 0;"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                        
                        <form method="POST" style="display:flex; gap:10px;">
                            <input type="hidden" name="review_id" value="<?php echo $r['id']; ?>">
                            <input type="hidden" name="vote_review" value="true">
                            <button type="submit" name="vote_value" value="1" class="btn-outline" style="padding:2px 8px; font-size:0.8rem;"><i class="fas fa-thumbs-up"></i> <?php echo $likes; ?></button>
                            <button type="submit" name="vote_value" value="-1" class="btn-outline" style="padding:2px 8px; font-size:0.8rem;"><i class="fas fa-thumbs-down"></i> <?php echo $dislikes; ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>