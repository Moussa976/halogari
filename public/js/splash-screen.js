// 🎬 Splash screen avec animation
document.addEventListener("DOMContentLoaded", () => {
  const splash = document.getElementById('splash-screen');
  const logo = document.getElementById('splash-logo');
  const text = document.getElementById('splash-text');

  if (!splash) return;

  if (!sessionStorage.getItem('splashShown')) {
    sessionStorage.setItem('splashShown', 'true');
    splash.style.display = 'flex';
    splash.style.opacity = '1';

    setTimeout(() => {
      logo.style.opacity = '1';
      logo.style.transform = 'scale(1)';
      text.style.opacity = '1';
      text.style.transform = 'translateY(0)';
    }, 100);

    setTimeout(() => {
      splash.style.opacity = '0';
      splash.style.transition = 'opacity 0.5s ease-out';
      setTimeout(() => splash.remove(), 500);
    }, 1800);
  } else {
    splash.remove();
  }
});