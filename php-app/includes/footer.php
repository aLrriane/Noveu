<?php
// includes/footer.php

// Load PHPMailer (Keep existing logic if you have it)
// ... (Your existing PHPMailer code can stay here if needed, or be removed if unused) ...

?>

</div> <div id="scrollTop" class="scroll-top"><i class="fas fa-arrow-up"></i></div>

<footer style="background: var(--bg-secondary); padding: 3rem 1rem; text-align: center; border-top: 1px solid var(--border-color); margin-top: auto;">
    <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; text-align: left;">
        <div>
            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">NoveU</h3>
            <p style="font-size: 0.9rem; color: var(--text-light);">
                Your ultimate personal vault for archiving novels, comics, and shows.
            </p>
        </div>
        <div>
            <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Explore</h4>
            <ul style="list-style: none; padding: 0;">
                <li><a href="index.php" style="color:var(--text-light);">Home</a></li>
                <li><a href="search.php?type=Novel" style="color:var(--text-light);">Novels</a></li>
                <li><a href="rankings.php" style="color:var(--text-light);">Rankings</a></li>
            </ul>
        </div>
        <div>
            <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Contact Us</h4>
            <form method="POST">
                <input type="text" name="name" placeholder="Name" required style="width:100%; padding:8px; margin-bottom:10px; background:var(--bg-dark); color:var(--text-color); border:1px solid var(--border-color);">
                <textarea name="message" placeholder="Message..." required style="width:100%; padding:8px; background:var(--bg-dark); color:var(--text-color); border:1px solid var(--border-color); height:80px; resize:none;"></textarea>
                <button type="submit" name="submit_feedback" class="btn" style="width:100%; margin-top:10px;">Send</button>
            </form>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/script.js"></script>

<?php
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    $icon = $s['icon'];
    $title = addslashes($s['title']);
    $text = addslashes($s['text'] ?? '');
    
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showPopup('$icon', '$title', '$text');
        });
    </script>";
    
    // Clear it so it doesn't show again on refresh
    unset($_SESSION['swal']);
}

if (isset($_SESSION['toast'])) {
    $t = $_SESSION['toast'];
    $icon = $t['icon'];
    $title = addslashes($t['title']);
    
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('$icon', '$title');
        });
    </script>";
    
    unset($_SESSION['toast']);
}
?>

<?php include 'includes/notification_sidebar.php'; ?>
</body>
</html>