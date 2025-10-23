document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('[data-nav-target]');
    const currentPage = document.body.dataset.currentPage;

    navItems.forEach((item) => {
        const target = item.getAttribute('data-nav-target');
        if (target === currentPage) {
            item.classList.add('text-white', 'scale-105');
            item.classList.remove('text-gray-400');
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
