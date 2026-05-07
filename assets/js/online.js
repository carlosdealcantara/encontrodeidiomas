document.addEventListener('DOMContentLoaded', function() {
    // Pegar dados iniciais dos atributos data ou variáveis globais injetadas
    const config = window.onlineConfig || {};
    
    const dayFilterBtn  = document.getElementById('day-filter-btn');
    const langFilterBtn = document.getElementById('language-filter-btn');
    const dayView       = document.getElementById('day-view');
    const langView      = document.getElementById('language-view');
    
    let currentView = config.initialView || 'day';
    let currentDay  = config.initialDay || '1';
    let currentLang = config.initialLang || '';

    // Força o carregamento da imagem de bandeira
    const flagImgEl = document.getElementById('selected-language-flag');
    if (flagImgEl && flagImgEl.getAttribute('src')) {
        const cachedSrc = flagImgEl.getAttribute('src');
        const preloader = new Image();
        preloader.onload = function() {
            if (flagImgEl.src !== this.src) flagImgEl.src = this.src;
            flagImgEl.style.display = '';
        };
        preloader.onerror = function() {
            flagImgEl.style.display = 'none';
        };
        preloader.src = cachedSrc;
    }

    function updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('view', currentView);
        if (currentView === 'day') {
            url.searchParams.set('dia', currentDay);
            url.searchParams.delete('idioma');
        } else {
            url.searchParams.set('idioma', currentLang);
            url.searchParams.delete('dia');
        }
        window.history.replaceState({}, '', url);
    }

    function smoothScrollTo(endY, duration) {
        const startY = window.pageYOffset;
        const distance = endY - startY;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = ease(timeElapsed, startY, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        function ease(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        requestAnimationFrame(animation);
    }

    function scrollToRelevantEvent() {
        if (currentView !== 'day') return;
        const target = document.querySelector('.day-events.active .scroll-target');
        if (target) {
            setTimeout(() => {
                const elementRect = target.getBoundingClientRect();
                const absoluteElementTop = elementRect.top + window.pageYOffset;
                const middle = absoluteElementTop - (window.innerHeight / 2) + (elementRect.height / 2);
                smoothScrollTo(middle, 1500);
            }, 1000);
        }
    }

    function activateDay(dayNum) {
        document.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.day-events').forEach(d => d.classList.remove('active'));
        const btn = document.querySelector(`.day-button[data-day="${dayNum}"]`);
        const panel = document.getElementById('day-' + dayNum);
        if (btn) btn.classList.add('active');
        if (panel) panel.classList.add('active');
        currentDay = String(dayNum);
    }

    // Toggle views
    if (dayFilterBtn) {
        dayFilterBtn.addEventListener('click', function() {
            currentView = 'day';
            dayView.classList.add('active');    langView.classList.remove('active');
            dayFilterBtn.classList.add('active'); langFilterBtn.classList.remove('active');
            activateDay(currentDay);
            updateURL();
            scrollToRelevantEvent();
        });
    }

    if (langFilterBtn) {
        langFilterBtn.addEventListener('click', function() {
            currentView = 'language';
            langView.classList.add('active');   dayView.classList.remove('active');
            langFilterBtn.classList.add('active'); dayFilterBtn.classList.remove('active');
            const fImg = document.getElementById('selected-language-flag');
            if (fImg && fImg.getAttribute('src') && fImg.style.display === 'none') {
                const fEmo = document.getElementById('selected-language-emoji');
                if (!fEmo || fEmo.style.display === 'none') {
                    fImg.style.display = '';
                }
            }
            updateURL();
        });
    }

    // Day buttons
    document.querySelectorAll('.day-button').forEach(btn => {
        btn.addEventListener('click', function() {
            activateDay(this.dataset.day);
            updateURL();
            scrollToRelevantEvent();
        });
    });

    // Initial scroll & State
    if (currentView === 'day') {
        activateDay(currentDay);
        scrollToRelevantEvent();
    }

    // Dropdown toggle
    const dropBtn     = document.getElementById('lang-dropdown-btn');
    const dropContent = document.getElementById('lang-dropdown-content');
    if (dropBtn) {
        dropBtn.addEventListener('click', () => dropContent.classList.toggle('show'));
    }
    document.addEventListener('click', e => {
        if (dropBtn && !dropBtn.contains(e.target) && !dropContent.contains(e.target))
            dropContent.classList.remove('show');
    });

    // Search filter
    const langSearch = document.getElementById('language-search');
    if (langSearch) {
        langSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.language-button').forEach(btn => {
                btn.style.display = btn.dataset.language.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Language button click
    document.querySelectorAll('.language-button').forEach(btn => {
        btn.addEventListener('click', function() {
            currentLang = this.dataset.languageId;
            const langName = this.dataset.language;
            const flagCode = this.dataset.flagCode;
            const flagEmoji = this.dataset.flagEmoji;

            document.getElementById('selected-language').textContent = langName;
            
            const flagImg = document.getElementById('selected-language-flag');
            const flagEmo = document.getElementById('selected-language-emoji');

            if (flagCode) {
                const loader = new Image();
                loader.onload = function() {
                    flagImg.src = this.src;
                    flagImg.style.display = '';
                };
                loader.onerror = function() {
                    flagImg.style.display = 'none';
                };
                loader.src = `https://flagcdn.com/32x24/${flagCode}.png`;
                if (flagEmo) flagEmo.style.display = 'none';
            } else if (flagEmoji) {
                flagImg.style.display = 'none';
                if (flagEmo) {
                    flagEmo.textContent = flagEmoji;
                    flagEmo.style.display = '';
                }
            } else {
                flagImg.style.display = 'none';
                if (flagEmo) flagEmo.style.display = 'none';
            }

            document.querySelectorAll('.language-button').forEach(b => b.classList.remove('active-lang'));
            this.classList.add('active-lang');
            dropContent.classList.remove('show');

            document.querySelectorAll('.language-events-container').forEach(div => div.style.display = 'none');
            const targetDiv = document.getElementById('lang-events-' + currentLang);
            if (targetDiv) targetDiv.style.display = 'block';

            updateURL();
        });
    });

    // Auto-scroll suave
    window.addEventListener('load', function() {
        if (window.location.hash) return;
        
        setTimeout(() => {
            const calendarNav = document.querySelector('.calendar-nav');
            if (calendarNav) {
                const header = document.querySelector('.header');
                const headerHeight = header ? header.offsetHeight : 80;
                const targetY = calendarNav.getBoundingClientRect().top + window.pageYOffset - headerHeight - 180;
                
                smoothScrollTo(targetY, 1500); // 1.5 segundos de rolagem
            }
        }, 1500); // 1.5 segundos de espera
    });
});
