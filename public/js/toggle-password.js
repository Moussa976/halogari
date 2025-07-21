// 👁️ Voir / masquer mot de passe
document.querySelectorAll('.toggle-password').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.target);
    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
    button.innerHTML = isPassword
      ? '<i class="bi bi-eye-fill"></i>'
      : '<i class="bi bi-eye-slash-fill"></i>';
  });
});