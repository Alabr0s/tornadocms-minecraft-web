<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name']);
    $site_url = trim($_POST['site_url']);
    $server_ip = trim($_POST['server_ip']);
    $discord_url = trim($_POST['discord_url']);

    if (empty($site_name) || empty($site_url) || empty($server_ip)) {
        $error = 'Site adı, URL ve Sunucu IP alanları zorunludur.';
    } else {
        $_SESSION['site_config'] = [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'server_ip' => $server_ip,
            'discord_url' => $discord_url
        ];
        
        header('Location: ?step=4');
        exit;
    }
}

// Site URL'sini otomatik al
$site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$site_url .= "://" . $_SERVER['HTTP_HOST'];
$site_url .= str_replace('/install', '', dirname($_SERVER['PHP_SELF']));
?>

<div class="settings-step">
    <h2>Site Ayarları</h2>
    <p>Temel site ayarlarını yapılandırın.</p>

    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="install-form">
        <div class="form-group">
            <label>Site Adı</label>
            <input type="text" name="site_name" value="Tornado CMS" required>
        </div>

        <div class="form-group">
            <label>Site URL</label>
            <input type="url" name="site_url" value="<?php echo $site_url; ?>" required>
        </div>

        <div class="form-group">
            <label>Sunucu IP</label>
            <input type="text" name="server_ip" value="play.tornadocms.com" required>
        </div>

        <div class="form-group">
            <label>Discord URL</label>
            <input type="url" name="discord_url" placeholder="https://discord.gg/...">
        </div>

        <div class="form-actions">
            <a href="?step=2" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Geri</span>
            </a>
            <button type="submit" class="next-btn">
                <span>Devam Et</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </form>
</div> 