const lockableFormSelector = 'form[data-submit-lock]';
const submitControlSelector = 'button[type="submit"], input[type="submit"]';

function getSubmitControls(form) {
    return form.querySelectorAll(submitControlSelector);
}

function lockForm(form) {
    if (form.dataset.submitting === 'true') {
        return false;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');

    getSubmitControls(form).forEach((control) => {
        control.dataset.submitLockOriginalDisabled = control.disabled ? 'true' : 'false';
        control.dataset.submitLockOriginalOpacity = control.style.opacity;
        control.dataset.submitLockOriginalPointerEvents = control.style.pointerEvents;

        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
        control.style.opacity = control.style.opacity || '0.7';
        control.style.pointerEvents = 'none';
    });

    return true;
}

function unlockForm(form) {
    form.dataset.submitting = 'false';
    form.removeAttribute('aria-busy');

    getSubmitControls(form).forEach((control) => {
        const initiallyDisabled = control.dataset.submitLockOriginalDisabled === 'true';
        const originalOpacity = control.dataset.submitLockOriginalOpacity ?? '';
        const originalPointerEvents = control.dataset.submitLockOriginalPointerEvents ?? '';

        control.style.opacity = originalOpacity;
        control.style.pointerEvents = originalPointerEvents;

        if (!initiallyDisabled) {
            control.disabled = false;
            control.removeAttribute('aria-disabled');
        }

        delete control.dataset.submitLockOriginalDisabled;
        delete control.dataset.submitLockOriginalOpacity;
        delete control.dataset.submitLockOriginalPointerEvents;
    });
}

document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches(lockableFormSelector)) {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        lockForm(form);
    },
    true,
);

window.addEventListener('pageshow', () => {
    document.querySelectorAll(lockableFormSelector).forEach((form) => {
        if (form instanceof HTMLFormElement) {
            unlockForm(form);
        }
    });
});
