<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { die("Access Denied"); }

$chapter_id = $_GET['id'] ?? 0;

// 1. Fetch Chapter & Verify Owner
$sql = "SELECT c.*, n.uploaded_by 
        FROM chapters c 
        JOIN novels n ON c.novel_id = n.id 
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$chapter_id]);
$chapter = $stmt->fetch();

if (!$chapter || ($chapter['uploaded_by'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin')) {
    echo "<div class='main-content'><h3>Access Denied / Not Found</h3></div>";
    require_once 'includes/footer.php'; exit;
}

// 2. Load Content from File
$current_content = "";
if (file_exists($chapter['file_path'])) {
    $current_content = file_get_contents($chapter['file_path']);
} else {
    $current_content = "Error: File not found at " . $chapter['file_path'];
}

// 3. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $new_content = $_POST['content'];
    
    // Update DB Title
    $upd = $pdo->prepare("UPDATE chapters SET title=? WHERE id=?");
    $upd->execute([$title, $chapter_id]);
    
    // Update File Content
    file_put_contents($chapter['file_path'], $new_content);
    
    // SUCCESS TOAST & REDIRECT TO READ PAGE
    $_SESSION['toast'] = ['icon' => 'success', 'title' => 'Chapter Saved'];
    header("Location: read.php?novel_id=" . $chapter['novel_id'] . "&chapter_id=" . $chapter_id);
    exit;
}
?>

<div class="main-content" style="padding: 20px;">
    <div class="form-container" style="max-width: 800px;">
        <h2>Edit Chapter <?php echo $chapter['chapter_number']; ?></h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Chapter Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($chapter['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" rows="20" required style="font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($current_content); ?></textarea>
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-top:20px;">
                <button type="submit" class="btn">Save Changes</button>
                <a href="manage_chapters.php?novel_id=<?php echo $chapter['novel_id']; ?>" class="btn" style="background: gray;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>