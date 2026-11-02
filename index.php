<?php
$page_title = 'Bilet Platformu - Türkiye\'nin Otobüs Bileti Sitesi';
require_once 'includes/header.php';

// Yaklaşan aktif seferleri çek
$stmt_featured = $pdo->prepare(
    "SELECT T.*, BC.name as company_name,
            (T.capacity - (SELECT COUNT(*) FROM Booked_Seats BS JOIN Tickets TK ON BS.ticket_id = TK.id WHERE TK.trip_id = T.id AND TK.status = 'ACTIVE')) as available_seats
     FROM Trips T
     JOIN Bus_Company BC ON T.company_id = BC.id
     WHERE T.departure_time > datetime('now', 'localtime')
     ORDER BY T.departure_time ASC
     LIMIT 6"
);
$stmt_featured->execute();
$featured_trips = $stmt_featured->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Banner -->
<section class="hero-section">
    <div class="app-container">
        <h1 class="hero-title">Yolculuğunu Kolayca Planla,<br>En Uygun Bileti Hemen Al</h1>
        <p class="hero-subtitle">Kâmil Koç, Pamukkale ve Metro Turizm seferlerini karşılaştır, dilediğin koltuğu anında seç.</p>
    </div>
</section>

<!-- Floating Search Card -->
<div class="app-container search-card-wrapper">
    <?php display_flash(); ?>
    <div class="search-card">
        <form action="/search.php" method="GET" class="search-form-grid" id="main-search-form">
            <div class="form-group">
                <div class="form-label-row">
                    <label for="departure" class="form-label" style="margin-bottom:0;">📍 Kalkış Noktası</label>
                </div>
                <div class="city-picker-wrapper" id="dep-wrapper">
                    <input type="text" id="departure" name="departure" class="form-control" placeholder="Şehir seçin veya yazın..." required autocomplete="off">
                    <span class="city-input-icon">▾</span>
                    <div class="city-dropdown" id="dep-dropdown">
                        <div class="city-dropdown-header">
                            <div class="city-dropdown-title">🔥 Popüler Şehirler</div>
                            <div class="city-popular-pills" id="dep-popular-pills"></div>
                        </div>
                        <div class="city-dropdown-title" style="padding: 0.25rem 0.5rem 0.25rem 0.5rem;">Tüm Güzergahlar</div>
                        <ul class="city-list-group" id="dep-list"></ul>
                    </div>
                </div>
            </div>

            <div class="swap-btn-container">
                <button type="button" class="swap-cities-btn" id="swap-btn" title="Kalkış ve Varış Şehirlerini Değiştir (⇄)">
                    ⇄
                </button>
            </div>

            <div class="form-group">
                <div class="form-label-row">
                    <label for="arrival" class="form-label" style="margin-bottom:0;">🎯 Varış Noktası</label>
                </div>
                <div class="city-picker-wrapper" id="arr-wrapper">
                    <input type="text" id="arrival" name="arrival" class="form-control" placeholder="Şehir seçin veya yazın..." required autocomplete="off">
                    <span class="city-input-icon">▾</span>
                    <div class="city-dropdown" id="arr-dropdown">
                        <div class="city-dropdown-header">
                            <div class="city-dropdown-title">🔥 Popüler Şehirler</div>
                            <div class="city-popular-pills" id="arr-popular-pills"></div>
                        </div>
                        <div class="city-dropdown-title" style="padding: 0.25rem 0.5rem 0.25rem 0.5rem;">Tüm Güzergahlar</div>
                        <ul class="city-list-group" id="arr-list"></ul>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="form-label-row">
                    <label for="search-date" class="form-label" style="margin-bottom:0;">📅 Gidiş Tarihi</label>
                    <span id="date-day-badge" class="date-day-badge">Bugün</span>
                </div>
                <div class="modern-date-container">
                    <span class="date-left-icon">📅</span>
                    <input type="date" id="search-date" name="date" class="form-control date-input-modern" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required>
                    <div class="date-quick-buttons">
                        <button type="button" class="date-quick-btn active" id="btn-today">Bugün</button>
                        <button type="button" class="date-quick-btn" id="btn-tomorrow">Yarın</button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="min-width: 170px;">
                🔍 Seferleri Bul
            </button>
        </form>

        <!-- Hızlı Popüler Güzergah Çipleri -->
        <div class="quick-routes">
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-right: 0.25rem;">Popüler Hatlar:</span>
            <button type="button" class="route-chip" onclick="setQuickRoute('İstanbul', 'Ankara')">İstanbul ⇄ Ankara</button>
            <button type="button" class="route-chip" onclick="setQuickRoute('İstanbul', 'İzmir')">İstanbul ⇄ İzmir</button>
            <button type="button" class="route-chip" onclick="setQuickRoute('Ankara', 'Antalya')">Ankara ⇄ Antalya</button>
            <button type="button" class="route-chip" onclick="setQuickRoute('İstanbul', 'Bursa')">İstanbul ⇄ Bursa</button>
            <button type="button" class="route-chip" onclick="setQuickRoute('İzmir', 'İstanbul')">İzmir ⇄ İstanbul</button>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="app-container" style="margin-bottom: 3.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; background: #eff6ff; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">⚡</div>
            <div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.2rem;">Anında Koltuk Onayı</h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">ACID transaction ile çifte rezervasyon riski olmadan saniyeler içinde biletleme.</p>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; background: #ecfdf5; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">🛡️</div>
            <div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.2rem;">Koşulsuz Bilet İptali</h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Sefere 1 saat kalana dek tek tıkla bilet iptali ve anında tam bakiye iadesi.</p>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; background: #fef3c7; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">🎟️</div>
            <div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.2rem;">Avantajlı Kuponlar</h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">`HOSGELDIN20` ile %20 indirimli bilet alma fırsatı.</p>
            </div>
        </div>
    </div>
