document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('app-ready');

    const progressBar = document.createElement('div');
    progressBar.className = 'hg-page-progress';
    progressBar.setAttribute('aria-hidden', 'true');
    document.body.prepend(progressBar);

    const isModifiedClick = (event) => event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

    const getInternalNavigationUrl = (link) => {
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return null;
        }

        if (
            link.matches('[download], [target]:not([target="_self"]), [data-bs-toggle], [data-no-loading], [data-confirm], [data-helpbot-toggle], .js-chat-image')
        ) {
            return null;
        }

        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) {
            return null;
        }

        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            return null;
        }

        return url;
    };

    const startPageLoading = (source) => {
        document.body.classList.add('is-page-loading');
        source?.classList.add('is-nav-loading');
        source?.setAttribute('aria-busy', 'true');
    };

    const clearPageLoading = () => {
        document.body.classList.remove('is-page-loading');
        document.querySelectorAll('.is-nav-loading').forEach((element) => {
            element.classList.remove('is-nav-loading');
            element.removeAttribute('aria-busy');
        });
    };

    const prefetchedUrls = new Set();
    const prefetchNavigation = (link) => {
        const url = getInternalNavigationUrl(link);
        if (!url || prefetchedUrls.has(url.href)) {
            return;
        }

        const path = url.pathname.toLowerCase();
        if (
            link.matches('.btn-danger, .text-danger, .app-drawer__logout')
            || /(logout|delete|supprimer|annuler|cancel|rembourser|refuser|accepter|valider|payer|paiement)/.test(path)
        ) {
            return;
        }

        prefetchedUrls.add(url.href);
        const hint = document.createElement('link');
        hint.rel = 'prefetch';
        hint.href = url.href;
        document.head.appendChild(hint);
    };

    document.querySelectorAll('.form-label').forEach((label) => {
        const parent = label.parentElement;
        if (!parent || parent.classList.contains('form-floating')) {
            return;
        }

        const control = label.nextElementSibling;
        if (!control || !control.matches('input.form-control, select.form-select, textarea.form-control')) {
            return;
        }

        const type = (control.getAttribute('type') || '').toLowerCase();
        if (['hidden', 'file', 'checkbox', 'radio'].includes(type) || control.closest('.input-group')) {
            return;
        }

        if (!control.id) {
            control.id = `${control.name || 'field'}-${Math.random().toString(36).slice(2, 8)}`;
        }

        label.setAttribute('for', control.id);
        control.setAttribute('placeholder', control.getAttribute('placeholder') || label.textContent.replace(':', '').trim());

        const wrapper = document.createElement('div');
        wrapper.className = 'form-floating app-smart-field';
        parent.insertBefore(wrapper, label);
        wrapper.appendChild(control);
        wrapper.appendChild(label);
    });

    document.querySelectorAll('input[type="email"]').forEach((input) => {
        input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'email');
        input.setAttribute('inputmode', input.getAttribute('inputmode') || 'email');
    });

    document.querySelectorAll('input[type="tel"], input[name*="telephone" i], input[name*="phone" i]').forEach((input) => {
        input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'tel');
        input.setAttribute('inputmode', input.getAttribute('inputmode') || 'tel');
    });

    document.querySelectorAll('input[name*="nom" i]').forEach((input) => {
        input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'family-name');
    });

    document.querySelectorAll('input[name*="prenom" i]').forEach((input) => {
        input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'given-name');
    });

    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const group = input.closest('.input-group');
        if (group) {
            group.classList.add('password-input-group');
        }
    });

    document.querySelectorAll('[data-password-rules]').forEach((rules) => {
        const input = document.getElementById(rules.dataset.for);
        if (!input) {
            return;
        }

        const updateRules = () => {
            const value = input.value || '';
            const checks = {
                length: value.length >= 8,
                lower: /[a-z]/.test(value),
                upper: /[A-Z]/.test(value),
                number: /\d/.test(value),
                special: /[\W_]/.test(value),
            };

            Object.entries(checks).forEach(([rule, ok]) => {
                rules.querySelector(`[data-rule="${rule}"]`)?.classList.toggle('is-ok', ok);
            });
        };

        updateRules();
        input.addEventListener('input', updateRules);
    });

    const getSmartMessage = (field) => {
        const label = document.querySelector(`label[for="${field.id}"]`)?.textContent?.trim() || field.getAttribute('aria-label') || 'Ce champ';
        const name = label.replace(/\s+/g, ' ');

        if (field.validity.valueMissing) {
            return `${name} est obligatoire.`;
        }

        if (field.validity.typeMismatch && field.type === 'email') {
            return 'Entrez une adresse e-mail valide, par exemple nom@exemple.com.';
        }

        if (field.validity.tooShort) {
            return `${name} doit contenir au moins ${field.minLength} caractères.`;
        }

        if (field.validity.rangeUnderflow) {
            return `${name} doit être supérieur ou égal à ${field.min}.`;
        }

        if (field.validity.rangeOverflow) {
            return `${name} doit être inférieur ou égal à ${field.max}.`;
        }

        if (field.validity.patternMismatch) {
            return `${name} ne respecte pas le format attendu.`;
        }

        return field.validationMessage || `${name} est à corriger.`;
    };

    const getFeedback = (field) => {
        const describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        const existing = describedBy.map((id) => document.getElementById(id)).find((item) => item?.classList.contains('invalid-feedback'));
        if (existing) {
            return existing;
        }

        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback smart-feedback';
        feedback.id = `${field.id || field.name || 'field'}-smart-feedback`;
        const current = field.getAttribute('aria-describedby');
        field.setAttribute('aria-describedby', [current, feedback.id].filter(Boolean).join(' '));
        (field.closest('.input-group') || field.closest('.form-floating') || field).insertAdjacentElement('afterend', feedback);
        return feedback;
    };

    document.querySelectorAll('form').forEach((form) => {
        form.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                if (field.checkValidity()) {
                    field.classList.remove('is-invalid');
                    const feedback = getFeedback(field);
                    if (feedback.classList.contains('smart-feedback')) {
                        feedback.textContent = '';
                    }
                }
            });
        });

        form.addEventListener('submit', (event) => {
            const fields = form.querySelectorAll('input, select, textarea');
            const invalidFields = [...fields].filter((field) => !field.disabled && field.willValidate && !field.checkValidity());
            if (!invalidFields.length) {
                return;
            }

            event.preventDefault();
            invalidFields.forEach((field) => {
                field.classList.add('is-invalid');
                getFeedback(field).textContent = getSmartMessage(field);
            });

            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalidFields[0].focus({ preventScroll: true });
        });
    });

    const setButtonLoading = (button) => {
        if (!button || button.classList.contains('is-loading')) {
            return;
        }

        button.dataset.loadingHtml = button.innerHTML;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        if (button.tagName === 'BUTTON') {
            button.disabled = true;
        } else {
            button.setAttribute('aria-disabled', 'true');
        }

        const label = button.dataset.loadingLabel || 'Chargement';
        button.innerHTML = `<span class="hg-btn-spinner" aria-hidden="true"></span><span>${label}</span>`;
    };

    const resetButtonLoading = (button) => {
        if (!button || !button.classList.contains('is-loading')) {
            return;
        }

        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');

        if (button.dataset.loadingHtml) {
            button.innerHTML = button.dataset.loadingHtml;
            delete button.dataset.loadingHtml;
        }

        if (button.tagName === 'BUTTON') {
            button.disabled = false;
        } else {
            button.removeAttribute('aria-disabled');
        }
    };

    const hasInvalidVillage = (form) => {
        const villages = [...form.querySelectorAll('.villages')];
        if (!villages.length || !window.HaloGariVillages || typeof window.HaloGariVillages.isValid !== 'function') {
            return false;
        }

        const loadedValues = typeof window.HaloGariVillages.values === 'function' ? window.HaloGariVillages.values() : [];
        if (!loadedValues.length) {
            return false;
        }

        return villages.some((field) => !window.HaloGariVillages.isValid(field.value));
    };

    const hasSameVillageRoute = (form) => {
        const departure = form.querySelector("[name='select_departure'], [name='departure']");
        const arrival = form.querySelector("[name='select_arrival'], [name='arrival']");
        const departureValue = String(departure?.value || '').trim();
        const arrivalValue = String(arrival?.value || '').trim();

        return departureValue !== '' && arrivalValue !== '' && departureValue === arrivalValue;
    };

    window.HaloGariSetButtonLoading = setButtonLoading;
    window.HaloGariResetButtonLoading = resetButtonLoading;

    const resetPageLoadingStates = () => {
        clearPageLoading();
        document.querySelectorAll('[data-loading-button].is-loading').forEach(resetButtonLoading);
    };

    window.HaloGariResetPageLoadingStates = resetPageLoadingStates;
    window.addEventListener('pageshow', resetPageLoadingStates);

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (
                form.matches('[data-loading-defer]') ||
                !form.checkValidity() ||
                hasInvalidVillage(form) ||
                hasSameVillageRoute(form)
            ) {
                return;
            }

            const afterSubmitListeners = window.queueMicrotask || ((callback) => window.setTimeout(callback, 0));
            afterSubmitListeners(() => {
                if (event.defaultPrevented) {
                    return;
                }

                const submitter = event.submitter || form.querySelector('[type="submit"][data-loading-button]');
                if (submitter?.matches('[data-loading-button]')) {
                    setButtonLoading(submitter);
                }

                if (!form.matches('[data-no-loading]')) {
                    startPageLoading(submitter || form);
                }
            });
        });
    });

    document.querySelectorAll('a[data-loading-button]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || link.classList.contains('is-disabled') || link.getAttribute('href') === '#') {
                return;
            }

            setButtonLoading(link);
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('pointerenter', () => prefetchNavigation(link), { passive: true });
        link.addEventListener('touchstart', () => prefetchNavigation(link), { passive: true });

        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || isModifiedClick(event) || link.classList.contains('is-disabled')) {
                return;
            }

            if (!getInternalNavigationUrl(link)) {
                return;
            }

            startPageLoading(link);
        });
    });

    const formatSearchDateSummary = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return "Aujourd'hui";
        }

        const normalized = raw
            .replace(/^aujourd['’]hui$/i, "Aujourd'hui")
            .replace(/^demain$/i, 'Demain');

        if (normalized === "Aujourd'hui" || normalized === 'Demain') {
            return normalized;
        }

        const parts = normalized.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!parts) {
            return normalized;
        }

        const [, day, month, year] = parts;
        const selected = new Date(Number(year), Number(month) - 1, Number(day));
        selected.setHours(0, 0, 0, 0);

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        if (selected.getTime() === today.getTime()) {
            return "Aujourd'hui";
        }

        if (selected.getTime() === tomorrow.getTime()) {
            return 'Demain';
        }

        return normalized;
    };

    const refreshSearchTriggerSummary = (trigger) => {
        const formId = trigger.getAttribute('aria-controls');
        const form = formId ? document.getElementById(formId) : null;
        if (!form) {
            return;
        }

        const departure = form.querySelector('[name="select_departure"]')?.value.trim();
        const arrival = form.querySelector('[name="select_arrival"]')?.value.trim();
        const date = form.querySelector('[name="date_trajet"]')?.value;
        const places = Number(form.querySelector('[name="places_min"]')?.value || 1);
        const passengersLabel = `${places || 1} passager${places > 1 ? 's' : ''}`;

        const title = trigger.querySelector('strong');
        const details = trigger.querySelector('small');

        if (title) {
            title.textContent = `${departure || 'Votre départ'} → ${arrival || 'Votre destination'}`;
        }

        if (details) {
            details.textContent = `${formatSearchDateSummary(date)}, ${passengersLabel}`;
        }
    };

    document.querySelectorAll('.home-search-mobile-trigger[aria-controls]').forEach((trigger) => {
        const formId = trigger.getAttribute('aria-controls');
        const form = formId ? document.getElementById(formId) : null;
        if (!form) {
            return;
        }

        const refresh = () => refreshSearchTriggerSummary(trigger);
        refresh();

        form.querySelectorAll('[name="select_departure"], [name="select_arrival"], [name="date_trajet"], [name="places_min"]').forEach((field) => {
            field.addEventListener('input', refresh);
            field.addEventListener('change', refresh);
            field.addEventListener('blur', refresh);
        });

        document.addEventListener('autocompletechange', refresh);
        window.addEventListener('pageshow', refresh);
        window.setTimeout(refresh, 250);
    });

    const revealItems = document.querySelectorAll(
        '.card, .feature-card, .route-item, .list-group-item, .quick-search, .app-section-heading, .ride-card, .app-panel'
    );

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });

        revealItems.forEach((item, index) => {
            item.classList.add('app-reveal');
            item.classList.add(index % 2 ? 'app-reveal--right' : 'app-reveal--left');
            item.style.setProperty('--reveal-delay', `${Math.min(index * 45, 360)}ms`);
            observer.observe(item);
        });
    }

    const updateScrollState = () => {
        const scrolled = window.scrollY > 18;
        document.body.classList.toggle('app-is-scrolled', scrolled);
        document.documentElement.style.setProperty('--scroll-y', `${Math.min(window.scrollY, 420)}px`);
        document.documentElement.style.setProperty('--hero-shift', `${Math.min(window.scrollY * 0.035, 18)}px`);
    };

    updateScrollState();
    window.addEventListener('scroll', updateScrollState, { passive: true });

    document.querySelectorAll('.btn, .mobile-tabbar a, .route-item, .listtrajet').forEach((element) => {
        element.addEventListener('pointerdown', () => element.classList.add('is-pressing'));
        element.addEventListener('pointerup', () => element.classList.remove('is-pressing'));
        element.addEventListener('pointerleave', () => element.classList.remove('is-pressing'));
    });

    document.querySelectorAll('[data-confirm]').forEach((trigger) => {
        trigger.addEventListener('click', async (event) => {
            if (trigger.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            const message = trigger.dataset.confirm || 'Confirmer cette action ?';
            let confirmed = true;

            if (window.Swal) {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: 'Confirmer',
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: 'Oui, continuer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#d33'
                });
                confirmed = result.isConfirmed;
            } else {
                confirmed = window.confirm(message);
            }

            if (!confirmed) {
                return;
            }

            trigger.dataset.confirmed = 'true';
            const form = trigger.closest('form');
            if (form) {
                form.requestSubmit(trigger);
            } else if (trigger.href) {
                window.location.href = trigger.href;
            }
        });
    });

    const helpbot = document.querySelector('[data-helpbot]');
    if (helpbot) {
        const trigger = helpbot.querySelector('[data-helpbot-toggle]');
        const closeButton = helpbot.querySelector('[data-helpbot-close]');
        const panel = helpbot.querySelector('.hg-helpbot__panel');
        const search = helpbot.querySelector('[data-helpbot-search]');
        const tabs = helpbot.querySelectorAll('[data-helpbot-tab]');
        const groups = helpbot.querySelectorAll('[data-helpbot-group]');
        const questionButtons = helpbot.querySelectorAll('[data-helpbot-question]');
        const answerBox = helpbot.querySelector('[data-helpbot-answer-box]');
        const defaultAnswer = answerBox ? answerBox.innerHTML : '';
        let activeTab = 'passager';

        const setOpen = (open) => {
            if (!panel || !trigger) {
                return;
            }

            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open && search) {
                window.setTimeout(() => search.focus(), 80);
            }
        };

        const activateTab = (name) => {
            activeTab = name;
            tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.helpbotTab === name));
            groups.forEach((group) => group.classList.toggle('active', group.dataset.helpbotGroup === name));

            if (search) {
                search.value = '';
            }

            questionButtons.forEach((button) => {
                button.hidden = button.closest('[data-helpbot-group]')?.dataset.helpbotGroup !== name;
                button.classList.remove('active');
            });

            const empty = helpbot.querySelector('.hg-helpbot__empty');
            if (empty) {
                empty.remove();
            }

            if (answerBox) {
                answerBox.innerHTML = defaultAnswer;
            }
        };

        const showAnswer = (button) => {
            if (!answerBox) {
                return;
            }

            questionButtons.forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            answerBox.innerHTML = `<strong>${button.dataset.helpbotQuestion}</strong>${button.dataset.helpbotAnswer}`;
            panel?.scrollTo({ top: 0, behavior: 'smooth' });
        };

        const filterQuestions = () => {
            const term = (search?.value || '').trim().toLowerCase();
            const currentGroup = helpbot.querySelector(`[data-helpbot-group="${activeTab}"]`);
            let visibleCount = 0;

            questionButtons.forEach((button) => {
                const belongsToActiveTab = button.closest('[data-helpbot-group]')?.dataset.helpbotGroup === activeTab;
                const haystack = `${button.dataset.helpbotQuestion || ''} ${button.dataset.helpbotAnswer || ''}`.toLowerCase();
                const visible = belongsToActiveTab && (!term || haystack.includes(term));
                button.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            const previousEmpty = helpbot.querySelector('.hg-helpbot__empty');
            if (previousEmpty) {
                previousEmpty.remove();
            }

            if (!visibleCount && currentGroup) {
                const empty = document.createElement('div');
                empty.className = 'hg-helpbot__empty';
                empty.textContent = 'Aucune réponse trouvée. Essayez un autre mot ou contactez le support.';
                currentGroup.appendChild(empty);
            }
        };

        trigger?.addEventListener('click', () => {
            setOpen(panel?.hidden !== false);
        });

        document.querySelectorAll('[data-helpbot-mobile-open]').forEach((button) => {
            button.addEventListener('click', () => {
                window.setTimeout(() => setOpen(true), 180);
            });
        });

        closeButton?.addEventListener('click', () => setOpen(false));

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => activateTab(tab.dataset.helpbotTab));
        });

        questionButtons.forEach((button) => {
            button.addEventListener('click', () => showAnswer(button));
        });

        search?.addEventListener('input', filterQuestions);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && panel && !panel.hidden) {
                setOpen(false);
            }
        });

        activateTab(activeTab);
    }
});

