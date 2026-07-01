// Initialisation du selecteur de date Flatpickr.
function initHaloGariDatepickers() {
  if (typeof window.flatpickr !== "function") {
    return;
  }

  const getCalendarParent = () => document.body;

  document.querySelectorAll(".dateDepart").forEach((input) => {
    if (input._flatpickr) {
      return;
    }

    window.flatpickr(input, {
      locale: "fr",
      allowInput: true,
      disableMobile: true,
      dateFormat: "d/m/Y",
      defaultDate: input.value || (input.dataset.defaultToday === "true" ? "today" : null),
      minDate: "today",
      appendTo: getCalendarParent(input),
      onReady: function () {
        input.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
      }
    });
  });

  document.querySelectorAll(".dateFr").forEach((input) => {
    if (input._flatpickr) {
      return;
    }

    window.flatpickr(input, {
      locale: "fr",
      allowInput: true,
      disableMobile: true,
      dateFormat: "d/m/Y",
      maxDate: input.dataset.maxDate || null,
      appendTo: getCalendarParent(input),
      onReady: function () {
        input.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
      }
    });
  });
}

document.addEventListener("DOMContentLoaded", initHaloGariDatepickers);
window.addEventListener("pageshow", initHaloGariDatepickers);
document.addEventListener("shown.bs.offcanvas", initHaloGariDatepickers);
document.addEventListener("shown.bs.modal", initHaloGariDatepickers);
