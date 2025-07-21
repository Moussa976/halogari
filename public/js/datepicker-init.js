// 📅 Initialisation du sélecteur de date (flatpickr)
$(document).ready(function () {
  flatpickr(".dateDepart", {
    locale: "fr",
    altInput: true,
    altFormat: "j F Y",
    dateFormat: "Y-m-d",
    minDate: "today"
  });
});