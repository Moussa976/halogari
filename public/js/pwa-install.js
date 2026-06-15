// Installation PWA Android/Chrome.
let deferredPrompt;

function isHaloGariStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function getInstallButtons() {
    return document.querySelectorAll('.installAppBtn');
}

function hideInstallButtons() {
    getInstallButtons().forEach((btn) => {
        btn.classList.add('d-none');
        btn.disabled = false;
    });
}

function showInstallButtons() {
    if (isHaloGariStandalone()) {
        hideInstallButtons();
        return;
    }

    getInstallButtons().forEach((btn) => {
        btn.classList.remove('d-none');
        btn.disabled = false;
        btn.removeEventListener('click', handlePWAInstall);
        btn.addEventListener('click', handlePWAInstall);
    });
}

function handlePWAInstall() {
    if (!deferredPrompt) return;

    this.disabled = true;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
            localStorage.setItem('halogariPwaInstalled', '1');
            hideInstallButtons();
        } else {
            this.disabled = false;
        }

        deferredPrompt = null;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (isHaloGariStandalone()) {
        localStorage.setItem('halogariPwaInstalled', '1');
        hideInstallButtons();
    }
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    showInstallButtons();
});

window.addEventListener('appinstalled', () => {
    localStorage.setItem('halogariPwaInstalled', '1');
    deferredPrompt = null;
    hideInstallButtons();
});
