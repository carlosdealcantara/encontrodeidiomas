// JavaScript for online.php
document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar and events
    initCalendar();
    updateJoinButtons();
    
    // Set up language buttons in dropdown
    setupLanguageButtons();
    
    // Set up hero button and scroll down functionality
    setupHeroButtons();
});

// Initialize calendar functionality
function initCalendar() {
    // Read URL parameters
    readURLParameters();
    
    // Get current day and time
    const { day, hour } = getCurrentDayAndTime();
    
    // Check for current events on the active day
    const activeDay = document.querySelector('.day-button.active');
    if (activeDay) {
        const dayNum = parseInt(activeDay.getAttribute('data-day'));
        checkCurrentEvents(dayNum);
    }
}

// Read URL parameters and update UI accordingly
function readURLParameters() {
    const params = new URLSearchParams(window.location.search);
    const view = params.get('view');
    const dia = params.get('dia');
    const idioma = params.get('idioma');
    
    if (view) {
        // Update view buttons
        document.querySelectorAll('.view-button').forEach(button => {
            button.classList.toggle('active', button.getAttribute('data-view') === view);
        });
        
        // Update view content
        document.querySelectorAll('.view-content').forEach(content => {
            content.classList.toggle('active', content.id === `${view}-view`);
        });
        
        // If day view is active, check for day parameter
        if (view === 'day' && dia) {
            showDayEvents(parseInt(dia), false);
            checkCurrentEvents(parseInt(dia));
        }
        
        // If language view is active, check for language parameter
        if (view === 'language' && idioma) {
            showLanguageEvents(idioma, false);
        }
    }
}

// Toggle between day and language views
function toggleView(view) {
    // Track view toggle with analytics
    trackViewToggleEvent(view);
    
    // Update view buttons
    document.querySelectorAll('.view-button').forEach(button => {
        button.classList.toggle('active', button.getAttribute('data-view') === view);
    });
    
    // Update view content
    document.querySelectorAll('.view-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${view}-view`).classList.add('active');
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    
    // Handle view-specific actions
    if (view === 'language') {
        // Find active language button or default to English
        const activeLanguageButton = document.querySelector('.dropdown-content .language-button.active');
        if (activeLanguageButton) {
            const language = activeLanguageButton.getAttribute('data-language');
            showLanguageEvents(language, false);
            url.searchParams.set('idioma', language);
        } else {
            showLanguageEvents('inglês', false);
            url.searchParams.set('idioma', 'inglês');
        }
        
        // Remove day parameter if exists
        if (url.searchParams.has('dia')) {
            url.searchParams.delete('dia');
        }
    } else if (view === 'day') {
        // Show all events in day view (no language filter)
        document.querySelectorAll('.timeline-event').forEach(event => {
            event.style.display = '';
        });
        
        // Remove any "no events" messages
        document.querySelectorAll('.no-events').forEach(noEvents => {
            noEvents.style.display = 'none';
        });
        
        // Check if a day is already selected
        const activeButton = document.querySelector('.day-button.active');
        let dayToShow;
        
        if (activeButton) {
            dayToShow = parseInt(activeButton.getAttribute('data-day'));
        } else {
            // Use current day or Friday if weekend
            const { day: currentDay } = getCurrentDayAndTime();
            dayToShow = (currentDay === 0 || currentDay === 6) ? 5 : currentDay;
        }
        
        // Show events for the selected day
        showDayEvents(dayToShow, false);
        url.searchParams.set('dia', dayToShow);
        
        // Remove language parameter if exists
        if (url.searchParams.has('idioma')) {
            url.searchParams.delete('idioma');
        }
        
        // Check for current events
        checkCurrentEvents(dayToShow);
    }
    
    // Update URL
    history.pushState({}, '', url);
    
    // Scroll to keep filters visible
    scrollToCalendarNav();
}

// Show events for a specific day
function showDayEvents(day, updateUrl = true) {
    // Hide all day events
    document.querySelectorAll('.day-events').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected day's events
    const dayEvents = document.getElementById(`day-${day}`);
    if (dayEvents) {
        dayEvents.classList.add('active');
    }
    
    // Update day buttons
    document.querySelectorAll('.day-button').forEach(button => {
        button.classList.toggle('active', parseInt(button.getAttribute('data-day')) === day);
    });
    
    // Update URL if needed
    if (updateUrl) {
        const url = new URL(window.location);
        url.searchParams.set('view', 'day');
        url.searchParams.set('dia', day);
        
        // Remove language parameter if exists
        if (url.searchParams.has('idioma')) {
            url.searchParams.delete('idioma');
        }
        
        history.pushState({}, '', url);
    }
    
    // Check for current events
    checkCurrentEvents(day);
    
    // Update join buttons status
    updateJoinButtons();
    
    // Scroll to keep filters visible
    scrollToCalendarNav();
}

// Show events for a specific language
function showLanguageEvents(language, updateUrl = true) {
    const languageId = document.querySelector(`.language-button[data-language="${language}"]`)?.getAttribute('data-language-id');
    
    if (languageId) {
        // Load events via AJAX
        loadLanguageEvents(languageId);
    } else if (language === 'seu') {
        // Special case for "Seu idioma aqui"
        window.location.href = 'contato.php?assunto=novo_idioma';
        return;
    }
    
    // Update language buttons
    document.querySelectorAll('.dropdown-content .language-button').forEach(button => {
        button.classList.toggle('active', button.getAttribute('data-language') === language);
    });
    
    // Update dropdown button text and flag
    const selectedLanguage = document.getElementById('selected-language');
    const selectedFlag = document.getElementById('selected-language-flag');
    
    if (selectedLanguage) {
        const langButton = document.querySelector(`.language-button[data-language="${language}"]`);
        if (langButton) {
            const langText = langButton.querySelector('.language-info span:not(.flag-icon)');
            if (langText) {
                selectedLanguage.textContent = langText.textContent;
            }
        }
    }
    
    if (selectedFlag) {
        const langButton = document.querySelector(`.language-button[data-language="${language}"]`);
        if (langButton) {
            const flagElement = langButton.querySelector('.flag-icon');
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
    }
    
    // Update URL if needed
    if (updateUrl) {
        const url = new URL(window.location);
        url.searchParams.set('view', 'language');
        url.searchParams.set('idioma', language);
        
        // Remove day parameter if exists
        if (url.searchParams.has('dia')) {
            url.searchParams.delete('dia');
        }
        
        history.pushState({}, '', url);
    }
    
    // Close dropdown
    const dropdownContent = document.querySelector('.dropdown-content');
    if (dropdownContent) {
        dropdownContent.classList.remove('show');
    }
}

// Load events for a specific language via AJAX
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

// Display language-specific events
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
        const descriptionElement = document.createElement('p');
        descriptionElement.className = 'event-description';
        descriptionElement.textContent = event.description;
        eventElement.appendChild(descriptionElement);
        
        // Add action buttons
        const actionsElement = document.createElement('div');
        actionsElement.className = 'event-actions';
        
        if (event.meet_link) {
            const joinButton = document.createElement('a');
            joinButton.href = event.meet_link;
            joinButton.target = '_blank';
            joinButton.className = 'event-button join-button';
            joinButton.textContent = 'Participar';
            joinButton.setAttribute('data-day', event.day_of_week);
            joinButton.setAttribute('data-time', event.time_hour);
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
        eventsContainer.appendChild(eventElement);
    });
    
    // Update join buttons status
    updateJoinButtons();
}

