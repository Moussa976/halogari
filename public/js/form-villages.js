// 🌍 Autocomplétion des villages avec Select2
$(document).ready(function () {
  $.getJSON('/cities.json').done(function (data) {
    const options = data.map(v => ({ name: v.name, name_2: v.name_2 || v.name }));

    $(".villages").each(function () {
      const $el = $(this);
      const tagName = $el.prop("tagName").toLowerCase();
      const currentValue = $el.attr("data-current") || $el.val();
      const name = $el.attr("name");
      const id = $el.attr("id") || name;
      const placeholder = $el.attr("placeholder") || "Choisissez un village";
      let $select;

      if (tagName === "select") {
        $select = $el.empty();
      } else {
        $select = $('<select>', { id, name, class: "form-select villages", required: true });
        const $wrapper = $el.closest(".form-floating");
        $el.remove();
        $wrapper.prepend($select);
        $wrapper.append(`<label for="${id}">${placeholder}</label>`);
      }

      $select.append('<option></option>');
      let foundInList = false;

      options.forEach(opt => {
        const value = opt.name;
        const label = opt.name_2 || value;
        const isSelected = value === currentValue;
        if (isSelected) foundInList = true;
        $select.append(new Option(label, value, false, isSelected));
      });

      if (!foundInList && currentValue) {
        $select.append(new Option(currentValue, currentValue, true, true));
      }

      $select.select2({
        placeholder: placeholder,
        allowClear: true,
        width: "100%",
        language: "fr"
      }).on('select2:open', function () {
        const searchInput = document.querySelector('.select2-search__field');
        if (searchInput) {
          searchInput.setAttribute('placeholder', 'Recherchez le nom ...');
          searchInput.classList.add('form-control');
        }
        $('.select2-results__options').addClass('bg-vert text-white');
      });

      $select.val(currentValue).trigger("change");
    });
  });
});