<nav class="navbar">
    <a href="index.php" class="logo">
        <i class="fas fa-book-open"></i> NoveU
    </a>

    <div class="nav-links">
        <div class="dropdown">
            <a href="#" class="dropbtn"><i class="fas fa-compass"></i> Browse <i class="fas fa-caret-down"></i></a>
            <div class="dropdown-content">
                <a href="search.php?type=Novel">Novels</a>
                <a href="search.php?type=Manhwa">Comics</a>
                <a href="search.php?type=Anime">Animation</a>
                <a href="search.php?type=KDrama">Dramas</a>
            </div>
        </div>

        <a href="search.php"><i class="fas fa-search"></i> Search</a>
        <a href="rankings.php"><i class="fas fa-trophy"></i> Rankings</a>

        <?php if(isset($_SESSION['user_id'])): 
            // Count unread notifications
            $notifCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id={$_SESSION['user_id']} AND is_read=0")->fetchColumn();
        ?>
            <a href="javascript:void(0)" onclick="openNotif()" class="nav-icon-btn">
                <i class="fas fa-bell"></i>
                <?php if($notifCount > 0): ?>
                    <span class="nav-badge"><?php echo $notifCount; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <button id="theme-toggle"><i class="fas fa-moon"></i></button>

        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="create_novel.php"><i class="fas fa-plus-circle"></i> Upload</a>
            
            <div class="dropdown">
                <a href="#" class="dropbtn">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i>
                </a>
                <div class="dropdown-content">
                    <a href="profile.php">Profile</a>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin_panel.php" style="color: var(--accent-color);">Admin Panel</a>
                    <?php endif; ?>
                    <a href="lists.php">My Lists</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-outline">Login</a>
            <a href="register.php" class="btn">Register</a>
        <?php endif; ?>
    </div>
</nav>