// Validation des formulaires de recherche et de publication.
(function () {
  function getVillageValues(form) {
    if (window.HaloGariVillages && typeof window.HaloGariVillages.values === "function") {
      const globalValues = window.HaloGariVillages.values();
      if (globalValues.length > 0) {
        return new Set(globalValues);
      }
    }

    return new Set(
      Array.from(form.querySelectorAll("option"))
        .map((option) => option.value)
        .filter(Boolean)
    );
  }

  function isValidVillage(value, allowedValues) {
    const normalized = String(value || "").trim();

    if (window.HaloGariVillages && typeof window.HaloGariVillages.isValid === "function") {
      const values = typeof window.HaloGariVillages.values === "function" ? window.HaloGariVillages.values() : [];
      if (values.length > 0) {
        return window.HaloGariVillages.isValid(normalized);
      }
    }

    return allowedValues.size === 0 ? normalized !== "" : allowedValues.has(normalized);
  }

  function setInvalid(field, invalid) {
    field.classList.toggle("is-invalid", invalid);
  }

  function showAlert(options) {
    if (window.Swal) {
      window.Swal.fire(options);
      return;
    }

    alert(options.text || options.title || "Veuillez verifier le formulaire.");
  }

  function firstValue(form, selectors) {
    const field = form.querySelector(selectors);
    return String(field?.value || "").trim();
  }

  function validateVillageForm(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const villages = Array.from(form.querySelectorAll(".villages"));
    if (!villages.length) {
      return;
    }

    const allowedValues = getVillageValues(form);
    let hasError = false;

    villages.forEach((field) => {
      const valid = isValidVillage(field.value, allowedValues);
      setInvalid(field, !valid);
      hasError = hasError || !valid;
    });

    const depart = firstValue(form, "[name='select_departure'], [name='departure']");
    const arrivee = firstValue(form, "[name='select_arrival'], [name='arrival']");

    if (depart && arrivee && depart === arrivee) {
      event.preventDefault();
      showAlert({
        icon: "warning",
        title: "Villages identiques",
        text: "Le village de depart et le village d'arrivee doivent etre differents."
      });
      return;
    }

    const dateField = form.querySelector("[name='date_trajet'], [name='date']");
    const dateTrajet = String(dateField?.value || "").trim();
    if (dateField && !dateTrajet) {
      event.preventDefault();
      showAlert({
        icon: "warning",
        title: "Date manquante",
        text: "Veuillez selectionner une date de trajet."
      });
      return;
    }

    if (hasError) {
      event.preventDefault();
      showAlert({
        icon: "error",
        title: "Village a verifier",
        text: "Choisissez un village dans la liste proposee pour que la recherche fonctionne correctement."
      });
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.addEventListener("submit", validateVillageForm);
  });
})();
