// JavaScript for online.php
document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let dropdownButton = document.querySelector('.dropdown-button');
    let dropdownContent = document.querySelector('.dropdown-content');
    let searchInput = document.getElementById('language-search');
    let noResults = document.getElementById('no-results');
    let dayButtons = document.querySelectorAll('.day-button');
    
    // Set up day buttons click events
    dayButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all day buttons
            dayButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get day number from data attribute
            const day = this.getAttribute('data-day');
            
            // Hide all day event sections
            document.querySelectorAll('.day-events').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show the selected day's events
            document.getElementById('day-' + day).classList.add('active');
            
            // Update join buttons for the selected day
            updateJoinButtons();
        });
    });

    // Toggle dropdown
    if (dropdownButton) {
        dropdownButton.addEventListener('click', function() {
            if (dropdownContent) {
                dropdownContent.classList.toggle('show');
            }
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.mobile-dropdown')) {
            if (dropdownContent && dropdownContent.classList.contains('show')) {
                dropdownContent.classList.remove('show');
            }
        }
    });

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            let visibleCount = 0;
            
            document.querySelectorAll('.dropdown-content .language-button').forEach(button => {
                const langText = button.querySelector('span:not(.language-badge)').textContent.toLowerCase();
                if (langText.includes(searchTerm)) {
                    button.style.display = 'flex';
                    visibleCount++;
                } else {
                    button.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    }

    // Language button click events
    const languageButtons = document.querySelectorAll('.dropdown-content .language-button');
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const language = this.getAttribute('data-language');
            const languageId = this.getAttribute('data-language-id');
            
            if (language === 'seu') {
                // Special case for "Seu idioma aqui!"
                window.location.href = 'contato.php?assunto=novo_idioma';
                return;
            }
            
            // Update dropdown button text and flag
            const selectedLanguage = document.getElementById('selected-language');
            const selectedFlag = document.getElementById('selected-language-flag');
            
            if (selectedLanguage) {
                const langInfo = this.querySelector('.language-info span:not(.flag-icon)');
                selectedLanguage.textContent = langInfo ? langInfo.textContent : language;
            }
            
            if (selectedFlag) {
                const flagElement = this.querySelector('.flag-icon');
                if (flagElement) {
                    if (flagElement.tagName === 'IMG') {
                        selectedFlag.src = flagElement.src;
                        selectedFlag.style.display = 'inline-block';
                        selectedFlag.removeAttribute('style');
                    } else {
                        // For emoji flags
                        selectedFlag.src = '';
                        selectedFlag.style.fontSize = '1.2rem';
                        selectedFlag.style.width = '24px';
                        selectedFlag.style.height = '24px';
                        selectedFlag.style.display = 'inline-block';
                        selectedFlag.style.textAlign = 'center';
                        selectedFlag.style.boxShadow = 'none';
                        selectedFlag.innerHTML = flagElement.innerHTML;
                    }
                }
            }
            
            // Close dropdown
            if (dropdownContent) {
                dropdownContent.classList.remove('show');
            }
            
            // Load language-specific events via AJAX
            if (languageId) {
                loadLanguageEvents(languageId);
            }
            
            // Update URL with language parameter
            const url = new URL(window.location);
            url.searchParams.set('lang', language);
            window.history.pushState({}, '', url);
        });
    });
    
    // Function to load language-specific events
    function loadLanguageEvents(languageId) {
        // Show loading state
        const eventsContainer = document.querySelector('#language-events .timeline');
        if (eventsContainer) {
            eventsContainer.innerHTML = '<div class="loading">Carregando eventos...</div>';
        }
        
        // Create an AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'ajax/get_language_events.php?language_id=' + languageId, true);
        
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    displayLanguageEvents(response.events, response.language);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    eventsContainer.innerHTML = '<div class="no-events">Erro ao carregar eventos.</div>';
                }
            } else {
                eventsContainer.innerHTML = '<div class="no-events">Erro ao carregar eventos.</div>';
            }
        };
        
        xhr.onerror = function() {
            eventsContainer.innerHTML = '<div class="no-events">Erro ao carregar eventos.</div>';
        };
        
        xhr.send();
    }
    
    // Function to display language-specific events
    function displayLanguageEvents(events, language) {
        const eventsContainer = document.querySelector('#language-events .timeline');
        if (!eventsContainer) return;
        
        eventsContainer.innerHTML = '';
        
        if (events.length === 0) {
            eventsContainer.innerHTML = `<div class="no-events">Nenhum encontro de ${language} encontrado na programação atual.</div>`;
            return;
        }
        
        const dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        
        events.forEach(event => {
            // Create event element
            const eventElement = document.createElement('div');
            eventElement.className = 'timeline-event';
            eventElement.setAttribute('data-time', event.time_hour);
            eventElement.setAttribute('data-language', language.toLowerCase());
            
            // Add day info
            const dayInfo = document.createElement('div');
            dayInfo.className = 'day-info';
            dayInfo.innerHTML = `<span>${dayNames[event.day_of_week % 7]}</span>`;
            eventElement.appendChild(dayInfo);
            
            // Add time
            const timeElement = document.createElement('span');
            timeElement.className = 'event-time';
            timeElement.textContent = `${event.time_hour}h`;
            eventElement.appendChild(timeElement);
            
            // Add title
            const titleElement = document.createElement('div');
            titleElement.className = 'event-title';
            
            // Add flag
            if (event.flag_emoji) {
                const flagElement = document.createElement('span');
                flagElement.className = 'flag-icon';
                flagElement.style.fontSize = '1.2rem';
                flagElement.style.width = '24px';
                flagElement.style.height = '24px';
                flagElement.style.display = 'inline-block';
                flagElement.style.textAlign = 'center';
                flagElement.style.boxShadow = 'none';
                flagElement.textContent = event.flag_emoji;
                titleElement.appendChild(flagElement);
            } else if (event.flag_code) {
                const flagElement = document.createElement('img');
                flagElement.className = 'flag-icon';
                flagElement.src = `https://flagcdn.com/32x24/${event.flag_code.toLowerCase()}.png`;
                flagElement.alt = language;
                titleElement.appendChild(flagElement);
            }
            
            // Add language name
            const nameElement = document.createElement('span');
            nameElement.textContent = language;
            titleElement.appendChild(nameElement);
            
            // Add social links
            const socialLinks = document.createElement('div');
            socialLinks.className = 'event-social-links';
            
            if (event.whatsapp_group_link) {
                const whatsappLink = document.createElement('a');
                whatsappLink.href = event.whatsapp_group_link;
                whatsappLink.target = '_blank';
                whatsappLink.className = 'social-icon whatsapp-icon';
                whatsappLink.title = `Grupo de ${language}`;
                whatsappLink.innerHTML = '<i class="fab fa-whatsapp"></i>';
                socialLinks.appendChild(whatsappLink);
            }
            
            if (event.instagram_link) {
                const instagramLink = document.createElement('a');
                instagramLink.href = event.instagram_link;
                instagramLink.target = '_blank';
                instagramLink.className = 'social-icon instagram-icon';
                instagramLink.title = `Perfil de ${language}`;
                instagramLink.innerHTML = '<i class="fab fa-instagram"></i>';
                socialLinks.appendChild(instagramLink);
            }
            
            titleElement.appendChild(socialLinks);
            eventElement.appendChild(titleElement);
            
            // Add description
            const descElement = document.createElement('p');
            descElement.className = 'event-description';
            descElement.textContent = event.description;
            eventElement.appendChild(descElement);
            
            // Add action buttons
            const actionsElement = document.createElement('div');
            actionsElement.className = 'event-actions';
            
            if (event.meet_link) {
                const joinButton = document.createElement('a');
                joinButton.href = event.meet_link;
                joinButton.target = '_blank';
                joinButton.className = 'event-button join-button';
                joinButton.setAttribute('data-day', event.day_of_week);
                joinButton.setAttribute('data-time', event.time_hour);
                joinButton.textContent = 'Participar';
                actionsElement.appendChild(joinButton);
            }
            
            if (event.youtube_link) {
                const replayButton = document.createElement('a');
                replayButton.href = event.youtube_link;
                replayButton.target = '_blank';
                replayButton.className = 'event-button replay-button';
                replayButton.innerHTML = '<i class="fab fa-youtube"></i> Anteriores';
                actionsElement.appendChild(replayButton);
            }
            
            eventElement.appendChild(actionsElement);
            
            // Add event to container
            eventsContainer.appendChild(eventElement);
        });
        
        // Update join buttons state
        updateJoinButtons();
    }
    
    // Function to get current day and time
    function getCurrentDayAndTime() {
        const now = moment().tz('America/Sao_Paulo');
        return {
            day: now.day() === 0 ? 7 : now.day(), // Convert to 1-7 where Monday=1, Sunday=7
            hour: now.hour(),
            minute: now.minute()
        };
    }
    
    // Function to update join button states
    function updateJoinButtons() {
        const { day, hour } = getCurrentDayAndTime();
        
        document.querySelectorAll('.join-button').forEach(button => {
            const buttonDay = parseInt(button.getAttribute('data-day'), 10);
            const buttonTime = parseInt(button.getAttribute('data-time'), 10);
            
            // Disable buttons for past events
            if (buttonDay < day || (buttonDay === day && buttonTime < hour)) {
                button.classList.add('disabled');
                button.textContent = 'Encerrado';
            } else {
                button.classList.remove('disabled');
                button.textContent = 'Participar';
            }
            
            // Enable buttons for current events (+/- 1 hour)
            if (buttonDay === day && Math.abs(buttonTime - hour) <= 1) {
                button.classList.remove('disabled');
                
                // If it's the exact hour, add the "AGORA" badge
                if (buttonDay === day && buttonTime === hour) {
                    // Check if badge already exists
                    if (!button.querySelector('.now-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'now-badge';
                        badge.textContent = 'AGORA';
                        button.appendChild(badge);
                    }
                }
            }
        });
    }
    
    // Function to check if any current events and add AGORA badge
    function checkCurrentEvents(day) {
        const { hour } = getCurrentDayAndTime();
        
        document.querySelectorAll(`.day-events[id="day-${day}"] .timeline-event`).forEach(event => {
            const eventTime = parseInt(event.getAttribute('data-time'), 10);
            
            if (eventTime === hour) {
                const title = event.querySelector('.event-title');
                
                // Check if badge already exists
                if (title && !title.querySelector('.now-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'now-badge';
                    badge.textContent = 'AGORA';
                    title.appendChild(badge);
                }
                
                // Highlight the event
                event.style.border = '2px solid var(--highlight-border)';
                event.style.backgroundColor = 'var(--highlight-bg)';
            }
        });
    }
    
    // Initialize with the current day
    const { day } = getCurrentDayAndTime();
    checkCurrentEvents(day);
    
    // Set up timer to update join buttons every minute
    setInterval(function() {
        updateJoinButtons();
        const { day } = getCurrentDayAndTime();
        checkCurrentEvents(day);
    }, 60000);
    
    // Check URL for language parameter
    const urlParams = new URLSearchParams(window.location.search);
    const langParam = urlParams.get('lang');
    
    if (langParam) {
        // Find the corresponding language button
        const langButton = Array.from(languageButtons).find(button => {
            return button.getAttribute('data-language').toLowerCase() === langParam.toLowerCase();
        });
        
        // Trigger click if found
        if (langButton) {
            langButton.click();
        }
    }
    
    // Update join buttons on load
    updateJoinButtons();
}); 