// Set up language buttons in dropdown
function setupLanguageButtons() {
    const dropdownButton = document.querySelector('.dropdown-button');
    const dropdownContent = document.querySelector('.dropdown-content');
    const searchInput = document.getElementById('language-search');
    const noResults = document.getElementById('no-results');
    
    // Toggle dropdown
    if (dropdownButton) {
        dropdownButton.addEventListener('click', function() {
            if (dropdownContent) {
                dropdownContent.classList.toggle('show');
                
                if (dropdownContent.classList.contains('show') && searchInput) {
                    // Focus on search input when dropdown opens
                    setTimeout(() => searchInput.focus(), 100);
                }
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
                const langText = button.querySelector('.language-info span:not(.flag-icon)').textContent.toLowerCase();
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
            
            // Special case for "Seu idioma aqui!"
            if (language === 'seu') {
                window.location.href = 'contato.php?assunto=novo_idioma';
                return;
            }
            
            // Reset search input
            if (searchInput) {
                searchInput.value = '';
                document.querySelectorAll('.dropdown-content .language-button').forEach(btn => {
                    btn.style.display = 'flex';
                });
                if (noResults) noResults.style.display = 'none';
            }
            
            // Show language events
            showLanguageEvents(language);
        });
    });
}

// Set up hero button and scroll down functionality
function setupHeroButtons() {
    const heroButton = document.querySelector('.hero-button');
    if (heroButton) {
        heroButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // If no URL parameters, set defaults
            if (!window.location.search) {
                const url = new URL(window.location);
                url.searchParams.set('view', 'day');
                
                // Get current day or use Friday if weekend
                const { day } = getCurrentDayAndTime();
                const dayToShow = (day === 0 || day === 6) ? 5 : day;
                
                url.searchParams.set('dia', dayToShow);
                history.pushState({}, '', url);
                
                // Show events for the selected day
                showDayEvents(dayToShow, false);
                
                // Update view buttons
                document.querySelectorAll('.view-button').forEach(button => {
                    button.classList.toggle('active', button.getAttribute('data-view') === 'day');
                });
                
                document.querySelectorAll('.view-content').forEach(content => {
                    content.classList.toggle('active', content.id === 'day-view');
                });
            }
            
            // Scroll to calendar section
            scrollToCalendarSection();
        });
    }

    // Scroll down button
    const scrollDown = document.querySelector('.scroll-down');
    if (scrollDown) {
        scrollDown.addEventListener('click', function(e) {
            e.preventDefault();
            
            // If no URL parameters, set defaults
            if (!window.location.search) {
                const url = new URL(window.location);
                url.searchParams.set('view', 'language');
                url.searchParams.set('idioma', 'inglês');
                history.pushState({}, '', url);
            }
            
            // Scroll to calendar section
            scrollToCalendarSection();
        });
    }
}

