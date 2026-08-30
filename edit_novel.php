<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit; }
$id = $_GET['id'];
$uid = $_SESSION['user_id'] ?? 0;

// 1. Fetch Novel Data
$stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
$stmt->execute([$id]);
$novel = $stmt->fetch();

// Security Check
if (!$novel || ($novel['uploaded_by'] != $uid && $_SESSION['role'] != 'admin')) {
    echo "<div class='main-content'><h3>Access Denied.</h3></div>";
    require_once 'includes/footer.php'; exit;
}

// 2. Fetch Helper Data
$allGenres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll();
$curGenresStmt = $pdo->prepare("SELECT genre_id FROM novel_genres WHERE novel_id = ?");
$curGenresStmt->execute([$id]);
$current_genre_ids = $curGenresStmt->fetchAll(PDO::FETCH_COLUMN);

$curTagsStmt = $pdo->prepare("SELECT t.name FROM tags t JOIN novel_tags nt ON t.id = nt.tag_id WHERE nt.novel_id = ?");
$curTagsStmt->execute([$id]);
$current_tags_str = implode(", ", $curTagsStmt->fetchAll(PDO::FETCH_COLUMN));

$msg = "";

// 3. HANDLE UPDATE
if (isset($_POST['update'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $desc = trim($_POST['description']);
    $type = $_POST['content_type'];
    $status = $_POST['status'];
    $link = trim($_POST['external_link']);
    $tags_input = trim($_POST['tags']);
    $selected_genres = $_POST['genres'] ?? [];
    
    // --- FILE HANDLING ---
    
    // A. Handle Cover Image
    $cover_path = $novel['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'png', 'jpeg'])) {
            // Delete Old File
            if ($cover_path && file_exists($cover_path) && strpos($cover_path, 'default_cover') === false) {
                unlink($cover_path);
            }
            // Upload New
            $new_name = time() . "_cover." . $ext;
            move_uploaded_file($_FILES['cover_image']['tmp_name'], "uploads/covers/" . $new_name);
            $cover_path = "uploads/covers/" . $new_name;
        }
    }

    // B. Handle Novel File (EPUB/PDF)
    $file_path = $novel['file_path'];
    $reset_chapters = isset($_POST['reset_chapters']); // Checkbox

    if (isset($_FILES['novel_file']) && $_FILES['novel_file']['error'] == 0) {
        $ext = pathinfo($_FILES['novel_file']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['pdf', 'epub'])) {
            // Delete Old File
            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }
            // Upload New
            $new_name = time() . "_file." . $ext;
            if (!is_dir('uploads/files')) mkdir('uploads/files', 0777, true);
            $dest = "uploads/files/" . $new_name;
            move_uploaded_file($_FILES['novel_file']['tmp_name'], $dest);
            $file_path = $dest;

            // Reset Chapters Logic
            if ($reset_chapters) {
                $pdo->prepare("DELETE FROM chapters WHERE novel_id = ?")->execute([$id]);
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $gStr = ""; foreach($allGenres as $g) { if(in_array($g['id'], $selected_genres)) $gStr .= $g['name'] . ", "; }
        $gStr = rtrim($gStr, ", ");

        // Update Main Table
        $sql = "UPDATE novels SET title=?, author=?, content_type=?, status=?, genre=?, description=?, external_link=?, cover_image=?, file_path=? WHERE id=?";
        $pdo->prepare($sql)->execute([$title, $author, $type, $status, $gStr, $desc, $link, $cover_path, $file_path, $id]);

        // Update Genres
        $pdo->prepare("DELETE FROM novel_genres WHERE novel_id = ?")->execute([$id]);
        foreach ($selected_genres as $gid) {
            $pdo->prepare("INSERT INTO novel_genres (novel_id, genre_id) VALUES (?, ?)")->execute([$id, $gid]);
        }

        // Update Tags
        $pdo->prepare("DELETE FROM novel_tags WHERE novel_id = ?")->execute([$id]);
        if ($tags_input) {
            foreach (explode(',', $tags_input) as $tn) {
                $tn = trim($tn); if(!$tn) continue;
                $tid = $pdo->query("SELECT id FROM tags WHERE name='$tn'")->fetchColumn();
                if (!$tid) {
                    $pdo->prepare("INSERT INTO tags (name) VALUES (?)")->execute([$tn]);
                    $tid = $pdo->lastInsertId();
                }
                $pdo->prepare("INSERT INTO novel_tags (novel_id, tag_id) VALUES (?, ?)")->execute([$id, $tid]);
            }
        }

        $pdo->commit();
        
        // SUCCESS POPUP & REDIRECT
        $_SESSION['swal'] = [
            'icon' => 'success', 
            'title' => 'Saved', 
            'text' => 'Novel details updated successfully.'
        ];
        header("Location: novel.php?id=$id"); 
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='alert' style='background:#f8d7da; color:#721c24;'>Error: " . $e->getMessage() . "</div>";
    }
}

// 4. HANDLE DELETE
if (isset($_POST['delete_novel'])) {
    if ($novel['cover_image'] && file_exists($novel['cover_image']) && strpos($novel['cover_image'], 'default') === false) unlink($novel['cover_image']);
    if ($novel['file_path'] && file_exists($novel['file_path'])) unlink($novel['file_path']);
    
    $pdo->prepare("DELETE FROM novels WHERE id = ?")->execute([$id]);
    
    $_SESSION['swal'] = ['icon' => 'success', 'title' => 'Deleted', 'text' => 'Novel removed successfully.'];
    header("Location: profile.php");
    exit;
}
?>

<div class="main-content">
    <div class="form-container" style="max-width: 800px;">
        <h2>Edit Novel: <?php echo htmlspecialchars($novel['title']); ?></h2>
        <?php echo $msg; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                <div class="form-group" style="flex:1;"><label>Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($novel['title']); ?>" required></div>
                <div class="form-group" style="flex:1;"><label>Author</label><input type="text" name="author" value="<?php echo htmlspecialchars($novel['author']); ?>" required></div>
            </div>

            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                <div class="form-group" style="flex:1;">
                    <label>Type</label>
                    <select name="content_type">
                        <?php foreach(['Novel', 'Manhwa', 'Manga', 'Anime', 'KDrama'] as $t) echo "<option value='$t' ".($novel['content_type']==$t?'selected':'').">$t</option>"; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach(['Ongoing', 'Completed', 'Hiatus'] as $s) echo "<option value='$s' ".($novel['status']==$s?'selected':'').">$s</option>"; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Genres</label>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:8px; max-height:150px; overflow-y:auto; padding:10px; border:1px solid var(--border-color);">
                    <?php foreach($allGenres as $g): ?>
                        <label style="cursor:pointer;"><input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>" <?php if(in_array($g['id'], $current_genre_ids)) echo "checked"; ?>> <?php echo htmlspecialchars($g['name']); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group"><label>Tags</label><input type="text" name="tags" value="<?php echo htmlspecialchars($current_tags_str); ?>"></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="6" required><?php echo htmlspecialchars($novel['description']); ?></textarea></div>
            <div class="form-group"><label>Link</label><input type="url" name="external_link" value="<?php echo htmlspecialchars($novel['external_link']); ?>"></div>

            <div class="form-group">
                <label>Change Cover</label>
                <div style="display:flex; align-items:center; gap:15px; margin-top:5px;">
                    <img src="<?php echo htmlspecialchars($novel['cover_image']); ?>" style="width:60px; height:90px; object-fit:cover; border-radius:4px;">
                    <input type="file" name="cover_image">
                </div>
            </div>

            <div class="form-group" style="background:var(--bg-dark); padding:15px; border-radius:8px; border:1px dashed var(--border-color);">
                <label>Update File (EPUB/PDF)</label>
                <input type="file" name="novel_file" id="file_input" accept=".epub,.pdf">
                
                <?php if(!empty($novel['file_path'])): ?>
                    <p style="font-size:0.8rem; color:green; margin:5px 0;"><i class="fas fa-check"></i> Current file: <?php echo basename($novel['file_path']); ?></p>
                <?php endif; ?>

                <div id="reset_chapters_box" style="display:none; margin-top:10px;">
                    <label style="color: #e74c3c; cursor:pointer;">
                        <input type="checkbox" name="reset_chapters" value="1"> 
                        <strong>Delete all existing chapters?</strong>
                    </label>
                    <p style="font-size:0.8rem; color:gray; margin-left:22px;">Check this if you plan to re-extract chapters from the new file.</p>
                </div>
            </div>

            <div style="margin-top:30px; display:flex; justify-content:space-between; align-items:center;">
                <button type="submit" name="update" class="btn">Save Changes</button>
                <a href="novel.php?id=<?php echo $id; ?>" class="btn" style="background:gray;">Cancel</a>
            </div>
        </form>

        <form method="POST" onsubmit="return confirm('Delete this novel?');" style="margin-top:30px; text-align:right;">
            <button type="submit" name="delete_novel" class="btn" style="background:darkred;">Delete Novel</button>
        </form>
    </div>
</div>

<script>
document.getElementById('file_input').addEventListener('change', function() {
    document.getElementById('reset_chapters_box').style.display = this.value ? 'block' : 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>