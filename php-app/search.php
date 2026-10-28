<?php
require_once 'config/db.php';
require_once 'classes/NovelSearchTree.php'; 
require_once 'includes/header.php';

// Get parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$genre_id = isset($_GET['genre_id']) ? $_GET['genre_id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default Sort

// Fetch Genres
$genres = $pdo->query("SELECT * FROM genres ORDER BY name ASC")->fetchAll();

// 1. Build SQL Query (Fetching Data)
// Note: We fetch ALL matching rows first, then sort in PHP to handle the Search Tree results consistently.
$sql = "SELECT DISTINCT n.*, 
        (SELECT AVG(rating) FROM reviews WHERE novel_id = n.id) as avg_rating 
        FROM novels n"; 

$params = [];
$conditions = [];

if (!empty($genre_id)) { $sql .= " JOIN novel_genres ng ON n.id = ng.novel_id"; $conditions[] = "ng.genre_id = ?"; $params[] = $genre_id; }
if (!empty($type)) {
    if ($type == 'Anime') $conditions[] = "n.content_type IN ('Anime', 'Donghua')";
    elseif ($type == 'Manhwa') $conditions[] = "n.content_type IN ('Manhwa', 'Manga', 'Manhua', 'Comic')";
    elseif ($type == 'KDrama') $conditions[] = "n.content_type IN ('KDrama', 'CDrama')";
    else { $conditions[] = "n.content_type = ?"; $params[] = $type; }
}

if (!empty($conditions)) $sql .= " WHERE " . implode(" AND ", $conditions);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_novels = $stmt->fetchAll();

// 2. Filter via Search Tree (if query exists)
$results = [];
if (!empty($query)) {
    $bst = new NovelSearchTree();
    foreach ($all_novels as $novel) { $bst->insert($novel); }
    $results = $bst->search($query);
} else {
    $results = $all_novels;
}

// 3. Apply Sorting (PHP Side)
usort($results, function($a, $b) use ($sort) {
    switch ($sort) {
        case 'title_az': return strcasecmp($a['title'], $b['title']);
        case 'title_za': return strcasecmp($b['title'], $a['title']);
        case 'oldest':   return strtotime($a['upload_date']) - strtotime($b['upload_date']);
        case 'rating':   return ($b['avg_rating'] * 10) - ($a['avg_rating'] * 10); // High to Low
        case 'newest':   
        default:         return strtotime($b['upload_date']) - strtotime($a['upload_date']);
    }
});
?>

<div class="main-content">
    <h1>Search Library</h1>

    <div class="content-box" style="padding: 15px; display: flex; flex-direction: column; gap: 15px;">
        
        <form method="GET" action="search.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="q" placeholder="Search by Title..." value="<?php echo htmlspecialchars($query); ?>" style="flex: 2; min-width: 200px;">
            
            <select name="type" style="flex: 1; min-width: 130px;">
                <option value="">All Types</option>
                <option value="Novel" <?php if($type=='Novel') echo 'selected'; ?>>Novels</option>
                <option value="Manhwa" <?php if($type=='Manhwa') echo 'selected'; ?>>Comics</option>
                <option value="Anime" <?php if($type=='Anime') echo 'selected'; ?>>Anime</option>
                <option value="KDrama" <?php if($type=='KDrama') echo 'selected'; ?>>Drama</option>
            </select>

            <select name="genre_id" style="flex: 1; min-width: 130px;">
                <option value="">All Genres</option>
                <?php foreach($genres as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php if($genre_id == $g['id']) echo 'selected'; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn"><i class="fas fa-search"></i></button>
        </form>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 15px;">
            
            <form method="GET" style="display:flex; align-items:center; gap:10px;">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="genre_id" value="<?php echo htmlspecialchars($genre_id); ?>">
                
                <span style="color:var(--text-light); font-size:0.9rem;">Sort by:</span>
                <select name="sort" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-dark); color: var(--text-color);">
                    <option value="newest" <?php if($sort=='newest') echo 'selected'; ?>>Newest Added</option>
                    <option value="oldest" <?php if($sort=='oldest') echo 'selected'; ?>>Oldest Added</option>
                    <option value="rating" <?php if($sort=='rating') echo 'selected'; ?>>Highest Rated</option>
                    <option value="title_az" <?php if($sort=='title_az') echo 'selected'; ?>>Title (A-Z)</option>
                    <option value="title_za" <?php if($sort=='title_za') echo 'selected'; ?>>Title (Z-A)</option>
                </select>
            </form>

            <div class="view-toggle">
                <button id="btnGrid" class="view-btn active" onclick="setView('grid')" title="Grid View"><i class="fas fa-th-large"></i></button>
                <button id="btnList" class="view-btn" onclick="setView('list')" title="List View"><i class="fas fa-list"></i></button>
            </div>
        </div>
    </div>

    <h3 style="margin-top: 20px;">Found <?php echo count($results); ?> Result(s)</h3>
    
    <div id="novelContainer" class="novel-grid" style="margin-top: 20px;">
        <?php if (!empty($results)): ?>
            <?php 
            foreach($results as $novel): 
                include 'includes/card_template.php'; 
            endforeach; 
            ?>
        <?php else: ?>
            <p style="color: var(--text-light);">No results found.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>