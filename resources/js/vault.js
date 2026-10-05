import {
    deriveKey,
    encryptJson,
    decryptJson,
    verifyVaultCheck,
    generateSalt,
    makeVaultCheck,
    encryptExport,
    DEFAULT_KDF_ITERATIONS,
} from './crypto';
import { setPasswordToggleRevealed } from './password-toggle.js';
import {
    buildApiHeaders,
    buildFormBody,
    formBackedFetchMethod,
    prepareMutatingApiRequest,
    syncCsrfToken,
    withJsonCsrf,
} from './http';

const AUTO_LOCK_MINUTES = 30;
const AUTO_LOCK_MS = AUTO_LOCK_MINUTES * 60 * 1000;
const CLIPBOARD_CLEAR_MS = 20 * 1000;
const SESSION_KEEPALIVE_MS = 4 * 60 * 1000;

let favoriteStarMarkup = '★';

function loadFavoriteStarMarkup() {
    const tpl = document.getElementById('tpl-icon-star');
    const node = tpl?.content?.firstElementChild;
    if (node) {
        favoriteStarMarkup = node.outerHTML;
    }
}

/** @type {CryptoKey|null} */
let vaultKey = null;
/** @type {Array<{id:number, ciphertext:string, iv:string, version:number, deleted_at?:string|null, updated_at?:string, plain?:object}>} */
let items = [];
let view = 'all';
let selectedItemId = null;
let detailPasswordRevealed = false;
let lastActivityAt = 0;
let idleWatchInterval = null;
let sessionKeepaliveTimer = null;
let saveInFlight = false;
let clipboardTimer = null;

function root() {
    return document.getElementById('vault-app');
}

/** @type {{salt:string, iterations:number, vaultCheck: unknown}|null} */
let cryptoConfig = null;
/** @type {Promise<{salt:string, iterations:number, vaultCheck: unknown}>|null} */
let cryptoConfigPromise = null;

function readEmbeddedCryptoConfig() {
    const root = document.getElementById('vault-app');
    const salt = root?.dataset.kdfSalt;
    if (!salt) {
        return null;
    }
    let vaultCheck = root.dataset.vaultCheck ?? '';
    if (vaultCheck) {
        try {
            vaultCheck = JSON.parse(vaultCheck);
        } catch {
            // keep raw string
        }
    }
    return {
        salt,
        iterations: Number(root.dataset.kdfIterations || DEFAULT_KDF_ITERATIONS),
        vaultCheck,
    };
}

function resolveAuthFailure(status, errBody) {
    const raw = formatApiErrorMessage(errBody) ?? '';
    if (status !== 401) {
        return null;
    }
    if (raw === 'Unauthenticated.' || raw === 'Unauthenticated') {
        return {
            message: 'Your sign-in session expired. Please sign in again.',
            redirect: '/login',
        };
    }
    if (raw.includes('Two-factor verification required')) {
        return {
            message: 'Complete two-factor authentication, then unlock your vault.',
            redirect: '/two-factor/challenge',
        };
    }
    if (raw.includes('Two-factor setup required')) {
        return {
            message: 'Finish two-factor setup, then unlock your vault.',
            redirect: '/two-factor/setup',
        };
    }
    return {
        message: raw || 'Sign-in session expired. Please sign in again.',
        redirect: '/login',
    };
}

async function ensureCryptoConfig() {
    if (cryptoConfig) {
        return cryptoConfig;
    }

    const embedded = readEmbeddedCryptoConfig();
    if (embedded) {
        cryptoConfig = embedded;
        return cryptoConfig;
    }

    if (cryptoConfigPromise) {
        return cryptoConfigPromise;
    }

    cryptoConfigPromise = (async () => {
    const data = await api('/api/crypto');
    let vaultCheck = data.vault_check;
    if (typeof vaultCheck === 'string') {
        try {
            vaultCheck = JSON.parse(vaultCheck);
        } catch {
            // keep string form
        }
    }

    if (data.csrf_token) {
        syncCsrfToken(data.csrf_token);
    }

    cryptoConfig = {
        salt: data.kdf_salt,
        iterations: Number(data.kdf_iterations || DEFAULT_KDF_ITERATIONS),
        vaultCheck,
    };

    return cryptoConfig;
    })();

    try {
        return await cryptoConfigPromise;
    } catch (error) {
        cryptoConfigPromise = null;
        throw error;
    }
}

let unlockProgressTimer = null;

function setUnlockBusy(busy) {
    const button = document.getElementById('unlock-submit');
    const status = document.getElementById('unlock-status');
    if (button) {
        button.disabled = busy;
        button.textContent = busy ? 'Unlocking…' : 'Unlock';
    }
    if (unlockProgressTimer) {
        clearInterval(unlockProgressTimer);
        unlockProgressTimer = null;
    }
    if (status) {
        status.classList.toggle('hidden', !busy);
        if (!busy) {
            status.textContent = '';
            return;
        }
        let seconds = 0;
        status.textContent = 'Deriving key locally… 0s (secure PBKDF2, please wait)';
        unlockProgressTimer = setInterval(() => {
            seconds += 1;
            status.textContent = `Deriving key locally… ${seconds}s (secure PBKDF2, please wait)`;
        }, 1000);
    }
}

