(() => {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('password-toggle');

    if (!passwordInput || !toggleButton) {
        return;
    }

    toggleButton.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'text';
        passwordInput.type = isVisible ? 'password' : 'text';
        toggleButton.dataset.visible = isVisible ? 'false' : 'true';
        toggleButton.setAttribute(
            'aria-label',
            isVisible ? 'Afficher le mot de passe' : 'Masquer le mot de passe'
        );
    });
})();
