// Kasa açma işlemi
function openCase(event, caseId) {
    event.preventDefault(); // Varsayılan davranışı engelle
    
    // Kasa açma butonunu devre dışı bırak
    const button = event.currentTarget;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Açılıyor...';

    // Modal'ı göster
    const modal = document.getElementById('caseModal');
    if (!modal) {
        console.error('Modal element bulunamadı!');
        return;
    }
    modal.style.display = 'flex';

    // Önce ödülleri çek
    fetch(`ajax/get_case_items.php?case_id=${caseId}`)
        .then(response => response.json())
        .then(itemsData => {
            if (!itemsData.success) {
                throw new Error(itemsData.error || 'Ödüller yüklenemedi');
            }

            // Animasyon için ödülleri hazırla
            const itemsContainer = document.querySelector('.items-container');
            itemsContainer.innerHTML = ''; // Önceki ödülleri temizle
            
            // İki grup oluştur (sonsuz döngü için)
            for (let group = 0; group < 2; group++) {
                const itemsGroup = document.createElement('div');
                itemsGroup.className = 'spin-items-group';
                
                // Her grupta ödülleri 5 kez tekrarla
                for(let i = 0; i < 5; i++) {
                    itemsData.items.forEach(item => {
                        const coinValue = parseInt(item.coin_value) || 0;
                        itemsGroup.innerHTML += `
                            <div class="spin-item">
                                <div class="spin-item-inner rarity-${item.rarity}">
                                    <img src="${item.image}" alt="${item.name}" class="spin-item-image">
                                    <div class="spin-item-name">${item.name}</div>
                                    <div class="spin-item-coins">
                                        <i class="fas fa-coins"></i>
                                        <span>${coinValue.toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                itemsContainer.appendChild(itemsGroup);
            }

            // Başlangıç pozisyonunu ayarla
            itemsContainer.style.transform = 'translateX(0)';

            // Kasa açma işlemini başlat
            return fetch(`ajax/open_case.php?case_id=${caseId}`);
        })
        .then(response => response.json())
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                // Dönme animasyonunu başlat
                const itemsContainer = document.querySelector('.items-container');
                const containerWidth = itemsContainer.scrollWidth;
                const modalWidth = modal.offsetWidth;
                
                setTimeout(() => {
                    // Kazanılan ödülün pozisyonunu bul
                    const spinItems = document.querySelectorAll('.spin-item');
                    const winningItem = Array.from(spinItems).find(item => 
                        item.querySelector('.spin-item-name').textContent === data.item.name
                    );

                    if (winningItem) {
                        // İlk hızlı dönme animasyonu
                        const initialScroll = containerWidth * 0.8; // Başlangıç mesafesi
                        itemsContainer.style.transition = 'transform 1.5s cubic-bezier(0.5, 0, 0.75, 0)';
                        itemsContainer.style.transform = `translateX(-${initialScroll}px)`;

                        // Yavaşlayarak kazanılan ödüle gelme
                        setTimeout(() => {
                            // Kazanılan ödülün tam ortaya gelmesi için pozisyonu hesapla
                            const targetOffset = winningItem.offsetLeft - (modalWidth / 2) + (winningItem.offsetWidth / 2);
                            
                            // Yavaşlama efekti için cubic-bezier kullan
                            itemsContainer.style.transition = 'transform 2.5s cubic-bezier(0.1, 0.7, 0.2, 1)';
                            itemsContainer.style.transform = `translateX(-${targetOffset}px)`;

                            // Animasyon bitiminde ödülü göster
                            setTimeout(() => {
                                // Kazanan öğeyi vurgula
                                winningItem.querySelector('.spin-item-inner').classList.add('winner');
                                
                                setTimeout(() => {
                                    showWonItem(data.item);
                                    updateBalance(data.newBalance);
                                    fireConfetti();
                                }, 500);
                            }, 2500);
                        }, 1500);
                    }
                }, 100);
            } else {
                alert(data.error || 'Bir hata oluştu!');
                modal.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bir hata oluştu! Lütfen daha sonra tekrar deneyin.');
            modal.style.display = 'none';
        })
        .finally(() => {
            // Butonu tekrar aktif et
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-box-open"></i> Kasa Aç';
        });
}

function showWonItem(item) {
    const wonContainer = document.querySelector('.won-item-container');
    const wonItem = document.querySelector('.won-item');
    
    if (!wonContainer || !wonItem) {
        console.error('Won item container veya won item elementi bulunamadı!');
        return;
    }

    // Animasyon elementini gizle
    const animationContainer = document.querySelector('.case-opening-animation');
    if (animationContainer) {
        animationContainer.style.display = 'none';
    }
    
    const coinValue = parseInt(item.coin_value) || 0;
    
    wonItem.innerHTML = `
        <div class="won-item-card rarity-${item.rarity}">
            <img src="${item.image}" alt="${item.name}">
            <h4>${item.name}</h4>
            <div class="won-coins">
                <i class="coin-icon"></i>
                <span>${coinValue.toLocaleString()}</span>
            </div>
        </div>
    `;
    
    wonContainer.style.display = 'block';
}

function updateBalance(newBalance) {
    const balanceElement = document.querySelector('.coins-header span');
    if (balanceElement) {
        balanceElement.textContent = newBalance.toLocaleString();
    }
}

// Modal'ı kapat
function closeModal() {
    const modal = document.getElementById('caseModal');
    if (!modal) return;
    
    modal.style.display = 'none';
    
    // Animasyon elementlerini sıfırla
    const animationContainer = document.querySelector('.case-opening-animation');
    const wonContainer = document.querySelector('.won-item-container');
    const itemsContainer = document.querySelector('.items-container');
    
    if (animationContainer) animationContainer.style.display = 'block';
    if (wonContainer) wonContainer.style.display = 'none';
    if (itemsContainer) itemsContainer.style.transform = 'translateX(0)';
}

// Bildirim göster
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Konfeti animasyonu fonksiyonu
function fireConfetti() {
    // Renkli konfetiler
    const colors = ['#ffd700', '#ffb700', '#ff9500', '#ff7300', '#ff5100'];
    
    // Yavaş düşen konfetiler
    confetti({
        particleCount: 150,
        spread: 100,
        origin: { y: 0.3 },
        colors: colors,
        angle: 90,
        startVelocity: 30,
        gravity: 0.5,
        scalar: 1.2,
        drift: 0,
        ticks: 300
    });
    
    // Yanlara doğru yayılan konfetiler
    setTimeout(() => {
        // Sol taraftan
        confetti({
            particleCount: 80,
            spread: 60,
            origin: { x: 0.2, y: 0.5 },
            colors: colors,
            angle: 60,
            startVelocity: 25,
            gravity: 0.5,
            ticks: 300
        });
        
        // Sağ taraftan
        confetti({
            particleCount: 80,
            spread: 60,
            origin: { x: 0.8, y: 0.5 },
            colors: colors,
            angle: 120,
            startVelocity: 25,
            gravity: 0.5,
            ticks: 300
        });
    }, 500);
} 