// Initialisation du selecteur de date Flatpickr.
function initHaloGariDatepickers() {
  if (typeof window.flatpickr !== "function") {
    return;
  }

  const getCalendarParent = () => document.body;
  const pad = (value) => String(value).padStart(2, "0");
  const dateKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const displayDateLabel = (date) => {
    if (!date) {
      return "";
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    const current = new Date(date);
    current.setHours(0, 0, 0, 0);

    if (dateKey(current) === dateKey(today)) {
      return "Aujourd'hui";
    }

    if (dateKey(current) === dateKey(tomorrow)) {
      return "Demain";
    }

    return `${pad(current.getDate())}/${pad(current.getMonth() + 1)}/${current.getFullYear()}`;
  };
  const refreshFriendlyDate = (picker) => {
    if (!picker || !picker.altInput) {
      return;
    }

    picker.altInput.value = displayDateLabel(picker.selectedDates[0]) || picker.input.value;
  };

  document.querySelectorAll(".dateDepart").forEach((input) => {
    if (input._flatpickr || input.readOnly || input.disabled || input.dataset.noDatepicker === "true") {
      return;
    }

    window.flatpickr(input, {
      locale: "fr",
      allowInput: false,
      disableMobile: true,
      altInput: true,
      altFormat: "d/m/Y",
      dateFormat: "d/m/Y",
      defaultDate: input.value || (input.dataset.defaultToday === "true" ? "today" : null),
      minDate: "today",
      appendTo: getCalendarParent(input),
      onReady: function (selectedDates, dateStr, picker) {
        input.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
        picker.altInput.setAttribute("placeholder", input.getAttribute("placeholder") || "jj/mm/aaaa");
        picker.altInput.dataset.noDatepicker = "true";
        picker.altInput.classList.remove("dateDepart");
        refreshFriendlyDate(picker);
      },
      onChange: function (selectedDates, dateStr, picker) {
        refreshFriendlyDate(picker);
      },
      onClose: function (selectedDates, dateStr, picker) {
        refreshFriendlyDate(picker);
      },
    });
  });

  document.querySelectorAll(".dateFr").forEach((input) => {
    if (input._flatpickr || input.readOnly || input.disabled || input.dataset.noDatepicker === "true") {
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