function withTimeout(promise, ms, message) {
    return Promise.race([
        promise,
        new Promise((_, reject) => {
            setTimeout(() => reject(new Error(message)), ms);
        }),
    ]);
}

function showVaultApiError(message) {
    const el = document.getElementById('vault-api-error');
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
    el.classList.remove('hidden');
}

function clearVaultApiError() {
    const el = document.getElementById('vault-api-error');
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
    el.classList.add('hidden');
}

function showEntrySaveError(message) {
    const el = document.getElementById('entry-save-error');
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
    el.classList.remove('hidden');
}

function clearEntrySaveError() {
    const el = document.getElementById('entry-save-error');
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
    el.classList.add('hidden');
}

async function silentRefreshSession() {
    try {
        const response = await fetch('/api/crypto', {
            credentials: 'same-origin',
            headers: buildApiHeaders(),
        });
        if (!response.ok) {
            return false;
        }
        const data = await response.json();
        if (data?.csrf_token) {
            syncCsrfToken(data.csrf_token);
        }
        return true;
    } catch {
        return false;
    }
}

function stopSessionKeepalive() {
    if (sessionKeepaliveTimer) {
        clearInterval(sessionKeepaliveTimer);
        sessionKeepaliveTimer = null;
    }
}

async function pingSession() {
    if (!vaultKey) return;
    await silentRefreshSession();
}

function startSessionKeepalive() {
    stopSessionKeepalive();
    if (!vaultKey) return;
    sessionKeepaliveTimer = setInterval(pingSession, SESSION_KEEPALIVE_MS);
}

function formatApiErrorMessage(body) {
    if (!body || typeof body !== 'object') {
        return null;
    }
    if (body.errors && typeof body.errors === 'object') {
        const parts = Object.values(body.errors).flat().filter(Boolean);
        if (parts.length) {
            return parts.join(' ');
        }
    }
    return typeof body.message === 'string' ? body.message : null;
}

async function api(path, options = {}) {
    const { _retried, headers: optionHeaders, body, formFields, ...fetchOptions } = options;
    const method = (fetchOptions.method || 'GET').toUpperCase();
    const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
    let requestMethod = method;
    let requestBody = body;
    let extraHeaders = optionHeaders || {};

    if (mutating && !_retried) {
        await silentRefreshSession();
    }

    if (formFields) {
        const { fetchMethod, spoofMethod } = formBackedFetchMethod(method);
        requestMethod = fetchMethod;
        requestBody = buildFormBody(formFields, { spoofMethod });
        const { 'Content-Type': _ignored, ...rest } = extraHeaders;
        extraHeaders = rest;
    } else if (mutating) {
        if (['PUT', 'PATCH', 'DELETE'].includes(method)) {
            const prepared = prepareMutatingApiRequest(method, requestBody);
            requestMethod = prepared.method;
            requestBody = prepared.body;
        } else if (requestBody) {
            requestBody = withJsonCsrf(requestBody);
        }
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...fetchOptions,
        method: requestMethod,
        body: requestBody,
        headers: buildApiHeaders(extraHeaders),
    });

    if (response.status === 419 && !_retried) {
        const refreshed = await silentRefreshSession();
        if (refreshed) {
            return api(path, { ...options, _retried: true });
        }
    }

    if (response.status === 401 || response.status === 419) {
        let message = 'Sign-in session expired or security token mismatch. Refresh the page, sign in again, then unlock the vault. Vault auto-lock after idle is separate (30 minutes).';
        let redirect = null;
        try {
            const errBody = await response.json();
            if (response.status === 401) {
                const authFailure = resolveAuthFailure(401, errBody);
                if (authFailure) {
                    message = authFailure.message;
                    redirect = authFailure.redirect;
                }
            } else {
                message = formatApiErrorMessage(errBody) ?? message;
            }
        } catch {
            // ignore non-JSON error bodies
        }
        if (redirect) {
            window.setTimeout(() => {
                window.location.assign(redirect);
            }, 1200);
        }
        throw new Error(message);
    }
    if (!response.ok) {
        let message = `Request failed (${response.status})`;
        try {
            const errBody = await response.json();
            message = formatApiErrorMessage(errBody) ?? message;
        } catch {
            // ignore non-JSON error bodies
        }
        throw new Error(message);
    }
    if (response.status === 204) return null;
    const data = await response.json();
    if (data?.csrf_token) {
        syncCsrfToken(data.csrf_token);
    }
    clearVaultApiError();
    return data;
}

