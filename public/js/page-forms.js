document.querySelectorAll('[data-public-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const state = form.querySelector('[data-public-form-state]');
        const submit = form.querySelector('[type="submit"]');
        const payload = Object.fromEntries(new FormData(form).entries());
        const csrfToken = String(payload._csrf || form.querySelector('input[name="_csrf"]')?.value || '');

        submit?.setAttribute('disabled', 'disabled');
        if (state) {
            state.textContent = '';
            state.classList.add('hidden');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken !== '' ? {'X-CSRF-Token': csrfToken} : {}),
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success === false) {
                throw new Error(result.reason || 'The form could not be submitted.');
            }

            form.reset();
            if (state) {
                state.textContent = result.message || 'Thank you.';
                state.classList.remove('hidden');
            }
        } catch (error) {
            if (state) {
                state.textContent = error.message || 'The form could not be submitted.';
                state.classList.remove('hidden');
            }
        } finally {
            submit?.removeAttribute('disabled');
        }
    });
});
