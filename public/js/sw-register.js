// ⚙️ Enregistrement du Service Worker (PWA + notifications)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/js/service-worker.js')
            .then(() => console.log('✅ Service Worker enregistré'))
            .catch(err => console.error('❌ Erreur Service Worker', err));
    });
}
