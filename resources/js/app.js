import './bootstrap';
import { initPasswordToggles } from './password-toggle.js';

/** Prevent native POST to GET-only vault routes when chunks load slowly or fail. */
function guardClientOnlyForms() {
    const blockNativeSubmit = (event) => {
        event.preventDefault();
    };
    document.getElementById('unlock-form')?.addEventListener('submit', blockNativeSubmit, { capture: true });
    document.getElementById('master-setup-form')?.addEventListener('submit', blockNativeSubmit, { capture: true });
}

function showBootError(message) {
    const targets = [
        document.getElementById('vault-boot-error'),
        document.getElementById('unlock-error'),
        document.getElementById('setup-error'),
    ];
    targets.forEach((el) => {
        if (!el) return;
        el.textContent = message;
        el.hidden = false;
        el.classList.remove('hidden');
    });
}

async function bootPageModules() {
    if (document.getElementById('vault-app')) {
        const { initVaultApp } = await import('./vault.js');
        initVaultApp();
    }

    if (document.querySelector('[data-setup-root]')) {
        const { initVaultSetup } = await import('./vault-setup.js');
        initVaultSetup();
    }

    if (document.querySelector('[data-invites-page]')) {
        const { initInvitesPage } = await import('./invites.js');
        initInvitesPage();
    }
}

function start() {
    guardClientOnlyForms();
    initPasswordToggles();
    bootPageModules().catch((error) => {
        console.error(error);
        showBootError('Vault scripts failed to load. Hard refresh, or run: npm run build');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}

window.addEventListener('error', (event) => {
    if (!document.getElementById('vault-app')) return;
    // Ignore failed asset loads; vault UI handles API errors in vault.js.
    if (event.target && event.target !== window) return;
    showBootError(event.message || 'A script error blocked the vault UI.');
});
