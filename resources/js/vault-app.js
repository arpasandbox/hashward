import { initVaultApp } from './vault';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVaultApp);
} else {
    initVaultApp();
}