// Get current day and time
function getCurrentDayAndTime() {
    const now = new Date();
    const day = now.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    const hour = now.getHours();
    return { day, hour };
}

// Update join buttons based on current time
function updateJoinButtons() {
    const { day, hour } = getCurrentDayAndTime();
    
    document.querySelectorAll('.join-button').forEach(button => {
        const buttonDay = parseInt(button.getAttribute('data-day'));
        const buttonTime = parseInt(button.getAttribute('data-time'));
        
        // Remove any existing badges
        const parent = button.closest('.event-title');
        if (parent) {
            const existingBadge = parent.querySelector('.now-badge');
            if (existingBadge) {
                existingBadge.remove();
            }
        }
        
        // If today is the event day
        if (day === buttonDay) {
            // Check if event is happening now
            if (hour === buttonTime) {
                button.classList.remove('disabled');
                
                // Add "AGORA" badge
                if (parent) {
                    const badge = document.createElement('span');
                    badge.className = 'now-badge';
                    badge.textContent = 'AGORA';
                    parent.appendChild(badge);
                }
            }
            // If event has already happened today
            else if (hour > buttonTime) {
                button.classList.add('disabled');
            }
            // If event will happen later today
            else {
                button.classList.remove('disabled');
            }
        }
        // If event is on a future day
        else if ((day < buttonDay) || (day === 0 && buttonDay <= 5) || (day === 6 && buttonDay <= 5)) {
            button.classList.remove('disabled');
        }
        // If event was on a past day
        else {
            button.classList.add('disabled');
        }
    });
}

// Check for events happening now on the given day
function checkCurrentEvents(day) {
    const { day: currentDay, hour } = getCurrentDayAndTime();
    
    // Only check if the day being viewed is today
    if (currentDay !== day) return;
    
    const dayEvents = document.getElementById(`day-${day}`);
    if (!dayEvents) return;
    
    // Get all events for this day
    const events = dayEvents.querySelectorAll('.timeline-event');
    
    events.forEach(event => {
        const eventTime = parseInt(event.getAttribute('data-time'));
        const eventTitle = event.querySelector('.event-title');
        
        // Remove any existing now badges
        const existingBadge = eventTitle?.querySelector('.now-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // If event is happening now
        if (hour === eventTime) {
            // Add "AGORA" badge
            if (eventTitle) {
                const badge = document.createElement('span');
                badge.className = 'now-badge';
                badge.textContent = 'AGORA';
                eventTitle.appendChild(badge);
            }
        }
    });
}

// Scroll to calendar section
function scrollToCalendarSection() {
    setTimeout(() => {
        const calendarSection = document.querySelector('.calendar-section');
        if (calendarSection) {
            const headerHeight = document.querySelector('.header').offsetHeight;
            const y = calendarSection.getBoundingClientRect().top + window.pageYOffset - headerHeight;
            window.scrollTo({top: y, behavior: 'smooth'});
        }
    }, 300);
}

// Scroll to keep filters visible
function scrollToCalendarNav() {
    setTimeout(() => {
        const headerHeight = document.querySelector('.header').offsetHeight;
        const calendarNav = document.querySelector('.calendar-nav');
        if (calendarNav) {
            const y = calendarNav.getBoundingClientRect().top + window.pageYOffset - headerHeight - 10;
            window.scrollTo({top: y, behavior: 'smooth'});
        }
    }, 150);
}

// Analytics tracking functions
function trackViewToggleEvent(view) {
    // This would typically use gtag or another analytics service
    console.log(`View toggled to: ${view}`);
}

function trackJoinButtonClick(language, day, time) {
    // This would typically use gtag or another analytics service
    console.log(`Join button clicked: ${language} on day ${day} at ${time}h`);
}

function trackReplayButtonClick(language) {
    // This would typically use gtag or another analytics service
    console.log(`Replay button clicked: ${language}`);
} 