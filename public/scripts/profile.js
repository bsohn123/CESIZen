(() => {
    const editBtn = document.getElementById('open-edit-panel');
    const passwordBtn = document.getElementById('open-password-panel');
    const editPanel = document.getElementById('edit-panel');
    const passwordPanel = document.getElementById('password-panel');
    const closeButtons = document.querySelectorAll('[data-close-panel]');
    const flashItems = document.querySelectorAll('.flash');
    const forms = document.querySelectorAll('form');

    const openPanel = (panelToOpen) => {
        if (!editPanel || !passwordPanel) {
            return;
        }

        editPanel.hidden = panelToOpen !== 'edit';
        passwordPanel.hidden = panelToOpen !== 'password';

        const target = panelToOpen === 'edit' ? editPanel : passwordPanel;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (editBtn) {
        editBtn.addEventListener('click', () => openPanel('edit'));
    }

    if (passwordBtn) {
        passwordBtn.addEventListener('click', () => openPanel('password'));
    }

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const panelId = button.getAttribute('data-close-panel');
            const panel = panelId ? document.getElementById(panelId) : null;
            if (panel) {
                panel.hidden = true;
            }
        });
    });

    flashItems.forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('is-leaving');
            window.setTimeout(() => {
                flash.remove();
            }, 220);
        }, 4500);
    });

    forms.forEach((form) => {
        form.addEventListener('submit', () => {
            document.body.classList.add('is-loading');

            const submitButton =
                (document.activeElement && form.contains(document.activeElement) && (document.activeElement.type === 'submit' || document.activeElement.tagName === 'BUTTON'))
                    ? document.activeElement
                    : form.querySelector('button[type="submit"], input[type="submit"]');

            if (submitButton) {
                submitButton.classList.add('is-loading');
                submitButton.setAttribute('aria-busy', 'true');
            }

            form.querySelectorAll('button, input, select, textarea').forEach((field) => {
                if (field !== submitButton) {
                    field.setAttribute('aria-disabled', 'true');
                }
            });
        });
    });
})();
