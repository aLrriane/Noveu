<?php
require_once 'config/db.php';
require_once 'classes/ChapterLinkedList.php'; 
require_once 'includes/header.php';

$novel_id = $_GET['novel_id'] ?? 0;
$chapter_id = $_GET['chapter_id'] ?? 0;
$current_user = $_SESSION['user_id'] ?? 0;

// 1. Fetch User Preferences (Font Size)
$fontSizeClass = 'font-medium';
if ($current_user) {
    $prefStmt = $pdo->prepare("SELECT font_size FROM users WHERE id = ?");
    $prefStmt->execute([$current_user]);
    $userPref = $prefStmt->fetch();
    if ($userPref) $fontSizeClass = 'font-' . $userPref['font_size']; 
}

// 2. Fetch Novel & Uploader
$novStmt = $pdo->prepare("SELECT title, uploaded_by FROM novels WHERE id = ?");
$novStmt->execute([$novel_id]);
$novelData = $novStmt->fetch();

if (!$novelData) {
    echo "<div class='main-content' style='padding:50px; text-align:center;'><h3>Content Unavailable</h3><p>This novel may have been deleted.</p><a href='index.php' class='btn'>Go Home</a></div>";
    require_once 'includes/footer.php'; exit;
}
$uploader_id = $novelData['uploaded_by'];

// 3. Fetch Chapters & Build List
$stmt = $pdo->prepare("SELECT id, title, chapter_number, file_path FROM chapters WHERE novel_id = ? ORDER BY chapter_number ASC");
$stmt->execute([$novel_id]);
$all_chapters = $stmt->fetchAll();

$chapterList = new ChapterLinkedList();
foreach ($all_chapters as $chapData) $chapterList->addChapter($chapData);

$currentNode = $chapterList->getChapterNode($chapter_id);
if (!$currentNode) {
    echo "<div class='main-content' style='padding:50px; text-align:center;'><h3>Chapter not found.</h3></div>";
    require_once 'includes/footer.php'; exit;
}

$currentChapter = $currentNode->data;
$prevNode = $currentNode->prev; 
$nextNode = $currentNode->next; 

// 4. Load Content
$content_text = "Error loading content.";
if (file_exists($currentChapter['file_path'])) {
    $content_text = file_get_contents($currentChapter['file_path']);
}

// 5. Update History
if ($current_user) {
    $check = $pdo->prepare("SELECT id FROM reading_history WHERE user_id=? AND novel_id=?");
    $check->execute([$current_user, $novel_id]);
    if ($check->rowCount() > 0) {
        $pdo->prepare("UPDATE reading_history SET last_chapter_id=? WHERE user_id=? AND novel_id=?")->execute([$chapter_id, $current_user, $novel_id]);
    } else {
        $pdo->prepare("INSERT INTO reading_history (user_id, novel_id, last_chapter_id) VALUES (?, ?, ?)")->execute([$current_user, $novel_id, $chapter_id]);
    }
}

// =========================================
// POST ACTIONS (Comments, Likes, Edit, Delete)
// =========================================

// A. Post Comment
if (isset($_POST['post_comment']) && $current_user) {
    $comment = trim($_POST['comment']);
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

    if ($comment) {
        $pdo->prepare("INSERT INTO chapter_comments (chapter_id, user_id, comment, parent_id) VALUES (?, ?, ?, ?)")
            ->execute([$chapter_id, $current_user, $comment, $parent_id]);
        
        if ($parent_id) {
            $pUser = $pdo->query("SELECT user_id FROM chapter_comments WHERE id=$parent_id")->fetchColumn();
            sendNotification($pdo, $pUser, 'reply_chapter', $chapter_id);
        } else {
            sendNotification($pdo, $uploader_id, 'comment_chapter', $chapter_id);
        }
    }
    header("Location: read.php?novel_id=$novel_id&chapter_id=$chapter_id"); exit;
}

