// Autocomplete intelligent des villages, sans dependance externe.
(function () {
  const MIN_CHARS = 2;
  const MAX_RESULTS = 8;

  const state = {
    loaded: false,
    options: [],
    values: new Set(),
    activeField: null
  };

  function normalize(value) {
    return String(value || "").trim();
  }

  function normalizeSearch(value) {
    return normalize(value)
      .toLocaleLowerCase("fr")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
  }

  function getPlaceholder(field) {
    const id = field.id || field.name;
    const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
    return field.getAttribute("placeholder") || label?.textContent?.trim() || "Choisissez un village";
  }

  function ensureWrapper(input) {
    const currentParent = input.parentElement;
    if (currentParent?.classList.contains("hg-autocomplete")) {
      return currentParent;
    }

    const wrapper = document.createElement("div");
    wrapper.className = "hg-autocomplete";
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    return wrapper;
  }

  function createPanel(input) {
    const wrapper = ensureWrapper(input);
    let panel = wrapper.querySelector(".hg-autocomplete__panel");
    if (panel) {
      return panel;
    }

    panel = document.createElement("div");
    panel.className = "hg-autocomplete__panel";
    panel.id = `${input.id || input.name}-suggestions`;
    panel.hidden = true;
    panel.setAttribute("role", "listbox");
    wrapper.appendChild(panel);

    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-controls", panel.id);
    return panel;
  }

  function replaceSelectWithInput(select) {
    const input = document.createElement("input");
    input.type = "text";
    input.id = select.id;
    input.name = select.name;
    input.className = String(select.className || "").replace("form-select", "form-control");
    input.placeholder = getPlaceholder(select);
    input.value = normalize(select.dataset.current || select.value);
    input.autocomplete = "off";

    if (select.hasAttribute("required")) {
      input.required = true;
      input.dataset.hgRequired = "true";
      input.setAttribute("aria-required", "true");
    }

    select.replaceWith(input);
    return input;
  }

  function getMatches(query) {
    const term = normalizeSearch(query);
    if (term.length < MIN_CHARS) {
      return [];
    }

    return state.options
      .map((item) => {
        const label = normalizeSearch(item.label);
        const value = normalizeSearch(item.value);
        const starts = label.startsWith(term) || value.startsWith(term);
        const includes = !starts && (label.includes(term) || value.includes(term));
        return { item, score: starts ? 0 : includes ? 1 : 9 };
      })
      .filter((entry) => entry.score < 9)
      .sort((a, b) => a.score - b.score || a.item.label.localeCompare(b.item.label, "fr"))
      .slice(0, MAX_RESULTS)
      .map((entry) => entry.item);
  }

  function closePanel(input) {
    const panel = input ? input.closest(".hg-autocomplete")?.querySelector(".hg-autocomplete__panel") : null;
    if (panel) {
      panel.hidden = true;
      panel.innerHTML = "";
    }
    if (input) {
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
    }
  }

  function selectValue(input, value) {
    input.value = value;
    input.classList.remove("is-invalid");
    closePanel(input);
    input.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function renderPanel(input) {
    const panel = createPanel(input);
    const value = normalize(input.value);
    const matches = getMatches(value);

    panel.innerHTML = "";
    input.removeAttribute("aria-activedescendant");

    if (matches.length === 0) {
      if (value.length >= MIN_CHARS) {
        const empty = document.createElement("div");
        empty.className = "hg-autocomplete__empty";
        empty.textContent = "Aucun village trouve";
        panel.appendChild(empty);
        panel.hidden = false;
        input.setAttribute("aria-expanded", "true");
        return;
      }

      closePanel(input);
      return;
    }

    matches.forEach((match, index) => {
      const option = document.createElement("button");
      option.type = "button";
      option.className = "hg-autocomplete__option";
      option.id = `${panel.id}-${index}`;
      option.setAttribute("role", "option");
      option.dataset.value = match.value;
      const title = document.createElement("strong");
      title.textContent = match.label;
      option.appendChild(title);

      if (match.label !== match.value) {
        const meta = document.createElement("span");
        meta.textContent = match.value;
        option.appendChild(meta);
      }
      option.addEventListener("mousedown", (event) => event.preventDefault());
      option.addEventListener("click", () => selectValue(input, match.value));
      panel.appendChild(option);
    });

    panel.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  function moveActive(input, direction) {
    const panel = input.closest(".hg-autocomplete")?.querySelector(".hg-autocomplete__panel");
    const options = panel ? Array.from(panel.querySelectorAll(".hg-autocomplete__option")) : [];
    if (!options.length) {
      return;
    }

    const current = options.findIndex((option) => option.classList.contains("is-active"));
    const nextIndex = current < 0 ? (direction > 0 ? 0 : options.length - 1) : (current + direction + options.length) % options.length;

    options.forEach((option, index) => {
      const active = index === nextIndex;
      option.classList.toggle("is-active", active);
      option.setAttribute("aria-selected", active ? "true" : "false");
      if (active) {
        input.setAttribute("aria-activedescendant", option.id);
        option.scrollIntoView({ block: "nearest" });
      }
    });
  }

  function prepareVillageField(field) {
    if (field.dataset.villagesReady === "true") {
      return;
    }

    const input = field.tagName.toLowerCase() === "select" ? replaceSelectWithInput(field) : field;
    input.classList.add("form-control", "villages");
    input.setAttribute("autocomplete", "off");
    input.setAttribute("placeholder", getPlaceholder(input));
    input.dataset.villagesReady = "true";

    if (input.hasAttribute("required")) {
      input.dataset.hgRequired = "true";
      input.setAttribute("aria-required", "true");
    }

    createPanel(input);

    input.addEventListener("focus", () => {
      state.activeField = input;
      renderPanel(input);
    });

    input.addEventListener("input", () => {
      state.activeField = input;
      input.classList.remove("is-invalid");
      renderPanel(input);
    });

    input.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        renderPanel(input);
        moveActive(input, 1);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        moveActive(input, -1);
      } else if (event.key === "Enter") {
        const active = input.closest(".hg-autocomplete")?.querySelector(".hg-autocomplete__option.is-active");
        if (active) {
          event.preventDefault();
          selectValue(input, active.dataset.value);
        }
      } else if (event.key === "Escape") {
        closePanel(input);
      }
    });

    input.addEventListener("blur", () => {
      window.setTimeout(() => closePanel(input), 120);
    });
  }

  function initVillages(root) {
    if (!state.loaded) {
      return;
    }

    (root || document).querySelectorAll(".villages").forEach(prepareVillageField);
  }

  window.HaloGariVillages = {
    init: initVillages,
    isValid(value) {
      const normalized = normalize(value);
      return normalized !== "" && state.values.has(normalized);
    },
    values() {
      return Array.from(state.values);
    }
  };

  document.addEventListener("click", (event) => {
    if (state.activeField && !event.target.closest(".hg-autocomplete")) {
      closePanel(state.activeField);
    }
  });

  document.addEventListener("DOMContentLoaded", async () => {
    try {
      const response = await fetch("/cities.json", { headers: { Accept: "application/json" } });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      state.options = data
        .map((village) => {
          const value = normalize(village.name);
          return {
            value,
            label: normalize(village.name_2) || value
          };
        })
        .filter((village) => village.value !== "");

      state.values = new Set(state.options.map((village) => village.value));
      state.loaded = true;
      initVillages(document);
    } catch (error) {
      console.warn("Impossible de charger la liste des villages.", error);
      state.loaded = true;
      initVillages(document);
    }
  });
})();
