// ✅ Validation formulaire de recherche
$(document).ready(function () {
  $("#form-recherche").on("submit", function (e) {
    let erreur = false;
    const options = $("select.villages option").map(function () {
      return this.text;
    }).get();

    $(".villages").each(function () {
      const valeur = $(this).val();
      const valide = valeur && options.includes(valeur);
      if (valide) {
        $(this).removeClass("is-invalid");
      } else {
        $(this).addClass("is-invalid");
        erreur = true;
      }
    });

    const depart = $("select[name='select_departure']").val();
    const arrivee = $("select[name='select_arrival']").val();
    if (depart && arrivee && depart === arrivee) {
      erreur = true;
      Swal.fire({ icon: "warning", title: "Villages identiques", text: "Le village de départ et d'arrivée ne peuvent pas être identiques." });
      e.preventDefault();
      return;
    }

    const dateTrajet = $("input[name='date_trajet']").val();
    if (!dateTrajet) {
      e.preventDefault();
      Swal.fire({ icon: "warning", title: "Date manquante", text: "Veuillez sélectionner une date de trajet." });
      return;
    }

    if (erreur) {
      e.preventDefault();
      Swal.fire({ icon: "error", title: "Erreur", text: "Veuillez sélectionner des villages valides." });
    }
  });
});