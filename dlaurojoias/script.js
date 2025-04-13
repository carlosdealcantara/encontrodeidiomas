document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu');
    const menu = document.querySelector('.menu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            menu.classList.toggle('active');
            this.classList.toggle('active');
        });
    }

    // Smooth Scrolling for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                // Close mobile menu if open
                if (menu.classList.contains('active')) {
                    menu.classList.remove('active');
                    mobileMenuBtn.classList.remove('active');
                }
                
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Product Information Request Form
    const productButtons = document.querySelectorAll('.btn-secondary');
    
    productButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productInfo = this.closest('.product-card');
            const productName = productInfo.querySelector('h3').textContent;
            const contactForm = document.getElementById('contactForm');
            
            // Scroll to contact form
            if (contactForm) {
                document.getElementById('subject').value = 'info';
                document.getElementById('message').value = `Olá, gostaria de obter mais informações sobre o produto "${productName}".`;
                
                window.scrollTo({
                    top: document.getElementById('contato').offsetTop - 80,
                    behavior: 'smooth'
                });
                
                // Focus the first input field
                contactForm.querySelector('input').focus();
            }
        });
    });

    // Contact Form Submission
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                subject: document.getElementById('subject').value,
                message: document.getElementById('message').value
            };
            
            // In a real implementation, you would send this data to a server
            // For demonstration, we'll just log it and show an alert
            console.log('Form Submission:', formData);
            
            // Show success message
            alert('Mensagem enviada com sucesso! Entraremos em contato em breve.');
            
            // Reset form
            this.reset();
        });
    }

    // Newsletter Form
    const newsletterForm = document.querySelector('.newsletter-form');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input').value;
            
            // In a real implementation, you would send this to a server
            console.log('Newsletter Subscription:', email);
            
            // Show success message
            alert('Obrigado por se inscrever em nossa newsletter!');
            
            // Reset form
            this.reset();
        });
    }

    // Animation on Scroll
    const animateOnScroll = function() {
        const sections = document.querySelectorAll('section');
        
        sections.forEach(section => {
            const sectionTop = section.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (sectionTop < windowHeight * 0.8) {
                section.classList.add('visible');
            }
        });
    };
    
    // Add initial visible class
    animateOnScroll();
    
    // Listen for scroll events
    window.addEventListener('scroll', animateOnScroll);

    // Lazy Load Images
    if ('loading' in HTMLImageElement.prototype) {
        // Browser supports 'loading' attribute
        document.querySelectorAll('img').forEach(img => {
            img.setAttribute('loading', 'lazy');
        });
    } else {
        // Fallback for browsers that don't support lazy loading
        const lazyImages = document.querySelectorAll('img');
        
        const lazyLoad = function() {
            lazyImages.forEach(img => {
                if (img.getBoundingClientRect().top < window.innerHeight + 300) {
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                }
            });
        };
        
        // Initial load
        lazyLoad();
        
        // Listen for scroll events
        window.addEventListener('scroll', lazyLoad);
        window.addEventListener('resize', lazyLoad);
    }

    // Product Category Hover Effect
    const categoryItems = document.querySelectorAll('.category-item');
    
    categoryItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.classList.add('hover');
        });
        
        item.addEventListener('mouseleave', function() {
            this.classList.remove('hover');
        });
    });
}); 