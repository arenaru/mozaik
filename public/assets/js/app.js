// Mozaic client-side script. No inline JS is allowed (CSP script-src 'self').
// Use data-* attributes + listeners here.

document.addEventListener('DOMContentLoaded', () => {
    // <button data-confirm="Delete this pin?"> inside a form asks before submitting.
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', (event) => {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
});
