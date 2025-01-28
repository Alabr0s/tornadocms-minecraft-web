document.addEventListener('DOMContentLoaded', function() {
    // Form gönderimlerinde loading göster
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('.next-btn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bekleyin...';
                submitBtn.disabled = true;
            }
        });
    });

    // Şifre kontrolü
    const passwordInput = document.querySelector('input[name="password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const submitBtn = document.querySelector('.next-btn');
            if (this.value.length < 6) {
                this.style.borderColor = 'var(--error-color)';
                submitBtn.disabled = true;
            } else {
                this.style.borderColor = 'var(--border-color)';
                submitBtn.disabled = false;
            }
        });
    }

    // URL otomatik düzeltme
    const urlInput = document.querySelector('input[name="site_url"]');
    if (urlInput) {
        urlInput.addEventListener('blur', function() {
            let url = this.value.trim();
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                url = 'http://' + url;
            }
            if (url.endsWith('/')) {
                url = url.slice(0, -1);
            }
            this.value = url;
        });
    }
}); 