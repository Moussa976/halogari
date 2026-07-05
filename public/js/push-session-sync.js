(function () {
  const deleteEndpoint = async (subscription) => {
    if (!subscription) return;

    try {
      await fetch('/abonnement-push/supprimer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: subscription.endpoint }),
        keepalive: true
      });
    } catch (error) {
      // La suppression locale reste prioritaire si le réseau est indisponible.
    }
  };

  const unsubscribeCurrentDevice = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      return;
    }

    const registration = await Promise.race([
      navigator.serviceWorker.ready,
      new Promise((_, reject) => window.setTimeout(() => reject(new Error('Service worker timeout')), 2500))
    ]);
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      return;
    }

    await deleteEndpoint(subscription);
    try {
      await subscription.unsubscribe();
    } catch (error) {
      // Certains navigateurs peuvent déjà avoir expiré l'abonnement.
    }

    if (navigator.clearAppBadge) {
      try {
        await navigator.clearAppBadge();
      } catch (error) {
        // Badge non supporté ou refusé.
      }
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (window.HALOGARI_AUTHENTICATED === false) {
      unsubscribeCurrentDevice();
    }

    document.querySelectorAll('a[href]').forEach((link) => {
      let url;
      try {
        url = new URL(link.href, window.location.origin);
      } catch (error) {
        return;
      }

      if (url.pathname !== '/logout') {
        return;
      }

      link.addEventListener('click', async (event) => {
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
          return;
        }

        event.preventDefault();
        try {
          await unsubscribeCurrentDevice();
        } catch (error) {
          // La déconnexion doit rester possible même si le push est indisponible.
        }
        window.location.href = link.href;
      });
    });
  });
})();
