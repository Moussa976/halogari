// 📲 Gestion de l'installation manuelle PWA (Android/Chrome)
let deferredPrompt;

function handlePWAInstall() {
    if (!deferredPrompt) return;

    this.disabled = true;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
            console.log('✅ L’app HaloGari a été installée');
        } else {
            console.log('❌ Installation refusée');
        }
        deferredPrompt = null;
    });
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    if (localStorage.getItem('pushPermissionAsked')) {
        document.querySelectorAll('.installAppBtn').forEach((btn) => {
            btn.classList.remove('d-none');
            btn.removeEventListener('click', handlePWAInstall); // sécurité
            btn.addEventListener('click', handlePWAInstall);
        });
    }
});
