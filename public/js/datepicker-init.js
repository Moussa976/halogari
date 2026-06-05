// Initialisation du selecteur de date Flatpickr.
document.addEventListener("DOMContentLoaded", () => {
  if (typeof window.flatpickr !== "function") {
    return;
  }

  const getCalendarParent = (input) => input.closest(".modal, .offcanvas") || document.body;

  document.querySelectorAll(".dateDepart").forEach((input) => {
    window.flatpickr(input, {
      locale: "fr",
      allowInput: true,
      dateFormat: "d/m/Y",
      minDate: "today",
      appendTo: getCalendarParent(input),
      onReady: function () {
        input.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
      }
    });
  });

  document.querySelectorAll(".dateFr").forEach((input) => {
    window.flatpickr(input, {
      locale: "fr",
      allowInput: true,
      dateFormat: "d/m/Y",
      maxDate: input.dataset.maxDate || null,
      appendTo: getCalendarParent(input),
      onReady: function () {
        input.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
      }
    });
  });
});