function stopIdleWatch() {
    if (idleWatchInterval) {
        clearInterval(idleWatchInterval);
        idleWatchInterval = null;
    }
    lastActivityAt = 0;
}

function checkIdleLock() {
    if (!vaultKey) return;
    if (Date.now() >= lastActivityAt + AUTO_LOCK_MS) {
        lockVault('inactivity');
    }
}

function resetIdleTimer() {
    if (!vaultKey) return;
    lastActivityAt = Date.now();
    if (!idleWatchInterval) {
        idleWatchInterval = setInterval(checkIdleLock, 1000);
    }
}

function recordVaultActivity() {
    if (vaultKey) {
        resetIdleTimer();
    }
}

function closeVaultDialogs() {
    ['entry-dialog', 'generator-dialog', 'detail-dialog'].forEach((id) => {
        const dialog = document.getElementById(id);
        if (dialog?.open) {
            dialog.close();
        }
    });
}

function showUnlockScreen() {
    const unlock = document.getElementById('unlock-screen');
    const vault = document.getElementById('vault-screen');
    vault?.classList.add('hidden');
    if (vault) vault.style.display = '';
    unlock?.classList.remove('hidden');
    if (unlock) unlock.style.display = '';
}

/** @param {'inactivity'|'manual'} [reason] */
function lockVault(reason = 'manual') {
    vaultKey = null;
    items = [];
    selectedItemId = null;
    detailPasswordRevealed = false;
    stopIdleWatch();
    stopSessionKeepalive();
    closeVaultDialogs();

    showUnlockScreen();
    const tbody = document.getElementById('item-table-body');
    if (tbody) tbody.innerHTML = '';
    document.getElementById('vault-table-wrap')?.classList.add('hidden');
    renderDetail(null);
    const input = document.getElementById('unlock-master');
    if (input) input.value = '';

    if (reason === 'inactivity') {
        const err = document.getElementById('unlock-error');
        if (err) {
            err.textContent = `Vault locked after ${AUTO_LOCK_MINUTES} minutes of inactivity. Enter your master password to continue.`;
            err.hidden = false;
            err.classList.remove('hidden');
        }
    }
}

