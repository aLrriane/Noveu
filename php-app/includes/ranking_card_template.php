<div class="ranking-card" onclick="window.location.href='novel.php?id=<?php echo $item['id']; ?>'">
    
    <div class="rank-badge">
        <?php 
        if($rank == 1) echo '<i class="fas fa-medal" style="color: gold;"></i>';
        elseif($rank == 2) echo '<i class="fas fa-medal" style="color: silver;"></i>';
        elseif($rank == 3) echo '<i class="fas fa-medal" style="color: #cd7f32;"></i>';
        else echo "#" . $rank;
        ?>
    </div>

    <img src="<?php echo $item['cover_image']; ?>" alt="Cover">
    
    <div class="ranking-details">
        <h4 style="margin:0 0 5px 0; font-size:1.1rem;"><?php echo htmlspecialchars($item['title']); ?></h4>
        
        <div class="meta-row">
            <span style="color:orange; font-weight:bold;"><i class="fas fa-star"></i> <?php echo number_format($item['avg_rating'], 1); ?></span>
            <?php if(!empty($item['uploaded_by'])): ?>
                <a href="profile.php?user_id=<?php echo $item['uploaded_by']; ?>" style="color:inherit;">
                    <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($item['author']); ?>
                </a>
            <?php else: ?>
                <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($item['author']); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="meta-row" style="margin-top:8px;">
            <span class="badge" style="background:var(--accent-color);"><?php echo $item['content_type']; ?></span>
            
            <?php $stColor = ($item['status'] == 'Ongoing') ? '#3498db' : '#27ae60'; ?>
            <span class="badge" style="background: <?php echo $stColor; ?>"><?php echo $item['status']; ?></span>
            
            <?php 
            $gList = array_slice(explode(',', $item['genre']), 0, 3); 
            foreach($gList as $g) {
                if(!empty($g)) echo "<span class='badge' style='background:#444;'>" . htmlspecialchars(trim($g)) . "</span>";
            }
            ?>
        </div>
    </div>

    <div class="ranking-hover-desc">
        <strong style="color:var(--primary-color);">Description:</strong><br>
        <?php echo htmlspecialchars(substr($item['description'], 0, 200)); ?>...
    </div>
</div>