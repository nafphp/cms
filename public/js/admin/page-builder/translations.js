import {
    escapeHtml,
    initStyledSelects,
    prompt,
    toast,
} from './shared.js';

export const translationMethods = {
    async loadPageBuilderLabels(language = this.translationLanguage(), {render = false} = {}) {
        language = this.normalizeLanguageCode(language) || this.defaultLanguage || 'en';
        if (this.loadedLabelLanguage === language) {
            return false;
        }
        if (this.loadingLabelLanguage === language) {
            return false;
        }

        this.loadingLabelLanguage = language;
        try {
            const response = await (window.NixCMS?.json || fetch)(this.translationReadEndpointFor(language), {
                method: 'GET',
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success || !result.entries || typeof result.entries !== 'object') {
                return false;
            }

            this.translationEntriesByLanguage[language] = result.entries;
            this.translationSuggestionsByLanguage[language] = this.normalizeTranslationSuggestions(
                Object.entries(result.entries).map(([key, value]) => ({key, value}))
            );
            this.applyPageBuilderLabels(result.entries);
            this.loadedLabelLanguage = language;
            this.loadingLabelLanguage = '';
            if (render) {
                this.render();
            }

            return true;
        } catch (error) {
            return false;
        } finally {
            if (this.loadingLabelLanguage === language) {
                this.loadingLabelLanguage = '';
            }
        }
    },

    async loadTranslations(language = this.translationLanguage()) {
        language = this.normalizeLanguageCode(language) || this.defaultLanguage || 'en';
        if (this.translationEntriesByLanguage[language]) {
            return true;
        }

        try {
            const response = await (window.NixCMS?.json || fetch)(this.translationReadEndpointFor(language), {
                method: 'GET',
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success || !result.entries || typeof result.entries !== 'object') {
                return false;
            }

            this.translationEntriesByLanguage[language] = result.entries;
            this.translationSuggestionsByLanguage[language] = this.normalizeTranslationSuggestions(
                Object.entries(result.entries).map(([key, value]) => ({key, value}))
            );

            return true;
        } catch (error) {
            return false;
        }
    },

    applyPageBuilderLabels(entries) {
        const labels = {};
        Object.entries(this.labelKeys || {}).forEach(([labelKey, translationKey]) => {
            const fallback = this.config.labels?.[labelKey] || translationKey;
            const translated = entries?.[translationKey];
            labels[labelKey] = typeof translated === 'string' && translated !== '' ? translated : fallback;
        });

        this.labels = labels;
        this.media.visibilityOptions = {
            ...this.media.visibilityOptions,
            public: this.labels.public,
            registered: this.labels.registered,
            private: this.labels.private,
        };
    },

    uiText(value) {
        const text = String(value || '');
        if (text === '') {
            return '';
        }

        const entries = this.translationEntriesByLanguage?.[this.uiLanguage] || {};
        return typeof entries[text] === 'string' && entries[text] !== '' ? entries[text] : text;
    },

    normalizeTranslationSuggestionsByLanguage(suggestions) {
        if (Array.isArray(suggestions)) {
            return {
                '': this.normalizeTranslationSuggestions(suggestions),
            };
        }

        if (!suggestions || typeof suggestions !== 'object') {
            return {};
        }

        return Object.fromEntries(Object.entries(suggestions).map(([language, entries]) => [
            this.normalizeLanguageCode(language) || language,
            this.normalizeTranslationSuggestions(entries),
        ]));
    },

    normalizeTranslationSuggestions(suggestions) {
        return (Array.isArray(suggestions) ? suggestions : [])
            .map((suggestion) => ({
                key: String(suggestion?.key || ''),
                value: String(suggestion?.value || ''),
            }))
            .filter((suggestion) => suggestion.key !== '');
    },

    translationLanguage() {
        const language = String(this.persistedValues.language || this.defaultLanguage || this.languages[0] || 'en');
        return language === 'current' ? this.defaultLanguage : language;
    },

    currentTranslationSuggestions(language = this.translationLanguage()) {
        const normalized = this.normalizeLanguageCode(language) || language || this.translationLanguage();
        if (!this.translationSuggestionsByLanguage[normalized] && Array.isArray(this.translationSuggestionsByLanguage[''])) {
            return this.translationSuggestionsByLanguage[''];
        }
        this.translationSuggestionsByLanguage[normalized] = Array.isArray(this.translationSuggestionsByLanguage[normalized])
            ? this.translationSuggestionsByLanguage[normalized]
            : [];

        return this.translationSuggestionsByLanguage[normalized];
    },

    findTranslationSuggestion(language, key) {
        const normalizedKey = String(key || '').trim();
        if (normalizedKey === '') {
            return null;
        }

        return this.currentTranslationSuggestions(language).find((suggestion) => suggestion.key === normalizedKey) || null;
    },

    translateContentText(value, language = this.translationLanguage()) {
        const text = String(value || '');
        if (text === '') {
            return '';
        }

        const normalized = this.normalizeLanguageCode(language) || language || this.translationLanguage();
        const entries = this.translationEntriesByLanguage?.[normalized] || {};
        if (typeof entries[text] === 'string' && entries[text] !== '') {
            return entries[text];
        }

        const suggestion = this.findTranslationSuggestion(normalized, text);
        return suggestion?.value || text;
    },

    setTranslationSuggestion(language, key, value) {
        const suggestions = this.currentTranslationSuggestions(language);
        const existing = suggestions.find((suggestion) => suggestion.key === key);
        if (existing) {
            existing.value = value;
            return;
        }

        suggestions.push({key, value});
        suggestions.sort((a, b) => a.key.localeCompare(b.key));
    },

    matchingTranslationSuggestions(language, value) {
        const query = String(value || '').trim().toLowerCase();
        if (query === '') {
            return this.currentTranslationSuggestions(language).slice(0, 8);
        }

        const parts = query.split(/\s+/).filter(Boolean);
        return this.currentTranslationSuggestions(language)
            .map((suggestion) => ({
                ...suggestion,
                score: this.translationSuggestionScore(suggestion, parts),
            }))
            .filter((suggestion) => suggestion.score > 0)
            .sort((a, b) => b.score - a.score || a.key.localeCompare(b.key))
            .slice(0, 8);
    },

    translationSuggestionScore(suggestion, parts) {
        const key = suggestion.key.toLowerCase();
        const value = suggestion.value.toLowerCase();
        let score = key.startsWith('page.') || key.startsWith('pages.') ? 12 : 0;

        for (const part of parts) {
            if (key === part) {
                score += 120;
            } else if (key.startsWith(part)) {
                score += 80;
            } else if (key.includes(part)) {
                score += 45;
            }

            if (value === part) {
                score += 100;
            } else if (value.startsWith(part)) {
                score += 70;
            } else if (value.includes(part)) {
                score += 55;
            }
        }

        return score;
    },

    showTranslationSuggestions(input) {
        const promptContainer = input?.closest('.notify');
        const language = promptContainer?.querySelector('[data-page-translation-language]')?.value || this.translationLanguage();
        const matches = this.matchingTranslationSuggestions(language, input?.value || '');
        if (matches.length === 0) {
            this.hideTranslationSuggestions();
            return;
        }

        const suggestBox = this.ensureTranslationSuggestBox();
        this.activeTranslationSuggestInput = input;
        const rect = input.getBoundingClientRect();
        suggestBox.style.left = `${rect.left}px`;
        suggestBox.style.top = `${rect.bottom + 6}px`;
        suggestBox.style.width = `${Math.max(rect.width, 320)}px`;
        suggestBox.innerHTML = matches.map((suggestion) => `
            <button type="button" class="grid w-full gap-0.5 rounded px-2.5 py-2 text-left transition hover:bg-slate-100 dark:hover:bg-slate-900" data-page-translation-suggestion-key="${escapeHtml(suggestion.key)}">
                <span class="font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">${this.highlight(suggestion.key, input.value)}</span>
                <span class="truncate text-xs text-slate-500 dark:text-slate-400">${escapeHtml(suggestion.value)}</span>
            </button>
        `).join('');
        suggestBox.classList.remove('hidden');
    },

    hideTranslationSuggestions() {
        this.activeTranslationSuggestInput = null;
        if (!this.translationSuggestBox) {
            return;
        }
        this.translationSuggestBox.classList.add('hidden');
        this.translationSuggestBox.innerHTML = '';
    },

    ensureTranslationSuggestBox() {
        if (this.translationSuggestBox instanceof HTMLElement) {
            return this.translationSuggestBox;
        }

        this.translationSuggestBox = document.createElement('div');
        this.translationSuggestBox.className = 'fixed z-50 hidden max-h-64 overflow-auto rounded-md border border-slate-300 bg-white p-1 text-sm shadow-xl dark:border-slate-700 dark:bg-slate-950';
        this.translationSuggestBox.style.zIndex = '3600';
        this.translationSuggestBox.dataset.pageTranslationSuggestBox = 'true';
        document.body.appendChild(this.translationSuggestBox);

        return this.translationSuggestBox;
    },

    handleTranslationSuggestionClick(event) {
        if (!(event.target instanceof Element)) {
            return;
        }

        const option = event.target.closest('[data-page-translation-suggestion-key]');
        if (option && this.activeTranslationSuggestInput instanceof HTMLInputElement) {
            const promptContainer = this.activeTranslationSuggestInput.closest('.notify');
            const language = promptContainer?.querySelector('[data-page-translation-language]')?.value || this.translationLanguage();
            const key = option.dataset.pageTranslationSuggestionKey || '';
            const suggestion = this.findTranslationSuggestion(language, key);
            const keyInput = promptContainer?.querySelector('[data-page-translation-key]');
            const valueInput = promptContainer?.querySelector('[data-page-translation-value]');

            if (keyInput instanceof HTMLInputElement) {
                keyInput.value = key;
                keyInput.dispatchEvent(new Event('input', {bubbles: true}));
            }
            if (suggestion && valueInput instanceof HTMLInputElement) {
                valueInput.value = suggestion.value;
                valueInput.dispatchEvent(new Event('input', {bubbles: true}));
            }
            this.hideTranslationSuggestions();
            return;
        }

        if (!event.target.closest('[data-page-translation-key]') && !event.target.closest('[data-page-translation-value]') && !event.target.closest('[data-page-translation-suggest-box]')) {
            this.hideTranslationSuggestions();
        }
    },

    async openTranslationPicker(propName) {
        const content = this.selectedContent();
        if (!propName || !content) {
            return;
        }

        const currentValue = String(content.props?.[propName] || '');
        const language = this.translationLanguage();
        const initialValue = this.findTranslationSuggestion(language, currentValue)?.value || '';
        const promptResult = prompt(this.translationPromptHtml(language, currentValue, initialValue), {
            confirmButtonText: this.labels.apply,
            cancelButtonText: this.labels.cancel,
            showCancelButton: true,
            style: {width: 'min(980px, calc(100vw - 32px))', maxWidth: 'min(980px, calc(100vw - 32px))'},
            confirmCallback: (promptInstance) => {
                this.applyTranslationPicker(promptInstance, propName);
                return true;
            },
        });

        window.setTimeout(() => this.bindTranslationPrompt(document.querySelector('.page-builder-translation-prompt')), 0);
        await promptResult;
        this.hideTranslationSuggestions();
    },

    async applyTranslationPicker(promptInstance, propName) {
        const container = promptInstance.container;
        const language = this.normalizeLanguageCode(container.querySelector('[data-page-translation-language]')?.value) || this.translationLanguage();
        const keyInput = container.querySelector('[data-page-translation-key]');
        const key = String(keyInput?.value || '').trim();
        const value = String(container.querySelector('[data-page-translation-value]')?.value || '');
        if (key === '') {
            toast(this.labels.emptyTranslationKey);
            keyInput?.focus();
            return;
        }

        if (value.trim() !== '' || !this.findTranslationSuggestion(language, key)) {
            const saved = await this.saveTranslation(language, key, value);
            if (!saved) {
                return;
            }
            toast(this.labels.translationSaved);
        }

        this.setBlockProp(propName, key, {renderSettings: true});
        promptInstance.promise.resolve(true);
        promptInstance.remove();
    },

    translationPromptHtml(language, key, value) {
        return `
            <div>${escapeHtml(this.labels.translationKey)}</div>
            <div class="page-builder-translation-prompt translation-key-prompt notify-form">
                <p class="notify-form-intro">${escapeHtml(this.labels.searchCreateTranslationKey)}</p>
                <div class="translation-key-grid">
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.language)}</span>
                        <div class="translation-key-language-row">
                            <select data-styled-select data-page-translation-language>
                                ${this.translationLanguageOptions(language)}
                            </select>
                            <button class="translation-key-language-button" type="button" data-page-translation-new-language>
                                ${escapeHtml(this.labels.newLanguage)}
                            </button>
                        </div>
                    </label>
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.key)}</span>
                        <input class="font-mono" type="text" value="${escapeHtml(key)}" placeholder="page.contact.title" autocomplete="off" data-page-translation-key>
                    </label>
                </div>
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.labels.value)}</span>
                    <input type="text" value="${escapeHtml(value)}" placeholder="Contact" data-page-translation-value>
                </label>
                <p class="notify-form-intro">${escapeHtml(this.labels.storesOnlyTranslationKey)}</p>
            </div>
        `;
    },

    translationLanguageOptions(selectedLanguage = this.translationLanguage()) {
        const languages = [...this.languages];
        if (!languages.includes(selectedLanguage) && selectedLanguage !== '') {
            languages.unshift(selectedLanguage);
        }

        return languages.map((language) => `
            <option value="${escapeHtml(language)}" ${language === selectedLanguage ? 'selected' : ''}>${escapeHtml(this.labelForLanguage(language))}</option>
        `).join('');
    },

    bindTranslationPrompt(modal) {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        const languageSelect = modal.querySelector('[data-page-translation-language]');
        const keyInput = modal.querySelector('[data-page-translation-key]');
        const valueInput = modal.querySelector('[data-page-translation-value]');
        initStyledSelects(modal.querySelectorAll('select[data-styled-select]'));
        keyInput?.focus({preventScroll: true});

        const syncValueForSelectedLanguage = async ({overwrite = false} = {}) => {
            if (!(valueInput instanceof HTMLInputElement)) {
                return;
            }

            const language = languageSelect?.value || this.translationLanguage();
            await this.loadTranslations(language);
            const existing = this.findTranslationSuggestion(language, keyInput?.value);
            if (existing && (overwrite || valueInput.value.trim() === '')) {
                valueInput.value = existing.value;
                valueInput.dispatchEvent(new Event('input', {bubbles: true}));
            }
        };

        languageSelect?.addEventListener('change', () => {
            syncValueForSelectedLanguage({overwrite: true});
            this.showTranslationSuggestions(this.activeTranslationSuggestInput || keyInput);
        });
        keyInput?.addEventListener('focus', () => this.showTranslationSuggestions(keyInput));
        keyInput?.addEventListener('input', () => {
            syncValueForSelectedLanguage({overwrite: false});
            this.showTranslationSuggestions(keyInput);
        });
        valueInput?.addEventListener('focus', () => this.showTranslationSuggestions(valueInput));
        valueInput?.addEventListener('input', () => this.showTranslationSuggestions(valueInput));
        modal.querySelector('[data-page-translation-new-language]')?.addEventListener('click', async () => {
            const language = await this.openLanguagePrompt();
            if (!language || !(languageSelect instanceof HTMLSelectElement)) {
                return;
            }

            languageSelect.innerHTML = this.translationLanguageOptions(language);
            languageSelect.value = language;
            languageSelect.dispatchEvent(new Event('change', {bubbles: true}));
            keyInput?.focus({preventScroll: true});
        });
    },

    async openLanguagePrompt() {
        let createdLanguage = null;
        const promptResult = prompt(`
            <div>${escapeHtml(this.labels.newLanguage)}</div>
            <div class="page-builder-language-prompt notify-form">
                <p class="notify-form-intro">${escapeHtml(this.labels.chooseLanguageCode)}</p>
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.labels.language)}</span>
                    <select id="page-builder-language-code-input" data-styled-select>
                        ${this.languageCreationOptions()}
                    </select>
                </label>
            </div>
        `, {
            confirmButtonText: this.labels.createLanguage,
            cancelButtonText: this.labels.cancel,
            showCancelButton: true,
            confirmCallback: async (promptInstance) => {
                const select = promptInstance.container.querySelector('#page-builder-language-code-input');
                const language = this.normalizeLanguageCode(select?.value);
                if (language === null) {
                    toast(this.labels.invalidLanguage);
                    select?.focus();
                    return true;
                }

                const created = await this.createLanguage(language);
                if (!created) {
                    return true;
                }

                createdLanguage = language;
                promptInstance.promise.resolve(true);
                promptInstance.remove();
                return true;
            },
        });

        window.setTimeout(() => initStyledSelects(document.querySelectorAll('.page-builder-language-prompt select[data-styled-select]')), 0);
        const confirmed = await promptResult;

        return confirmed === true ? createdLanguage : null;
    },

    languageCreationOptions(selectedLanguage = '') {
        return [
            `<option value="" ${selectedLanguage === '' ? 'selected' : ''} disabled>${escapeHtml(this.labels.language)}</option>`,
            ...Object.entries(this.languageOptions).map(([language, label]) => `
                <option value="${escapeHtml(language)}" ${language === selectedLanguage ? 'selected' : ''}>${escapeHtml(label)} (${escapeHtml(language)})</option>
            `),
        ].join('');
    },

    async createLanguage(language) {
        language = this.normalizeLanguageCode(language);
        if (language === null) {
            toast(this.labels.invalidLanguage);
            return false;
        }

        // Merge, not replace: creating a language that already has overrides
        // must not wipe them.
        const response = await (window.NixCMS?.json || fetch)(this.translationEndpointFor(language), {
            method: 'POST',
            body: JSON.stringify({entries: {}, mode: 'merge'}),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            toast(result.reason || this.labels.couldNotSave);
            return false;
        }

        if (!this.languages.includes(language)) {
            this.languages.push(language);
            this.languages.sort();
        }
        this.translationSuggestionsByLanguage[language] = this.translationSuggestionsByLanguage[language] || [];
        toast(this.labels.languageCreated);
        return true;
    },

    async saveTranslation(language, key, value) {
        // Only the edited key. Sending every known suggestion turned the whole
        // default language pack into overrides and clobbered concurrent edits.
        const response = await (window.NixCMS?.json || fetch)(this.translationEndpointFor(language), {
            method: 'POST',
            body: JSON.stringify({entries: {[key]: value}, mode: 'merge'}),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            toast(result.reason || this.labels.couldNotSave);
            return false;
        }

        this.setTranslationSuggestion(language, key, value);
        return true;
    },

    translationEndpointFor(language) {
        return String(this.translationEndpoint || '/api/admin/translations/{language}')
            .replace('{language}', encodeURIComponent(language));
    },

    translationReadEndpointFor(language) {
        return String(this.translationReadEndpoint || this.translationEndpoint || '/api/admin/translations/{language}')
            .replace('{language}', encodeURIComponent(language));
    },

    normalizeLanguageCode(value) {
        const language = String(value || '').trim().toLowerCase();
        return new RegExp(String(window.NixCMS?.languagePattern || '^[a-z]{2,3}(?:-[a-z0-9]{2,4})?$')).test(language) && language.length <= 8 ? language : null;
    },

    labelForLanguage(language) {
        return String(language || '').toUpperCase();
    },

    highlight(value, query) {
        const safe = escapeHtml(value);
        const term = String(query || '').trim().split(/\s+/).find(Boolean);
        if (!term) {
            return safe;
        }

        const index = value.toLowerCase().indexOf(term.toLowerCase());
        if (index < 0) {
            return safe;
        }

        return `${escapeHtml(value.slice(0, index))}<mark class="rounded bg-sky-500/20 px-0.5 text-inherit">${escapeHtml(value.slice(index, index + term.length))}</mark>${escapeHtml(value.slice(index + term.length))}`;
    },

};
