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
        const mediaInput = composer.querySelector('[data-composer-media-url]');
        const pollQuestionInput = composer.querySelector('[data-composer-poll-question]');
        const pollOptionInputs = composer.querySelectorAll('[data-composer-poll-option]');

        const basePlaceholder = textArea ? textArea.dataset.placeholderBase || textArea.placeholder : '';
        const photoPlaceholder = textArea ? textArea.dataset.placeholderPhoto || basePlaceholder : '';
        const pollPlaceholder = textArea ? textArea.dataset.placeholderPoll || basePlaceholder : '';

        const toggleRequiredAttributes = (mode) => {
            if (mediaInput) {
                mediaInput.required = mode === 'photo';
            }

            if (pollQuestionInput) {
                pollQuestionInput.required = mode === 'poll';
            }

            pollOptionInputs.forEach((input, index) => {
                input.required = mode === 'poll' && index < 2;
            });
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

        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                applyMode(button.dataset.composerMode || 'text');
            });
        });

        const initialMode = modeInput ? modeInput.value : 'text';
        applyMode(initialMode);
    }
});
