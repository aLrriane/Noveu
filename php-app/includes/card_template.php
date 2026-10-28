<div class="novel-card" onclick="<?php echo isset($selection_mode) && $selection_mode ? '' : "window.location.href='novel.php?id={$novel['id']}'"; ?>" title="<?php echo htmlspecialchars($novel['title']); ?>">
    
    <div style="position: relative;">
        <img src="<?php echo $novel['cover_image']; ?>" alt="Cover">
        
        <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold;">
            <?php echo $novel['content_type']; ?>
        </span>

        <?php if(isset($selection_mode) && $selection_mode): ?>
            <div class="card-select-overlay">
                <input type="checkbox" name="items[]" value="<?php echo $novel['id']; ?>" onclick="event.stopPropagation();">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card-content">
        <h4><?php echo htmlspecialchars($novel['title']); ?></h4>
        
        <div class="meta-row">
            <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($novel['author']); ?></span>
            
            <?php 
                // Safe Rating Check
                $rating_val = 'N/A';
                if(isset($stats) && is_array($stats) && isset($stats['rating'])) {
                    $rating_val = $stats['rating'];
                } else {
                    // Fallback calculation
                    $q = $pdo->prepare("SELECT AVG(rating) as avg_r FROM reviews WHERE novel_id = ?");
                    $q->execute([$novel['id']]);
                    $d = $q->fetch();
                    $rating_val = $d['avg_r'] ? number_format($d['avg_r'], 1) : 'N/A';
                }
            ?>
            <span style="color:orange; margin-left: auto;"><i class="fas fa-star"></i> <?php echo $rating_val; ?></span>
        </div>

        <div class="meta-row" style="flex-wrap: wrap; gap: 5px; margin-top: 5px;">
            <?php $stColor = ($novel['status'] == 'Ongoing') ? '#3498db' : '#27ae60'; ?>
            <span class="badge badge-status" style="background: <?php echo $stColor; ?>"><?php echo $novel['status']; ?></span>
            
            <?php 
            $gList = array_map('trim', explode(',', $novel['genre']));
            $count = 0;
            foreach($gList as $gName): 
                if($count >= 3) break; 
                if(!empty($gName)):
            ?>
                <span class="badge" style="font-size: 0.7rem; background: #444; border: 1px solid #555;">
                    <?php echo htmlspecialchars($gName); ?>
                </span>
            <?php endif; $count++; endforeach; ?>
        </div>

        <?php if(!empty($novel['description'])): ?>
            <div class="desc-snippet">
                <?php echo htmlspecialchars($novel['description']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>