document.addEventListener('DOMContentLoaded', () => {
    const debounce = (callback, delay = 250) => {
        let timeoutId = null;
        return (...args) => {
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
            timeoutId = window.setTimeout(() => {
                callback(...args);
            }, delay);
        };
    };

    // Handle mobile navigation toggle to keep menu accessible on small screens.
    const mobileNavToggle = document.querySelector('[data-mobile-nav-toggle]');
    const mobileNavPanel = document.querySelector('[data-mobile-nav-panel]');
    if (mobileNavToggle && mobileNavPanel) {
        const menuIcons = mobileNavToggle.querySelectorAll('[data-menu-icon]');
        const body = document.body;
        const setExpanded = (expanded) => {
            mobileNavToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            mobileNavPanel.classList.toggle('hidden', !expanded);
            body.classList.toggle('overflow-hidden', expanded);
            menuIcons.forEach((icon) => {
                const isOpenIcon = icon.dataset.menuIcon === 'open';
                icon.classList.toggle('hidden', expanded ? isOpenIcon : !isOpenIcon);
            });
        };

        setExpanded(false);

        mobileNavToggle.addEventListener('click', () => {
            const isExpanded = mobileNavToggle.getAttribute('aria-expanded') === 'true';
            setExpanded(!isExpanded);
        });

        mobileNavPanel.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setExpanded(false));
        });

        const mdQuery = window.matchMedia('(min-width: 768px)');
        const handleViewportChange = (event) => {
            if (event.matches) {
                setExpanded(false);
            }
        };
        if (typeof mdQuery.addEventListener === 'function') {
            mdQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mdQuery.addListener === 'function') {
            mdQuery.addListener(handleViewportChange);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setExpanded(false);
            }
        });
    }

    const navItems = document.querySelectorAll('[data-nav-target]');
    const currentPage = document.body.dataset.currentPage;

    navItems.forEach((item) => {
        const target = item.getAttribute('data-nav-target');
        if (target === currentPage) {
            item.classList.add('scale-105');
            item.classList.remove('text-gray-400', 'text-gray-300');
            if (item.classList.contains('bg-white')) {
                item.classList.add('text-black');
                item.classList.remove('text-white');
            } else {
                item.classList.add('text-white');
            }
        }

        item.addEventListener('click', () => {
            navItems.forEach((link) => link.classList.remove('scale-105'));
            item.classList.add('scale-105');
        });
    });

    const initProfileSearchInstant = () => {
        const root = document.querySelector('[data-profile-search-root]');
        if (!root) {
            return;
        }

        const form = root.querySelector('[data-profile-search-form]');
        const input = root.querySelector('[data-profile-search-input]');
        const resultsContainer = root.querySelector('[data-profile-search-results]');
        const statusElement = root.querySelector('[data-profile-search-status]');
        const resetControls = root.querySelectorAll('[data-profile-search-reset]');

        if (!form || !input || !resultsContainer) {
            return;
        }

        const minimumLength = parseInt(root.dataset.profileSearchMinLength || '2', 10);
        let renderedQuery = (root.dataset.profileSearchInitialQuery || '').trim();
        let renderedPage = parseInt(root.dataset.profileSearchInitialPage || '1', 10);
        if (Number.isNaN(renderedPage) || renderedPage < 1) {
            renderedPage = 1;
        }

        const supportsAbort = typeof AbortController !== 'undefined';
        let activeController = null;

        const setResetVisibility = (shouldShow) => {
            resetControls.forEach((control) => {
                if (control instanceof HTMLElement) {
                    control.classList.toggle('hidden', !shouldShow);
                }
            });
        };

        setResetVisibility(renderedQuery !== '');

        const clearStatusClasses = () => {
            if (!statusElement) {
                return;
            }
            statusElement.classList.remove(
                'border-rose-500/40',
                'bg-rose-500/10',
                'text-rose-200',
                'border-amber-400/50',
                'bg-amber-500/10',
                'text-amber-200'
            );
        };

        const setStatus = (message, tone = 'info') => {
            if (!statusElement) {
                return;
            }

            const text = message ? String(message).trim() : '';
            if (text === '') {
                statusElement.textContent = '';
                statusElement.classList.add('hidden');
                clearStatusClasses();
                return;
            }

            statusElement.textContent = text;
            statusElement.classList.remove('hidden');
            clearStatusClasses();

            if (tone === 'error') {
                statusElement.classList.add('border-rose-500/40', 'bg-rose-500/10', 'text-rose-200');
            } else if (tone === 'loading') {
                statusElement.classList.add('border-amber-400/50', 'bg-amber-500/10', 'text-amber-200');
            }
        };

        const setFormDisabled = (state) => {
            form.querySelectorAll('button, input[type="submit"]').forEach((control) => {
                // eslint-disable-next-line no-param-reassign
                control.disabled = state;
            });
            form.classList.toggle('opacity-75', state);
        };

        const updateHistory = (query, page) => {
            if (!window.history || typeof window.history.replaceState !== 'function') {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('page', 'profile_search');

            const trimmedQuery = query.trim();
            if (trimmedQuery === '') {
                url.searchParams.delete('q');
            } else {
                url.searchParams.set('q', trimmedQuery);
            }

            if (page > 1) {
                url.searchParams.set('p', String(page));
            } else {
                url.searchParams.delete('p');
            }

            url.searchParams.delete('ajax');
            window.history.replaceState(null, '', url.toString());
        };

        const buildRequestUrl = (query, page) => {
            const action = form.getAttribute('action') || window.location.href;
            const url = new URL(action, window.location.href);

            if (!url.searchParams.has('page')) {
                url.searchParams.set('page', 'profile_search');
            }

            url.searchParams.set('ajax', '1');
            url.searchParams.set('q', query);
            url.searchParams.set('p', String(page));

            return url;
        };

        const performRequest = (query, page, options = {}) => {
            const normalizedQuery = query.trim();
            const targetPage = page > 0 ? page : 1;

            if (!options.force && normalizedQuery === renderedQuery && targetPage === renderedPage) {
                return;
            }

            if (supportsAbort && activeController) {
                activeController.abort();
                activeController = null;
            }

            const requestUrl = buildRequestUrl(normalizedQuery, targetPage);
            const controller = supportsAbort ? new AbortController() : null;
            if (controller) {
                activeController = controller;
            }

            setFormDisabled(true);
            setStatus('Ricerca in corso…', 'loading');

            fetch(requestUrl.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Unexpected response status: ${response.status}`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    if (controller && controller.signal.aborted) {
                        return;
                    }

                    setFormDisabled(false);
                    setStatus('');

                    if (typeof payload.html === 'string') {
                        resultsContainer.innerHTML = payload.html;
                    }

                    if (typeof payload.query === 'string') {
                        input.value = payload.query;
                    } else {
                        input.value = normalizedQuery;
                    }

                    renderedQuery = input.value.trim();
                    renderedPage = typeof payload.page === 'number' && payload.page > 0 ? payload.page : targetPage;

                    setResetVisibility(renderedQuery !== '');
                    updateHistory(renderedQuery, renderedPage);
                    activeController = null;
                })
                .catch((error) => {
                    if (controller && controller.signal.aborted) {
                        return;
                    }

                    setFormDisabled(false);
                    setStatus('Errore durante la ricerca. Riprova tra qualche istante.', 'error');
                    activeController = null;
                    // eslint-disable-next-line no-console
                    console.error('Instant profile search failed:', error);
                });
        };

        const handleTyping = debounce(() => {
            performRequest(input.value, 1);
        }, 250);

        input.addEventListener('input', () => {
            handleTyping();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            performRequest(input.value, 1, { force: true });
        });

        resetControls.forEach((control) => {
            control.addEventListener('click', (event) => {
                event.preventDefault();
                input.value = '';
                performRequest('', 1, { force: true });
            });
        });

        resultsContainer.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('[data-profile-search-page-link]') : null;
            if (!link || !(link instanceof HTMLElement)) {
                return;
            }

            const linkPage = parseInt(link.getAttribute('data-profile-search-page') || '1', 10);
            const linkQuery = (link.getAttribute('data-profile-search-query') || renderedQuery).trim();

            if (link.tagName.toLowerCase() === 'a') {
                event.preventDefault();
            }

            performRequest(linkQuery, Number.isNaN(linkPage) ? 1 : linkPage, { force: true });
        });

        const currentLength = renderedQuery.length;
        if (renderedQuery !== '' && currentLength < minimumLength) {
            setStatus(`Inserisci almeno ${minimumLength} caratteri per avviare la ricerca.`, 'loading');
        }
    };

    const initNavbarProfileSearch = () => {
        const container = document.querySelector('[data-nav-profile-search-container]');
        if (!container) {
            return;
        }

        const form = container.querySelector('[data-nav-profile-search-form]');
        const input = container.querySelector('[data-nav-profile-search-input]');
        const resultsPanel = container.querySelector('[data-nav-profile-search-results]');
        if (!form || !input || !resultsPanel) {
            return;
        }

        const minimumLength = parseInt(container.dataset.navProfileSearchMinLength || '2', 10);
        const supportsAbort = typeof AbortController !== 'undefined';
        let activeController = null;
        let lastQuery = '';

        const hideResults = () => {
            resultsPanel.innerHTML = '';
            resultsPanel.classList.add('hidden');
            resultsPanel.dataset.state = 'hidden';
        };

        hideResults();

        const renderMessage = (message) => {
            const text = typeof message === 'string' ? message.trim() : '';
            if (text === '') {
                hideResults();
                return;
            }

            resultsPanel.innerHTML = `<p class="text-xs text-gray-300">${text}</p>`;
            resultsPanel.classList.remove('hidden');
            resultsPanel.dataset.state = 'message';
        };

        const renderResults = (items, hasMore, query) => {
            if (!Array.isArray(items) || items.length === 0) {
                renderMessage('Nessun tifoso trovato.');
                return;
            }

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const listItems = items.slice(0, 5).map((item) => {
                const username = typeof item.username === 'string' ? item.username : '';
                const badge = typeof item.badge === 'string' ? item.badge : 'Tifoso';
                const displayName = username !== '' ? username : 'Tifoso anonimo';
                const safeName = escapeHtml(displayName);
                const safeBadge = escapeHtml(badge);
                const targetQuery = username !== '' ? username : query;
                const targetUrl = `?page=profile_search&q=${encodeURIComponent(targetQuery)}`;
                return `
                    <li>
                        <a href="${targetUrl}" class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white transition-all hover:border-white/30 hover:bg-white/10">
                            <span class="font-medium">${safeName}</span>
                            <span class="text-xs text-gray-300">${safeBadge}</span>
                        </a>
                    </li>
                `;
            }).join('');

            const moreLink = hasMore
                ? `<div class="mt-2 border-t border-white/10 pt-2 text-center text-xs text-gray-300">
                        <a href="?page=profile_search&q=${encodeURIComponent(query)}" class="font-semibold text-white hover:underline">Vedi tutti i risultati</a>
                   </div>`
                : '';

            resultsPanel.innerHTML = `<ul class="space-y-2">${listItems}</ul>${moreLink}`;
            resultsPanel.classList.remove('hidden');
            resultsPanel.dataset.state = 'results';
        };

        const buildRequestUrl = (query) => {
            const action = form.getAttribute('action') || '?page=profile_search';
            const url = new URL(action, window.location.href);
            if (!url.searchParams.has('page')) {
                url.searchParams.set('page', 'profile_search');
            }
            url.searchParams.set('ajax', '1');
            url.searchParams.set('q', query);
            url.searchParams.set('p', '1');
            return url;
        };

        const executeSearch = (query) => {
            const normalizedQuery = query.trim();
            if (normalizedQuery.length < minimumLength) {
                hideResults();
                lastQuery = normalizedQuery;
                if (supportsAbort && activeController) {
                    activeController.abort();
                    activeController = null;
                }
                return;
            }

            if (normalizedQuery === lastQuery) {
                return;
            }

            if (supportsAbort && activeController) {
                activeController.abort();
                activeController = null;
            }

            const requestUrl = buildRequestUrl(normalizedQuery);
            const controller = supportsAbort ? new AbortController() : null;
            if (controller) {
                activeController = controller;
            }

            fetch(requestUrl.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Unexpected response status: ${response.status}`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    if (controller && controller.signal.aborted) {
                        return;
                    }

                    lastQuery = normalizedQuery;
                    activeController = null;

                    if (payload.too_short) {
                        hideResults();
                        return;
                    }

                    if (Array.isArray(payload.results)) {
                        renderResults(payload.results, Boolean(payload.has_more), normalizedQuery);
                        return;
                    }

                    if (payload.results_count && payload.results_count > 0) {
                        renderResults([], false, normalizedQuery);
                    } else {
                        renderMessage('Nessun tifoso trovato.');
                    }
                })
                .catch((error) => {
                    if (controller && controller.signal.aborted) {
                        return;
                    }

                    activeController = null;
                    renderMessage('Errore durante la ricerca.');
                    // eslint-disable-next-line no-console
                    console.error('Navbar profile search failed:', error);
                });
        };

        const handleInput = debounce(() => {
            executeSearch(input.value);
        }, 250);

        input.addEventListener('input', () => {
            handleInput();
        });

        input.addEventListener('focus', () => {
            const current = input.value.trim();
            if (current.length >= minimumLength && resultsPanel.dataset.state === 'results') {
                resultsPanel.classList.remove('hidden');
            }
        });

        form.addEventListener('submit', () => {
            hideResults();
        });

        resultsPanel.addEventListener('click', (event) => {
            const targetLink = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (targetLink) {
                hideResults();
            }
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                hideResults();
            }
        });
    };

    const initCityAutocomplete = () => {
        const inputs = Array.from(document.querySelectorAll('[data-city-autocomplete]'));
        if (inputs.length === 0) {
            return;
        }

        const sourceCache = new Map();

        const ensureData = (sourceUrl) => {
            if (sourceCache.has(sourceUrl)) {
                const cacheEntry = sourceCache.get(sourceUrl);
                if (cacheEntry.loaded) {
                    return Promise.resolve(cacheEntry.cities);
                }
                if (cacheEntry.promise) {
                    return cacheEntry.promise;
                }
            }

            const fetchPromise = fetch(sourceUrl, {
                credentials: 'same-origin',
                cache: 'default',
                headers: {
                    Accept: 'application/json',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Failed to load city list (${response.status})`);
                    }
                    return response.json();
                })
                .then((data) => {
                    const pickFirstString = (...candidates) => {
                        for (let index = 0; index < candidates.length; index += 1) {
                            const candidate = candidates[index];
                            if (typeof candidate === 'string') {
                                const trimmed = candidate.trim();
                                if (trimmed !== '') {
                                    return trimmed;
                                }
                            }
                        }
                        return '';
                    };

                    const normalizeEntry = (entry) => {
                        if (typeof entry === 'string') {
                            const trimmed = entry.trim();
                            if (trimmed === '') {
                                return null;
                            }
                            return {
                                value: trimmed,
                                search: trimmed.toLowerCase(),
                            };
                        }

                        if (entry && typeof entry === 'object') {
                            const name = pickFirstString(entry.nome, entry.denominazione, entry.name);
                            if (name === '') {
                                return null;
                            }

                            const provinceSigla = pickFirstString(
                                entry.sigla,
                                entry.siglaProvincia,
                                entry.sigla_provincia,
                                entry.provincia && entry.provincia.sigla,
                                entry.province && entry.province.sigla
                            );

                            const provinceName = pickFirstString(
                                entry.nomeProvincia,
                                entry.provincia && entry.provincia.nome,
                                entry.province && entry.province.nome
                            );

                            const regionName = pickFirstString(
                                entry.nomeRegione,
                                entry.regione && entry.regione.nome,
                                entry.region && entry.region.nome
                            );

                            const cap = Array.isArray(entry.cap)
                                ? entry.cap.map((value) => String(value).trim()).filter((value) => value !== '').join(' ')
                                : pickFirstString(entry.cap);

                            const value = provinceSigla !== '' ? `${name} (${provinceSigla})` : name;
                            const tokens = [name, provinceSigla, provinceName, regionName, cap]
                                .filter((token) => token && token !== '')
                                .join(' ')
                                .toLowerCase();

                            return {
                                value,
                                search: tokens !== '' ? tokens : name.toLowerCase(),
                            };
                        }

                        return null;
                    };

                    const cities = Array.isArray(data)
                        ? data
                            .map((entry) => normalizeEntry(entry))
                            .filter((entry) => entry !== null)
                        : [];
                    sourceCache.set(sourceUrl, {
                        loaded: true,
                        cities,
                        promise: null,
                    });
                    return cities;
                })
                .catch((error) => {
                    console.error('City autocomplete load failed:', error);
                    sourceCache.set(sourceUrl, {
                        loaded: true,
                        cities: [],
                        promise: null,
                    });
                    return [];
                });

            sourceCache.set(sourceUrl, {
                loaded: false,
                cities: [],
                promise: fetchPromise,
            });

            return fetchPromise;
        };

        const renderOptions = (input, datalist, minimumLength, cities) => {
            datalist.innerHTML = '';
            if (!Array.isArray(cities) || cities.length === 0) {
                return;
            }

            const normalizedQuery = String(input.value || '').trim().toLowerCase();
            if (normalizedQuery.length < minimumLength) {
                return;
            }

            const fragment = document.createDocumentFragment();
            let rendered = 0;

            cities.some((entry) => {
                if (!entry || typeof entry.value !== 'string' || entry.value === '') {
                    return false;
                }

                const haystack = typeof entry.search === 'string' && entry.search !== ''
                    ? entry.search
                    : entry.value.toLowerCase();

                if (!haystack.includes(normalizedQuery)) {
                    return false;
                }

                const option = document.createElement('option');
                option.value = entry.value;
                fragment.appendChild(option);
                rendered += 1;
                return rendered >= 12;
            });

            if (fragment.childNodes.length > 0) {
                datalist.appendChild(fragment);
            }
        };

        inputs.forEach((input) => {
            const listId = input.getAttribute('list');
            if (!listId) {
                return;
            }

            const datalist = document.getElementById(listId);
            if (!datalist) {
                return;
            }

            const minimumLength = parseInt(input.dataset.cityAutocompleteMin || '2', 10);
            const sourceUrl = input.dataset.cityAutocompleteSource || 'assets/data/italian_cities.json';

            const handleRender = () => {
                const cacheEntry = sourceCache.get(sourceUrl);
                const cities = cacheEntry && cacheEntry.loaded ? cacheEntry.cities : [];
                renderOptions(input, datalist, minimumLength, cities);
            };

            const debouncedInput = debounce(() => {
                ensureData(sourceUrl).then(() => {
                    handleRender();
                });
            }, 150);

            input.addEventListener('focus', () => {
                ensureData(sourceUrl).then(() => {
                    handleRender();
                });
            });

            input.addEventListener('input', () => {
                debouncedInput();
            });

            if (input.value.trim() !== '') {
                ensureData(sourceUrl).then(() => {
                    handleRender();
                });
            }
        });
    };

    initProfileSearchInstant();
    initNavbarProfileSearch();
    initCityAutocomplete();

    const composer = document.querySelector('[data-community-composer]');
    if (composer) {
        const modeButtons = composer.querySelectorAll('[data-composer-mode]');
        const modeInput = composer.querySelector('[data-composer-mode-input]');
        const textArea = composer.querySelector('[data-composer-textarea]');
        const photoSection = composer.querySelector('[data-composer-photo-section]');
        const pollSection = composer.querySelector('[data-composer-poll-section]');
        const photoFileInput = composer.querySelector('[data-composer-photo-file]');
        const photoTrigger = composer.querySelector('[data-composer-photo-trigger]');
        const photoPreviewWrapper = composer.querySelector('[data-composer-photo-preview-wrapper]');
        const photoPreviewsContainer = composer.querySelector('[data-composer-photo-previews]');
        const photoTemplate = composer.querySelector('[data-composer-photo-template]');
        const photoError = composer.querySelector('[data-composer-photo-error]');
        const photoHint = composer.querySelector('[data-composer-photo-hint]');
        const photoClipboardField = composer.querySelector('[data-composer-photo-clipboard]');
        const photoClipboardNameField = composer.querySelector('[data-composer-photo-clipboard-name]');
    const pollQuestionInput = composer.querySelector('[data-composer-poll-question]');
    const pollOptionInputs = composer.querySelectorAll('[data-composer-poll-option]');
    const storySection = composer.querySelector('[data-composer-story-section]');
    const storyTitleInput = composer.querySelector('[data-composer-story-title]');
    const storyCaptionInput = composer.querySelector('[data-composer-story-caption]');
    const storyCreditInput = composer.querySelector('[data-composer-story-credit]');
        const actionButtons = composer.querySelectorAll('[data-composer-action]');
        const actionInput = composer.querySelector('[data-composer-action-input]');
        const scheduleWrapper = composer.querySelector('[data-composer-schedule-wrapper]');
        const scheduleInput = composer.querySelector('[data-composer-schedule-input]');
        const submitLabel = composer.querySelector('[data-composer-submit-label]');
        const composerMaxAttachments = parseInt(composer.dataset.composerMaxAttachments || '4', 10);

        const composerState = {
            files: [],
            clipboard: null,
            objectUrls: [],
        };

        const allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        const composerActionLabels = {
            publish: 'Pubblica',
            schedule: 'Programma',
            draft: 'Salva bozza',
        };

    const getCurrentMode = () => (modeInput ? modeInput.value : 'text');
    const getAttachmentCount = () => composerState.files.length + (composerState.clipboard ? 1 : 0);
    const getAttachmentLimit = () => (getCurrentMode() === 'story' ? 1 : composerMaxAttachments);

        const basePlaceholder = textArea ? textArea.dataset.placeholderBase || textArea.placeholder : '';
        const photoPlaceholder = textArea ? textArea.dataset.placeholderPhoto || basePlaceholder : '';
    const pollPlaceholder = textArea ? textArea.dataset.placeholderPoll || basePlaceholder : '';
    const storyPlaceholder = textArea ? textArea.dataset.placeholderStory || basePlaceholder : '';

        const setPhotoError = (message) => {
            if (!photoError) {
                return;
            }
            if (message && String(message).trim() !== '') {
                photoError.textContent = message;
                photoError.classList.remove('hidden');
            } else {
                photoError.textContent = '';
                photoError.classList.add('hidden');
            }
        };

        const updatePhotoHint = () => {
            if (!photoHint) {
                return;
            }

            const limit = getAttachmentLimit();
            const mode = getCurrentMode();

            if (limit <= 0) {
                photoHint.textContent = '';
                return;
            }

            const attachmentsCount = getAttachmentCount();

            if (limit === 1) {
                if (mode === 'story') {
                    photoHint.textContent = attachmentsCount === 0
                        ? 'Carica o incolla l’immagine che racconta la tua storia.'
                        : 'Hai allegato l’immagine della tua storia.';
                } else {
                    photoHint.textContent = attachmentsCount === 0
                        ? 'Al momento puoi allegare una sola immagine oppure incollarne una dagli appunti.'
                        : 'Hai allegato l’unica immagine disponibile per questo post.';
                }
                return;
            }

            if (attachmentsCount === 0) {
                photoHint.textContent = `Puoi allegare fino a ${limit} immagini (anche incollandole dagli appunti).`;
                return;
            }

            const remaining = limit - attachmentsCount;
            if (remaining <= 0) {
                photoHint.textContent = 'Hai raggiunto il limite di immagini per questo post.';
            } else {
                photoHint.textContent = `Puoi aggiungere ancora ${remaining} ${remaining === 1 ? 'immagine' : 'immagini'}.`;
            }
        };

        const clearClipboardData = () => {
            composerState.clipboard = null;
            if (photoClipboardField) {
                photoClipboardField.value = '';
            }
            if (photoClipboardNameField) {
                photoClipboardNameField.value = '';
            }
        };

        const toggleRequiredAttributes = (mode) => {
            if (photoFileInput) {
                photoFileInput.required = ['photo', 'story'].includes(mode) && getAttachmentCount() === 0;
            }

            if (pollQuestionInput) {
                pollQuestionInput.required = mode === 'poll';
            }

            pollOptionInputs.forEach((input, index) => {
                input.required = mode === 'poll' && index < 2;
            });

            if (storyTitleInput) {
                storyTitleInput.required = mode === 'story';
            }

            if (storyCaptionInput) {
                storyCaptionInput.required = mode === 'story';
            }

            if (scheduleInput) {
                const currentAction = actionInput ? actionInput.value : 'publish';
                scheduleInput.required = currentAction === 'schedule';
            }
        };

        const syncFileInput = () => {
            if (!photoFileInput) {
                return true;
            }

            if (typeof window.DataTransfer === 'undefined') {
                return false;
            }

            const dataTransfer = new DataTransfer();
            composerState.files.forEach((entry) => {
                dataTransfer.items.add(entry.file);
            });
            photoFileInput.files = dataTransfer.files;
            return true;
        };

        const showMaxAttachmentError = () => {
            const limit = getAttachmentLimit();
            const mode = getCurrentMode();

            if (limit === 1) {
                if (mode === 'story') {
                    setPhotoError('Le storie possono includere una sola immagine.');
                } else {
                    setPhotoError('Puoi allegare una sola immagine per post al momento.');
                }
                return;
            }

            setPhotoError(`Puoi allegare al massimo ${limit} immagini per post.`);
        };

        const refreshPhotoPreview = () => {
            if (!photoPreviewsContainer || !photoPreviewWrapper || !photoTemplate) {
                updatePhotoHint();
                toggleRequiredAttributes(getCurrentMode());
                return;
            }

            composerState.objectUrls.forEach((url) => URL.revokeObjectURL(url));
            composerState.objectUrls = [];
            photoPreviewsContainer.innerHTML = '';

            const attachments = [];

            composerState.files.forEach((entry) => {
                const objectUrl = URL.createObjectURL(entry.file);
                composerState.objectUrls.push(objectUrl);
                attachments.push({
                    id: entry.id,
                    type: 'file',
                    name: entry.file.name && entry.file.name.trim() !== '' ? entry.file.name : 'Immagine caricata',
                    source: 'Dispositivo',
                    url: objectUrl,
                });
            });

            if (composerState.clipboard) {
                attachments.push({
                    id: composerState.clipboard.id,
                    type: 'clipboard',
                    name: composerState.clipboard.name,
                    source: 'Appunti',
                    url: composerState.clipboard.dataUrl,
                });
            }

            if (attachments.length === 0) {
                photoPreviewWrapper.classList.add('hidden');
                updatePhotoHint();
                toggleRequiredAttributes(getCurrentMode());
                return;
            }

            attachments.forEach((attachment) => {
                const fragment = photoTemplate.content.cloneNode(true);
                const previewImage = fragment.querySelector('[data-composer-photo-preview]');
                const nameLabel = fragment.querySelector('[data-composer-photo-name]');
                const originLabel = fragment.querySelector('[data-composer-photo-origin]');
                const removeButton = fragment.querySelector('[data-composer-photo-remove]');

                if (previewImage) {
                    previewImage.src = attachment.url;
                }
                if (nameLabel) {
                    nameLabel.textContent = attachment.name;
                }
                if (originLabel) {
                    originLabel.textContent = attachment.source;
                }
                if (removeButton) {
                    removeButton.dataset.attachmentId = attachment.id;
                    removeButton.dataset.attachmentType = attachment.type;
                }

                photoPreviewsContainer.appendChild(fragment);
            });

            photoPreviewWrapper.classList.remove('hidden');
            updatePhotoHint();
            toggleRequiredAttributes(getCurrentMode());
        };

        const addFileEntries = (fileList) => {
            if (!fileList || fileList.length === 0) {
                return;
            }

            let addedSomething = false;

            // Convert to array to iterate more comfortably in older browsers.
            Array.from(fileList).some((file) => {
                if (!allowedImageMimeTypes.includes(file.type)) {
                    setPhotoError('Formato immagine non supportato. Usa JPEG, PNG, WEBP o GIF.');
                    return false;
                }

                if (getAttachmentCount() >= getAttachmentLimit()) {
                    showMaxAttachmentError();
                    return true;
                }

                const isDuplicate = composerState.files.some((entry) => (
                    entry.file.name === file.name
                    && entry.file.size === file.size
                    && entry.file.lastModified === file.lastModified
                ));

                if (isDuplicate) {
                    setPhotoError('Hai già aggiunto questa immagine.');
                    return false;
                }

                composerState.files.push({
                    id: `file-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    file,
                });
                addedSomething = true;
                return false;
            });

            if (addedSomething && syncFileInput()) {
                setPhotoError('');
            }

            refreshPhotoPreview();
        };

        const addClipboardFile = (file) => {
            if (!file) {
                return;
            }

            if (!allowedImageMimeTypes.includes(file.type)) {
                setPhotoError('Formato immagine non supportato. Usa JPEG, PNG, WEBP o GIF.');
                return;
            }

            if (getAttachmentCount() >= getAttachmentLimit()) {
                showMaxAttachmentError();
                return;
            }

            const entry = {
                id: `file-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                file,
            };

            composerState.files.push(entry);
            const assigned = syncFileInput();
            if (assigned) {
                clearClipboardData();
                setPhotoError('');
                refreshPhotoPreview();
                return;
            }

            // Fallback when DataTransfer is unavailable.
            composerState.files = composerState.files.filter((item) => item.id !== entry.id);

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                const result = reader.result;
                if (typeof result !== 'string') {
                    return;
                }

                composerState.clipboard = {
                    id: `clipboard-${Date.now()}`,
                    name: file.name && file.name.trim() !== '' ? file.name.trim() : `clipboard-${Date.now()}.png`,
                    dataUrl: result,
                };

                if (photoClipboardField) {
                    photoClipboardField.value = result;
                }
                if (photoClipboardNameField) {
                    photoClipboardNameField.value = composerState.clipboard.name;
                }

                setPhotoError('');
                refreshPhotoPreview();
            });
            reader.readAsDataURL(file);
        };

        const applyMode = (mode) => {
            const allowedModes = ['text', 'photo', 'poll', 'story'];
            const normalizedMode = allowedModes.includes(mode) ? mode : 'text';
            const attachmentCount = getAttachmentCount();

            if (!['photo', 'story'].includes(normalizedMode) && attachmentCount > 0) {
                setPhotoError('Rimuovi le immagini prima di cambiare modalità.');
                return;
            }

            if (normalizedMode === 'story' && attachmentCount > 1) {
                setPhotoError('Le storie possono includere una sola immagine. Rimuovi quelle in eccesso.');
                return;
            }

            setPhotoError('');

            if (modeInput) {
                modeInput.value = normalizedMode;
            }

            modeButtons.forEach((button) => {
                if (button.dataset.composerMode === normalizedMode) {
                    button.classList.add('composer-mode-active');
                } else {
                    button.classList.remove('composer-mode-active');
                }
            });

            if (photoSection) {
                photoSection.classList.toggle('hidden', !['photo', 'story'].includes(normalizedMode));
            }

            if (storySection) {
                storySection.classList.toggle('hidden', normalizedMode !== 'story');
            }

            if (pollSection) {
                pollSection.classList.toggle('hidden', normalizedMode !== 'poll');
            }

            if (textArea) {
                if (normalizedMode === 'photo') {
                    textArea.placeholder = photoPlaceholder;
                } else if (normalizedMode === 'poll') {
                    textArea.placeholder = pollPlaceholder;
                } else if (normalizedMode === 'story') {
                    textArea.placeholder = storyPlaceholder;
                } else {
                    textArea.placeholder = basePlaceholder;
                }
            }

            toggleRequiredAttributes(normalizedMode);
            updatePhotoHint();
        };

        const applyAction = (action) => {
            const normalizedAction = Object.prototype.hasOwnProperty.call(composerActionLabels, action)
                ? action
                : 'publish';

            if (actionInput) {
                actionInput.value = normalizedAction;
            }

            actionButtons.forEach((button) => {
                if (button.dataset.composerAction === normalizedAction) {
                    button.classList.add('composer-action-active');
                } else {
                    button.classList.remove('composer-action-active');
                }
            });

            if (scheduleWrapper) {
                scheduleWrapper.classList.toggle('hidden', normalizedAction !== 'schedule');
            }

            if (scheduleInput) {
                scheduleInput.required = normalizedAction === 'schedule';
            }

            if (submitLabel) {
                submitLabel.textContent = composerActionLabels[normalizedAction];
            }

            toggleRequiredAttributes(getCurrentMode());
        };

        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                applyMode(button.dataset.composerMode || 'text');
            });
        });

        actionButtons.forEach((button) => {
            button.addEventListener('click', () => {
                applyAction(button.dataset.composerAction || 'publish');
            });
        });

        if (photoTrigger && photoFileInput) {
            photoTrigger.addEventListener('click', () => {
                if (typeof window.DataTransfer !== 'undefined') {
                    photoFileInput.value = '';
                    syncFileInput();
                }
                photoFileInput.click();
            });
        }

        if (photoFileInput) {
            photoFileInput.addEventListener('change', () => {
                if (photoFileInput.files && photoFileInput.files.length > 0) {
                    addFileEntries(photoFileInput.files);
                    const currentMode = getCurrentMode();
                    if (currentMode === 'photo' || currentMode === 'story') {
                        applyMode(currentMode);
                    } else {
                        applyMode('photo');
                    }
                } else if (typeof window.DataTransfer === 'undefined') {
                    composerState.files = [];
                    refreshPhotoPreview();
                }
            });
        }

        if (photoPreviewsContainer) {
            photoPreviewsContainer.addEventListener('click', (event) => {
                const target = event.target.closest('[data-composer-photo-remove]');
                if (!target) {
                    return;
                }

                event.preventDefault();
                const attachmentId = target.dataset.attachmentId;
                const attachmentType = target.dataset.attachmentType;

                if (attachmentType === 'file') {
                    composerState.files = composerState.files.filter((entry) => entry.id !== attachmentId);
                    if (typeof window.DataTransfer !== 'undefined') {
                        syncFileInput();
                    } else if (photoFileInput) {
                        photoFileInput.value = '';
                    }
                } else if (attachmentType === 'clipboard') {
                    clearClipboardData();
                }

                setPhotoError('');
                refreshPhotoPreview();
            });
        }

        if (textArea) {
            textArea.addEventListener('paste', (event) => {
                const items = event.clipboardData && event.clipboardData.items;
                if (!items) {
                    return;
                }

                for (let index = 0; index < items.length; index += 1) {
                    const item = items[index];
                    if (item && item.kind === 'file' && item.type.startsWith('image/')) {
                        const pastedFile = item.getAsFile();
                        if (pastedFile) {
                            event.preventDefault();
                            addClipboardFile(pastedFile);
                            const currentMode = getCurrentMode();
                            if (currentMode === 'photo' || currentMode === 'story') {
                                applyMode(currentMode);
                            } else {
                                applyMode('photo');
                            }
                        }
                        break;
                    }
                }
            });
        }

        const initialMode = getCurrentMode();
        applyMode(initialMode);

        const initialAction = actionInput ? actionInput.value : 'publish';
        applyAction(initialAction);

        refreshPhotoPreview();
    }

    const emojiPickerContainers = new Set();

    const closeAllEmojiPanels = (exceptContainer = null) => {
        emojiPickerContainers.forEach((container) => {
            if (!(container instanceof HTMLElement) || (exceptContainer && container === exceptContainer)) {
                return;
            }

            const toggle = container.querySelector('[data-emoji-toggle]');
            const panel = container.querySelector('[data-emoji-panel]');

            if (panel && !panel.classList.contains('hidden')) {
                panel.classList.add('hidden');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            container.dataset.emojiPickerOpen = '0';
        });
    };

    const initEmojiPickers = () => {
        const containers = document.querySelectorAll('[data-emoji-picker]');

        containers.forEach((container) => {
            if (!(container instanceof HTMLElement) || container.dataset.emojiPickerReady === '1') {
                return;
            }

            const toggle = container.querySelector('[data-emoji-toggle]');
            const panel = container.querySelector('[data-emoji-panel]');
            const input = container.querySelector('[data-emoji-input]');

            if (!(toggle instanceof HTMLElement) || !(panel instanceof HTMLElement) || !(input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement)) {
                return;
            }

            container.dataset.emojiPickerReady = '1';
            container.dataset.emojiPickerOpen = '0';
            emojiPickerContainers.add(container);
            panel.tabIndex = -1;

            const closePanel = () => {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                container.dataset.emojiPickerOpen = '0';
            };

            const openPanel = () => {
                closeAllEmojiPanels(container);
                panel.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                container.dataset.emojiPickerOpen = '1';
                if (typeof panel.focus === 'function') {
                    panel.focus();
                }
            };

            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                const isHidden = panel.classList.contains('hidden');
                if (isHidden) {
                    openPanel();
                } else {
                    closePanel();
                }
            });

            panel.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closePanel();
                    toggle.focus();
                }
            });

            const emojiButtons = panel.querySelectorAll('[data-emoji-value]');
            emojiButtons.forEach((button) => {
                if (!(button instanceof HTMLElement)) {
                    return;
                }

                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const emoji = button.dataset.emojiValue || button.textContent || '';
                    if (!emoji) {
                        return;
                    }

                    const target = input;
                    const currentValue = target.value || '';
                    const selectionStart = typeof target.selectionStart === 'number' ? target.selectionStart : currentValue.length;
                    const selectionEnd = typeof target.selectionEnd === 'number' ? target.selectionEnd : currentValue.length;
                    const before = currentValue.slice(0, selectionStart);
                    const after = currentValue.slice(selectionEnd);
                    const nextValue = before + emoji + after;

                    target.value = nextValue;
                    const caretPosition = selectionStart + emoji.length;

                    if (typeof target.setSelectionRange === 'function') {
                        target.focus();
                        target.setSelectionRange(caretPosition, caretPosition);
                    } else {
                        target.focus();
                    }

                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    closePanel();
                });
            });
        });
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        emojiPickerContainers.forEach((container) => {
            if (!(container instanceof HTMLElement) || container.dataset.emojiPickerOpen !== '1') {
                return;
            }

            if (container.contains(target)) {
                return;
            }

            const toggle = container.querySelector('[data-emoji-toggle]');
            const panel = container.querySelector('[data-emoji-panel]');
            if (panel && !panel.classList.contains('hidden')) {
                panel.classList.add('hidden');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            container.dataset.emojiPickerOpen = '0';
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllEmojiPanels();
        }
    });

    initEmojiPickers();

    const feedContainer = document.querySelector('[data-community-feed]');
    if (feedContainer) {
        const feedList = feedContainer.querySelector('[data-community-feed-list]');
        const loadMoreButton = feedContainer.querySelector('[data-community-load-more]');
        const statusLabel = feedContainer.querySelector('[data-community-feed-status]');
        const sentinel = feedContainer.querySelector('[data-community-feed-sentinel]');
        const endpoint = feedContainer.dataset.feedEndpoint || '';
        const pageSize = parseInt(feedContainer.dataset.feedPageSize || '8', 10);
        let offset = parseInt(feedContainer.dataset.feedOffset || '0', 10);
        let hasMore = feedContainer.dataset.feedHasMore === '1';
        let isLoading = false;
        let sentinelObserver = null;

        const updateControlsVisibility = () => {
            if (loadMoreButton) {
                loadMoreButton.classList.toggle('hidden', !hasMore);
                loadMoreButton.disabled = !hasMore;
            }
        };

        const setStatus = (visible, message) => {
            if (!statusLabel) {
                return;
            }
            if (message) {
                statusLabel.textContent = message;
            }
            statusLabel.classList.toggle('hidden', !visible);
        };

        const buildRequestUrl = () => {
            if (!endpoint) {
                return null;
            }

            try {
                const requestUrl = new URL(endpoint, window.location.origin);
                requestUrl.searchParams.set('offset', String(offset));
                requestUrl.searchParams.set('limit', String(pageSize));
                return requestUrl.toString();
            } catch (error) {
                console.error('Community feed endpoint non valido:', error);
                return null;
            }
        };

        const appendPosts = (html) => {
            if (!feedList || !html) {
                return;
            }
            feedList.insertAdjacentHTML('beforeend', html);
            initEmojiPickers();
        };

        const fetchNextBatch = async () => {
            if (isLoading || !hasMore) {
                return;
            }

            const requestUrl = buildRequestUrl();
            if (!requestUrl) {
                return;
            }

            isLoading = true;
            setStatus(true, 'Caricamento in corso…');
            if (loadMoreButton) {
                loadMoreButton.disabled = true;
            }

            try {
                const response = await fetch(requestUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Richiesta non valida (${response.status})`);
                }

                const payload = await response.json();
                if (payload && typeof payload.html === 'string') {
                    appendPosts(payload.html);
                }

                hasMore = Boolean(payload && payload.has_more);
                const nextOffset = payload && typeof payload.next_offset === 'number'
                    ? payload.next_offset
                    : offset;
                offset = nextOffset;
            } catch (error) {
                console.error('Errore durante il caricamento del feed community:', error);
                setStatus(true, 'Errore durante il caricamento. Riprova.');
                if (loadMoreButton) {
                    loadMoreButton.disabled = false;
                }
                isLoading = false;
                return;
            }

            setStatus(false);
            feedContainer.dataset.feedHasMore = hasMore ? '1' : '0';
            feedContainer.dataset.feedOffset = String(offset);
            updateControlsVisibility();
            if (!hasMore && sentinelObserver) {
                sentinelObserver.disconnect();
            }
            if (loadMoreButton) {
                loadMoreButton.disabled = false;
            }
            isLoading = false;
        };

        updateControlsVisibility();

        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', fetchNextBatch);
        }

        if ('IntersectionObserver' in window && sentinel) {
            sentinelObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        fetchNextBatch();
                    }
                });
            }, {
                root: null,
                rootMargin: '0px 0px 200px 0px',
            });

            sentinelObserver.observe(sentinel);
        }
    }

    const initCommentLikeEnhancements = () => {
        const numberFormatter = typeof Intl !== 'undefined'
            ? new Intl.NumberFormat('it-IT')
            : null;

        const isFiniteNumber = (value) => {
            if (typeof Number.isFinite === 'function') {
                return Number.isFinite(value);
            }
            return isFinite(value);
        };

        const formatCount = (value) => {
            const numericValue = isFiniteNumber(value) ? value : parseInt(String(value || '0'), 10);
            if (numberFormatter) {
                return numberFormatter.format(isFiniteNumber(numericValue) ? numericValue : 0);
            }
            return String(isFiniteNumber(numericValue) ? numericValue : 0);
        };

        const toggleCommentLike = async (form) => {
            if (!(form instanceof HTMLFormElement) || form.dataset.commentLikeBusy === '1') {
                return;
            }

            const endpoint = form.dataset.commentLikeEndpoint || form.getAttribute('action') || '';
            const button = form.querySelector('[data-comment-like-button]');
            const countElement = form.querySelector('[data-comment-like-count]');

            if (!endpoint || !button || !countElement) {
                form.dataset.commentLikeFallback = '1';
                window.setTimeout(() => {
                    form.submit();
                }, 0);
                return;
            }

            form.dataset.commentLikeBusy = '1';
            button.setAttribute('aria-busy', 'true');

            try {
                const formData = new FormData(form);
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    if (response.status === 401 || response.status === 419) {
                        form.dataset.commentLikeFallback = '1';
                        window.setTimeout(() => {
                            form.submit();
                        }, 0);
                        return;
                    }
                    throw new Error(`Unexpected response (${response.status})`);
                }

                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error(payload && payload.message ? payload.message : 'Operazione non riuscita');
                }

                const likesCount = Number(payload.likes_count);
                const safeCount = isFiniteNumber(likesCount)
                    ? likesCount
                    : parseInt(String(payload.likes_count || '0'), 10) || 0;
                const liked = payload.liked === true || payload.state === 'added';

                countElement.dataset.commentLikeCountValue = String(safeCount);
                countElement.textContent = payload.formatted_count || formatCount(safeCount);

                button.dataset.commentLikeState = liked ? 'liked' : 'unliked';
                button.setAttribute('aria-pressed', liked ? 'true' : 'false');
                if (liked) {
                    button.classList.add('text-white');
                } else {
                    button.classList.remove('text-white');
                }
            } catch (error) {
                console.error('Impossibile aggiornare il Mi piace del commento:', error);
                form.dataset.commentLikeFallback = '1';
                window.setTimeout(() => {
                    form.submit();
                }, 0);
            } finally {
                delete form.dataset.commentLikeBusy;
                button.removeAttribute('aria-busy');
            }
        };

        document.addEventListener('submit', (event) => {
            const targetForm = event.target;
            if (!(targetForm instanceof HTMLFormElement) || !targetForm.matches('[data-comment-like-form]')) {
                return;
            }

            if (targetForm.dataset.commentLikeFallback === '1') {
                delete targetForm.dataset.commentLikeFallback;
                return;
            }

            event.preventDefault();
            toggleCommentLike(targetForm);
        });
    };

    initCommentLikeEnhancements();

    const pushSetupContainer = document.querySelector('[data-push-setup]');
    if (pushSetupContainer) {
        initPushModule(pushSetupContainer);
    }

    function initPushModule(container) {
        const endpoint = container.dataset.pushEndpoint || '';
        const publicKey = container.dataset.pushPublicKey || '';
        const csrfToken = container.dataset.pushToken || '';
        const hasFollowing = container.dataset.pushFollowingAvailable === '1';
        const enableButton = container.querySelector('[data-push-enable]');
        const disableButton = container.querySelector('[data-push-disable]');
        const scopeInputs = Array.from(container.querySelectorAll('input[name="push-scope"]'));
        const statusLabel = container.querySelector('[data-push-status]');
        const unsupportedLabel = container.querySelector('[data-push-unsupported]');
        const localScopeKey = 'bhPushScope';
        const localEnabledKey = 'bhPushEnabled';
        let registration = null;
        let currentSubscription = null;
        let isProcessing = false;

        const setStatus = (message) => {
            if (!statusLabel) {
                return;
            }
            statusLabel.textContent = message || '';
            statusLabel.classList.toggle('hidden', !message);
        };

        const toggleButtons = (enabled) => {
            if (enableButton) {
                enableButton.classList.toggle('hidden', enabled);
                enableButton.disabled = enabled;
            }
            if (disableButton) {
                disableButton.classList.toggle('hidden', !enabled);
                disableButton.disabled = !enabled;
            }
        };

        const storeState = (enabled, scope) => {
            try {
                if (enabled) {
                    window.localStorage.setItem(localEnabledKey, '1');
                    if (scope) {
                        const normalizedScope = scope === 'following' && !hasFollowing ? 'global' : scope;
                        window.localStorage.setItem(localScopeKey, normalizedScope);
                    }
                } else {
                    window.localStorage.removeItem(localEnabledKey);
                }
            } catch (error) {
                // Silently ignore storage issues.
            }
        };

        const loadStoredScope = () => {
            try {
                return window.localStorage.getItem(localScopeKey) || 'global';
            } catch (error) {
                return 'global';
            }
        };

        const getSelectedScope = () => {
            const active = scopeInputs.find((input) => input.checked && !input.disabled);
            return active ? active.value : 'global';
        };

        const applyStoredScope = () => {
            const stored = loadStoredScope();
            let matched = false;
            scopeInputs.forEach((input) => {
                if (input.disabled) {
                    input.checked = false;
                    return;
                }
                if (input.value === stored) {
                    input.checked = true;
                    matched = true;
                }
            });
            if (!matched && scopeInputs.length > 0) {
                scopeInputs[0].checked = true;
            }
        };

        const getRegistration = async () => {
            if (registration) {
                return registration;
            }

            try {
                const existing = await navigator.serviceWorker.getRegistration();
                if (existing) {
                    registration = existing;
                } else {
                    registration = await navigator.serviceWorker.register('/service-worker.js');
                }
                const ready = await navigator.serviceWorker.ready;
                registration = ready;
                return ready;
            } catch (error) {
                registration = null;
                throw error;
            }
        };

        const getSubscription = async () => {
            const reg = await getRegistration();
            return reg.pushManager.getSubscription();
        };

        const sendRequest = async (action, body) => {
            if (!endpoint) {
                throw new Error('Endpoint non configurato.');
            }

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    action,
                    token: csrfToken,
                    ...body,
                }),
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Richiesta notifiche non riuscita.');
            }

            return payload;
        };

        const subscribe = async (updateOnly = false) => {
            if (isProcessing) {
                return;
            }

            if (!publicKey && !updateOnly) {
                setStatus('Chiave pubblica VAPID non configurata.');
                return;
            }

            isProcessing = true;
            setStatus(updateOnly ? 'Aggiornamento preferenze…' : 'Attivazione notifiche…');

            try {
                const reg = await getRegistration();
                let subscription = await reg.pushManager.getSubscription();

                if (!subscription) {
                    if ('Notification' in window) {
                        const permission = Notification.permission;
                        if (permission === 'denied') {
                            setStatus('Notifiche bloccate dal browser: abilita le notifiche per questo sito e riprova.');
                            toggleButtons(false);
                            storeState(false);
                            return;
                        }

                        if (permission !== 'granted') {
                            let requestResult = permission;
                            try {
                                requestResult = await Notification.requestPermission();
                            } catch (permissionError) {
                                console.warn('Notification permission request failed:', permissionError);
                            }

                            if (requestResult !== 'granted') {
                                setStatus('Per attivare le notifiche consenti le notifiche nel browser e riprova.');
                                toggleButtons(false);
                                storeState(false);
                                return;
                            }
                        }
                    }

                    if (updateOnly) {
                        setStatus('Non ci sono notifiche attive su questo dispositivo.');
                        toggleButtons(false);
                        storeState(false);
                        return;
                    }

                    const keyBuffer = urlBase64ToUint8Array(publicKey);
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: keyBuffer,
                    });
                }

                const scope = getSelectedScope();
                if (scope === 'following' && !hasFollowing) {
                    setStatus('Segui almeno un tifoso per attivare le notifiche mirate.');
                    toggleButtons(false);
                    storeState(false);
                    isProcessing = false;
                    return;
                }
                const response = await sendRequest(updateOnly ? 'update' : 'subscribe', {
                    scope,
                    subscription: subscription ? subscription.toJSON() : null,
                    meta: {
                        user_agent: navigator.userAgent.slice(0, 250),
                        language: navigator.language || '',
                        content_encoding: 'aes128gcm',
                    },
                });

                currentSubscription = subscription;
                storeState(true, scope);
                toggleButtons(true);
                setStatus(response.message || (updateOnly ? 'Preferenze aggiornate.' : 'Notifiche attivate.'));
            } catch (error) {
                console.error('Push subscription error:', error);
                setStatus(error.message || 'Impossibile completare la richiesta.');
                if (!updateOnly) {
                    toggleButtons(false);
                    storeState(false);
                }
            } finally {
                isProcessing = false;
            }
        };

        const unsubscribe = async () => {
            if (isProcessing) {
                return;
            }

            isProcessing = true;
            setStatus('Disattivazione notifiche…');

            try {
                const subscription = await getSubscription();
                if (!subscription) {
                    toggleButtons(false);
                    storeState(false);
                    setStatus('Notifiche già disattivate.');
                    return;
                }

                try {
                    await sendRequest('unsubscribe', {
                        endpoint: subscription.endpoint,
                        subscription: subscription.toJSON(),
                    });
                } catch (error) {
                    console.warn('Errore durante la deregistrazione dal server:', error);
                }

                await subscription.unsubscribe();
                currentSubscription = null;
                toggleButtons(false);
                storeState(false);
                setStatus('Notifiche disattivate.');
            } catch (error) {
                console.error('Push unsubscribe error:', error);
                setStatus(error.message || 'Impossibile disattivare le notifiche.');
            } finally {
                isProcessing = false;
            }
        };

        const initialise = async () => {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                if (enableButton) {
                    enableButton.disabled = true;
                }
                if (unsupportedLabel) {
                    unsupportedLabel.classList.remove('hidden');
                }
                setStatus('Il browser non supporta le notifiche push.');
                return;
            }

            applyStoredScope();

            try {
                const subscription = await getSubscription();
                currentSubscription = subscription;
                const enabled = Boolean(subscription);
                toggleButtons(enabled);
                if (enabled) {
                    setStatus('');
                } else {
                    storeState(false);
                }
            } catch (error) {
                console.error('Push init error:', error);
                setStatus('Impossibile inizializzare le notifiche.');
                toggleButtons(false);
            }
        };

        if (enableButton) {
            enableButton.addEventListener('click', () => {
                subscribe(false);
            });
        }

        if (disableButton) {
            disableButton.addEventListener('click', () => {
                unsubscribe();
            });
        }

        scopeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                if (!input.checked) {
                    return;
                }
                if (currentSubscription) {
                    subscribe(true);
                }
            });
        });

        initialise();
    }

    function urlBase64ToUint8Array(base64String) {
        const sanitized = (base64String || '').replace(/\s+/g, '');
        const padding = '='.repeat((4 - (sanitized.length % 4)) % 4);
        const base64 = (sanitized + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let index = 0; index < rawData.length; index += 1) {
            outputArray[index] = rawData.charCodeAt(index);
        }
        return outputArray;
    }
});
