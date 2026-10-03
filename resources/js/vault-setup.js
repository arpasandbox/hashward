import {
    DEFAULT_KDF_ITERATIONS,
    generateSalt,
    deriveKey,
    makeVaultCheck,
} from './crypto';
import { buildApiHeaders, buildFormBody } from './http';

function showError(message) {
    const el = document.getElementById('setup-error');
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
    el.classList.remove('hidden');
}

function setSubmitting(submitting) {
    const button = document.querySelector('#master-setup-form button[type="submit"]');
    if (!button) return;
    button.disabled = submitting;
    button.textContent = submitting
        ? 'Securing vault… (this may take a few seconds)'
        : 'Save and open vault';
}

async function submitSetup(event) {
    event.preventDefault();
    const root = document.querySelector('[data-setup-root]');
    const master = document.getElementById('master')?.value ?? '';
    const confirm = document.getElementById('master_confirm')?.value ?? '';

    const errEl = document.getElementById('setup-error');
    if (errEl) {
        errEl.hidden = true;
        errEl.classList.add('hidden');
    }

    if (master.length < 12) {
        showError('Master password must be at least 12 characters.');
        return;
    }
    if (master !== confirm) {
        showError('Passwords do not match.');
        return;
    }

    setSubmitting(true);

    try {
        const iterations = Number(root?.dataset.kdfIterations || DEFAULT_KDF_ITERATIONS);
        const salt = generateSalt();
        const key = await deriveKey(master, salt, iterations);
        const vaultCheck = await makeVaultCheck(key);

        const response = await fetch(root?.dataset.setupUrl ?? '/api/crypto/setup', {
            method: 'POST',
            credentials: 'same-origin',
            headers: buildApiHeaders(),
            body: buildFormBody({
                kdf_salt: salt,
                kdf_iterations: iterations,
                vault_check: vaultCheck,
            }),
        });

        if (!response.ok) {
            let detail = '';
            try {
                const body = await response.json();
                detail = body.message || Object.values(body.errors || {}).flat().join(' ');
            } catch {
                // ignore parse errors
            }
            showError(detail || 'Could not save vault configuration. Try again.');
            return;
        }

        window.location.href = '/vault';
    } catch {
        showError('Something went wrong while securing your vault. Refresh and try again.');
    } finally {
        setSubmitting(false);
    }
}

export function initVaultSetup() {
    if (!window.crypto?.subtle) {
        showError(
            'Web Crypto is unavailable. Use http://127.0.0.1:8000 or http://localhost:8000 in the address bar.'
        );
        return;
    }

    document.getElementById('master-setup-form')?.addEventListener('submit', submitSetup);
}