async function copyWithClear(text) {
    await navigator.clipboard.writeText(text);
    if (clipboardTimer) clearTimeout(clipboardTimer);
    clipboardTimer = setTimeout(async () => {
        try {
            const current = await navigator.clipboard.readText();
            if (current === text) {
                await navigator.clipboard.writeText('');
            }
        } catch {
            // Clipboard read may be denied; ignore.
        }
    }, CLIPBOARD_CLEAR_MS);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatTagsPills(tags) {
    const list = (tags || []).filter(Boolean);
    if (!list.length) {
        return '<span class="sb-table-tags-empty">—</span>';
    }
    const pills = list.map((tag) => `<span class="sb-table-tag-pill">${escapeHtml(tag)}</span>`).join('');
    return `<span class="sb-table-tags">${pills}</span>`;
}

function formatEditedDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

function getSelectedItem() {
    return items.find((item) => item.id === selectedItemId) ?? null;
}

function setNavActive() {
    const map = { all: 'nav-all', favorites: 'nav-favorites', trash: 'nav-trash' };
    Object.values(map).forEach((id) => {
        document.getElementById(id)?.classList.remove('is-active');
    });
    document.getElementById(map[view])?.classList.add('is-active');
}

function filteredItems() {
    const q = (document.getElementById('search-input')?.value || '').trim().toLowerCase();
    return items.filter((item) => {
        if (view === 'trash') return Boolean(item.deleted_at);
        if (item.deleted_at) return false;
        if (view === 'favorites' && !item.plain?.favorite) return false;
        if (!q) return true;
        const plain = item.plain || {};
        const hay = [
            plain.title ?? '',
            plain.url ?? '',
            plain.username ?? '',
            plain.notes ?? '',
            (plain.tags || []).join(' '),
        ].join(' ').toLowerCase();
        return hay.includes(q);
    });
}

function renderDetail(item, { openModal = false } = {}) {
    const dialog = document.getElementById('detail-dialog');
    if (!item || !item.plain) {
        dialog?.close();
        return;
    }

    const title = item.plain.title || 'Untitled';
    const url = item.plain.url || '';
    const host = url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    const edited = formatEditedDate(item.updated_at);

    const titleEl = document.getElementById('detail-title');
    const metaEl = document.getElementById('detail-meta');
    const userEl = document.getElementById('detail-username');
    const urlEl = document.getElementById('detail-url');
    const notesEl = document.getElementById('detail-notes');
    const toggleEl = document.getElementById('detail-toggle-pass');

    if (titleEl) titleEl.textContent = title;
    if (metaEl) metaEl.textContent = [host, edited ? `edited ${edited}` : ''].filter(Boolean).join(' · ');
    if (userEl) userEl.textContent = item.plain.username || '—';
    if (urlEl) urlEl.textContent = url || '—';
    if (notesEl) notesEl.textContent = item.plain.notes || '—';

    const passEl = document.getElementById('detail-password');
    if (passEl) {
        passEl.textContent = detailPasswordRevealed ? (item.plain.password || '') : '••••••••••••';
    }
    if (toggleEl) {
        setPasswordToggleRevealed(toggleEl, detailPasswordRevealed);
    }

    if (openModal && dialog) {
        dialog.showModal();
    }
}

function tableHost(url) {
    if (!url) return '—';
    return url.replace(/^https?:\/\//, '').replace(/\/.*$/, '') || url;
}

const MOBILE_PANEL_PAIRS = [
    ['btn-mobile-actions', 'mobile-actions-panel'],
    ['btn-mobile-account', 'mobile-account-panel'],
];

let openMobilePanelId = null;

function closeMobilePanels() {
    MOBILE_PANEL_PAIRS.forEach(([btnId, panelId]) => {
        const panel = document.getElementById(panelId);
        if (panel) {
            panel.hidden = true;
        }
        document.getElementById(btnId)?.setAttribute('aria-expanded', 'false');
    });
    const scrim = document.getElementById('mobile-sheet-scrim');
    if (scrim) {
        scrim.hidden = true;
        scrim.setAttribute('aria-hidden', 'true');
    }
    openMobilePanelId = null;
}

function toggleMobilePanel(btnId, panelId) {
    if (openMobilePanelId === panelId) {
        closeMobilePanels();
        return;
    }
    closeMobilePanels();
    const panel = document.getElementById(panelId);
    const scrim = document.getElementById('mobile-sheet-scrim');
    const btn = document.getElementById(btnId);
    if (panel) {
        panel.hidden = false;
    }
    if (scrim) {
        scrim.hidden = false;
        scrim.setAttribute('aria-hidden', 'false');
    }
    btn?.setAttribute('aria-expanded', 'true');
    openMobilePanelId = panelId;
}

function bindMobilePanels() {
    MOBILE_PANEL_PAIRS.forEach(([btnId, panelId]) => {
        document.getElementById(btnId)?.addEventListener('click', () => {
            toggleMobilePanel(btnId, panelId);
        });
    });

    document.getElementById('mobile-sheet-scrim')?.addEventListener('click', closeMobilePanels);

    document.getElementById('btn-mobile-generator')?.addEventListener('click', () => {
        closeMobilePanels();
        refreshGenerator();
        document.getElementById('generator-dialog')?.showModal();
    });
    document.getElementById('btn-mobile-import')?.addEventListener('click', () => {
        closeMobilePanels();
        document.getElementById('import-file')?.click();
    });
    document.getElementById('btn-mobile-export')?.addEventListener('click', () => {
        closeMobilePanels();
        openExportDialog();
    });
    document.getElementById('btn-mobile-change-master')?.addEventListener('click', () => {
        closeMobilePanels();
        openChangeMasterDialog();
    });
    document.getElementById('btn-mobile-bar-lock')?.addEventListener('click', () => {
        closeMobilePanels();
        lockVault('manual');
    });
}

function selectItem(item) {
    selectedItemId = item.id;
    detailPasswordRevealed = false;
    renderList();
    renderDetail(item, { openModal: true });
}

function tableRowTemplate(item) {
    const title = item.plain?.title || 'Untitled';
    const username = item.plain?.username || '—';
    const host = tableHost(item.plain?.url || '');
    const edited = formatEditedDate(item.updated_at) || '—';
    const tagList = item.plain?.tags || [];
    const tagsTitle = tagList.filter(Boolean).join(', ');
    const favorite = item.plain?.favorite
        ? `<span class="sb-table-fav" aria-hidden="true">${favoriteStarMarkup}</span>`
        : '';

    const tr = document.createElement('tr');
    tr.className = `sb-table-row${item.id === selectedItemId ? ' is-selected' : ''}`;
    tr.tabIndex = 0;
    tr.innerHTML = `
        <td class="sb-table-cell sb-table-cell-title">${favorite}${escapeHtml(title)}</td>
        <td class="sb-table-cell" data-label="Username">${escapeHtml(username)}</td>
        <td class="sb-table-cell sb-table-cell-muted" data-label="Website" title="${escapeHtml(item.plain?.url || '')}">${escapeHtml(host)}</td>
        <td class="sb-table-cell sb-table-cell-muted sb-table-cell-tags" data-label="Tags"${tagsTitle ? ` title="${escapeHtml(tagsTitle)}"` : ''}>${formatTagsPills(tagList)}</td>
        <td class="sb-table-cell sb-table-cell-muted sb-table-col-date" data-label="Updated">${escapeHtml(edited)}</td>
    `;
    tr.addEventListener('click', () => selectItem(item));
    tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectItem(item);
        }
    });
    return tr;
}

