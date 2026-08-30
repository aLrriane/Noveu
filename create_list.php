<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$popupScript = "";
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $privacy = ($_POST['privacy'] === 'public') ? 1 : 0;
    
    // Handle "Focus Types" (e.g., Novel, Comic)
    $types = isset($_POST['focus_types']) ? implode(',', $_POST['focus_types']) : '';

    if (empty($title)) {
        $popupScript = "showPopup('error', 'Missing Title', 'Please give your collection a name.');";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO custom_lists (user_id, title, description, is_public, focuses_types) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $desc, $privacy, $types]);
            
            // BEAUTIFUL SUCCESS POPUP
            $popupScript = "showPopup('success', 'Created!', 'Your collection is ready.', 'profile.php');";
            
        } catch (PDOException $e) {
            $popupScript = "showPopup('error', 'Error', 'Database error: " . addslashes($e->getMessage()) . "');";
        }
    }
}
?>

<div class="main-content">
    <div class="form-container" style="max-width: 600px;">
        <h2><i class="fas fa-plus-square"></i> Create Collection</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Collection Title <span style="color:red">*</span></label>
                <input type="text" name="title" placeholder="e.g., Top Tier Martial Arts" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="What is this list about?"></textarea>
            </div>

            <div class="form-group">
                <label>Content Focus (Optional)</label>
                <p style="font-size:0.8rem; color:var(--text-light); margin-bottom:5px;">What kind of content goes here?</p>
                <div style="display:flex; gap:15px; flex-wrap:wrap;">
                    <label><input type="checkbox" name="focus_types[]" value="Novel"> Novel</label>
                    <label><input type="checkbox" name="focus_types[]" value="Comic"> Comic</label>
                    <label><input type="checkbox" name="focus_types[]" value="Animation"> Animation</label>
                    <label><input type="checkbox" name="focus_types[]" value="Drama"> Drama</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Privacy</label>
                <select name="privacy">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>
            
            <button type="submit" class="btn" style="margin-top:10px;">Create Collection</button>
            <a href="profile.php" class="btn btn-outline" style="margin-top:10px;">Cancel</a>
        </form>
    </div>
</div>

<?php if($popupScript): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { <?php echo $popupScript; ?> });</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>