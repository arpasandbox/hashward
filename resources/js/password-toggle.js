export function setPasswordToggleRevealed(button, revealed) {
    if (!button) {
        return;
    }

    button.querySelector('.sb-password-toggle-icon--show')?.classList.toggle('hidden', revealed);
    button.querySelector('.sb-password-toggle-icon--hide')?.classList.toggle('hidden', !revealed);
    button.setAttribute('aria-label', revealed ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', revealed ? 'true' : 'false');
}

export function resetPasswordToggles(root = document) {
    root.querySelectorAll('.sb-password-toggle').forEach((button) => {
        const input = button.dataset.passwordTarget
            ? document.getElementById(button.dataset.passwordTarget)
            : null;
        if (input) {
            input.type = 'password';
        }
        setPasswordToggleRevealed(button, false);
    });
}

export function initPasswordToggles(root = document) {
    root.querySelectorAll('.sb-password-toggle').forEach((button) => {
        if (button.dataset.passwordToggleBound === '1') {
            return;
        }
        button.dataset.passwordToggleBound = '1';

        button.addEventListener('click', () => {
            const targetId = button.dataset.passwordTarget;
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            setPasswordToggleRevealed(button, reveal);
        });
    });
}
