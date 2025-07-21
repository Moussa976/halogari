// 📬 Vérification des messages et notifications
function checkNewMessages() {
  fetch('/user/messages/unread')
    .then(response => response.json())
    .then(data => {
      document.querySelectorAll('.message-notif').forEach(badge => {
        badge.textContent = data.unreadCount > 0 ? data.unreadCount : '';
        badge.style.display = data.unreadCount > 0 ? 'inline-block' : 'none';
      });
    });
}

function checkNotifications() {
  fetch('/user/notifications/unread')
    .then(response => response.json())
    .then(data => {
      document.querySelectorAll('.notification-badge').forEach(badge => {
        badge.textContent = data.unreadCount > 0 ? data.unreadCount : '';
        badge.style.display = data.unreadCount > 0 ? 'inline-block' : 'none';
      });
    });

  fetch('/user/notifications/list')
    .then(res => res.text())
    .then(html => {
      const container = document.querySelector('#notifDropdownMenu');
      if (container) container.innerHTML = html;
    });
}