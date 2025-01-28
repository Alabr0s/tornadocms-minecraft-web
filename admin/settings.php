<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'settings';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name']);
    $site_logo = trim($_POST['site_logo']);
    $server_ip = trim($_POST['server_ip']);
    $discord_url = trim($_POST['discord_url']);
    $shopier_api_key = trim($_POST['shopier_api_key']);
    $shopier_api_secret = trim($_POST['shopier_api_secret']);
    $shopier_callback_url = trim($_POST['shopier_callback_url']);
    $shopier_website_url = trim($_POST['shopier_website_url']);
    $shopier_website_index = isset($_POST['shopier_website_index']) ? $_POST['shopier_website_index'] : 1;
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenance_message = trim($_POST['maintenance_message']);

    try {
        $stmt = $db->prepare("UPDATE settings SET 
            site_name = ?,
            site_logo = ?,
            server_ip = ?, 
            discord_url = ?,
            shopier_api_key = ?,
            shopier_api_secret = ?,
            shopier_callback_url = ?,
            shopier_website_url = ?,
            shopier_website_index = ?,
            maintenance_mode = ?,
            maintenance_message = ?
            WHERE id = 1");

        $stmt->execute([
            $site_name,
            $site_logo,
            $server_ip,
            $discord_url,
            $shopier_api_key,
            $shopier_api_secret,
            $shopier_callback_url,
            $shopier_website_url,
            $shopier_website_index,
            $maintenance_mode,
            $maintenance_message
        ]);

        $success = 'Ayarlar başarıyla güncellendi.';
    } catch (PDOException $e) {
        $error = 'Ayarlar güncellenirken bir hata oluştu: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Ayarları - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
    .settings-form {
        display: block;
        max-width: 100%;
        margin: 0 auto;
    }

    .settings-section {
        background: var(--admin-secondary);
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .settings-section h2 {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--admin-accent);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .settings-section-content {
        padding: 0 1rem;
    }

    .form-group {
        margin-bottom: 1.8rem;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--admin-text);
        font-weight: 500;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        background: rgba(0, 0, 0, 0.2);
        color: var(--admin-text);
    }

    .form-control:focus {
        border-color: var(--admin-accent);
        outline: none;
    }

    /* Logo input için özel stil */
    .logo-input-group {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .logo-preview {
        flex-shrink: 0;
        width: 150px;
        height: 80px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        overflow: hidden;
    }

    .logo-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .logo-input-container {
        flex-grow: 1;
    }

    /* Form actions */
    .form-actions {
        margin-top: 2rem;
        padding: 1.5rem;
        background: var(--admin-secondary);
        border-radius: 8px;
        display: flex;
        justify-content: flex-end;
    }

    .submit-btn {
        padding: 0.75rem 2rem;
        background: var(--admin-accent);
        color: var(--admin-primary);
        border: none;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    /* Checkbox stil */
    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .checkbox-label input[type="checkbox"] {
        margin-right: 0.5rem;
    }

    /* Textarea stil */
    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Site Ayarları</h1>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="settings-form">
                <!-- Temel Ayarlar -->
                <div class="settings-section">
                    <h2><i class="fas fa-cog"></i> Temel Ayarlar</h2>
                    <div class="settings-section-content">
                    <div class="form-group">
                        <label>Site Adı</label>
                        <input type="text" name="site_name" class="form-control" 
                               placeholder="Örn: Tornado CMS"
                               value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Site Logo URL</label>
                        <div class="logo-input-group">
                            <div class="logo-preview">
                                <img src="<?php echo htmlspecialchars($settings['site_logo']); ?>" id="logoPreview" alt="Site Logo">
                            </div>
                            <div class="logo-input-container">
                                <input type="url" name="site_logo" id="logoInput" class="form-control" 
                                       placeholder="https://example.com/logo.png"
                                       value="<?php echo htmlspecialchars($settings['site_logo']); ?>">
                                <small class="form-text">Logo URL'sini girin (önerilen boyut: 300x80px)</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sunucu IP</label>
                        <input type="text" name="server_ip" class="form-control" 
                               placeholder="Örn: play.tornadocms.com"
                               value="<?php echo htmlspecialchars($settings['server_ip']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Discord URL</label>
                        <input type="url" name="discord_url" class="form-control" 
                               placeholder="https://discord.gg/..."
                               value="<?php echo htmlspecialchars($settings['discord_url']); ?>">
                    </div>
                    </div>
                </div>

                <!-- Shopier Ayarları -->
                <div class="settings-section">
                    <h2><i class="fas fa-shopping-cart"></i> Shopier Ayarları</h2>
                    <div class="settings-section-content">
                        <div class="form-group">
                            <label>Shopier API Key</label>
                            <input type="text" name="shopier_api_key" class="form-control" 
                                   placeholder="örnek: 5c395bda4ad903e3e6XXXXXXXXXXXX"
                                   value="<?php echo htmlspecialchars($settings['shopier_api_key']); ?>">
                            <small class="form-text">32 karakterlik API anahtarı</small>
                        </div>

                        <div class="form-group">
                            <label>Shopier API Secret</label>
                            <input type="text" name="shopier_api_secret" class="form-control" 
                                   placeholder="örnek: ff5d19e05609cb6e3f69XXXXXXXXXX"
                                   value="<?php echo htmlspecialchars($settings['shopier_api_secret']); ?>">
                            <small class="form-text">32 karakterlik API şifresi</small>
                        </div>

                        <div class="form-group">
                            <label>Callback URL</label>
                            <input type="url" name="shopier_callback_url" class="form-control" 
                                   placeholder="https://siteadresiniz.com/shopier_callback.php"
                                   value="<?php echo htmlspecialchars($settings['shopier_callback_url']); ?>">
                            <small class="form-text">Ödeme sonrası dönüş adresi. Tam URL girilmelidir.</small>
                        </div>

                        <div class="form-group">
                            <label>Website URL</label>
                            <input type="url" name="shopier_website_url" class="form-control" 
                                   placeholder="https://example.com"
                                   value="<?php echo htmlspecialchars($settings['shopier_website_url']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Website Index</label>
                            <input type="number" name="shopier_website_index" class="form-control" 
                                   placeholder="1"
                                   value="<?php echo htmlspecialchars($settings['shopier_website_index']); ?>">
                            <small class="form-text">Shopier mağaza panelinden alabilirsiniz. Varsayılan: 1</small>
                        </div>
                    </div>
                </div>

                <!-- Bakım Modu -->
                <div class="settings-section">
                    <h2><i class="fas fa-tools"></i> Bakım Modu</h2>
                    <div class="settings-section-content">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="maintenance_mode" 
                                       <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                Bakım Modunu Etkinleştir
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Bakım Mesajı</label>
                            <textarea name="maintenance_message" class="form-control" 
                                      placeholder="Sunucumuz bakımdadır. En kısa sürede hizmetinize açılacaktır."><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Ayarları Kaydet
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');

    logoInput.addEventListener('input', function() {
        const url = this.value;
        if (url) {
            logoPreview.src = url;
        }
    });

    // Resim yüklenemezse varsayılan logo göster
    logoPreview.addEventListener('error', function() {
        this.src = '../assets/images/default-logo.png';
    });
    </script>
</body>
</html> 