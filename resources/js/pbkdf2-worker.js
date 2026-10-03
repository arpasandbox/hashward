self.onmessage = async (event) => {
    const { password, saltB64, iterations } = event.data;

    try {
        const binary = atob(saltB64);
        const salt = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i += 1) {
            salt[i] = binary.charCodeAt(i);
        }

        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            new TextEncoder().encode(password),
            'PBKDF2',
            false,
            ['deriveBits']
        );

        const bits = await crypto.subtle.deriveBits(
            {
                name: 'PBKDF2',
                salt,
                iterations,
                hash: 'SHA-256',
            },
            keyMaterial,
            256
        );

        self.postMessage({ bits }, [bits]);
    } catch (error) {
        self.postMessage({ error: error instanceof Error ? error.message : 'PBKDF2 failed' });
    }
};
