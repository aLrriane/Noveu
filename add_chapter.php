<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Access Denied', 'text' => 'Please login first.'];
    header("Location: login.php"); exit;
}

$novel_id = $_GET['novel_id'] ?? 0;

// Verify Owner
$stmt = $pdo->prepare("SELECT title, uploaded_by FROM novels WHERE id = ?");
$stmt->execute([$novel_id]);
$novel = $stmt->fetch();

if (!$novel || ($novel['uploaded_by'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin')) {
    $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Access Denied', 'text' => 'You do not own this novel.'];
    header("Location: index.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novel_id = $_POST['novel_id'];
    $title = trim($_POST['title']);
    $num = (float)$_POST['chapter_number']; // Allow Decimals
    $content = $_POST['content'];

    // Create Text File
    $filename = "chapter_" . $novel_id . "_" . str_replace('.', '-', $num) . "_" . uniqid() . ".txt";
    $target_dir = "uploads/chapters/";
    $target_file = $target_dir . $filename;

    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    if (file_put_contents($target_file, $content) !== false) {
        try {
            $stmt = $pdo->prepare("INSERT INTO chapters (novel_id, title, chapter_number, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$novel_id, $title, $num, $target_file]);
            
            // Notify Followers
            $followers = $pdo->prepare("SELECT follower_id FROM user_follows WHERE followed_id = ?");
            $followers->execute([$_SESSION['user_id']]);
            $f_list = $followers->fetchAll();
            
            // (Optional: You can add notification logic here later for "New Chapter Uploaded")

            $_SESSION['toast'] = ['icon' => 'success', 'title' => 'Chapter Added'];
            header("Location: novel.php?id=$novel_id"); exit;

        } catch (PDOException $e) {
            unlink($target_file); // Delete file if DB fails
            $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
        }
    } else {
        $_SESSION['swal'] = ['icon' => 'error', 'title' => 'File Error', 'text' => 'Could not save chapter content.'];
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width: 800px;">
        <h2>Add Chapter to: <?php echo htmlspecialchars($novel['title']); ?></h2>
        
        <form method="POST">
            <input type="hidden" name="novel_id" value="<?php echo $novel_id; ?>">
            
            <div class="form-group">
                <label>Chapter Number</label>
                <input type="number" name="chapter_number" step="0.01" required placeholder="e.g. 1 or 1.5">
            </div>
            
            <div class="form-group">
                <label>Chapter Title</label>
                <input type="text" name="title" required placeholder="e.g. The Beginning">
            </div>
            
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" rows="15" style="width:100%; font-family: monospace;" required placeholder="Paste story here..."></textarea>
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-top:20px;">
                <button type="submit" class="btn">Publish Chapter</button>
                <a href="novel.php?id=<?php echo $novel_id; ?>" class="btn" style="background:gray;">Back</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>