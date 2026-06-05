// 👁️ Voir / masquer mot de passe
document.querySelectorAll('.toggle-password').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.target) || button.closest('form')?.querySelector(`#${button.dataset.target}`);
    if (!input) {
      return;
    }
    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
    input.toggleAttribute('data-password-visible', isPassword);
    button.innerHTML = isPassword
      ? '<i class="bi bi-eye-fill"></i>'
      : '<i class="bi bi-eye-slash-fill"></i>';
  });
});
