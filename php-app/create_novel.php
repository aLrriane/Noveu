<?php
require_once 'config/db.php';
require_once 'includes/header.php';


if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$allGenres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll();
$popupScript = "";

// Increase time limit for parsing large files (InfinityFree might limit this, but it helps)
@set_time_limit(300); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Basic Sanitization
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $description = trim($_POST['description']);
    $tags_input = trim($_POST['tags']);
    $type = $_POST['content_type'];
    $status = $_POST['status'];
    $link = trim($_POST['external_link']);
    $genres = $_POST['genres'] ?? [];
    $uid = $_SESSION['user_id'];
    $auto_extract = isset($_POST['auto_extract']); // Checkbox Status

    if (empty($title) || empty($author) || empty($genres)) {
        $popupScript = "showPopup('error', 'Missing Info', 'Title, Author, and Genre are required.');";
    } else {
        // 2. Handle File Uploads
        $cover = "assets/images/default_cover.png";
        $file_path = null;
        $uploaded_epub_tmp = null; // Temp path for parser

        // Cover Image
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg','png','jpeg'])) {
                $new = time()."_cover.".$ext;
                move_uploaded_file($_FILES['cover_image']['tmp_name'], "uploads/covers/".$new);
                $cover = "uploads/covers/".$new;
            }
        }

        // Novel File (EPUB/PDF)
        if (isset($_FILES['novel_file']) && $_FILES['novel_file']['error'] == 0) {
            $ext = pathinfo($_FILES['novel_file']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['pdf','epub'])) {
                $new = time()."_file.".$ext;
                if (!is_dir('uploads/files')) mkdir('uploads/files', 0777, true);
                
                $dest = "uploads/files/".$new;
                move_uploaded_file($_FILES['novel_file']['tmp_name'], $dest);
                $file_path = $dest;
                
                // Keep track if it's an EPUB for extraction
                if (strtolower($ext) === 'epub') {
                    $uploaded_epub_tmp = $dest;
                }
            }
        }

        // 3. Database Insertion (Novel Info)
        try {
            $pdo->beginTransaction();
            
            $gStr = ""; foreach($allGenres as $g) { if(in_array($g['id'], $genres)) $gStr .= $g['name'].", "; }
            
            $sql = "INSERT INTO novels (title, author, content_type, status, genre, description, cover_image, file_path, external_link, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$title, $author, $type, $status, $gStr, $description, $cover, $file_path, $link, $uid]);
            $nid = $pdo->lastInsertId();

            foreach ($genres as $gid) $pdo->prepare("INSERT INTO novel_genres (novel_id, genre_id) VALUES (?, ?)")->execute([$nid, $gid]);
            
            if ($tags_input) {
                $tags = explode(',', $tags_input);
                foreach ($tags as $tn) {
                    $tn = trim($tn); if(!$tn) continue;
                    $tid = $pdo->query("SELECT id FROM tags WHERE name='$tn'")->fetchColumn();
                    if(!$tid) { $pdo->prepare("INSERT INTO tags (name) VALUES (?)")->execute([$tn]); $tid = $pdo->lastInsertId(); }
                    $pdo->prepare("INSERT INTO novel_tags (novel_id, tag_id) VALUES (?, ?)")->execute([$nid, $tid]);
                }
            }



            $pdo->commit();
            
            // Success Message
            if ($extracted_count > 0) {
                $popupScript = "showPopup('success', 'Uploaded & Extracted!', 'Novel saved and $extracted_count chapters were automatically extracted.', 'profile.php');";
            } else {
                $popupScript = "showPopup('success', 'Uploaded!', 'Your content is now live.', 'profile.php');";
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $popupScript = "showPopup('error', 'Failed', 'Database error: " . addslashes($e->getMessage()) . "');";
        }
    }
}
?>

<div class="main-content">
    <div class="form-container">
        <h2>Upload Content</h2>
        <form method="POST" enctype="multipart/form-data">
             <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1"><label>Type</label><select name="content_type"><option>Novel</option><option>Manhwa</option><option>Manga</option><option>Anime</option><option>KDrama</option></select></div>
                <div class="form-group" style="flex:1"><label>Status</label><select name="status"><option>Ongoing</option><option>Completed</option></select></div>
            </div>
            <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Author</label><input type="text" name="author" required></div>
            <div class="form-group"><label>Genres</label>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:5px;">
                    <?php foreach($allGenres as $g): ?><label><input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>"> <?php echo $g['name']; ?></label><?php endforeach; ?>
                </div>
            </div>
            <div class="form-group"><label>Tags</label><input type="text" name="tags"></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="4"></textarea></div>
            <div class="form-group"><label>Link</label><input type="url" name="external_link"></div>
            <div class="form-group"><label>Cover</label><input type="file" name="cover_image"></div>
            
            <div class="form-group" style="background: var(--bg-dark); padding: 15px; border-radius: 8px; border: 1px dashed var(--border-color);">
                <label>Novel File (EPUB or PDF)</label>
                <input type="file" name="novel_file" id="novel_file_input" accept=".epub,.pdf">
                
                <div id="pdf_warning" style="display:none; color: #e67e22; font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Automated chapter extraction is not supported for PDF files. The file will be available for download only.
                </div>

            
            </div>

            <button type="submit" class="btn">Upload</button>
        </form>
    </div>
</div>

<script>
// FRONTEND LOGIC FOR FILE INPUT
document.getElementById('novel_file_input').addEventListener('change', function(e) {
    const fileName = e.target.value;
    const pdfWarning = document.getElementById('pdf_warning');
    const epubOption = document.getElementById('epub_option');
    
    // Reset display
    pdfWarning.style.display = 'none';
    epubOption.style.display = 'none';

    if (fileName) {
        // Get extension
        const ext = fileName.split('.').pop().toLowerCase();
        
        if (ext === 'pdf') {
            pdfWarning.style.display = 'block';
        } else if (ext === 'epub') {
            epubOption.style.display = 'block';
        }
    }
});
</script>

<?php if($popupScript): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { <?php echo $popupScript; ?> });</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>