(() => {
    class FormFieldErrors {
        constructor(form, options = {}) {
            this.form = form instanceof HTMLFormElement ? form : null;
            this.options = options;
            this.fieldState = new Map();
            this.summaryNode = null;

            if (!(this.form instanceof HTMLFormElement)) {
                throw new Error('FormFieldErrors requires a valid form element.');
            }
        }

        clearAll() {
            for (const fieldName of this.fieldState.keys()) {
                this.clearField(fieldName);
            }
            this.#clearSummary();
        }

        setErrors(errors = {}) {
            this.clearAll();
            if (!errors || typeof errors !== 'object') {
                return;
            }

            const unmappedMessages = [];

            Object.entries(errors).forEach(([fieldName, fieldErrors]) => {
                const messages = Array.isArray(fieldErrors)
                    ? fieldErrors.map((msg) => String(msg ?? '').trim()).filter(Boolean)
                    : [String(fieldErrors ?? '').trim()].filter(Boolean);

                if (messages.length === 0) {
                    return;
                }

                const handled = this.#applyFieldError(fieldName, messages);
                if (!handled) {
                    unmappedMessages.push(...messages);
                }
            });

            if (unmappedMessages.length > 0) {
                this.#renderSummary(unmappedMessages);
            }
        }

        clearField(fieldName) {
            const key = String(fieldName ?? '').trim();
            if (key === '' || !this.fieldState.has(key)) {
                return;
            }

            const state = this.fieldState.get(key);
            if (!state) {
                return;
            }

            state.elements.forEach((element) => {
                if (!(element instanceof HTMLElement)) {
                    return;
                }
                element.removeAttribute('aria-invalid');
                if (element.dataset.formErrorOriginalBorderColor !== undefined) {
                    element.style.borderColor = element.dataset.formErrorOriginalBorderColor;
                    delete element.dataset.formErrorOriginalBorderColor;
                } else {
                    element.style.removeProperty('border-color');
                }

                if (element.dataset.formErrorOriginalOutline !== undefined) {
                    element.style.outline = element.dataset.formErrorOriginalOutline;
                    delete element.dataset.formErrorOriginalOutline;
                } else {
                    element.style.removeProperty('outline');
                }
            });

            if (state.messageNode instanceof HTMLElement) {
                state.messageNode.remove();
            }

            if (Array.isArray(state.listeners)) {
                state.listeners.forEach((listener) => {
                    if (listener?.element instanceof HTMLElement && typeof listener.handler === 'function') {
                        listener.element.removeEventListener('input', listener.handler);
                        listener.element.removeEventListener('change', listener.handler);
                    }
                });
            }

            this.fieldState.delete(key);
        }

        #applyFieldError(fieldName, messages) {
            const key = String(fieldName ?? '').trim();
            if (key === '') {
                return false;
            }

            const elements = this.#resolveFieldElements(key);
            if (elements.length === 0) {
                return false;
            }

            this.clearField(key);

            elements.forEach((element) => {
                if (!(element instanceof HTMLElement)) {
                    return;
                }

                element.dataset.formErrorOriginalBorderColor = element.style.borderColor ?? '';
                element.dataset.formErrorOriginalOutline = element.style.outline ?? '';
                element.style.borderColor = '#dc2626';
                element.style.outline = '1px solid rgba(220, 38, 38, 0.25)';
                element.setAttribute('aria-invalid', 'true');
            });

            const messageNode = document.createElement('div');
            messageNode.setAttribute('data-form-field-error', key);
            messageNode.style.marginTop = '6px';
            messageNode.style.fontSize = '12px';
            messageNode.style.lineHeight = '1.35';
            messageNode.style.color = '#dc2626';
            messageNode.textContent = messages.join(' ');

            const anchor = this.#resolveMessageAnchor(elements[0]);
            if (anchor?.parentNode) {
                anchor.parentNode.insertBefore(messageNode, anchor.nextSibling);
            }

            const listeners = [];
            const clearHandler = () => this.clearField(key);
            elements.forEach((element) => {
                if (!(element instanceof HTMLElement)) {
                    return;
                }
                element.addEventListener('input', clearHandler);
                element.addEventListener('change', clearHandler);
                listeners.push({ element, handler: clearHandler });
            });

            this.fieldState.set(key, {
                elements,
                messageNode,
                listeners,
            });

            return true;
        }

        #resolveFieldElements(fieldName) {
            const configuredTarget = this.options?.fieldTargets?.[fieldName];
            if (configuredTarget) {
                const resolved = this.#resolveConfiguredTarget(configuredTarget);
                if (resolved.length > 0) {
                    return resolved;
                }
            }

            const escapedName = (window.CSS && typeof window.CSS.escape === 'function')
                ? window.CSS.escape(fieldName)
                : fieldName.replace(/["\\]/g, '\\$&');

            return Array.from(this.form.querySelectorAll(`[name="${escapedName}"]`));
        }

        #resolveConfiguredTarget(configuredTarget) {
            if (typeof configuredTarget === 'string') {
                return Array.from(this.form.querySelectorAll(configuredTarget));
            }

            if (configuredTarget instanceof HTMLElement) {
                return [configuredTarget];
            }

            if (Array.isArray(configuredTarget)) {
                return configuredTarget.filter((item) => item instanceof HTMLElement);
            }

            if (typeof configuredTarget === 'function') {
                const result = configuredTarget(this.form);
                if (result instanceof HTMLElement) {
                    return [result];
                }
                if (Array.isArray(result)) {
                    return result.filter((item) => item instanceof HTMLElement);
                }
            }

            return [];
        }

        #resolveMessageAnchor(element) {
            if (!(element instanceof HTMLElement)) {
                return element;
            }

            const type = (element instanceof HTMLInputElement)
                ? String(element.type ?? '').toLowerCase()
                : '';

            if (type === 'checkbox' || type === 'radio') {
                return element.closest('label') ?? element;
            }

            const wrapper = element.closest('.wow-input-wrap');
            return wrapper ?? element;
        }

        #renderSummary(messages) {
            this.#clearSummary();
            const summary = document.createElement('div');
            summary.setAttribute('data-form-error-summary', 'true');
            summary.style.marginBottom = '14px';
            summary.style.padding = '10px 12px';
            summary.style.borderRadius = '10px';
            summary.style.border = '1px solid rgba(220, 38, 38, 0.35)';
            summary.style.background = 'rgba(220, 38, 38, 0.08)';
            summary.style.color = '#b91c1c';
            summary.style.fontSize = '13px';
            summary.style.lineHeight = '1.4';
            summary.textContent = messages.join(' ');

            this.form.insertBefore(summary, this.form.firstChild);
            this.summaryNode = summary;
        }

        #clearSummary() {
            if (this.summaryNode instanceof HTMLElement) {
                this.summaryNode.remove();
            }
            this.summaryNode = null;
        }
    }

    window.FormFieldErrors = FormFieldErrors;
})();