(() => {
    const MAYOTTE_CENTER = [-12.8275, 45.1662];
    let villageCoordinatesPromise = null;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const normalizeVillageName = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[’`]/g, "'")
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();

    const createRouteIcon = (url) => L.icon({
        iconUrl: url,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        shadowSize: [41, 41],
    });

    const loadVillageCoordinates = async () => {
        if (!villageCoordinatesPromise) {
            villageCoordinatesPromise = fetch('/cities.json', {
                headers: { Accept: 'application/json' },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Village catalog unavailable');
                    }

                    return response.json();
                })
                .then((cities) => {
                    const coordinates = new Map();

                    cities.forEach((city) => {
                        const point = [Number(city.lat), Number(city.lon)];
                        if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) {
                            return;
                        }

                        [city.name, city.name_2].filter(Boolean).forEach((name) => {
                            coordinates.set(normalizeVillageName(name), point);
                        });
                    });

                    return coordinates;
                });
        }

        return villageCoordinatesPromise;
    };

    const geocodeRouteVillage = async (village) => {
        const villages = await loadVillageCoordinates().catch(() => new Map());
        const localPoint = villages.get(normalizeVillageName(village));
        if (localPoint) {
            return localPoint;
        }

        const query = encodeURIComponent(`${village}, Mayotte`);
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${query}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Geocoding failed');
        }

        const results = await response.json();
        if (!results.length) {
            throw new Error('Village not found');
        }

        return [parseFloat(results[0].lat), parseFloat(results[0].lon)];
    };

    const fetchRouteGeometry = async (from, to) => {
        const coords = `${from[1]},${from[0]};${to[1]},${to[0]}`;
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Route unavailable');
        }

        const payload = await response.json();
        const geometry = payload.routes?.[0]?.geometry;

        if (!geometry) {
            throw new Error('Route missing');
        }

        return geometry;
    };

    const initRouteMap = (mapElement) => {
        const modal = mapElement.closest('.modal');
        if (!modal || mapElement.dataset.routeMapBound === '1') {
            return;
        }

        mapElement.dataset.routeMapBound = '1';

        let map = null;
        let routeLoaded = false;
        let routeLayer = null;
        let markers = [];

        const showMapError = () => {
            mapElement.classList.add('reservation-route-map--error');
            mapElement.innerHTML = "<div class=\"reservation-map-error\"><i class=\"bi bi-exclamation-triangle\"></i><strong>Carte indisponible</strong><span>Impossible d'afficher le trajet pour le moment.</span></div>";
        };

        const clearRoute = () => {
            if (routeLayer) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }

            markers.forEach((marker) => marker.remove());
            markers = [];
        };

        const loadRoute = async () => {
            if (routeLoaded || !map) {
                return;
            }

            routeLoaded = true;
            clearRoute();
            const depart = mapElement.dataset.depart || '';
            const arrivee = mapElement.dataset.arrivee || '';

            try {
                const [from, to] = await Promise.all([
                    geocodeRouteVillage(depart),
                    geocodeRouteVillage(arrivee),
                ]);

                const departMarker = L.marker(from, { icon: createRouteIcon(mapElement.dataset.departIcon) })
                    .addTo(map)
                    .bindPopup(`<strong>Départ</strong><br>${escapeHtml(depart)}`);

                const arriveeMarker = L.marker(to, { icon: createRouteIcon(mapElement.dataset.arriveeIcon) })
                    .addTo(map)
                    .bindPopup(`<strong>Arrivée</strong><br>${escapeHtml(arrivee)}`);

                markers.push(departMarker, arriveeMarker);

                const geometry = await fetchRouteGeometry(from, to);
                routeLayer = L.geoJSON(geometry, {
                    style: () => ({
                        color: '#ff8000',
                        lineCap: 'round',
                        lineJoin: 'round',
                        opacity: 0.95,
                        weight: 5,
                    }),
                }).addTo(map);

                map.fitBounds(routeLayer.getBounds(), { padding: [34, 34] });
                window.setTimeout(() => map.invalidateSize(), 80);
            } catch (error) {
                routeLoaded = false;
                showMapError();
            }
        };

        modal.addEventListener('shown.bs.modal', () => {
            if (!map) {
                mapElement.innerHTML = '';
                map = L.map(mapElement, { scrollWheelZoom: false }).setView(MAYOTTE_CENTER, 10);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
            }

            window.setTimeout(() => map.invalidateSize(), 80);
            window.setTimeout(() => {
                map.invalidateSize();
                loadRoute();
            }, 220);
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof L === 'undefined') {
            return;
        }

        document.querySelectorAll('.js-route-map').forEach(initRouteMap);
    });
})();
