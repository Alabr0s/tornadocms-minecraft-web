<div class="admin-sidebar">
    <div class="admin-logo">
        <h1>TORNADO CMS</h1>
    </div>
    
    <nav class="admin-menu">
        <a href="index.php" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="users.php" class="menu-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Kullanıcılar</span>
        </a>

        <a href="servers.php" class="menu-item <?php echo $current_page === 'servers' ? 'active' : ''; ?>">
            <i class="fas fa-server"></i>
            <span>Sunucular</span>
        </a>
        
        <a href="store.php" class="<?php echo $current_page === 'store' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Market</span>
        </a>
        
        <a href="cases.php" class="menu-item <?php echo $current_page === 'cases' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i>
            <span>Kasa Yönetimi</span>
        </a>
        
        <a href="tickets.php" class="menu-item <?php echo $current_page === 'tickets' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i>
            <span>Destek</span>
        </a>
        
        <a href="settings.php" class="menu-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Ayarlar</span>
        </a>
        
        <a href="blog.php" class="<?php echo $current_page === 'blog' ? 'active' : ''; ?>">
            <i class="fas fa-newspaper"></i>
            <span>Blog Yönetimi</span>
        </a>
        
        <a href="../index.php" class="menu-item">
            <i class="fas fa-external-link-alt"></i>
            <span>Siteye Git</span>
        </a>
    </nav>
    <div class="admin-logo">
        <p>I ♥ Alabros</p>
        <p>Thanks For Ozaii</p>
    </div>
</div> 