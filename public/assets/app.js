/*
 * Products Avenue: small progressive enhancements (US-31).
 * Everything works without this file: the server checks every rule.
 */
(() => {
    'use strict';

    // Ask before deleting a review. Without JavaScript, the form is simply sent.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Character counter under the review field, « 120 / 1000 », announced to screen readers.
    // Counted like the server: line breaks as one character, an accented letter or an emoji as one.
    document.querySelectorAll('textarea[data-max-length]').forEach((field) => {
        const max = Number(field.dataset.maxLength);
        const counter = document.createElement('span');
        counter.id = `${field.id}-count`;
        counter.className = 'field__counter';
        counter.setAttribute('aria-live', 'polite');
        (field.parentElement.querySelector('.field__footer') ?? field.parentElement).append(counter);
        field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), counter.id].filter(Boolean).join(' '));

        const update = () => {
            const length = [...field.value.replace(/\r\n/g, '\n')].length;
            counter.textContent = `${length} / ${max}`;
            counter.classList.toggle('field__counter--over', length > max);
        };
        field.addEventListener('input', update);
        update();
    });
})();
