<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$list_id = $_GET['id'];
$uid = $_SESSION['user_id'];
$popupScript = "";

// Fetch List
$stmt = $pdo->prepare("SELECT * FROM custom_lists WHERE id = ?");
$stmt->execute([$list_id]);
$list = $stmt->fetch();

if (!$list || $list['user_id'] != $uid) die("Access Denied.");

// Pre-process Focus Types for Checkboxes
$currentTypes = !empty($list['focuses_types']) ? explode(',', $list['focuses_types']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $privacy = ($_POST['privacy'] === 'public') ? 1 : 0;
    $types = isset($_POST['focus_types']) ? implode(',', $_POST['focus_types']) : '';
    
    try {
        $upd = $pdo->prepare("UPDATE custom_lists SET title=?, description=?, is_public=?, focuses_types=? WHERE id=?");
        $upd->execute([$title, $desc, $privacy, $types, $list_id]);
        
        $popupScript = "showPopup('success', 'Updated!', 'List details saved.', 'view_list.php?id=$list_id');";
    } catch (Exception $e) {
        $popupScript = "showPopup('error', 'Error', 'Could not update list.');";
    }
}
?>

<div class="main-content">
    <div class="form-container">
        <h2>Edit Collection</h2>
        <form method="POST">
            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($list['title']); ?>" required></div>
            
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?php echo htmlspecialchars($list['description']); ?></textarea></div>

            <div class="form-group">
                <label>Content Focus</label>
                <div style="display:flex; gap:15px; flex-wrap:wrap;">
                    <?php 
                    $options = ['Novel', 'Comic', 'Animation', 'Drama'];
                    foreach($options as $opt): 
                        $checked = in_array($opt, $currentTypes) ? 'checked' : '';
                    ?>
                        <label><input type="checkbox" name="focus_types[]" value="<?php echo $opt; ?>" <?php echo $checked; ?>> <?php echo $opt; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Privacy</label>
                <select name="privacy">
                    <option value="public" <?php if($list['is_public']) echo 'selected'; ?>>Public</option>
                    <option value="private" <?php if(!$list['is_public']) echo 'selected'; ?>>Private</option>
                </select>
            </div>
            
            <button type="submit" class="btn">Save Changes</button>
            <a href="view_list.php?id=<?php echo $list_id; ?>" class="btn btn-outline">Cancel</a>
        </form>
    </div>
</div>

<?php if($popupScript): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { <?php echo $popupScript; ?> });</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>