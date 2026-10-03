/** @typedef {import('crypto').webcrypto} WebCrypto */

import pbkdf2WorkerSource from './pbkdf2-worker.js?raw';

export const VAULT_CHECK_STRING = 'vault-check-v1';
export const DEFAULT_KDF_ITERATIONS = 600000;

/** Identifies encrypted backup files (separate from vault item blobs). Bump suffix when the envelope changes. */
export const EXPORT_FORMAT = 'passphrase-1';

const LEGACY_EXPORT_FORMATS = ['hashward-export'];
/** KDF + cipher used for export passphrase → AES key (switch in decryptExport when adding new KDFs). */
export const EXPORT_KDF = 'pbkdf2-sha256-aes-gcm-256';

function b64Encode(bytes) {
    return btoa(String.fromCharCode(...bytes));
}

function b64Decode(str) {
    const binary = atob(str);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

export function generateSalt() {
    const salt = new Uint8Array(16);
    crypto.getRandomValues(salt);
    return b64Encode(salt);
}

function deriveKeyOnMainThread(masterPassword, saltB64, iterations) {
    const enc = new TextEncoder();
    return crypto.subtle.importKey(
        'raw',
        enc.encode(masterPassword),
        'PBKDF2',
        false,
        ['deriveKey']
    ).then((keyMaterial) => crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: b64Decode(saltB64),
            iterations,
            hash: 'SHA-256',
        },
        keyMaterial,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    ));
}

function deriveKeyInWorker(masterPassword, saltB64, iterations) {
    return new Promise((resolve, reject) => {
        // Blob workers use the page origin (e.g. 127.0.0.1:8000), not the Vite dev server origin.
        const blob = new Blob([pbkdf2WorkerSource], { type: 'application/javascript' });
        const objectUrl = URL.createObjectURL(blob);
        const worker = new Worker(objectUrl);

        worker.onmessage = (event) => {
            URL.revokeObjectURL(objectUrl);
            worker.terminate();
            if (event.data?.error) {
                reject(new Error(event.data.error));
                return;
            }
            crypto.subtle.importKey(
                'raw',
                event.data.bits,
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            ).then(resolve, reject);
        };

        worker.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            worker.terminate();
            reject(new Error('PBKDF2 worker failed to start'));
        };

        worker.postMessage({ password: masterPassword, saltB64, iterations });
    });
}

/**
 * Derive AES-256-GCM key from master password (never sent to server).
 */
export async function deriveKey(masterPassword, saltB64, iterations = DEFAULT_KDF_ITERATIONS) {
    const canUseWorker = typeof Worker !== 'undefined' && import.meta.env?.MODE !== 'test';

    if (canUseWorker) {
        try {
            return await deriveKeyInWorker(masterPassword, saltB64, iterations);
        } catch {
            // Blob/worker CSP or browser quirks: fall back (UI may freeze briefly).
        }
    }

    return deriveKeyOnMainThread(masterPassword, saltB64, iterations);
}

export async function encryptJson(key, obj) {
    const iv = new Uint8Array(12);
    crypto.getRandomValues(iv);
    const plaintext = new TextEncoder().encode(JSON.stringify(obj));
    const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, plaintext);

    return {
        ciphertext: b64Encode(new Uint8Array(cipher)),
        iv: b64Encode(iv),
    };
}

export async function decryptJson(key, ciphertextB64, ivB64) {
    const plainBuffer = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: b64Decode(ivB64) },
        key,
        b64Decode(ciphertextB64)
    );
    const text = new TextDecoder().decode(plainBuffer);
    return JSON.parse(text);
}

export async function makeVaultCheck(key) {
    const { ciphertext, iv } = await encryptJson(key, { check: VAULT_CHECK_STRING });
    return JSON.stringify({ ciphertext, iv });
}

export async function verifyVaultCheck(key, vaultCheckPayload) {
    try {
        const parsed = typeof vaultCheckPayload === 'string'
            ? JSON.parse(vaultCheckPayload)
            : vaultCheckPayload;
        const data = await decryptJson(key, parsed.ciphertext, parsed.iv);
        return data?.check === VAULT_CHECK_STRING;
    } catch {
        return false;
    }
}

async function deriveExportKey(passphrase, saltB64, iterations, kdfId) {
    const id = kdfId ?? EXPORT_KDF;
    switch (id) {
        case EXPORT_KDF:
            return deriveKey(passphrase, saltB64, iterations);
        default:
            throw new Error(`Unsupported export KDF: ${id}`);
    }
}

export async function encryptExport(passphrase, data) {
    const salt = generateSalt();
    const iterations = DEFAULT_KDF_ITERATIONS;
    const key = await deriveExportKey(passphrase, salt, iterations, EXPORT_KDF);
    const { ciphertext, iv } = await encryptJson(key, data);
    return JSON.stringify({
        format: EXPORT_FORMAT,
        v: 1,
        kdf: EXPORT_KDF,
        salt,
        iterations,
        ciphertext,
        iv,
    });
}

function assertSupportedExportFormat(format) {
    if (format == null) {
        return;
    }
    if (format === EXPORT_FORMAT || LEGACY_EXPORT_FORMATS.includes(format)) {
        return;
    }
    throw new Error(`Unsupported export format: ${format}`);
}

export async function decryptExport(passphrase, payload) {
    const parsed = typeof payload === 'string' ? JSON.parse(payload) : payload;
    assertSupportedExportFormat(parsed.format);
    const key = await deriveExportKey(
        passphrase,
        parsed.salt,
        parsed.iterations ?? DEFAULT_KDF_ITERATIONS,
        parsed.kdf,
    );
    return decryptJson(key, parsed.ciphertext, parsed.iv);
}
