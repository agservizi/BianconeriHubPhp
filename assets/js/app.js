document.addEventListener('DOMContentLoaded', () => {
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

        const basePlaceholder = textArea ? textArea.dataset.placeholderBase || textArea.placeholder : '';
        const photoPlaceholder = textArea ? textArea.dataset.placeholderPhoto || basePlaceholder : '';
        const pollPlaceholder = textArea ? textArea.dataset.placeholderPoll || basePlaceholder : '';

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

            if (composerMaxAttachments <= 0) {
                photoHint.textContent = '';
                return;
            }

            const attachmentsCount = getAttachmentCount();

            if (composerMaxAttachments === 1) {
                photoHint.textContent = attachmentsCount === 0
                    ? 'Al momento puoi allegare una sola immagine oppure incollarne una dagli appunti.'
                    : 'Hai allegato l’unica immagine disponibile per questo post.';
                return;
            }

            if (attachmentsCount === 0) {
                photoHint.textContent = `Puoi allegare fino a ${composerMaxAttachments} immagini (anche incollandole dagli appunti).`;
                return;
            }

            const remaining = composerMaxAttachments - attachmentsCount;
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
                photoFileInput.required = mode === 'photo' && getAttachmentCount() === 0;
            }

            if (pollQuestionInput) {
                pollQuestionInput.required = mode === 'poll';
            }

            pollOptionInputs.forEach((input, index) => {
                input.required = mode === 'poll' && index < 2;
            });

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
            if (composerMaxAttachments === 1) {
                setPhotoError('Puoi allegare una sola immagine per post al momento.');
            } else {
                setPhotoError(`Puoi allegare al massimo ${composerMaxAttachments} immagini per post.`);
            }
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

                if (getAttachmentCount() >= composerMaxAttachments) {
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

            if (getAttachmentCount() >= composerMaxAttachments) {
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
            const normalizedMode = ['text', 'photo', 'poll'].includes(mode) ? mode : 'text';

            if (normalizedMode !== 'photo' && getAttachmentCount() > 0) {
                setPhotoError('Rimuovi le immagini prima di cambiare modalità.');
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
                photoSection.classList.toggle('hidden', normalizedMode !== 'photo');
            }

            if (pollSection) {
                pollSection.classList.toggle('hidden', normalizedMode !== 'poll');
            }

            if (textArea) {
                if (normalizedMode === 'photo') {
                    textArea.placeholder = photoPlaceholder;
                } else if (normalizedMode === 'poll') {
                    textArea.placeholder = pollPlaceholder;
                } else {
                    textArea.placeholder = basePlaceholder;
                }
            }

            toggleRequiredAttributes(normalizedMode);
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
                    applyMode('photo');
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
                            applyMode('photo');
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
});