// B. Edit Comment (NEW)
if (isset($_POST['edit_comment']) && $current_user) {
    $cid = $_POST['comment_id'];
    $new_text = trim($_POST['new_text']);
    // Verify Owner
    $stmt = $pdo->prepare("UPDATE chapter_comments SET comment = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$new_text, $cid, $current_user]);
    
    $_SESSION['toast'] = ['icon' => 'success', 'title' => 'Comment Updated'];
    header("Location: read.php?novel_id=$novel_id&chapter_id=$chapter_id"); exit;
}

// C. Delete Comment (NEW)
if (isset($_POST['delete_comment']) && $current_user) {
    $cid = $_POST['comment_id'];
    // Verify Owner
    $stmt = $pdo->prepare("DELETE FROM chapter_comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$cid, $current_user]);
    
    $_SESSION['toast'] = ['icon' => 'success', 'title' => 'Comment Deleted'];
    header("Location: read.php?novel_id=$novel_id&chapter_id=$chapter_id"); exit;
}

// D. Like Comment
if (isset($_POST['like_comment']) && $current_user) {
    $cid = $_POST['comment_id'];
    $chk = $pdo->prepare("SELECT id FROM likes WHERE user_id=? AND target_id=? AND target_type='chapter_comment'");
    $chk->execute([$current_user, $cid]);
    
    if ($chk->rowCount() > 0) {
        $pdo->prepare("DELETE FROM likes WHERE user_id=? AND target_id=? AND target_type='chapter_comment'")->execute([$current_user, $cid]);
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, target_id, target_type, vote) VALUES (?, ?, 'chapter_comment', 1)")->execute([$current_user, $cid]);
        $cUser = $pdo->query("SELECT user_id FROM chapter_comments WHERE id=$cid")->fetchColumn();
        sendNotification($pdo, $cUser, 'like_chapter', $chapter_id);
    }
    header("Location: read.php?novel_id=$novel_id&chapter_id=$chapter_id"); exit;
}

// FETCH COMMENTS
$comments = $pdo->query("SELECT c.*, u.username, u.avatar, 
                        (SELECT COUNT(*) FROM likes WHERE target_id=c.id AND target_type='chapter_comment') as likes
                        FROM chapter_comments c JOIN users u ON c.user_id = u.id 
                        WHERE chapter_id=$chapter_id ORDER BY created_at ASC")->fetchAll();

$threaded = [];
foreach ($comments as $c) {
    if ($c['parent_id']) $threaded[$c['parent_id']]['replies'][] = $c;
    else $threaded[$c['id']] = $c;
}
?>

<div class="content-box" style="max-width: 900px; margin: 30px auto;">
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <?php if ($prevNode): ?>
            <a href="read.php?novel_id=<?php echo $novel_id; ?>&chapter_id=<?php echo $prevNode->data['id']; ?>" class="btn"><i class="fas fa-arrow-left"></i> Prev</a>
        <?php else: ?>
            <button class="btn" style="background: grey; cursor: not-allowed;" disabled>Prev</button>
        <?php endif; ?>

        <a href="novel.php?id=<?php echo $novel_id; ?>" class="btn" style="background: var(--primary-color);">Table of Contents</a>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <a href="edit_chapter.php?id=<?php echo $chapter_id; ?>" class="btn" style="background: #e67e22;">Edit</a>
        <?php endif; ?>

        <?php if ($nextNode): ?>
            <a href="read.php?novel_id=<?php echo $novel_id; ?>&chapter_id=<?php echo $nextNode->data['id']; ?>" class="btn">Next <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
            <button class="btn" style="background: grey; cursor: not-allowed;" disabled>Next</button>
        <?php endif; ?>
    </div>

    <h2 style="text-align: center;">Ch. <?php echo $currentChapter['chapter_number']; ?>: <?php echo htmlspecialchars($currentChapter['title']); ?></h2>
    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
    
    <div class="chapter-content <?php echo $fontSizeClass; ?>">
        <?php echo nl2br(htmlspecialchars($content_text)); ?>
    </div>

    <div style="display: flex; justify-content: center; margin-top: 40px; margin-bottom: 50px;">
        <?php if ($nextNode): ?>
            <a href="read.php?novel_id=<?php echo $novel_id; ?>&chapter_id=<?php echo $nextNode->data['id']; ?>" class="btn" style="width: 100%; text-align: center;">Next Chapter <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
             <div class="alert" style="background: #d1ecf1; color: #0c5460;">You have reached the latest chapter.</div>
        <?php endif; ?>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 40px 0;">
    <h3 style="margin-bottom: 20px;">Discussion (<?php echo count($comments); ?>)</h3>

    <?php if($current_user): ?>
        <form method="POST" style="margin-bottom:30px;">
            <div style="display:flex; gap:10px;">
                <input type="text" name="comment" placeholder="Join the discussion..." required style="flex:1;">
                <button type="submit" name="post_comment" class="btn">Post</button>
            </div>
        </form>
    <?php else: ?>
        <p style="margin-bottom:20px; color:gray;"><a href="login.php" style="color:var(--primary-color);">Login</a> to comment.</p>
    <?php endif; ?>

    <div class="comment-thread">
        <?php foreach($threaded as $c): ?>
            <?php renderComment($c, $current_user, 0); ?>
            <?php if(isset($c['replies'])): foreach($c['replies'] as $reply): ?>
                <?php renderComment($reply, $current_user, 1); ?>
            <?php endforeach; endif; ?>
        <?php endforeach; ?>
    </div>

</div>

<script>
function toggleEditComment(id) {
    const textDiv = document.getElementById('comment-text-' + id);
    const formDiv = document.getElementById('edit-form-' + id);
    if(formDiv.style.display === 'block') {
        formDiv.style.display = 'none';
        textDiv.style.display = 'block';
    } else {
        formDiv.style.display = 'block';
        textDiv.style.display = 'none';
    }
}
</script>

<?php 
// Helper to Render Comments with Edit/Delete
function renderComment($c, $uid, $is_reply) {
    $indentClass = $is_reply ? 'comment-reply' : '';
    ?>
    <div class="comment-box <?php echo $indentClass; ?>">
        <div style="display:flex; gap:10px; align-items:flex-start;">
            <img src="<?php echo htmlspecialchars($c['avatar']); ?>" style="width:30px; height:30px; border-radius:50%; flex-shrink:0;">
            
            <div style="flex:1;">
                <div style="display:flex; align-items:center; margin-bottom:5px;">
                    <a href="profile.php?user_id=<?php echo $c['user_id']; ?>" style="color:var(--primary-color); text-decoration:none; font-weight:bold; margin-right:10px;">
                        <?php echo htmlspecialchars($c['username']); ?>
                    </a>
                    <small style="color:var(--text-light);"><?php echo date('M d', strtotime($c['created_at'])); ?></small>
                    
                    <?php if($uid == $c['user_id']): ?>
                        <div class="comment-tools">
                            <button onclick="toggleEditComment(<?php echo $c['id']; ?>)" class="tool-btn" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete comment?');">
                                <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" name="delete_comment" class="tool-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <p id="comment-text-<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['comment']); ?></p>

                <?php if($uid == $c['user_id']): ?>
                    <form method="POST" id="edit-form-<?php echo $c['id']; ?>" class="edit-comment-form">
                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                        <div style="display:flex; gap:5px;">
                            <input type="text" name="new_text" value="<?php echo htmlspecialchars($c['comment']); ?>" required style="flex:1; padding:5px; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-color);">
                            <button type="submit" name="edit_comment" class="btn" style="padding:5px 10px; font-size:0.8rem;">Save</button>
                            <button type="button" class="btn btn-outline" onclick="toggleEditComment(<?php echo $c['id']; ?>)" style="padding:5px 10px; font-size:0.8rem;">&times;</button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="comment-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" name="like_comment" style="background:none; border:none; color:var(--text-light); cursor:pointer;">
                            <i class="fas fa-heart"></i> <?php echo $c['likes']; ?>
                        </button>
                    </form>

                    <?php if($uid && !$is_reply): ?>
                        <span class="action-link" onclick="document.getElementById('reply-form-<?php echo $c['id']; ?>').classList.toggle('active')">
                            <i class="fas fa-reply"></i> Reply
                        </span>
                    <?php endif; ?>
                </div>

                <?php if($uid && !$is_reply): ?>
                <div id="reply-form-<?php echo $c['id']; ?>" class="reply-form">
                    <form method="POST" style="display:flex; gap:10px;">
                        <input type="hidden" name="parent_id" value="<?php echo $c['id']; ?>">
                        <input type="text" name="comment" placeholder="Reply..." required style="flex:1; font-size:0.9rem;">
                        <button type="submit" name="post_comment" class="btn" style="padding:5px 10px; font-size:0.8rem;">Send</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
require_once 'includes/footer.php'; 
?>