function renderList() {
    const tbody = document.getElementById('item-table-body');
    const tableWrap = document.getElementById('vault-table-wrap');
    const empty = document.getElementById('empty-state');
    if (!tbody) return;
    tbody.innerHTML = '';
    const list = filteredItems();
    list.forEach((item) => tbody.appendChild(tableRowTemplate(item)));
    empty?.classList.toggle('hidden', list.length > 0);
    tableWrap?.classList.toggle('hidden', list.length === 0);

    if (list.length && !list.some((i) => i.id === selectedItemId)) {
        selectedItemId = list[0].id;
        detailPasswordRevealed = false;
    } else if (!list.length) {
        selectedItemId = null;
        renderDetail(null);
    }

    const dialog = document.getElementById('detail-dialog');
    if (dialog?.open && getSelectedItem()) {
        renderDetail(getSelectedItem());
    }
}

async function decryptAllRows(rows) {
    if (!vaultKey) return [];
    const out = [];
    for (const row of rows) {
        try {
            const plain = await decryptJson(vaultKey, row.ciphertext, row.iv);
            out.push({ ...row, plain });
        } catch {
            out.push({
                ...row,
                plain: { title: 'Decrypt error', username: '', password: '', notes: '', tags: [], folder: '', favorite: false },
            });
        }
    }
    return out;
}

async function loadItems() {
    if (view === 'trash') {
        const data = await api('/api/vault/trash');
        items = await decryptAllRows(data.data || []);
    } else {
        const data = await api('/api/vault/items');
        items = await decryptAllRows(data.data || []);
    }
    renderList();
}

function setUnlockStatus(message) {
    const status = document.getElementById('unlock-status');
    if (!status) return;
    status.hidden = false;
    status.classList.remove('hidden');
    status.textContent = message;
}

function showVaultScreen() {
    const unlock = document.getElementById('unlock-screen');
    const vault = document.getElementById('vault-screen');
    unlock?.classList.add('hidden');
    if (unlock) unlock.style.display = 'none';
    vault?.classList.remove('hidden');
    if (vault) vault.style.display = 'flex';
}

function showUnlockError(message) {
    const err = document.getElementById('unlock-error');
    if (!err) {
        return;
    }
    err.textContent = message;
    err.hidden = false;
    err.classList.remove('hidden');
}

function clearUnlockError() {
    const err = document.getElementById('unlock-error');
    if (!err) {
        return;
    }
    err.textContent = '';
    err.hidden = true;
    err.classList.add('hidden');
}

async function unlock(masterPassword) {
    const password = masterPassword.trim();
    if (!password) {
        return false;
    }

    if (!window.crypto?.subtle) {
        throw new Error('Web Crypto is unavailable in this browser context.');
    }

    setUnlockStatus('Loading vault configuration…');

    const { salt, iterations, vaultCheck } = await ensureCryptoConfig();

    setUnlockStatus('Deriving key locally… (secure PBKDF2, please wait)');

    const key = await withTimeout(
        deriveKey(password, salt, iterations),
        180000,
        'Key derivation timed out after 3 minutes. Close other tabs and try again.'
    );

    setUnlockStatus('Verifying master password…');
    const ok = await verifyVaultCheck(key, vaultCheck);
    if (!ok) {
        return false;
    }

    vaultKey = key;
    try {
        setUnlockStatus('Loading vault entries…');
        await loadItems();
    } catch (error) {
        vaultKey = null;
        items = [];
        throw error;
    }

    showVaultScreen();
    clearUnlockError();
    setUnlockStatus('');
    resetIdleTimer();
    startSessionKeepalive();
    return true;
}

function openEntryDialog(item = null) {
    clearEntrySaveError();
    document.getElementById('detail-dialog')?.close();
    const dialog = document.getElementById('entry-dialog');
    document.getElementById('entry-dialog-title').textContent = item ? 'Edit entry' : 'New entry';
    document.getElementById('entry-id').value = item?.id ?? '';
    document.getElementById('entry-title').value = item?.plain?.title ?? '';
    document.getElementById('entry-url').value = item?.plain?.url ?? '';
    document.getElementById('entry-username').value = item?.plain?.username ?? '';
    document.getElementById('entry-password').value = item?.plain?.password ?? '';
    document.getElementById('entry-notes').value = item?.plain?.notes ?? '';
    document.getElementById('entry-tags').value = (item?.plain?.tags || []).join(', ');
    document.getElementById('entry-favorite').checked = Boolean(item?.plain?.favorite);
    dialog?.showModal();
}

