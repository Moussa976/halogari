let haloGariUnreadMessages = 0;
let haloGariUnreadNotifications = 0;

function updateHaloGariAppBadge() {
  const total = Math.max(0, haloGariUnreadMessages + haloGariUnreadNotifications);

  if (navigator.setAppBadge && navigator.clearAppBadge) {
    if (total > 0) {
      navigator.setAppBadge(total).catch(() => {});
    } else {
      navigator.clearAppBadge().catch(() => {});
    }
  }
}

function checkNewMessages() {
  fetch('/user/messages/unread')
    .then(response => response.json())
    .then(data => {
      haloGariUnreadMessages = Number(data.unreadCount) || 0;
      document.querySelectorAll('.message-notif').forEach(badge => {
        badge.textContent = haloGariUnreadMessages > 0 ? haloGariUnreadMessages : '';
        badge.style.display = haloGariUnreadMessages > 0 ? 'inline-block' : 'none';
      });
      updateHaloGariAppBadge();
    })
    .catch(() => {});
}

function checkNotifications() {
  fetch('/user/notifications/unread')
    .then(response => response.json())
    .then(data => {
      haloGariUnreadNotifications = Number(data.unreadCount) || 0;
      document.querySelectorAll('.notification-badge').forEach(badge => {
        badge.textContent = haloGariUnreadNotifications > 0 ? haloGariUnreadNotifications : '';
        badge.style.display = haloGariUnreadNotifications > 0 ? 'inline-block' : 'none';
      });
      updateHaloGariAppBadge();
    })
    .catch(() => {});

  fetch('/user/notifications/list', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(res => res.text())
    .then(html => {
      const container = document.querySelector('#notifDropdownMenu');
      if (container) container.innerHTML = html;
    })
    .catch(() => {});
}
