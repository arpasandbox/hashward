import { describe, it, expect } from 'vitest';
import {
    generateSalt,
    deriveKey,
    encryptJson,
    decryptJson,
    makeVaultCheck,
    verifyVaultCheck,
    encryptExport,
    decryptExport,
    EXPORT_FORMAT,
    EXPORT_KDF,
} from '../../resources/js/crypto.js';

describe('crypto', () => {
    it('round-trips encrypt and decrypt', async () => {
        const salt = generateSalt();
        const key = await deriveKey('test-master-password', salt, 600000);
        const payload = { title: 'Example', password: 'hunter2' };
        const { ciphertext, iv } = await encryptJson(key, payload);
        const out = await decryptJson(key, ciphertext, iv);
        expect(out).toEqual(payload);
    });

    it('uses unique IVs', async () => {
        const salt = generateSalt();
        const key = await deriveKey('test-master-password', salt, 600000);
        const a = await encryptJson(key, { x: 1 });
        const b = await encryptJson(key, { x: 1 });
        expect(a.iv).not.toEqual(b.iv);
    });

    it('fails with wrong key', async () => {
        const salt = generateSalt();
        const key = await deriveKey('one', salt, 600000);
        const wrong = await deriveKey('two', salt, 600000);
        const { ciphertext, iv } = await encryptJson(key, { secret: true });
        await expect(decryptJson(wrong, ciphertext, iv)).rejects.toThrow();
    });

    it('detects tampered ciphertext', async () => {
        const salt = generateSalt();
        const key = await deriveKey('test', salt, 600000);
        const { ciphertext, iv } = await encryptJson(key, { ok: true });
        const bytes = Uint8Array.from(atob(ciphertext), (c) => c.charCodeAt(0));
        bytes[0] ^= 0xff;
        const tampered = btoa(String.fromCharCode(...bytes));
        await expect(decryptJson(key, tampered, iv)).rejects.toThrow();
    });

    it('verifies vault check', async () => {
        const salt = generateSalt();
        const key = await deriveKey('master', salt, 600000);
        const check = await makeVaultCheck(key);
        await expect(verifyVaultCheck(key, check)).resolves.toBe(true);
    });

    it('round-trips encrypted export with format and kdf metadata', async () => {
        const payload = { exported_at: '2026-01-01T00:00:00.000Z', items: [{ title: 'x' }] };
        const blob = await encryptExport('export-passphrase-12', payload);
        const envelope = JSON.parse(blob);
        expect(envelope.format).toBe(EXPORT_FORMAT);
        expect(envelope.kdf).toBe(EXPORT_KDF);
        expect(envelope.v).toBe(1);
        const out = await decryptExport('export-passphrase-12', blob);
        expect(out).toEqual(payload);
    });

    it('decrypts exports with legacy hashward-export format id', async () => {
        const payload = { items: [] };
        const blob = await encryptExport('export-passphrase-12', payload);
        const envelope = JSON.parse(blob);
        envelope.format = 'hashward-export';
        const out = await decryptExport('export-passphrase-12', envelope);
        expect(out).toEqual(payload);
    });

    it('decrypts legacy export files that only had v', async () => {
        const salt = generateSalt();
        const key = await deriveKey('legacy-export-pass', salt, 600000);
        const { ciphertext, iv } = await encryptJson(key, { legacy: true });
        const legacy = {
            v: 1,
            salt,
            iterations: 600000,
            ciphertext,
            iv,
        };
        const out = await decryptExport('legacy-export-pass', legacy);
        expect(out).toEqual({ legacy: true });
    });

    it('rejects unknown export format', async () => {
        const salt = generateSalt();
        const key = await deriveKey('p', salt, 600000);
        const { ciphertext, iv } = await encryptJson(key, {});
        await expect(
            decryptExport('p', {
                format: 'other-app',
                v: 1,
                kdf: EXPORT_KDF,
                salt,
                iterations: 600000,
                ciphertext,
                iv,
            }),
        ).rejects.toThrow(/Unsupported export format/);
    });
});
