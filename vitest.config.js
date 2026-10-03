import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['tests/Unit/**/*.test.js', 'resources/js/**/*.test.js'],
    },
});
