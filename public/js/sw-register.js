if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/js/service-worker.js', { updateViaCache: 'none' })
            .then((registration) => {
                registration.update();
                console.log('Service Worker enregistre');
            })
            .catch(err => console.error('Erreur Service Worker', err));
    });
}
