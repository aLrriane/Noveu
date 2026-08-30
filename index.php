<?php
require_once 'config/db.php';
require_once 'classes/Sorter.php'; 
require_once 'includes/header.php';

function getTopByType($pdo, $types) {
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $sql = "SELECT n.*, (SELECT AVG(rating) FROM reviews WHERE novel_id = n.id) as avg_rating 
            FROM novels n WHERE content_type IN ($placeholders) HAVING avg_rating IS NOT NULL ORDER BY avg_rating DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($types);
    return $stmt->fetchAll();
}

$top_novels = getTopByType($pdo, ['Novel']);
$top_comics = getTopByType($pdo, ['Manhwa', 'Manga', 'Manhua', 'Comic']);
$top_shows  = getTopByType($pdo, ['Anime', 'Donghua', 'KDrama', 'CDrama']);

$recent_stmt = $pdo->query("SELECT * FROM novels ORDER BY upload_date DESC LIMIT 24");
$recent_novels = $recent_stmt->fetchAll();

$covers = $pdo->query("SELECT cover_image FROM novels WHERE cover_image IS NOT NULL AND cover_image != 'assets/images/default_cover.png' ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content">
    
    <div class="hero-container">
        <div class="hero-slideshow">
            <?php if($covers): foreach($covers as $index => $img): ?>
                <img src="<?php echo $img; ?>" class="hero-slide <?php echo ($index === 0) ? 'active' : ''; ?>">
            <?php endforeach; else: ?>
                <div style="width:100%; height:100%; background:#333;"></div>
            <?php endif; ?>
        </div>
        <div class="hero-content">
            <h1>Welcome to NoveU</h1>
            <p>Your Personal Vault for Novels, Comics, and Dramas.</p>
            <a href="search.php" class="btn" style="margin-top:15px;">Browse Library</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-top: 40px;">
        <?php 
        $sections = [
            ['title' => 'Top Novels', 'icon' => 'book', 'data' => $top_novels, 'color' => '#f47521'],
            ['title' => 'Top Comics', 'icon' => 'book-open', 'data' => $top_comics, 'color' => '#9b59b6'],
            ['title' => 'Top Shows', 'icon' => 'tv', 'data' => $top_shows, 'color' => '#e74c3c']
        ];
        foreach($sections as $sec): ?>
        <div>
            <h3 style="border-bottom: 3px solid <?php echo $sec['color']; ?>; display:inline-block; margin-bottom:20px;">
                <i class="fas fa-<?php echo $sec['icon']; ?>"></i> <?php echo $sec['title']; ?>
            </h3>
            
            <div class="ranking-list">
                <?php $rank=1; foreach($sec['data'] as $item): ?>
                    <?php include 'includes/ranking_card_template.php'; ?>
                <?php $rank++; endforeach; ?>
                <?php if(empty($sec['data'])) echo "<p style='color:gray'>No ratings yet.</p>"; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <hr style="margin: 50px 0; border: 0; border-top: 1px solid var(--border-color);">

    <h2><i class="fas fa-clock"></i> Just Added</h2>
    
    <?php if($recent_novels): $rank=null; ?>
        
        <div class="novel-grid" style="margin-top:20px;">
            <?php 
            $first_six = array_slice($recent_novels, 0, 8);
            foreach($first_six as $novel) include 'includes/card_template.php'; 
            ?>
        </div>

        <?php if(count($recent_novels) > 8): ?>
            <div id="moreRecent" class="hidden-uploads">
                <div class="novel-grid" style="margin-top:25px;">
                    <?php 
                    $rest = array_slice($recent_novels, 8);
                    foreach($rest as $novel) include 'includes/card_template.php'; 
                    ?>
                </div>
            </div>
            
            <button id="btnRecent" class="see-more-btn" onclick="toggleSection('moreRecent', 'btnRecent')">
                See More <i class="fas fa-chevron-down"></i>
            </button>
            
            <script>
                function toggleSection(id, btnId) {
                    var el = document.getElementById(id);
                    var btn = document.getElementById(btnId);
                    if (el.style.display === "block") {
                        el.style.display = "none";
                        btn.innerHTML = 'See More <i class="fas fa-chevron-down"></i>';
                    } else {
                        el.style.display = "block";
                        btn.innerHTML = 'See Less <i class="fas fa-chevron-up"></i>';
                    }
                }
            </script>
        <?php endif; ?>
    <?php endif; ?>

</div>
<?php require_once 'includes/footer.php'; ?>