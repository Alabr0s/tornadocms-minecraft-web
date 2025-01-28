// Bildirim gösterme fonksiyonu
function showNotification(type, message) {
    // Varsa eski bildirimi kaldır
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Yeni bildirimi oluştur
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        </div>
        <div class="notification-message">${message}</div>
    `;
    
    document.body.appendChild(notification);

    // Animasyon ekle
    requestAnimationFrame(() => {
        notification.classList.add('show');
    });

    // Bildirimi kaldır
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 3000);
}

// Minecraft kullanıcı adı validasyonu
function isValidMinecraftUsername(username) {
    if (!username) return false;
    
    // Minecraft kullanıcı adı kuralları:
    // - 3-16 karakter uzunluğunda
    // - Sadece harf, rakam ve alt çizgi içerebilir
    // - Boşluk içeremez
    const minecraftUsernameRegex = /^[a-zA-Z0-9_]{3,16}$/;
    return minecraftUsernameRegex.test(username);
}

// Form validasyonu
function validateForm(form) {
    const username = form.querySelector('[name="username"]').value;
    const password = form.querySelector('[name="password"]').value;
    
    // Boş alan kontrolü
    if (!username || !password) {
        showNotification('error', 'Tüm alanları doldurun!');
        return false;
    }
    
    // Minecraft kullanıcı adı kontrolü
    if (!isValidMinecraftUsername(username)) {
        showNotification('error', 'Geçersiz kullanıcı adı! Sadece harf, rakam ve alt çizgi kullanabilirsiniz (3-16 karakter).');
        return false;
    }

    return true;
}

// Form gönderme işlemi
function submitForm(form, url) {
    if (!validateForm(form)) return;

    const formData = new FormData(form);

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => window.location.href = data.redirect || 'index.php', 2000);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Bir hata oluştu! Lütfen daha sonra tekrar deneyin.');
    });
} 