document.addEventListener("DOMContentLoaded", () => {
  const notifButtons = document.querySelectorAll('.btnEnableNotifications');

  if (!notifButtons.length || !('Notification' in window)) {
    return;
  }

  const hideButtons = () => notifButtons.forEach(btn => btn.classList.add('d-none'));
  const showButtons = () => notifButtons.forEach(btn => btn.classList.remove('d-none'));

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
      hideButtons();
    }
    return;
  }

  if (Notification.permission === 'denied') {
    hideButtons();
    return;
  }

  showButtons();

  notifButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const permission = await Notification.requestPermission();

      if (permission !== 'granted') {
        console.log('Notifications refusées');
        return;
      }

      let success = true;
      if (typeof window.subscribeToHaloGariPush === 'function') {
        success = await window.subscribeToHaloGariPush();
      }

      if (success) {
        console.log('Notifications autorisées');
        hideButtons();
      }
    });
  });
});
