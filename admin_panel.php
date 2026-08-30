<?php
require_once 'config/db.php';
require_once 'includes/header.php';

//checks for:
//is user logged in? (!isset)
//is role strictly 'admin'?
//if any is false, redirect them to the homepage
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

//HANDLE USER DELETION (POST Request)
if (isset($_POST['delete_user'])) {
    $id = $_POST['target_id']; //The ID of the user to delete
    

    //because of ON DELETE CASCADE in the database schema, deleting a user automatically deletes their reviews, custom lists, and reading history.
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    
    //Alert and Refresh
    echo "<script>alert('User deleted.'); window.location.href='admin_panel.php';</script>";
}

//4. HANDLE NOVEL DELETION (POST Request)
if (isset($_POST['delete_novel'])) {
    $id = $_POST['target_id'];
    

    //This removes the book, its chapters, and reviews from the database.
    $pdo->prepare("DELETE FROM novels WHERE id=?")->execute([$id]);
    

    //After deleting a novel, check if any tags are not attached to any book, if a tag has 0 connections in 'novel_tags', delete it to keep the DB clean
    $pdo->query("DELETE FROM tags WHERE id NOT IN (SELECT DISTINCT tag_id FROM novel_tags)");
    
    //resets the auto-increment if the table is empty, to keep IDs low
    $pdo->query("ALTER TABLE tags AUTO_INCREMENT = 1");
    
    echo "<script>alert('Novel deleted (Tags cleaned).'); window.location.href='admin_panel.php';</script>";
}



//use simple COUNT queries to get the total numbers
//fetchColumn() is faster than fetching rows because one number only is needed
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$novelCount = $pdo->query("SELECT COUNT(*) FROM novels")->fetchColumn();
$reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();


//Users: Gets everyone, newest first.
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

//Novels: Get all books + join with Users table to show who uploaded them
//LEFT JOIN ensures users see the novel even if the user account was deleted (uploaded_by IS NULL).
$novels = $pdo->query("SELECT n.*, u.username FROM novels n LEFT JOIN users u ON n.uploaded_by = u.id ORDER BY n.upload_date DESC")->fetchAll();
?>

<div class="main-content" style="padding: 20px;">
    <div class="content-box" style="max-width: 1200px;">
        <h1><i class="fas fa-user-shield"></i> Admin Dashboard</h1>
        
        <div style="display: flex; gap: 20px; margin: 30px 0; flex-wrap: wrap;">
            <div style="flex: 1; background: var(--primary-color); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <h3><?php echo $userCount; ?></h3>
                <p>Total Users</p>
            </div>
            <div style="flex: 1; background: var(--accent-color); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <h3><?php echo $novelCount; ?></h3>
                <p>Novels Uploaded</p>
            </div>
            <div style="flex: 1; background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <h3><?php echo $reviewCount; ?></h3>
                <p>Total Reviews</p>
            </div>
        </div>

        <h3>Manage Novels</h3>
        <div style="overflow-x: auto; margin-bottom: 40px;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead style="background: #eee;">
                    <tr>
                        <th style="padding: 10px; text-align: left;">ID</th>
                        <th style="padding: 10px; text-align: left;">Title</th>
                        <th style="padding: 10px; text-align: left;">Uploader</th>
                        <th style="padding: 10px; text-align: left;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($novels as $n): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><?php echo $n['id']; ?></td>
                        <td style="padding: 10px;"><a href="novel.php?id=<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['title']); ?></a></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($n['username'] ?? 'Unknown'); ?></td>
                        <td style="padding: 10px;">
                            <form method="POST">
                                <input type="hidden" name="target_id" value="<?php echo $n['id']; ?>">
                                <input type="hidden" name="delete_novel" value="true">
                                
                                <button type="submit" name="delete_novel_btn" class="btn" style="padding: 5px 10px; background: darkred; font-size: 0.8rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3>Manage Users</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead style="background: #eee;">
                    <tr>
                        <th style="padding: 10px; text-align: left;">ID</th>
                        <th style="padding: 10px; text-align: left;">Username</th>
                        <th style="padding: 10px; text-align: left;">Email</th>
                        <th style="padding: 10px; text-align: left;">Role</th>
                        <th style="padding: 10px; text-align: left;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><?php echo $u['id']; ?></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($u['username']); ?></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td style="padding: 10px;"><?php echo $u['role']; ?></td>
                        <td style="padding: 10px;">
                            <?php if($u['role'] != 'admin'): ?>
                                <form method="POST">
                                    <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="delete_user" value="true">
                                    
                                    <button type="submit" name="delete_user_btn" class="btn" style="padding: 5px 10px; background: darkred; font-size: 0.8rem;">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: gray;">Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>