async function saveEntry(event) {
    event?.preventDefault();
    if (!vaultKey || saveInFlight) return;

    recordVaultActivity();
    clearEntrySaveError();

    const saveBtn = document.getElementById('entry-save');
    saveInFlight = true;
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
    }

    try {
        const id = document.getElementById('entry-id').value;
        const payload = {
            title: document.getElementById('entry-title').value.trim(),
            url: document.getElementById('entry-url').value.trim(),
            username: document.getElementById('entry-username').value,
            password: document.getElementById('entry-password').value,
            notes: document.getElementById('entry-notes').value,
            tags: document.getElementById('entry-tags').value.split(',').map((t) => t.trim()).filter(Boolean),
            folder: '',
            favorite: document.getElementById('entry-favorite').checked,
        };
        const encrypted = await encryptJson(vaultKey, payload);
        if (id) {
            await api(`/api/vault/items/${id}`, {
                method: 'PUT',
                formFields: encrypted,
            });
        } else {
            await api('/api/vault/items', {
                method: 'POST',
                formFields: encrypted,
            });
        }
        document.getElementById('entry-dialog')?.close();
        await loadItems();
        recordVaultActivity();
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not save this entry.';
        showEntrySaveError(message);
        showVaultApiError(message);
    } finally {
        saveInFlight = false;
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
    }
}

async function deleteItem(item) {
    if (view === 'trash') {
        await api(`/api/vault/items/${item.id}/force`, { method: 'DELETE' });
    } else {
        await api(`/api/vault/items/${item.id}`, { method: 'DELETE' });
    }
    if (selectedItemId === item.id) {
        selectedItemId = null;
        renderDetail(null);
    }
    await loadItems();
}

async function restoreItem(item) {
    await api(`/api/vault/items/${item.id}/restore`, { method: 'POST' });
    await loadItems();
}

function generatePassword() {
    const passphrase = document.getElementById('gen-passphrase')?.checked;
    if (passphrase) {
        const words = ['correct', 'horse', 'battery', 'staple', 'forest', 'river', 'cloud', 'metal', 'quiet', 'amber'];
        const parts = [];
        for (let i = 0; i < 4; i += 1) {
            const idx = crypto.getRandomValues(new Uint32Array(1))[0] % words.length;
            parts.push(words[idx]);
        }
        return parts.join('-');
    }
    const length = Number(document.getElementById('gen-length')?.value || 20);
    let charset = '';
    if (document.getElementById('gen-lower')?.checked) charset += 'abcdefghijklmnopqrstuvwxyz';
    if (document.getElementById('gen-upper')?.checked) charset += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (document.getElementById('gen-digits')?.checked) charset += '0123456789';
    if (document.getElementById('gen-symbols')?.checked) charset += '!@#$%^&*()-_=+[]{}';
    if (!charset) charset = 'abcdefghijklmnopqrstuvwxyz';
    const bytes = crypto.getRandomValues(new Uint8Array(length));
    let out = '';
    for (let i = 0; i < length; i += 1) {
        out += charset[bytes[i] % charset.length];
    }
    return out;
}

function refreshGenerator() {
    const value = generatePassword();
    const out = document.getElementById('generated-password');
    if (out) out.textContent = value;
}

function clearExportError() {
    const el = document.getElementById('export-error');
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
    el.classList.add('hidden');
}

function showExportError(message) {
    const el = document.getElementById('export-error');
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
    el.classList.remove('hidden');
}

function setExportBusy(busy) {
    const submit = document.getElementById('export-submit');
    const cancel = document.getElementById('export-cancel');
    const close = document.getElementById('export-dialog-close');
    const inputs = [
        document.getElementById('export-passphrase'),
        document.getElementById('export-passphrase-confirm'),
    ];
    inputs.forEach((input) => {
        if (input) input.disabled = busy;
    });
    if (submit) {
        submit.disabled = busy;
        submit.textContent = busy ? 'Preparing…' : 'Download export';
    }
    if (cancel) cancel.disabled = busy;
    if (close) close.disabled = busy;
}

async function openExportDialog() {
    const { resetPasswordToggles } = await import('./password-toggle.js');
    const dialog = document.getElementById('export-dialog');
    const form = document.getElementById('export-form');
    if (!dialog || !form) return;
    form.reset();
    resetPasswordToggles(form);
    clearExportError();
    setExportBusy(false);
    dialog.showModal();
    document.getElementById('export-passphrase')?.focus();
}

function closeExportDialog() {
    document.getElementById('export-dialog')?.close();
}

