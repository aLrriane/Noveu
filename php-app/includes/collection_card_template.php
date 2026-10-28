<div class="novel-card" onclick="window.location.href='novel.php?id=<?php echo $novel['id']; ?>'" title="Click to view details">
    <div style="position: relative;">
        <img src="<?php echo $novel['cover_image']; ?>" alt="Cover">
        <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold;">
            <?php echo $novel['content_type']; ?>
        </span>
    </div>
    
    <div class="card-content">
        <h4><?php echo htmlspecialchars($novel['title']); ?></h4>
        
        <div class="meta-row">
            <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($novel['author']); ?></span>
        </div>

        <div class="meta-row" style="flex-wrap: wrap; gap: 5px; margin-top: 5px;">
            <?php 
            $stColor = ($novel['status'] == 'Ongoing') ? '#3498db' : '#27ae60';
            ?>
            <span class="badge badge-status" style="background: <?php echo $stColor; ?>"><?php echo $novel['status']; ?></span>
            
            <?php 
            $gList = array_map('trim', explode(',', $novel['genre']));
            $count = 0;
            foreach($gList as $gName): 
                if($count >= 2) break; 
                if(!empty($gName)):
            ?>
                <span class="badge" style="font-size: 0.7rem; background: var(--bg-dark); border: 1px solid var(--border-color);">
                    <?php echo htmlspecialchars($gName); ?>
                </span>
            <?php endif; $count++; endforeach; ?>
        </div>

        <div class="desc-snippet">
            <?php 
            // If there's a note, show it first in the snippet
            $displayText = !empty($novel['user_note']) ? "[NOTE]: " . $novel['user_note'] : $novel['description'];
            echo htmlspecialchars($displayText); 
            ?>
        </div>
    </div>

    <div class="collection-hover-desc">
        <?php if(!empty($novel['user_note'])): ?>
            <div class="note-section">
                <span class="note-label"><i class="fas fa-sticky-note"></i> Curator's Note:</span>
                "<?php echo htmlspecialchars($novel['user_note']); ?>"
            </div>
        <?php endif; ?>

        <div style="color: #ddd;">
            <span class="note-label" style="color:var(--text-light); font-size:0.8rem;">Description:</span>
            <?php echo nl2br(htmlspecialchars($novel['original_desc'] ?? $novel['description'])); ?>
        </div>
    </div>
</div>