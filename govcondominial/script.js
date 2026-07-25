// script.js
document.addEventListener('DOMContentLoaded', () => {
    // Efeito de sombra na navbar ao rolar a página
    const navbar = document.querySelector('.navbar');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        } else {
            navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        }
    });

    // Rolagem suave já é tratada pelo CSS (scroll-behavior: smooth), 
    // mas aqui você pode adicionar futuras lógicas do protótipo
    console.log("Protótipo GovCondominial inicializado!");
});
