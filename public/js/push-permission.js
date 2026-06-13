// 🔔 Déclenchement via clic utilisateur (tous les boutons)
document.addEventListener("DOMContentLoaded", () => {
  const notifButtons = document.querySelectorAll('.btnEnableNotifications');

  if (!notifButtons.length || localStorage.getItem('pushPermissionAsked')) {
    notifButtons.forEach(btn => btn.classList.add('d-none'));
    return;
  }

  notifButtons.forEach(btn => {
    btn.classList.remove('d-none');
    btn.addEventListener('click', () => {
      Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
          localStorage.setItem('pushPermissionAsked', 'true');
          if (typeof window.subscribeToHaloGariPush === 'function') {
            window.subscribeToHaloGariPush();
          }
          console.log('🔔 Notifications autorisées');
          notifButtons.forEach(b => b.classList.add('d-none'));
        } else {
          console.log('🔕 Notifications refusées');
        }
      });
    });
  });
});
