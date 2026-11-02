/**
 * Bespoke Interactive Travel Datepicker & Calendar System
 * 100% Vanilla JS, lightweight, responsive, Turkish localization.
 */
(function() {
    'use strict';

    const TURKISH_MONTHS = [
        'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
    ];
    const TURKISH_DAYS_SHORT = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
    const TURKISH_DAYS_FULL = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    function padZero(num) {
        return num < 10 ? '0' + num : '' + num;
    }

    function parseInputDate(val) {
        if (!val) return null;
        const [dPart, tPart] = val.split('T');
        const [y, m, d] = dPart.split('-').map(Number);
        let hh = 12, mm = 0;
        if (tPart) {
            const [h, min] = tPart.split(':').map(Number);
            if (!isNaN(h)) hh = h;
            if (!isNaN(min)) mm = min;
        }
        return { year: y, month: m - 1, day: d, hours: hh, minutes: mm };
    }

    function formatOutputDate(year, month, day, isDateTime, hours, minutes) {
        const dStr = `${year}-${padZero(month + 1)}-${padZero(day)}`;
        if (isDateTime) {
            return `${dStr}T${padZero(hours)}:${padZero(minutes)}`;
        }
        return dStr;
    }

    function initTravelDatepickers() {
        const inputs = document.querySelectorAll('input[type="date"], input[type="datetime-local"]');

        inputs.forEach(input => {
            if (input.dataset.travelDatepickerInitialized) return;
            input.dataset.travelDatepickerInitialized = 'true';

            const isDateTime = input.type === 'datetime-local';
            const minDate = input.getAttribute('min');
            const maxDate = input.getAttribute('max');

            // Wrap input in container if not already wrapped
            let container = input.closest('.modern-date-container') || input.closest('.custom-datepicker-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'custom-datepicker-container';
                input.parentNode.insertBefore(container, input);
                container.appendChild(input);
            }

            // Visible calendar trigger button
            let triggerBtn = container.querySelector('.custom-datepicker-trigger-btn');
            if (!triggerBtn && !container.classList.contains('modern-date-container')) {
                triggerBtn = document.createElement('button');
                triggerBtn.type = 'button';
                triggerBtn.className = 'custom-datepicker-trigger-btn';
                triggerBtn.innerHTML = '📅';
                triggerBtn.title = 'Takvimi Aç';
                container.appendChild(triggerBtn);
            }

            // Create Calendar Modal
            const modal = document.createElement('div');
            modal.className = 'travel-calendar-modal';
            container.appendChild(modal);

            // Internal View State
            const parsedInitial = parseInputDate(input.value) || {
                year: new Date().getFullYear(),
                month: new Date().getMonth(),
                day: new Date().getDate(),
                hours: 12,
                minutes: 0
            };

            let viewYear = parsedInitial.year;
            let viewMonth = parsedInitial.month;
            let selectedDate = parseInputDate(input.value);

            function renderCalendar() {
                modal.innerHTML = '';

                // 1. Navigation Header
                const navHeader = document.createElement('div');
                navHeader.className = 'cal-nav-header';

                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.className = 'cal-nav-btn';
                prevBtn.innerHTML = '‹';
                prevBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    viewMonth--;
                    if (viewMonth < 0) {
                        viewMonth = 11;
                        viewYear--;
                    }
                    renderCalendar();
                });

                const titleEl = document.createElement('div');
                titleEl.className = 'cal-month-title';
                titleEl.textContent = `${TURKISH_MONTHS[viewMonth]} ${viewYear}`;

                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.className = 'cal-nav-btn';
                nextBtn.innerHTML = '›';
                nextBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    viewMonth++;
                    if (viewMonth > 11) {
                        viewMonth = 0;
                        viewYear++;
                    }
                    renderCalendar();
                });

                navHeader.appendChild(prevBtn);
                navHeader.appendChild(titleEl);
                navHeader.appendChild(nextBtn);
                modal.appendChild(navHeader);

                // 2. Quick Selection Row
                const quickRow = document.createElement('div');
                quickRow.className = 'cal-quick-row';

                const todayBtn = document.createElement('button');
                todayBtn.type = 'button';
                todayBtn.className = 'cal-quick-chip';
                todayBtn.textContent = 'Bugün';
                todayBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const now = new Date();
                    chooseDate(now.getFullYear(), now.getMonth(), now.getDate());
                });

                const tomorrowBtn = document.createElement('button');
                tomorrowBtn.type = 'button';
                tomorrowBtn.className = 'cal-quick-chip';
                tomorrowBtn.textContent = 'Yarın';
                tomorrowBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const tom = new Date();
                    tom.setDate(tom.getDate() + 1);
                    chooseDate(tom.getFullYear(), tom.getMonth(), tom.getDate());
                });

                const nextWeekBtn = document.createElement('button');
                nextWeekBtn.type = 'button';
                nextWeekBtn.className = 'cal-quick-chip';
                nextWeekBtn.textContent = '+7 Gün';
                nextWeekBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const nw = new Date();
                    nw.setDate(nw.getDate() + 7);
                    chooseDate(nw.getFullYear(), nw.getMonth(), nw.getDate());
                });

                quickRow.appendChild(todayBtn);
                quickRow.appendChild(tomorrowBtn);
                quickRow.appendChild(nextWeekBtn);
                modal.appendChild(quickRow);

                // 3. Weekdays Header
                const weekdaysRow = document.createElement('div');
                weekdaysRow.className = 'cal-weekdays';
                TURKISH_DAYS_SHORT.forEach(dayName => {
                    const span = document.createElement('span');
                    span.className = 'cal-weekday';
                    span.textContent = dayName;
                    weekdaysRow.appendChild(span);
                });
                modal.appendChild(weekdaysRow);

                // 4. Days Grid
                const daysGrid = document.createElement('div');
                daysGrid.className = 'cal-days-grid';

                const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
                // In JS getDay(): 0 is Sunday, 1 is Monday... convert to Mon=0 ... Sun=6
                let startDay = firstDayOfMonth.getDay() - 1;
                if (startDay < 0) startDay = 6;

                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
                const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

                const todayObj = new Date();
                const todayY = todayObj.getFullYear();
                const todayM = todayObj.getMonth();
                const todayD = todayObj.getDate();

                // Prev month padding
                for (let i = startDay - 1; i >= 0; i--) {
                    const cell = document.createElement('div');
                    cell.className = 'cal-day-cell other-month';
                    cell.textContent = daysInPrevMonth - i;
                    daysGrid.appendChild(cell);
                }

                // Current month days
                for (let d = 1; d <= daysInMonth; d++) {
                    const cell = document.createElement('div');
                    cell.className = 'cal-day-cell';
                    cell.textContent = d;

                    const thisDateStr = `${viewYear}-${padZero(viewMonth + 1)}-${padZero(d)}`;

                    // Check Today
                    if (viewYear === todayY && viewMonth === todayM && d === todayD) {
                        cell.classList.add('today');
                    }

                    // Check Selected
                    if (selectedDate && selectedDate.year === viewYear && selectedDate.month === viewMonth && selectedDate.day === d) {
                        cell.classList.add('selected');
                    }

                    // Check Min / Max
                    let isDisabled = false;
                    if (minDate && thisDateStr < minDate.substring(0, 10)) {
                        isDisabled = true;
                    }
                    if (maxDate && thisDateStr > maxDate.substring(0, 10)) {
                        isDisabled = true;
                    }

                    if (isDisabled) {
                        cell.classList.add('disabled');
                    } else {
                        cell.addEventListener('click', (e) => {
                            e.stopPropagation();
                            chooseDate(viewYear, viewMonth, d);
                        });
                    }

                    daysGrid.appendChild(cell);
                }

                modal.appendChild(daysGrid);

                // 5. Time Section if Datetime-Local
                if (isDateTime) {
                    const timeRow = document.createElement('div');
                    timeRow.className = 'cal-time-picker-row';
                    timeRow.innerHTML = `<span>⏰ Sefer Saati:</span>`;

                    const timeInputs = document.createElement('div');
                    timeInputs.className = 'cal-time-inputs';

                    const hoursSelect = document.createElement('select');
                    hoursSelect.className = 'cal-time-select';
                    for (let h = 0; h < 24; h++) {
                        const opt = document.createElement('option');
                        opt.value = h;
                        opt.textContent = padZero(h);
                        if (selectedDate && selectedDate.hours === h) opt.selected = true;
                        hoursSelect.appendChild(opt);
                    }

                    const minSelect = document.createElement('select');
                    minSelect.className = 'cal-time-select';
                    for (let m = 0; m < 60; m += 5) {
                        const opt = document.createElement('option');
                        opt.value = m;
                        opt.textContent = padZero(m);
                        if (selectedDate && Math.abs(selectedDate.minutes - m) < 5) opt.selected = true;
                        minSelect.appendChild(opt);
                    }

                    hoursSelect.addEventListener('change', () => {
                        if (selectedDate) selectedDate.hours = parseInt(hoursSelect.value, 10);
                        updateInputValue();
                    });
                    minSelect.addEventListener('change', () => {
                        if (selectedDate) selectedDate.minutes = parseInt(minSelect.value, 10);
                        updateInputValue();
                    });

                    timeInputs.appendChild(hoursSelect);
                    timeInputs.appendChild(document.createTextNode(' : '));
                    timeInputs.appendChild(minSelect);
                    timeRow.appendChild(timeInputs);
                    modal.appendChild(timeRow);
                }

                // 6. Footer (Kapat / Uygula)
                const footer = document.createElement('div');
                footer.className = 'cal-footer';

                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'cal-footer-btn cal-btn-close';
                closeBtn.textContent = 'Kapat';
                closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeModal();
                });

                const applyBtn = document.createElement('button');
                applyBtn.type = 'button';
                applyBtn.className = 'cal-footer-btn cal-btn-apply';
                applyBtn.textContent = 'Tamam';
                applyBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeModal();
                });

                footer.appendChild(closeBtn);
                footer.appendChild(applyBtn);
                modal.appendChild(footer);
            }

            function chooseDate(y, m, d) {
                const curHours = selectedDate ? selectedDate.hours : 12;
                const curMins = selectedDate ? selectedDate.minutes : 0;
                selectedDate = { year: y, month: m, day: d, hours: curHours, minutes: curMins };
                updateInputValue();

                if (!isDateTime) {
                    closeModal();
                } else {
                    renderCalendar();
                }
            }

            function updateInputValue() {
                if (!selectedDate) return;
                const formatted = formatOutputDate(
                    selectedDate.year,
                    selectedDate.month,
                    selectedDate.day,
                    isDateTime,
                    selectedDate.hours,
                    selectedDate.minutes
                );
                input.value = formatted;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                // Update badge if on index.php
                const badge = document.getElementById('date-day-badge');
                if (badge && input.id === 'search-date') {
                    const d = new Date(selectedDate.year, selectedDate.month, selectedDate.day);
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    const target = new Date(selectedDate.year, selectedDate.month, selectedDate.day);
                    target.setHours(0,0,0,0);
                    const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24));

                    if (diffDays === 0) {
                        badge.textContent = `Bugün • ${TURKISH_DAYS_FULL[d.getDay()]}`;
                    } else if (diffDays === 1) {
                        badge.textContent = `Yarın • ${TURKISH_DAYS_FULL[d.getDay()]}`;
                    } else {
                        badge.textContent = `${TURKISH_DAYS_FULL[d.getDay()]}, ${selectedDate.day} ${TURKISH_MONTHS[selectedDate.month]}`;
                    }
                }
            }

            function openModal() {
                document.querySelectorAll('.travel-calendar-modal').forEach(m => m.classList.remove('show'));
                selectedDate = parseInputDate(input.value) || {
                    year: new Date().getFullYear(),
                    month: new Date().getMonth(),
                    day: new Date().getDate(),
                    hours: 12,
                    minutes: 0
                };
                viewYear = selectedDate.year;
                viewMonth = selectedDate.month;
                renderCalendar();
                modal.classList.add('show');
            }

            function closeModal() {
                modal.classList.remove('show');
            }

            // Click events
            input.addEventListener('click', (e) => {
                e.preventDefault();
                openModal();
            });

            const leftIcon = container.querySelector('.date-left-icon');
            if (leftIcon) {
                leftIcon.style.pointerEvents = 'auto';
                leftIcon.style.cursor = 'pointer';
                leftIcon.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (modal.classList.contains('show')) {
                        closeModal();
                    } else {
                        openModal();
                    }
                });
            }

            if (triggerBtn) {
                triggerBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (modal.classList.contains('show')) {
                        closeModal();
                    } else {
                        openModal();
                    }
                });
            }

            // Keyboard Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeModal();
            });

            // Outside Click
            document.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    closeModal();
                }
            });
        });
    }

    // Auto initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTravelDatepickers);
    } else {
        initTravelDatepickers();
    }

    // Expose for dynamic pages
    window.initTravelDatepickers = initTravelDatepickers;
})();
