// public/js/villages.js

const VILLAGES = {
  apiKey: '5b3ce3597851110001cf6248c90e14bce4464ae992638cb4242e3350',
  data: [],
  options: [],

  async loadCities() {
    const res = await fetch('/cities.json');
    this.data = await res.json();
    this.options = this.data.map(v => ({ id: v.name, text: v.name_2 }));
  },

  getCoords(name) {
    return this.data.find(c => c.name === name);
  },

  getPrixMax(distanceKm) {
    if (distanceKm < 14) return 3;
    if (distanceKm < 28) return 5;
    if (distanceKm < 42) return 7;
    if (distanceKm < 56) return 9;
    if (distanceKm < 70) return 11;
    return 13;
  },

  getRoute(from, to) {
    return fetch('https://api.openrouteservice.org/v2/directions/driving-car/geojson', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': this.apiKey
      },
      body: JSON.stringify({
        coordinates: [
          [from.lon, from.lat],
          [to.lon, to.lat]
        ]
      })
    }).then(res => res.json());
  },

  geocode(village) {
    return fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(village)}`)
      .then(res => res.json())
      .then(data => {
        if (data.length > 0)
          return [parseFloat(data[0].lon), parseFloat(data[0].lat)];
        throw new Error(`Village non trouvé : ${village}`);
      });
  },

  applySelect2(selector = ".villages") {
    const $ = window.jQuery;

    $(selector).each(function () {
      const $el = $(this);
      const currentValue = $el.attr("data-current") || $el.val();
      const placeholder = $el.attr("placeholder") || "Choisissez un village";
      $el.empty().append('<option></option>');

      VILLAGES.options.forEach(opt => {
        const isSelected = opt.id === currentValue;
        $el.append(new Option(opt.text, opt.id, false, isSelected));
      });

      if (currentValue && !VILLAGES.options.find(opt => opt.id === currentValue)) {
        $el.append(new Option(currentValue, currentValue, true, true));
      }

      $el.select2({
        placeholder,
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
    });
  }
};