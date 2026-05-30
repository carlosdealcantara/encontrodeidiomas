/**
 * Encontro de Idiomas - Timezone Global Manager
 * Faz a conversão 100% client-side (Opção C: Movimentação Dinâmica do DOM)
 */

document.addEventListener("DOMContentLoaded", function() {
    const tzToggleBtn = document.getElementById("tz-toggle-btn");
    const tzDropdownMenu = document.getElementById("tz-dropdown-menu");
    const tzCurrentLabel = document.getElementById("tz-current-label");
    const tzInfoLabel = document.getElementById("tz-info-label");
    const smartBanner = document.getElementById("smart-suggestion-banner");
    const tzBannerRow = document.querySelector(".tz-banner-row");

    const BRT_TZ = "America/Sao_Paulo";
    let userSelectedTZ = getCookie("user_tz");
    let currentLang = document.documentElement.lang || "pt";

    // Traduções passadas do PHP (mockadas ou lidas do data-attributes, mas aqui usaremos strings diretas com base no currentLang)
    const i18n = {
        pt: {
            all_timezones: "Selecione o fuso horário",
            original_note: "Horários base: UTC-3 (Brasília)",
            day_shift_next: "(dia seguinte)",
            day_shift_prev: "(dia anterior)",
            auto_detect: "Detectar automaticamente",
            banner_text: "Detectamos que você está no fuso {tz_name}. Deseja ver os horários adaptados?",
            banner_cta: "Sim, adaptar horários",
            banner_dismiss: "Manter original",
            empty_day_text: "Não há mais encontros neste dia devido à mudança de fuso."
        },
        en: {
            all_timezones: "Select Timezone",
            original_note: "Base times: UTC-3 (Brasília Time)",
            day_shift_next: "(next day)",
            day_shift_prev: "(previous day)",
            auto_detect: "Auto-detect",
            banner_text: "We detected you're in the {tz_name} timezone. Show adapted times?",
            banner_cta: "Yes, adapt times",
            banner_dismiss: "Keep original",
            empty_day_text: "No more meetups on this day due to timezone shift."
        }
    };
    const t = i18n[currentLang] || i18n["pt"];

    function getCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? v[2] : null;
    }

    function setCookie(name, value, days) {
        let d = new Date();
        d.setTime(d.getTime() + 24*60*60*1000*days);
        document.cookie = name + "=" + value + ";path=/;max-age=" + (days*86400);
    }

    // Calcula a diferença em horas entre o timezone alvo e Brasília (neste instante)
    function getOffsetHourDiff(targetTZ) {
        try {
            const now = new Date();
            // Formata a data e hora atual em BRT e no fuso alvo, usando a formatação "en-US" estrita para parse correto
            const opts = { timeZone: BRT_TZ, year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false };
            const brtStr = now.toLocaleString("en-US", opts);
            
            opts.timeZone = targetTZ;
            const targetStr = now.toLocaleString("en-US", opts);

            const brtDate = new Date(brtStr);
            const targetDate = new Date(targetStr);

            // Diferença em milissegundos convertida para horas arredondadas
            return Math.round((targetDate - brtDate) / 3600000);
        } catch (e) {
            console.error("Fuso horário inválido:", targetTZ);
            return 0;
        }
    }

    function formatHour(hour) {
        if (currentLang === 'en') {
            const period = (hour >= 12) ? 'PM' : 'AM';
            let h12 = hour % 12;
            if (h12 === 0) h12 = 12;
            return `${h12} ${period}`;
        }
        return `${hour}h`;
    }

    // Função para buscar nome do dia da semana localizado
    function getDayName(dayNum) {
        const ptDays = {1:'Segunda', 2:'Terça', 3:'Quarta', 4:'Quinta', 5:'Sexta', 6:'Sábado', 7:'Domingo'};
        const enDays = {1:'Monday', 2:'Tuesday', 3:'Wednesday', 4:'Thursday', 5:'Friday', 6:'Saturday', 7:'Sunday'};
        return currentLang === 'en' ? enDays[dayNum] : ptDays[dayNum];
    }

    const curatedTimezones = [
        { tz: "Pacific/Midway", label: "(UTC-11) Midway Island, Samoa" },
        { tz: "Pacific/Honolulu", label: "(UTC-10) Hawaii" },
        { tz: "America/Anchorage", label: "(UTC-9) Alaska" },
        { tz: "America/Los_Angeles", label: "(UTC-8) Pacific Time (US & Canada)" },
        { tz: "America/Denver", label: "(UTC-7) Mountain Time (US & Canada)" },
        { tz: "America/Chicago", label: "(UTC-6) Central Time (US & Canada), Mexico City" },
        { tz: "America/New_York", label: "(UTC-5) Eastern Time (US & Canada), Bogota, Lima" },
        { tz: "America/Caracas", label: "(UTC-4) Caracas, La Paz, Santiago" },
        { tz: "America/Sao_Paulo", label: "(UTC-3) Brasilia, Buenos Aires, Montevideo" },
        { tz: "America/Noronha", label: "(UTC-2) Mid-Atlantic" },
        { tz: "Atlantic/Azores", label: "(UTC-1) Azores, Cape Verde Is." },
        { tz: "Europe/London", label: "(UTC+0) London, Dublin, Lisbon" },
        { tz: "Europe/Paris", label: "(UTC+1) Amsterdam, Berlin, Rome, Paris, Madrid" },
        { tz: "Europe/Helsinki", label: "(UTC+2) Athens, Bucharest, Istanbul, Cairo" },
        { tz: "Europe/Moscow", label: "(UTC+3) Moscow, Kuwait, Riyadh" },
        { tz: "Asia/Dubai", label: "(UTC+4) Abu Dhabi, Muscat" },
        { tz: "Asia/Karachi", label: "(UTC+5) Islamabad, Karachi, Tashkent" },
        { tz: "Asia/Kolkata", label: "(UTC+5:30) Chennai, Kolkata, Mumbai, New Delhi" },
        { tz: "Asia/Dhaka", label: "(UTC+6) Astana, Dhaka" },
        { tz: "Asia/Bangkok", label: "(UTC+7) Bangkok, Hanoi, Jakarta" },
        { tz: "Asia/Shanghai", label: "(UTC+8) Beijing, Perth, Singapore, Hong Kong" },
        { tz: "Asia/Tokyo", label: "(UTC+9) Tokyo, Seoul, Osaka, Sapporo" },
        { tz: "Australia/Sydney", label: "(UTC+10) Brisbane, Canberra, Melbourne, Sydney" },
        { tz: "Pacific/Noumea", label: "(UTC+11) Solomon Is., New Caledonia" },
        { tz: "Pacific/Auckland", label: "(UTC+12) Auckland, Wellington, Fiji" }
    ];

    function buildDropdown() {
        if (!tzDropdownMenu) return;

        let html = `<div class="tz-group-title">${t.all_timezones}</div>`;
        
        curatedTimezones.forEach(item => {
            html += `<button class="tz-option ${userSelectedTZ === item.tz ? 'active' : (!userSelectedTZ && item.tz === BRT_TZ ? 'active' : '')}" data-tz="${item.tz}">${item.label}</button>`;
        });

        tzDropdownMenu.innerHTML = html;

        // Adicionar eventos aos botões recém-criados
        tzDropdownMenu.querySelectorAll('.tz-option').forEach(btn => {
            btn.addEventListener('click', function() {
                const selected = this.getAttribute('data-tz');
                applyTimezone(selected);
                setCookie("user_tz", selected, 30);
                tzDropdownMenu.classList.remove('show');
                closeSmartBannerTz();
            });
        });
    }

    function applyTimezone(tz) {
        userSelectedTZ = tz;
        if(tzDropdownMenu) {
            tzDropdownMenu.querySelectorAll('.tz-option').forEach(btn => {
                if(btn.getAttribute('data-tz') === tz) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }
        
        if (tzCurrentLabel) {
            startLiveClock(tz);
        }

        if (tzInfoLabel) {
            tzInfoLabel.style.display = 'none'; // Esconde para sempre, conforme feedback
        }

        const diff = getOffsetHourDiff(tz);

        // Encontra todos os cards de eventos da página
        const cards = document.querySelectorAll('.tz-event-card');
        
        cards.forEach(card => {
            const brtHour = parseInt(card.getAttribute('data-brt-hour'), 10);
            const brtDay = parseInt(card.getAttribute('data-brt-day'), 10);
            
            let targetHour = brtHour + diff;
            let dayShift = 0;

            if (targetHour >= 24) {
                targetHour -= 24;
                dayShift = 1;
            } else if (targetHour < 0) {
                targetHour += 24;
                dayShift = -1;
            }

            // Atualiza tag de horário dentro do card
            const tag = card.querySelector('.tz-event-tag');
            if (tag) {
                let newDayName = getDayName(brtDay); // O dia exibido normalmente não muda o texto se não for pra explicitar
                // Mas queremos deixar transparente, então atualizamos o dia local!
                let localDayNum = brtDay + dayShift;
                if (localDayNum > 7) localDayNum = 1;
                if (localDayNum < 1) localDayNum = 7;
                
                let dayText = getDayName(localDayNum);
                let shiftText = "";
                // Como faremos relocação no DOM (Opção C), o dia já vai estar no container certo.
                // Mas, no language-view, a pessoa vê tudo numa lista só, lá a label de dia é mais importante.
                tag.textContent = `${dayText} ${currentLang === 'en' ? 'at' : 'às'} ${formatHour(targetHour)}`;
            }

            // Atributos lógicos atualizados para possível ordenação
            card.setAttribute('data-local-hour', targetHour);
            card.setAttribute('data-local-day', brtDay + dayShift);

            // REALOCAÇÃO DO DOM (OPÇÃO C)
            if (dayShift !== 0) {
                // Descobre onde o card está e move-o
                const parentTimeline = card.closest('.timeline');
                if (parentTimeline) {
                    const dayContainer = parentTimeline.closest('.day-events'); // No day-view
                    if (dayContainer) {
                        let newDayNum = brtDay + dayShift;
                        if (newDayNum > 7) newDayNum = 1;
                        if (newDayNum < 1) newDayNum = 7;
                        
                        const targetContainer = document.getElementById(`day-${newDayNum}`);
                        if (targetContainer) {
                            const targetTimeline = targetContainer.querySelector('.timeline');
                            if (targetTimeline) {
                                // Adiciona a class pra animar ou apenas appendChild (que remove do original)
                                targetTimeline.appendChild(card);
                                
                                // Limpa "Empty states" do alvo, caso existisse
                                const emptyMsg = targetTimeline.querySelector('.empty-day-card');
                                if(emptyMsg) emptyMsg.style.display = 'none';
                            }
                        }
                    }
                }
            }
        });

        // Após mover, ordenar as timelines por hora local
        document.querySelectorAll('.timeline').forEach(timeline => {
            const children = Array.from(timeline.querySelectorAll('.tz-event-card'));
            if(children.length > 0) {
                children.sort((a, b) => {
                    // Ordena por dia local primeiro (útil pro language-view) e depois por hora
                    const dA = parseInt(a.getAttribute('data-local-day') || a.getAttribute('data-brt-day'));
                    const dB = parseInt(b.getAttribute('data-local-day') || b.getAttribute('data-brt-day'));
                    const hA = parseInt(a.getAttribute('data-local-hour'));
                    const hB = parseInt(b.getAttribute('data-local-hour'));
                    
                    if (dA !== dB) return dA - dB;
                    return hA - hB;
                });
                children.forEach(c => timeline.appendChild(c));
            }
        });

    }

    function initTimezone() {
        // Toggle Dropdown
        if (tzToggleBtn && tzDropdownMenu) {
            tzToggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                tzDropdownMenu.classList.toggle('show');
            });
            document.addEventListener('click', function(e) {
                if (!tzDropdownMenu.contains(e.target) && !tzToggleBtn.contains(e.target)) {
                    tzDropdownMenu.classList.remove('show');
                }
            });
        }

        buildDropdown();

        // Banner inteligente e auto-detect
        try {
            const detectedTZ = Intl.DateTimeFormat().resolvedOptions().timeZone;
            
            if (!userSelectedTZ) {
                // Primeira visita
                if (detectedTZ && detectedTZ !== BRT_TZ && tzBannerRow) {
                    // Mostra o aviso para adaptar
                    tzBannerRow.innerHTML = `
                        🕐 ${t.banner_text.replace('{tz_name}', detectedTZ)}
                        <button class="action-link" id="tz-accept-btn">${t.banner_cta}</button>
                        <button class="action-link" id="tz-dismiss-btn" style="color: #ccc; text-decoration: none;">${t.banner_dismiss}</button>
                    `;
                    tzBannerRow.style.display = 'flex';
                    smartBanner.style.display = 'flex';
                    
                    document.getElementById('tz-accept-btn').addEventListener('click', () => {
                        applyTimezone(detectedTZ);
                        setCookie("user_tz", detectedTZ, 30);
                        closeSmartBannerTz();
                    });
                    
                    document.getElementById('tz-dismiss-btn').addEventListener('click', () => {
                        applyTimezone(BRT_TZ);
                        setCookie("user_tz", BRT_TZ, 30);
                        closeSmartBannerTz();
                    });
                    
                    // Aplica BRT enquanto o usuário não confirma
                    applyTimezone(BRT_TZ);
                } else {
                    // Já é do BRT ou não detectou, assume BRT silenciosamente
                    applyTimezone(BRT_TZ);
                    setCookie("user_tz", BRT_TZ, 30);
                }
            } else {
                // Já escolheu
                applyTimezone(userSelectedTZ);
                
                // Se ele estiver acessando de outro lugar e o cookie for diferente, 
                // talvez mostrar sugestão pra mudar? Mas pra não irritar, melhor respeitar o cookie.
            }
        } catch (e) {
            console.error("Timezone detect error:", e);
            if(userSelectedTZ) applyTimezone(userSelectedTZ);
            else applyTimezone(BRT_TZ);
        }
    }

    function closeSmartBannerTz() {
        if(tzBannerRow) tzBannerRow.style.display = 'none';
        const langRow = document.querySelector('.lang-banner-row');
        if (!langRow || langRow.style.display === 'none') {
            if(smartBanner) smartBanner.style.display = 'none';
        }
        if (typeof syncHeaderHeight === 'function') syncHeaderHeight();
    }

    let clockInterval;
    let lastTimeStr = "";

    function startLiveClock(tz) {
        if (clockInterval) clearInterval(clockInterval);
        lastTimeStr = ""; // Força atualização no primeiro tick

        function updateClock() {
            if (!tzCurrentLabel) return;
            const now = new Date();
            const locale = currentLang === 'en' ? 'en-US' : 'pt-BR';
            const opts = { timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: currentLang === 'en' };
            
            let timeStr = now.toLocaleString(locale, opts);
            
            if (timeStr !== lastTimeStr) {
                lastTimeStr = timeStr;
                const parts = timeStr.split(':');
                if (parts.length >= 2) {
                    const first = parts[0];
                    const rest = parts.slice(1).join(':');
                    tzCurrentLabel.innerHTML = `${first}<span class="blink-colon">:</span>${rest}`;
                } else {
                    tzCurrentLabel.textContent = timeStr;
                }
            }
        }
        
        updateClock();
        clockInterval = setInterval(updateClock, 1000);
    }

    initTimezone();
});
