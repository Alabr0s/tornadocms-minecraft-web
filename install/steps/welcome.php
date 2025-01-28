<div class="welcome-step">
    <h2>Kuruluma Hoş Geldiniz</h2>
    <p>Kuruluma başlamadan önce sistem gereksinimlerini kontrol edelim.</p>
    
    <div class="requirements">
        <?php
        $requirements = [
            'php_version' => [
                'name' => 'PHP 7.4 veya üzeri',
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
            ],
            'pdo' => [
                'name' => 'MySQL Desteği',
                'status' => extension_loaded('pdo_mysql')
            ],
            'config_dir' => [
                'name' => 'Config Dizini Yazma İzni',
                'status' => is_writable('../includes/config') || is_writable('../includes')
            ]
        ];

        foreach ($requirements as $req): ?>
            <div class="requirement-item <?php echo $req['status'] ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo $req['status'] ? 'check' : 'times'; ?>"></i>
                <span><?php echo $req['name']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (array_sum(array_column($requirements, 'status')) === count($requirements)): ?>
        <a href="?step=2" class="next-btn">
            <span>Devam Et</span>
            <i class="fas fa-arrow-right"></i>
        </a>
    <?php else: ?>
        <div class="error-message">
            Kuruluma devam etmek için gereksinimleri karşılamalısınız.
        </div>
    <?php endif; ?>
</div> 