document.addEventListener("DOMContentLoaded", () => {
  const notifButtons = document.querySelectorAll('.btnEnableNotifications');

  if (!notifButtons.length) {
    return;
  }

  const hideButtons = () => notifButtons.forEach(btn => btn.classList.add('d-none'));
  const showButtons = () => notifButtons.forEach(btn => btn.classList.remove('d-none'));
  const ua = navigator.userAgent.toLowerCase();
  const isIos = /iphone|ipad|ipod/.test(ua);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || (('standalone' in window.navigator) && window.navigator.standalone);

  const notify = (icon, title, text) => {
    if (window.Swal) {
      Swal.fire({
        icon,
        title,
        text,
        confirmButtonText: 'OK',
        timer: icon === 'success' ? 5000 : undefined,
        timerProgressBar: icon === 'success'
      });
      return;
    }

    alert(`${title}\n${text}`);
  };

  const explainIosInstall = () => {
    notify(
      'info',
      'Installation necessaire',
      "Sur iPhone, les notifications fonctionnent apres installation de HaloGari sur l'ecran d'accueil. Ouvrez le partage Safari, choisissez Sur l'ecran d'accueil, puis relancez HaloGari."
    );
  };

  const setLoading = (button, loading) => {
    if (!button) {
      return;
    }

    button.disabled = loading;
    button.dataset.originalText = button.dataset.originalText || button.innerHTML;
    button.innerHTML = loading
      ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Activation...'
      : button.dataset.originalText;
  };

  if (!('Notification' in window)) {
    showButtons();
    notifButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (isIos && !isStandalone) {
          explainIosInstall();
          return;
        }

        notify('warning', 'Notifications indisponibles', "Ce navigateur ne permet pas d'activer les notifications HaloGari.");
      });
    });
    return;
  }

  if (isIos && !isStandalone) {
    showButtons();
    notifButtons.forEach(btn => {
      btn.addEventListener('click', explainIosInstall);
    });
    return;
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    showButtons();
    notifButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        notify('warning', 'Notifications indisponibles', 'Les notifications push ne sont pas disponibles sur ce navigateur.');
      });
    });
    return;
  }

  if (Notification.permission === 'granted') {
    if (typeof window.subscribeToHaloGariPush === 'function') {
      window.subscribeToHaloGariPush().then((success) => {
        if (success) {
          hideButtons();
        } else {
          showButtons();
        }
      });
    } else {
      showButtons();
    }
    return;
  }

  if (Notification.permission === 'denied') {
    showButtons();
    notifButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        notify('warning', 'Notifications bloquees', "Votre navigateur bloque les notifications HaloGari. Activez-les dans les reglages du site ou de l'application.");
      });
    });
    return;
  }

  showButtons();

  notifButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      setLoading(btn, true);

      let permission = Notification.permission;
      if (permission === 'default') {
        permission = await Notification.requestPermission();
      }

      if (permission !== 'granted') {
        setLoading(btn, false);
        notify('warning', 'Notifications non activees', 'Vous devez autoriser les notifications pour recevoir les alertes HaloGari.');
        return;
      }

      let success = false;
      if (typeof window.subscribeToHaloGariPush === 'function') {
        success = await window.subscribeToHaloGariPush();
      }

      setLoading(btn, false);

      if (success) {
        notify('success', 'Notifications activees', 'Vous recevrez les alertes importantes de HaloGari sur cet appareil.');
        hideButtons();
      } else {
        notify('error', 'Activation impossible', "L'autorisation est accordee, mais l'abonnement push n'a pas pu etre enregistre. Reessayez apres avoir ferme puis rouvert HaloGari.");
      }
    });
  });
});
