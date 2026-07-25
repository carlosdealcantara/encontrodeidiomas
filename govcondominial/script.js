// script.js
document.addEventListener('DOMContentLoaded', () => {
    // 1. Efeito Glassmorphism na Navbar ao Rolar
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // 2. Animação de Surgimento (Scroll Reveal)
    const revealElements = document.querySelectorAll('.reveal');
    
    const revealOptions = {
        threshold: 0.15, // Aciona quando 15% do elemento estiver visível
        rootMargin: "0px 0px -50px 0px"
    };

    const revealOnScroll = new IntersectionObserver(function(entries, observer) {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add('active');
            
            // Se o elemento for um contador, dispara a função de contar
            if (entry.target.classList.contains('number-item')) {
                const counter = entry.target.querySelector('.counter');
                if (counter && !counter.classList.contains('counted')) {
                    animateCounter(counter);
                    counter.classList.add('counted'); // Para não contar de novo
                }
            }
            
            observer.unobserve(entry.target); // Para a animação rodar apenas uma vez
        });
    }, revealOptions);

    revealElements.forEach(el => {
        revealOnScroll.observe(el);
    });

    // 3. Lógica do Contador Animado
    function animateCounter(counterElement) {
        const target = +counterElement.getAttribute('data-target');
        const duration = 2000; // 2 segundos
        const increment = target / (duration / 16); // 60 FPS
        
        let current = 0;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counterElement.innerText = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                counterElement.innerText = target;
                if(target > 0) {
                    counterElement.innerText = "+" + target; // Adiciona o sinal de + no fim
                }
            }
        };
        updateCounter();
    }
});
