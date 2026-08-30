<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (!isset($_GET['novel_id'])) { header("Location: index.php"); exit; }

$novel_id = $_GET['novel_id'];
$uid = $_SESSION['user_id'];

// 1. Verify Ownership
$stmt = $pdo->prepare("SELECT title, uploaded_by FROM novels WHERE id = ?");
$stmt->execute([$novel_id]);
$novel = $stmt->fetch();

if (!$novel || ($novel['uploaded_by'] != $uid && $_SESSION['role'] != 'admin')) {
    echo "<div class='main-content'><h3>Access Denied</h3></div>";
    require_once 'includes/footer.php'; exit;
}

// --- HANDLE ACTIONS VIA HIDDEN INPUT ---
$action = $_POST['action'] ?? '';

// A. Handle Bulk Delete
if ($action === 'delete_selected' && !empty($_POST['selected_chapters'])) {
    $ids_to_delete = $_POST['selected_chapters']; 
    $count = 0;
    
    $delStmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $fileStmt = $pdo->prepare("SELECT file_path FROM chapters WHERE id = ?");

    foreach ($ids_to_delete as $chap_id) {
        $fileStmt->execute([$chap_id]);
        $path = $fileStmt->fetchColumn();
        if ($path && file_exists($path)) unlink($path);
        
        $delStmt->execute([$chap_id]);
        $count++;
    }
    
    $_SESSION['toast'] = ['icon' => 'success', 'title' => "Deleted $count chapters"];
    header("Location: manage_chapters.php?novel_id=$novel_id"); exit;
}

// B. Handle "Delete All"
if ($action === 'delete_all') {
    $files = $pdo->query("SELECT file_path FROM chapters WHERE novel_id = $novel_id")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files as $f) { if ($f && file_exists($f)) unlink($f); }
    
    $pdo->prepare("DELETE FROM chapters WHERE novel_id = ?")->execute([$novel_id]);
    
    $_SESSION['swal'] = ['icon' => 'success', 'title' => 'Clean Slate', 'text' => 'All chapters deleted.'];
    header("Location: manage_chapters.php?novel_id=$novel_id"); exit;
}

// C. Handle "Save Order"
if ($action === 'save_order' && isset($_POST['orders'])) {
    $updateStmt = $pdo->prepare("UPDATE chapters SET chapter_number = ? WHERE id = ?");
    
    foreach ($_POST['orders'] as $chap_id => $new_num) {
        $new_num = (float)$new_num; 
        $updateStmt->execute([$new_num, $chap_id]);
    }
    
    $_SESSION['toast'] = ['icon' => 'success', 'title' => 'Order Updated'];
    header("Location: manage_chapters.php?novel_id=$novel_id"); exit;
}

// Fetch Chapters
$chapters = $pdo->prepare("SELECT * FROM chapters WHERE novel_id = ? ORDER BY chapter_number ASC");
$chapters->execute([$novel_id]);
$all_chapters = $chapters->fetchAll();
?>

<div class="main-content">
    <div class="content-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2>Manage: <?php echo htmlspecialchars($novel['title']); ?></h2>
            <a href="novel.php?id=<?php echo $novel_id; ?>" class="btn" style="background:gray;">Back to Novel</a>
        </div>

        <form method="POST" id="manageForm">
            <input type="hidden" name="action" id="formAction" value="">

            <div style="background:var(--bg-dark); padding:10px; border-radius:8px; margin-bottom:15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                
                <label style="cursor:pointer; margin-right:auto; display:flex; align-items:center; gap:5px;">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()"> 
                    <strong>Select All</strong>
                </label>
                
                <button type="button" onclick="autoRenumber()" class="btn btn-outline" style="font-size:0.9rem;">
                    <i class="fas fa-sort-numeric-down"></i> Auto 1-N
                </button>

                <button type="button" onclick="submitAction('save_order')" class="btn" style="background:var(--primary-color); font-size:0.9rem;">
                    <i class="fas fa-save"></i> Save Order
                </button>
                
                <div style="width:1px; height:20px; background:var(--border-color); margin:0 5px;"></div>

                <button type="submit" name="delete_selected_btn" onclick="setAction('delete_selected')" class="btn" style="background:#e74c3c; font-size:0.9rem;">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                
                <button type="submit" name="delete_all_btn" onclick="setAction('delete_all')" class="btn" style="background:darkred; font-size:0.9rem;">
                    <i class="fas fa-bomb"></i> Delete All
                </button>
            </div>

            <div style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:var(--bg-secondary); position:sticky; top:0; z-index:10;">
                        <tr>
                            <th style="padding:10px; width:40px;">Select</th>
                            <th style="padding:10px; width:80px;">Order</th>
                            <th style="padding:10px; text-align:left;">Chapter Title</th>
                            <th style="padding:10px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($all_chapters): foreach($all_chapters as $chap): ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:10px; text-align:center;">
                                <input type="checkbox" name="selected_chapters[]" value="<?php echo $chap['id']; ?>" class="chap-checkbox">
                            </td>
                            <td style="padding:10px;">
                                <input type="number" step="0.1" name="orders[<?php echo $chap['id']; ?>]" value="<?php echo $chap['chapter_number']; ?>" class="order-input" style="width:70px; padding:5px; text-align:center; background:var(--bg-dark); color:var(--text-color); border:1px solid var(--border-color); border-radius:4px;">
                            </td>
                            <td style="padding:10px;"><?php echo htmlspecialchars($chap['title']); ?></td>
                            <td style="padding:10px; text-align:right;">
                                <a href="edit_chapter.php?id=<?php echo $chap['id']; ?>" class="btn btn-outline" style="padding:4px 10px; font-size:0.8rem;"><i class="fas fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" style="padding:20px; text-align:center;">No chapters found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectAll() {
    const master = document.getElementById('selectAll');
    document.querySelectorAll('.chap-checkbox').forEach(box => box.checked = master.checked);
}

function autoRenumber() {
    const inputs = document.querySelectorAll('.order-input');
    let count = 1;
    inputs.forEach(input => {
        input.value = count++;
        input.style.borderColor = 'var(--primary-color)';
    });
    alert("Numbers updated visually! Click 'Save Order' to apply.");
}

// 1. FOR SAVE: Bypasses SweetAlert completely
function submitAction(actionName) {
    document.getElementById('formAction').value = actionName;
    document.getElementById('manageForm').submit();
}

// 2. FOR DELETE: Sets the hidden value so PHP knows what to do after SweetAlert submits
function setAction(actionName) {
    document.getElementById('formAction').value = actionName;
}
</script>

<?php require_once 'includes/footer.php'; ?>