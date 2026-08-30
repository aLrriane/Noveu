<?php
require_once 'config/db.php';
require_once 'classes/Sorter.php';
require_once 'includes/header.php';

$type = $_GET['type'] ?? '';
$genre_id = $_GET['genre_id'] ?? '';

// FETCH ALL DATA needed for the template
$sql = "SELECT n.*, AVG(r.rating) as avg_rating FROM novels n LEFT JOIN reviews r ON n.id = r.novel_id";
$cond = []; $params = [];

if ($type) { $cond[] = "n.content_type = ?"; $params[] = $type; }
if ($genre_id) { $sql .= " JOIN novel_genres ng ON n.id = ng.novel_id"; $cond[] = "ng.genre_id = ?"; $params[] = $genre_id; }

if ($cond) $sql .= " WHERE " . implode(" AND ", $cond);
$sql .= " GROUP BY n.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$novels = $stmt->fetchAll();

// Sort & Limit
foreach ($novels as &$n) $n['avg_rating'] = $n['avg_rating'] ?: 0;

// 1. Sort High -> Low
$sorted = Sorter::quickSort($novels, 'avg_rating'); 

// 2. NO REVERSE (This fixes the ranking issue)
$ranked = array_slice($sorted, 0, 10); // Top 10

$genres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll();
?>

<div class="main-content">
    <div class="content-box" style="text-align:center;">
        <h1><i class="fas fa-trophy" style="color: gold;"></i> Leaderboard</h1>
        <p style="color:var(--text-light);">The top 10 highest-rated content on NoveU.</p>
    </div>
    
    <div class="filter-bar" style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;">
        <a href="rankings.php" class="btn <?php echo !$type?'':'btn-outline';?>">All</a>
        <a href="rankings.php?type=Novel" class="btn <?php echo $type=='Novel'?'':'btn-outline';?>">Novels</a>
        <a href="rankings.php?type=Manhwa" class="btn <?php echo $type=='Manhwa'?'':'btn-outline';?>">Comics</a>
        <a href="rankings.php?type=Anime" class="btn <?php echo $type=='Anime'?'':'btn-outline';?>">Anime</a>
    </div>
    
    <form method="GET" style="text-align:center; margin-bottom: 30px;">
        <?php if($type) echo "<input type='hidden' name='type' value='$type'>"; ?>
        <select name="genre_id" onchange="this.form.submit()" style="padding:10px; border-radius:6px; background:var(--bg-secondary); color:var(--text-color); border:1px solid var(--border-color); width:200px;">
            <option value="">Filter by Genre...</option>
            <?php foreach($genres as $g): ?>
                <option value="<?php echo $g['id']; ?>" <?php if($genre_id==$g['id']) echo 'selected'; ?>><?php echo $g['name']; ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="ranking-list">
        <?php $rank=1; foreach($ranked as $item): ?>
            <?php include 'includes/ranking_card_template.php'; ?>
        <?php $rank++; endforeach; ?>
        <?php if(empty($ranked)) echo "<p style='text-align:center; color:gray;'>No data found.</p>"; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>