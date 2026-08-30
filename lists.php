<?php

require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

//helper function
//Since three different lists are needed(Reading, Completed, Favorite),
//it is better to write one function and call it 3 times than to repeat the SQL code 3 times
function getList($pdo, $uid, $type) {
    //Join 'user_lists' with 'novels' to get the book details (title, image)
    //purely based on the user ID and the list type ('reading', 'favorite', etc).
    $sql = "SELECT n.* FROM user_lists ul 
            JOIN novels n ON ul.novel_id = n.id 
            WHERE ul.user_id = ? AND ul.list_type = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $type]);
    return $stmt->fetchAll();
}

//call helper function 3 times to populate the specific arrays.
$reading = getList($pdo, $user_id, 'reading');
$completed = getList($pdo, $user_id, 'completed');
$favorites = getList($pdo, $user_id, 'favorite');
?>

<div class="main-content" style="padding: 2rem;">
    <h1>My Library</h1>
    
    <div class="content-box" style="max-width: 1000px; margin-bottom: 30px;">
        <h3 style="border-bottom: 2px solid var(--accent-color); padding-bottom: 10px;">
            <i class="fas fa-book-reader"></i> Currently Reading
        </h3>
        
        <div style="display: flex; gap: 15px; overflow-x: auto; padding-top: 15px;">
            <?php if($reading): foreach($reading as $nov): ?>
                <div class="novel-card" style="min-width: 120px;">
                    <a href="novel.php?id=<?php echo $nov['id']; ?>">
                        <img src="<?php echo $nov['cover_image']; ?>" style="width: 100px; height: 150px; object-fit: cover;">
                        <p style="font-size: 0.9rem; font-weight: bold; margin-top: 5px;"><?php echo htmlspecialchars($nov['title']); ?></p>
                    </a>
                </div>
            <?php endforeach; else: echo "<p>No novels in this list.</p>"; endif; ?>
        </div>
    </div>

    <div class="content-box" style="max-width: 1000px; margin-bottom: 30px;">
        <h3 style="border-bottom: 2px solid gold; padding-bottom: 10px;">
            <i class="fas fa-star" style="color: gold;"></i> Favorites
        </h3>
        <div style="display: flex; gap: 15px; flex-wrap: wrap; padding-top: 15px;">
            <?php if($favorites): foreach($favorites as $nov): ?>
                <div class="novel-card" style="width: 150px;">
                    <a href="novel.php?id=<?php echo $nov['id']; ?>">
                        <img src="<?php echo $nov['cover_image']; ?>" style="width: 100%; height: 200px; object-fit: cover;">
                        <p style="font-weight: bold; margin-top: 5px;"><?php echo htmlspecialchars($nov['title']); ?></p>
                    </a>
                </div>
            <?php endforeach; else: echo "<p>No favorites yet.</p>"; endif; ?>
        </div>
    </div>

    <div class="content-box" style="max-width: 1000px;">
        <h3 style="border-bottom: 2px solid green; padding-bottom: 10px;">
            <i class="fas fa-check-circle" style="color: green;"></i> Completed
        </h3>
        <ul style="list-style: none; padding-top: 15px;">
            <?php if($completed): foreach($completed as $nov): ?>
                <li style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                    <a href="novel.php?id=<?php echo $nov['id']; ?>" style="text-decoration: none; font-weight: bold;">
                        <?php echo htmlspecialchars($nov['title']); ?>
                    </a> - <span style="color: gray; font-size: 0.9rem;">by <?php echo htmlspecialchars($nov['author']); ?></span>
                </li>
            <?php endforeach; else: echo "<p>No completed novels.</p>"; endif; ?>
        </ul>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>