</div>

<!-- Featured Trips Section -->
<div class="app-container">
    <div class="section-header">
        <div>
            <h2 class="section-title">🔥 Yaklaşan Popüler Seferler</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">Anlık kontenjan ve fiyatlarla hemen rezervasyon yapabilirsiniz.</p>
        </div>
    </div>

    <?php if (empty($featured_trips)): ?>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted); font-size: 1.1rem;">Şu anda planlanmış aktif sefer bulunmamaktadır.</p>
        </div>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($featured_trips as $trip): 
                $is_full = ($trip['available_seats'] <= 0);
            ?>
                <div class="trip-card">
                    <div>
                        <div class="trip-header">
                            <div class="company-badge">
                                <span>🚍</span>
                                <span><?= htmlspecialchars($trip['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="price-tag">
                                ₺<?= number_format($trip['price'], 2); ?>
                            </div>
                        </div>

                        <div class="route-info">
                            <div class="route-point">
                                <div class="route-city"><?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="route-time"><?= date('H:i', strtotime($trip['departure_time'])); ?> • <?= date('d M', strtotime($trip['departure_time'])); ?></div>
                            </div>
                            <div class="route-divider">
                                <span>── 🚌 ──</span>
                            </div>
                            <div class="route-point right">
                                <div class="route-city"><?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="route-time"><?= date('H:i', strtotime($trip['arrival_time'])); ?> • <?= date('d M', strtotime($trip['arrival_time'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="trip-footer">
                        <div class="seat-availability <?= $is_full ? 'full' : 'available'; ?>">
                            <span class="legend-indicator" style="background: <?= $is_full ? 'var(--danger)' : 'var(--success)'; ?>;"></span>
                            <?= $is_full ? 'Kontenjan Dolu' : 'Kalan Koltuk: ' . $trip['available_seats'] . '/' . $trip['capacity']; ?>
                        </div>

                        <?php if ($is_full): ?>
                            <button class="btn btn-sm btn-secondary" disabled>Tükendi</button>
                        <?php else: ?>
                            <a href="/buy_ticket.php?trip_id=<?= urlencode($trip['id']); ?>" class="btn btn-sm btn-primary">
                                Koltuk Seç →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    let globalCityData = [];

    function setQuickRoute(dep, arr) {
        const depInput = document.getElementById('departure');
        const arrInput = document.getElementById('arrival');
        if (depInput && arrInput) {
            depInput.value = dep;
            arrInput.value = arr;
            closeAllCityDropdowns();
        }
    }

    function closeAllCityDropdowns() {
        document.querySelectorAll('.city-dropdown').forEach(d => d.classList.remove('show'));
    }

    document.addEventListener('DOMContentLoaded', function() {
        const depInput = document.getElementById('departure');
        const arrInput = document.getElementById('arrival');
        const depDropdown = document.getElementById('dep-dropdown');
        const arrDropdown = document.getElementById('arr-dropdown');
        const depList = document.getElementById('dep-list');
        const arrList = document.getElementById('arr-list');
        const depPills = document.getElementById('dep-popular-pills');
        const arrPills = document.getElementById('arr-popular-pills');
        const swapBtn = document.getElementById('swap-btn');

        // Şehir Değiştir (Swap) Butonu
        if (swapBtn && depInput && arrInput) {
            swapBtn.addEventListener('click', function() {
                const temp = depInput.value;
                depInput.value = arrInput.value;
                arrInput.value = temp;
                
                this.style.transform = 'rotate(180deg) scale(1.1)';
                setTimeout(() => {
                    this.style.transform = 'none';
                }, 300);
            });
        }

        // Şehir Verilerini API'den Çek
        fetch('/api/get_cities.php')
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.cities)) {
                    globalCityData = data.cities;
                } else if (Array.isArray(data)) {
                    globalCityData = data.map(c => ({ name: c, total_trips: 0, is_popular: false }));
                }
                setupPicker(depInput, depDropdown, depList, depPills, () => {
                    setTimeout(() => {
                        arrInput.focus();
                    }, 100);
                });
                setupPicker(arrInput, arrDropdown, arrList, arrPills, null);
            })
            .catch(err => console.error('Şehir listesi yüklenemedi:', err));

        function setupPicker(inputEl, dropdownEl, listEl, pillsEl, onSelectCallback) {
            if (!inputEl || !dropdownEl || !listEl) return;

            // Popüler Şehir Butonlarını Oluştur
            if (pillsEl) {
                pillsEl.innerHTML = '';
                const populars = globalCityData.filter(c => c.is_popular).slice(0, 5);
                populars.forEach(c => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'city-pill-btn';
                    btn.textContent = c.name;
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectCity(c.name);
                    });
                    pillsEl.appendChild(btn);
                });
            }

            function renderList(filterText = '') {
                listEl.innerHTML = '';
                const query = filterText.trim().toLocaleLowerCase('tr-TR');
                const filtered = globalCityData.filter(c => 
                    c.name.toLocaleLowerCase('tr-TR').includes(query)
                );

                if (filtered.length === 0) {
                    listEl.innerHTML = `
                        <li class="city-empty-msg">
                            🔍 <strong>"${filterText}"</strong> için aktif güzergah bulunamadı.
                        </li>`;
                    return;
                }

                filtered.forEach((c) => {
                    const li = document.createElement('li');
                    li.className = 'city-list-item';
                    li.dataset.cityName = c.name;
                    
                    const tripBadge = c.total_trips > 0 
                        ? `<span class="city-subtext">${c.total_trips} Aktif Sefer</span>` 
                        : `<span class="city-subtext">Otogar Mevcut</span>`;

                    li.innerHTML = `
                        <div class="city-item-left">
                            <span class="city-icon-badge">🏙️</span>
                            <div>
                                <div class="city-name-text">${c.name}</div>
                                ${tripBadge}
                            </div>
                        </div>
                        <span class="city-arrow-badge">→</span>
                    `;

                    li.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectCity(c.name);
                    });
                    listEl.appendChild(li);
                });
            }

            function selectCity(cityName) {
                inputEl.value = cityName;
                closeAllCityDropdowns();
                if (typeof onSelectCallback === 'function') {
                    onSelectCallback(cityName);
                }
            }

            function show() {
                closeAllCityDropdowns();
                dropdownEl.classList.add('show');
                renderList(inputEl.value);
            }

            inputEl.addEventListener('focus', show);
            inputEl.addEventListener('click', show);
            inputEl.addEventListener('input', () => {
                dropdownEl.classList.add('show');
                renderList(inputEl.value);
            });

            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    dropdownEl.classList.remove('show');
                } else if (e.key === 'Enter') {
                    const firstItem = listEl.querySelector('.city-list-item');
                    if (firstItem && dropdownEl.classList.contains('show')) {
                        e.preventDefault();
                        selectCity(firstItem.dataset.cityName);
                    }
                }
            });
        }

        // Tarih Seçici Etkileşimi (Bugün / Yarın ve Canlı Türkçe Tarih Rozeti)
        const dateInput = document.getElementById('search-date');
        const dateDayBadge = document.getElementById('date-day-badge');
        const btnToday = document.getElementById('btn-today');
        const btnTomorrow = document.getElementById('btn-tomorrow');

        const turkishMonths = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        const turkishDays = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

        function formatDateDisplay(dateStr) {
            if (!dateStr || !dateDayBadge) return;
            const parts = dateStr.split('-');
            if (parts.length !== 3) return;
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            const d = new Date(year, month, day);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const target = new Date(year, month, day);
            target.setHours(0, 0, 0, 0);

            const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                dateDayBadge.textContent = `Bugün • ${turkishDays[d.getDay()]}`;
                if (btnToday) btnToday.classList.add('active');
                if (btnTomorrow) btnTomorrow.classList.remove('active');
            } else if (diffDays === 1) {
                dateDayBadge.textContent = `Yarın • ${turkishDays[d.getDay()]}`;
                if (btnToday) btnToday.classList.remove('active');
                if (btnTomorrow) btnTomorrow.classList.add('active');
            } else {
                dateDayBadge.textContent = `${turkishDays[d.getDay()]}, ${day} ${turkishMonths[month]}`;
                if (btnToday) btnToday.classList.remove('active');
                if (btnTomorrow) btnTomorrow.classList.remove('active');
            }
        }

        if (dateInput) {
            formatDateDisplay(dateInput.value);

            dateInput.addEventListener('change', function() {
                formatDateDisplay(this.value);
            });

            dateInput.addEventListener('input', function() {
                formatDateDisplay(this.value);
            });
        }

        if (btnToday) {
            btnToday.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;
                dateInput.value = todayStr;
                formatDateDisplay(todayStr);
            });
        }

        if (btnTomorrow) {
            btnTomorrow.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const yyyy = tomorrow.getFullYear();
                const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
                const dd = String(tomorrow.getDate()).padStart(2, '0');
                const tomorrowStr = `${yyyy}-${mm}-${dd}`;
                dateInput.value = tomorrowStr;
                formatDateDisplay(tomorrowStr);
            });
        }

        // Dışarı tıklayınca dropdownları kapat
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.city-picker-wrapper') && !e.target.closest('.route-chip')) {
                closeAllCityDropdowns();
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>