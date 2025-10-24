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
    const photoPreviewImage = composer.querySelector('[data-composer-photo-preview]');
    const photoNameLabel = composer.querySelector('[data-composer-photo-name]');
    const photoClearButton = composer.querySelector('[data-composer-photo-clear]');
    const photoClipboardField = composer.querySelector('[data-composer-photo-clipboard]');
    const photoClipboardNameField = composer.querySelector('[data-composer-photo-clipboard-name]');
        const pollQuestionInput = composer.querySelector('[data-composer-poll-question]');
        const pollOptionInputs = composer.querySelectorAll('[data-composer-poll-option]');
    const getCurrentMode = () => (modeInput ? modeInput.value : 'text');

        const basePlaceholder = textArea ? textArea.dataset.placeholderBase || textArea.placeholder : '';
        const photoPlaceholder = textArea ? textArea.dataset.placeholderPhoto || basePlaceholder : '';
        const pollPlaceholder = textArea ? textArea.dataset.placeholderPoll || basePlaceholder : '';

        const hasClipboardSelection = () => Boolean(photoClipboardField && photoClipboardField.value && photoClipboardField.value.trim() !== '');

        const toggleRequiredAttributes = (mode) => {
            if (photoFileInput) {
                const requireFile = mode === 'photo' && !hasClipboardSelection();
                photoFileInput.required = requireFile;
            }

            if (pollQuestionInput) {
                pollQuestionInput.required = mode === 'poll';
            }

            pollOptionInputs.forEach((input, index) => {
                input.required = mode === 'poll' && index < 2;
            });
        };

        const clearClipboardData = () => {
            if (photoClipboardField) {
                photoClipboardField.value = '';
            }
            if (photoClipboardNameField) {
                photoClipboardNameField.value = '';
            }
            toggleRequiredAttributes(getCurrentMode());
        };

        const applyMode = (mode) => {
            const normalizedMode = ['text', 'photo', 'poll'].includes(mode) ? mode : 'text';
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
                if (normalizedMode === 'photo') {
                    photoSection.classList.remove('hidden');
                } else {
                    photoSection.classList.add('hidden');
                }
            }

            if (pollSection) {
                if (normalizedMode === 'poll') {
                    pollSection.classList.remove('hidden');
                } else {
                    pollSection.classList.add('hidden');
                }
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

        const clearPhotoSelection = () => {
            if (photoFileInput) {
                photoFileInput.value = '';
            }
            if (photoPreviewWrapper) {
                photoPreviewWrapper.classList.add('hidden');
            }
            if (photoPreviewImage) {
                photoPreviewImage.removeAttribute('src');
            }
            if (photoNameLabel) {
                photoNameLabel.textContent = '';
            }
            clearClipboardData();
        };

        const renderPhoto = (file, options = {}) => {
            if (!photoPreviewWrapper || !photoPreviewImage || !file) {
                return;
            }

            const { persistClipboard = false } = options;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                const result = reader.result;
                if (typeof result === 'string') {
                    photoPreviewImage.src = result;
                    if (persistClipboard && photoClipboardField) {
                        photoClipboardField.value = result;
                    }
                }

                const fallbackName = file.name && file.name.trim() !== ''
                    ? file.name.trim()
                    : 'immagine incollata';

                if (persistClipboard) {
                    if (photoClipboardNameField) {
                        const clipboardName = file.name && file.name.trim() !== ''
                            ? file.name.trim()
                            : `clipboard-${Date.now()}.png`;
                        photoClipboardNameField.value = clipboardName;
                    }
                    toggleRequiredAttributes(getCurrentMode());
                }

                if (photoNameLabel) {
                    photoNameLabel.textContent = fallbackName;
                }

                photoPreviewWrapper.classList.remove('hidden');
            });
            reader.readAsDataURL(file);
        };

        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                applyMode(button.dataset.composerMode || 'text');
            });
        });

        if (photoTrigger && photoFileInput) {
            photoTrigger.addEventListener('click', () => {
                photoFileInput.click();
            });
        }

        if (photoClearButton) {
            photoClearButton.addEventListener('click', (event) => {
                event.preventDefault();
                clearPhotoSelection();
            });
        }

        if (photoFileInput) {
            photoFileInput.addEventListener('change', () => {
                const file = photoFileInput.files && photoFileInput.files[0];
                if (file) {
                    clearClipboardData();
                    applyMode('photo');
                    renderPhoto(file);
                } else {
                    clearPhotoSelection();
                }
            });
        }

        const assignPastedImage = (file) => {
            if (!file) {
                return;
            }

            clearPhotoSelection();

            let assignedFile = false;
            if (photoFileInput && window.DataTransfer) {
                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    photoFileInput.files = dataTransfer.files;
                    assignedFile = Boolean(photoFileInput.files && photoFileInput.files.length > 0);
                } catch (error) {
                    // Ignore assignment failure; preview will still be displayed.
                }
            }

            applyMode('photo');
            renderPhoto(file, { persistClipboard: !assignedFile });

            if (assignedFile) {
                clearClipboardData();
            }
        };

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
                            assignPastedImage(pastedFile);
                        }
                        break;
                    }
                }
            });
        }

        const initialMode = modeInput ? modeInput.value : 'text';
        applyMode(initialMode);
    }
});