async function submitExportVault(event) {
    event?.preventDefault();
    clearExportError();

    const passphrase = document.getElementById('export-passphrase')?.value ?? '';
    const confirm = document.getElementById('export-passphrase-confirm')?.value ?? '';

    if (passphrase.length < 12) {
        showExportError('Passphrase must be at least 12 characters.');
        return;
    }
    if (passphrase !== confirm) {
        showExportError('Passphrases do not match.');
        return;
    }

    setExportBusy(true);
    try {
        const active = items.filter((i) => !i.deleted_at).map((i) => ({ id: i.id, ...i.plain }));
        const blob = await encryptExport(passphrase, { exported_at: new Date().toISOString(), items: active });
        await api('/api/audit/export', { method: 'POST' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([blob], { type: 'application/json' }));
        a.download = `${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(a.href);
        closeExportDialog();
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not create export.';
        showExportError(message);
    } finally {
        setExportBusy(false);
    }
}

function parseCsvLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i += 1) {
        const ch = line[i];
        if (ch === '"') {
            inQuotes = !inQuotes;
            continue;
        }
        if (ch === ',' && !inQuotes) {
            result.push(current);
            current = '';
            continue;
        }
        current += ch;
    }
    result.push(current);
    return result.map((v) => v.trim());
}

async function importCsv(file) {
    if (!vaultKey) return;
    const text = await file.text();
    const lines = text.split(/\r?\n/).filter(Boolean);
    if (lines.length < 2) return;
    const header = parseCsvLine(lines[0]).map((h) => h.toLowerCase());
    const rows = [];
    for (let i = 1; i < lines.length; i += 1) {
        const cols = parseCsvLine(lines[i]);
        const row = {};
        header.forEach((key, idx) => {
            row[key] = cols[idx] ?? '';
        });
        rows.push({
            title: row.name || row.title || row.url || 'Imported',
            url: row.url || row.login_uri || '',
            username: row.username || row.login || '',
            password: row.password || '',
            notes: row.notes || row.extra || '',
            tags: [],
            folder: row.folder || '',
            favorite: false,
        });
    }
    const encryptedItems = [];
    for (const plain of rows) {
        encryptedItems.push(await encryptJson(vaultKey, plain));
    }
    await api('/api/vault/items/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: encryptedItems }),
    });
    await loadItems();
}

function clearChangeMasterError() {
    const el = document.getElementById('change-master-error');
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
    el.classList.add('hidden');
}

function showChangeMasterError(message) {
    const el = document.getElementById('change-master-error');
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
    el.classList.remove('hidden');
}

function setChangeMasterBusy(busy) {
    const submit = document.getElementById('change-master-submit');
    const cancel = document.getElementById('change-master-cancel');
    const close = document.getElementById('change-master-dialog-close');
    const inputs = [
        document.getElementById('change-master-current'),
        document.getElementById('change-master-new'),
        document.getElementById('change-master-confirm'),
    ];
    inputs.forEach((input) => {
        if (input) input.disabled = busy;
    });
    if (submit) {
        submit.disabled = busy;
        submit.textContent = busy ? 'Updating…' : 'Update password';
    }
    if (cancel) cancel.disabled = busy;
    if (close) close.disabled = busy;
}

async function openChangeMasterDialog() {
    const { resetPasswordToggles } = await import('./password-toggle.js');
    const dialog = document.getElementById('change-master-dialog');
    const form = document.getElementById('change-master-form');
    if (!dialog || !form) return;
    form.reset();
    resetPasswordToggles(form);
    clearChangeMasterError();
    setChangeMasterBusy(false);
    dialog.showModal();
    document.getElementById('change-master-current')?.focus();
}

function closeChangeMasterDialog() {
    document.getElementById('change-master-dialog')?.close();
}

async function submitChangeMasterPassword(event) {
    event?.preventDefault();
    clearChangeMasterError();

    const current = document.getElementById('change-master-current')?.value ?? '';
    const next = document.getElementById('change-master-new')?.value ?? '';
    const confirm = document.getElementById('change-master-confirm')?.value ?? '';

    if (!current) {
        showChangeMasterError('Enter your current master password.');
        return;
    }
    if (next.length < 12) {
        showChangeMasterError('New password must be at least 12 characters.');
        return;
    }
    if (next !== confirm) {
        showChangeMasterError('New passwords do not match.');
        return;
    }

    setChangeMasterBusy(true);
    try {
        const { salt, iterations, vaultCheck } = await ensureCryptoConfig();
        const currentKey = await deriveKey(current, salt, iterations);
        if (!(await verifyVaultCheck(currentKey, vaultCheck))) {
            showChangeMasterError('Current master password is incorrect.');
            return;
        }

        const newSalt = generateSalt();
        const newKey = await deriveKey(next, newSalt, iterations);
        const newCheck = await makeVaultCheck(newKey);
        const payloadItems = [];
        for (const item of items.filter((i) => !i.deleted_at)) {
            const plain = item.plain ?? (await decryptJson(currentKey, item.ciphertext, item.iv));
            const enc = await encryptJson(newKey, plain);
            payloadItems.push({ id: item.id, ...enc });
        }
        await api('/api/crypto/rotate', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kdf_salt: newSalt,
                kdf_iterations: iterations,
                vault_check: newCheck,
                items: payloadItems,
            }),
        });
        cryptoConfig = {
            salt: newSalt,
            iterations,
            vaultCheck: JSON.parse(newCheck),
        };
        vaultKey = newKey;
        closeChangeMasterDialog();
        await loadItems();
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not update master password.';
        showChangeMasterError(message);
    } finally {
        setChangeMasterBusy(false);
    }
}

function bindUi() {
    loadFavoriteStarMarkup();

    document.getElementById('unlock-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearUnlockError();
        const master = document.getElementById('unlock-master')?.value?.trim() ?? '';

        setUnlockBusy(true);
        try {
            const ok = await unlock(master);
            if (!ok) {
                showUnlockError('Incorrect master password.');
            }
        } catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'Could not unlock the vault. Refresh and try again.';
            showUnlockError(message);
        } finally {
            setUnlockBusy(false);
        }
    });

    const activityEvents = ['click', 'keydown', 'touchstart', 'scroll', 'wheel', 'pointerdown', 'input'];
    activityEvents.forEach((evt) => {
        document.addEventListener(evt, recordVaultActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkIdleLock();
        }
    });

    document.getElementById('btn-lock')?.addEventListener('click', () => {
        lockVault('manual');
    });

    bindMobilePanels();

    document.getElementById('search-input')?.addEventListener('input', renderList);
    document.getElementById('btn-new')?.addEventListener('click', () => openEntryDialog());
    document.getElementById('entry-save')?.addEventListener('click', () => saveEntry());
    document.getElementById('entry-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        saveEntry(e);
    });
    document.getElementById('entry-cancel')?.addEventListener('click', () => document.getElementById('entry-dialog')?.close());
    document.getElementById('entry-dialog-close')?.addEventListener('click', () => document.getElementById('entry-dialog')?.close());
    document.getElementById('detail-close')?.addEventListener('click', () => {
        document.getElementById('detail-dialog')?.close();
    });

    document.getElementById('detail-edit')?.addEventListener('click', () => {
        const item = getSelectedItem();
        if (item) openEntryDialog(item);
    });
    document.getElementById('detail-delete')?.addEventListener('click', () => {
        const item = getSelectedItem();
        if (item) deleteItem(item);
    });
    document.getElementById('detail-copy-user')?.addEventListener('click', () => {
        const item = getSelectedItem();
        if (item) copyWithClear(item.plain?.username || '');
    });
    document.getElementById('detail-copy-pass')?.addEventListener('click', () => {
        const item = getSelectedItem();
        if (item) copyWithClear(item.plain?.password || '');
    });
    document.getElementById('detail-toggle-pass')?.addEventListener('click', () => {
        detailPasswordRevealed = !detailPasswordRevealed;
        renderDetail(getSelectedItem());
    });

    document.getElementById('nav-all')?.addEventListener('click', async () => {
        view = 'all';
        setNavActive();
        await loadItems();
    });
    document.getElementById('nav-favorites')?.addEventListener('click', async () => {
        view = 'favorites';
        setNavActive();
        renderList();
    });
    document.getElementById('nav-trash')?.addEventListener('click', async () => {
        view = 'trash';
        setNavActive();
        await loadItems();
    });

    document.getElementById('btn-generator')?.addEventListener('click', () => {
        refreshGenerator();
        document.getElementById('generator-dialog')?.showModal();
    });
    document.getElementById('gen-regenerate')?.addEventListener('click', refreshGenerator);
    document.getElementById('gen-copy')?.addEventListener('click', () => {
        const value = document.getElementById('generated-password')?.textContent ?? '';
        copyWithClear(value);
    });
    document.getElementById('gen-dialog-close')?.addEventListener('click', () => document.getElementById('generator-dialog')?.close());

    document.getElementById('btn-export')?.addEventListener('click', openExportDialog);
    document.getElementById('export-form')?.addEventListener('submit', submitExportVault);
    document.getElementById('export-cancel')?.addEventListener('click', closeExportDialog);
    document.getElementById('export-dialog-close')?.addEventListener('click', closeExportDialog);
    document.getElementById('btn-change-master')?.addEventListener('click', openChangeMasterDialog);
    document.getElementById('change-master-form')?.addEventListener('submit', submitChangeMasterPassword);
    document.getElementById('change-master-cancel')?.addEventListener('click', closeChangeMasterDialog);
    document.getElementById('change-master-dialog-close')?.addEventListener('click', closeChangeMasterDialog);

    document.getElementById('btn-import')?.addEventListener('click', () => {
        document.getElementById('import-file')?.click();
    });
    document.getElementById('import-file')?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file) importCsv(file);
        e.target.value = '';
    });

}

export function initVaultApp() {
    setNavActive();
    bindUi();

    if (!document.getElementById('unlock-form')) {
        return;
    }

    const boot = document.getElementById('vault-boot-error');
    if (boot) {
        boot.hidden = true;
    }

    const embedded = readEmbeddedCryptoConfig();
    if (!embedded) {
        ensureCryptoConfig().catch((error) => {
            const message = error instanceof Error ? error.message : 'Could not load vault configuration. Sign in again or hard refresh.';
            showUnlockError(message);
        });
    }
}
