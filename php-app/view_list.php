<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit; }
$list_id = $_GET['id'];
$current_user = $_SESSION['user_id'] ?? 0;

// 1. Fetch List
$stmt = $pdo->prepare("SELECT c.*, u.username, u.avatar FROM custom_lists c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
$stmt->execute([$list_id]);
$list = $stmt->fetch();

if (!$list) die("Collection not found.");
if ($list['is_public'] == 0 && $list['user_id'] != $current_user) {
    echo "<div class='main-content'><h2>Access Denied</h2></div>";
    require_once 'includes/footer.php'; exit;
}
$is_owner = ($list['user_id'] == $current_user);

// 2. ACTIONS
if (isset($_POST['delete_list']) && $is_owner) {
    $pdo->prepare("DELETE FROM custom_lists WHERE id=?")->execute([$list_id]);
    echo "<script>window.location.href='profile.php';</script>"; exit;
}
if (isset($_POST['remove_item']) && $is_owner) {
    $pdo->prepare("DELETE FROM list_items WHERE list_id=? AND novel_id=?")->execute([$list_id, $_POST['novel_id']]);
    header("Location: view_list.php?id=$list_id"); exit;
}
if (isset($_POST['save_note']) && $is_owner) {
    $pdo->prepare("UPDATE list_items SET user_note = ? WHERE list_id = ? AND novel_id = ?")->execute([trim($_POST['user_note']), $list_id, $_POST['novel_id_note']]);
    header("Location: view_list.php?id=$list_id"); exit;
}
if (isset($_POST['like_list']) && $current_user) {
    $chk = $pdo->prepare("SELECT id FROM likes WHERE user_id=? AND target_id=? AND target_type='collection'");
    $chk->execute([$current_user, $list_id]);
    if ($chk->rowCount() > 0) $pdo->prepare("DELETE FROM likes WHERE user_id=? AND target_id=? AND target_type='collection'")->execute([$current_user, $list_id]);
    else $pdo->prepare("INSERT INTO likes (user_id, target_id, target_type, vote) VALUES (?, ?, 'collection', 1)")->execute([$current_user, $list_id]);
    header("Location: view_list.php?id=$list_id"); exit;
}

// 3. FETCH ITEMS
$novels = $pdo->query("SELECT n.*, li.user_note, li.added_at FROM list_items li JOIN novels n ON li.novel_id = n.id WHERE li.list_id = $list_id ORDER BY li.added_at DESC")->fetchAll();
$like_count = $pdo->query("SELECT COUNT(*) FROM likes WHERE target_id=$list_id AND target_type='collection'")->fetchColumn();
$i_liked = $current_user ? $pdo->query("SELECT id FROM likes WHERE user_id=$current_user AND target_id=$list_id AND target_type='collection'")->rowCount() : 0;
?>

<div class="main-content">
    <div class="content-box">
        
        <div style="border-bottom:1px solid var(--border-color); padding-bottom:20px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                
                <div>
                    <h1 style="margin-bottom:5px;"><?php echo htmlspecialchars($list['title']); ?></h1>
                    
                    <div class="meta-row">
                        <span>By <?php echo htmlspecialchars($list['username']); ?></span>
                        <span>• <?php echo $like_count; ?> Likes</span>
                        
                        <?php if(!empty($list['focuses_types'])): ?>
                            <span style="margin-left:10px;">
                                <?php foreach(explode(',', $list['focuses_types']) as $ft): ?>
                                    <span class="badge" style="background:var(--accent-color);"><?php echo trim($ft); ?></span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <p style="margin-top:15px; color:var(--text-light);"><?php echo nl2br(htmlspecialchars($list['description'])); ?></p>
                </div>

                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                    <?php if($is_owner): ?>
                        <?php 
                            $btnText = "Add Content";
                            $icon = "plus";
                            if(strpos($list['focuses_types'], 'Novel') !== false && strpos($list['focuses_types'], 'Comic') === false) {
                                $btnText = "Add Novel"; $icon = "book";
                            } elseif(strpos($list['focuses_types'], 'Comic') !== false && strpos($list['focuses_types'], 'Novel') === false) {
                                $btnText = "Add Comic"; $icon = "columns";
                            }
                        ?>
                        <a href="search.php?add_to_list=<?php echo $list_id; ?>" class="btn">
                            <i class="fas fa-<?php echo $icon; ?>"></i> <?php echo $btnText; ?>
                        </a>

                        <div style="display:flex; gap:5px;">
                            <a href="edit_list.php?id=<?php echo $list_id; ?>" class="btn btn-outline"><i class="fas fa-pencil-alt"></i></a>
                            
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this collection?');">
                                <input type="hidden" name="delete_list" value="true">
                                <button type="submit" class="btn" style="background:darkred;"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($current_user && !$is_owner): ?>
                        <form method="POST">
                            <button type="submit" name="like_list" class="btn <?php echo $i_liked?'':'btn-outline'; ?>">
                                <i class="fas fa-heart"></i> <?php echo $i_liked?'Liked':'Like'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if($novels): ?>
            <div class="novel-grid">
                <?php foreach($novels as $novel): ?>
                    <div style="position: relative;" id="item-container-<?php echo $novel['id']; ?>">
                        <?php include 'includes/collection_item.php'; ?>
                        
                        <?php if($is_owner): ?>
                            <button type="button" class="btn-edit-note" onclick="toggleNoteEditor(<?php echo $novel['id']; ?>)" 
                                    style="position:absolute; top:5px; right:40px; z-index:15; width:25px; height:25px; border-radius:50%; border:none; background:var(--primary-color); color:white; cursor:pointer;">
                                <i class="fas fa-pencil-alt" style="font-size:0.8rem;"></i>
                            </button>
                            <form method="POST" style="position: absolute; top: 5px; right: 5px; z-index: 15;" onsubmit="return confirm('Remove?');">
                                <input type="hidden" name="novel_id" value="<?php echo $novel['id']; ?>">
                                <button type="submit" name="remove_item" style="background:rgba(200,0,0,0.8); color:white; border:none; width:25px; height:25px; border-radius:50%; cursor:pointer;">&times;</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if($is_owner): ?>
                        <div id="note-editor-<?php echo $novel['id']; ?>" class="note-editor-container" style="display:none; background:var(--bg-secondary); padding:15px; border:1px solid var(--primary-color); border-radius:8px; margin-bottom:20px; grid-column:1/-1;">
                            <form method="POST">
                                <input type="hidden" name="novel_id_note" value="<?php echo $novel['id']; ?>">
                                <label style="font-weight:bold; color:var(--primary-color);">Edit Note</label>
                                <textarea name="user_note" rows="3" style="width:100%; margin:10px 0; padding:8px; background:var(--bg-dark); color:var(--text-color); border:1px solid var(--border-color);"><?php echo htmlspecialchars($novel['user_note']); ?></textarea>
                                <div style="text-align:right;">
                                    <button type="button" class="btn btn-outline" onclick="toggleNoteEditor(<?php echo $novel['id']; ?>)">Cancel</button>
                                    <button type="submit" name="save_note" class="btn">Save</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:50px 20px;">
                <p style="color:var(--text-light); font-size:1.1rem; margin-bottom:20px;">This collection is empty.</p>
                <a href="search.php?add_to_list=<?php echo $list_id; ?>" class="btn" style="padding:10px 20px;"><i class="fas fa-search"></i> Add Content Now</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function toggleNoteEditor(id) {
    const editor = document.getElementById('note-editor-' + id);
    if (editor.style.display === 'block') editor.style.display = 'none';
    else {
        document.querySelectorAll('.note-editor-container').forEach(el => el.style.display = 'none');
        editor.style.display = 'block';
    }
}
</script>
<?php