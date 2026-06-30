document.addEventListener("DOMContentLoaded", () => {
  const notifButtons = document.querySelectorAll('.btnEnableNotifications');
  const helpTexts = document.querySelectorAll('[data-push-help]');

  if (!notifButtons.length) {
    return;
  }

  const hideButtons = () => notifButtons.forEach(btn => btn.classList.add('d-none'));
  const showButtons = () => notifButtons.forEach(btn => btn.classList.remove('d-none'));
  const setHelpText = (text) => helpTexts.forEach(element => {
    element.textContent = text;
  });
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
      'Installation n\u00e9cessaire',
      "Sur iPhone, les notifications fonctionnent apr\u00e8s installation de HaloGari sur l'\u00e9cran d'accueil. Ouvrez le partage Safari, choisissez Sur l'\u00e9cran d'accueil, puis relancez HaloGari."
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
    setHelpText(isIos && !isStandalone
      ? "Ouvrez HaloGari depuis l'\u00e9cran d'accueil pour activer les notifications sur iPhone."
      : "Ce navigateur ne permet pas d'activer les notifications HaloGari.");
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
    setHelpText("Ouvrez HaloGari depuis l'\u00e9cran d'accueil pour activer les notifications sur iPhone.");
    notifButtons.forEach(btn => {
      btn.addEventListener('click', explainIosInstall);
    });
    return;
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    showButtons();
    setHelpText('Les notifications push ne sont pas disponibles sur ce navigateur.');
    notifButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        notify('warning', 'Notifications indisponibles', 'Les notifications push ne sont pas disponibles sur ce navigateur.');
      });
    });
    return;
  }

  if (Notification.permission === 'granted') {
    setHelpText("Les notifications sont autoris\u00e9es sur cet appareil. V\u00e9rification de l'abonnement...");
    if (typeof window.subscribeToHaloGariPush === 'function') {
      window.subscribeToHaloGariPush().then((success) => {
        if (success) {
          setHelpText('Notifications activ\u00e9es sur cet appareil.');
          hideButtons();
        } else {
          setHelpText("Autorisation accord\u00e9e, mais l'abonnement n'est pas encore enregistr\u00e9.");
          showButtons();
        }
      });
    } else {
      setHelpText("Autorisation accord\u00e9e, mais le module d'abonnement n'est pas charg\u00e9.");
      showButtons();
    }
    return;
  }

  if (Notification.permission === 'denied') {
    showButtons();
    setHelpText("Les notifications sont bloqu\u00e9es dans les r\u00e9glages du navigateur ou de l'application.");
    notifButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        notify('warning', 'Notifications bloqu\u00e9es', "Votre navigateur bloque les notifications HaloGari. Activez-les dans les r\u00e9glages du site ou de l'application.");
      });
    });
    return;
  }

  showButtons();
  setHelpText(isStandalone
    ? 'Activez les notifications pour recevoir les alertes importantes de HaloGari.'
    : "Vous \u00eates dans le navigateur. Si HaloGari est install\u00e9, utilisez Ouvrir dans l'appli pour les notifications de l'app.");

  notifButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      setLoading(btn, true);

      let permission = Notification.permission;
      if (permission === 'default') {
        permission = await Notification.requestPermission();
      }

      if (permission !== 'granted') {
        setLoading(btn, false);
        notify('warning', 'Notifications non activ\u00e9es', 'Vous devez autoriser les notifications pour recevoir les alertes HaloGari.');
        return;
      }

      let success = false;
      if (typeof window.subscribeToHaloGariPush === 'function') {
        success = await window.subscribeToHaloGariPush();
      }

      setLoading(btn, false);

      if (success) {
        notify('success', 'Notifications activ\u00e9es', 'Vous recevrez les alertes importantes de HaloGari sur cet appareil.');
        setHelpText('Notifications activ\u00e9es sur cet appareil.');
        hideButtons();
      } else {
        setHelpText("Autorisation accord\u00e9e, mais l'abonnement n'a pas pu \u00eatre enregistr\u00e9.");
        notify('error', 'Activation impossible', "L'autorisation est accord\u00e9e, mais l'abonnement push n'a pas pu \u00eatre enregistr\u00e9. R\u00e9essayez apr\u00e8s avoir ferm\u00e9 puis rouvert HaloGari.");
      }
    });
  });
});
