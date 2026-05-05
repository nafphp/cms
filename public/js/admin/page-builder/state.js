import {
    contentWidthPresets,
    optionLabel,
    previewViewports,
    storageKey,
} from './shared.js';

export const stateMethods = {
    async init() {
        this.stepNumber = this.root.querySelector('[data-step-number]');
        this.stepTitle = this.root.querySelector('[data-step-title]');
        this.stepSubtitle = this.root.querySelector('[data-step-subtitle]');
        this.backButton = this.root.querySelector('[data-back-step]');
        this.nextButton = this.root.querySelector('[data-next-step]');
        this.templateList = this.root.querySelector('[data-template-list]');
        this.templatePreview = this.root.querySelector('[data-template-preview]');
        this.templatePreviewTitle = this.root.querySelector('[data-template-preview-title]');
        this.headerNavigationControl = this.root.querySelector('[data-header-navigation-control]');
        this.footerNavigationControl = this.root.querySelector('[data-footer-navigation-control]');
        this.blockCanvas = this.root.querySelector('[data-block-canvas]');
        this.filledCount = this.root.querySelector('[data-filled-count]');
        this.pagePreview = this.root.querySelector('[data-page-preview]');
        this.settingsDrawer = this.root.querySelector('[data-settings-drawer]');
        this.contentSettings = this.root.querySelector('[data-content-settings]');
        this.json = this.root.querySelector('[data-builder-json]');
        this.previewStage = this.root.querySelector('[data-preview-stage]');
        this.previewRuler = this.root.querySelector('[data-preview-ruler]');
        this.previewFrame = this.root.querySelector('[data-preview-frame]');
        this.previewTitle = this.root.querySelector('[data-preview-title]');
        this.previewViewportLabel = this.root.querySelector('[data-preview-viewport-label]');
        this.contentWidthLabel = this.root.querySelector('[data-content-width-label]');
        this.saveButton = this.root.querySelector('[data-save-page]');
        this.metaTemplate = this.root.querySelector('[data-meta-template]');
        this.metaSummary = this.root.querySelector('[data-meta-summary]');
        this.metaPreviewTitle = this.root.querySelector('[data-meta-preview-title]');
        this.metaPreviewPath = this.root.querySelector('[data-meta-preview-path]');
        this.metaPreviewDescription = this.root.querySelector('[data-meta-preview-description]');

        if (this.editMode) {
            this.applyInitialState();
            this.restoreState();
        } else {
            this.restoreState();
        }
        if (this.isCustomTemplate() && this.customLayout.length === 0) {
            this.applyTemplateCustomLayout();
        } else {
            this.syncCustomTemplateBlocks();
        }
        this.applyDefaultPersistedValues();
        this.ensureTemplateDefaults();
        this.syncPageTheme();
        await this.loadPageBuilderLabels(this.uiLanguage);

        this.root.addEventListener('click', (event) => this.handleClick(event));
        this.root.addEventListener('input', (event) => this.handleInput(event));
        this.root.addEventListener('change', (event) => this.handleInput(event));
        this.root.addEventListener('dragstart', (event) => this.handleDragStart(event));
        this.root.addEventListener('dragover', (event) => this.handleDragOver(event));
        this.root.addEventListener('drop', (event) => this.handleDrop(event));
        this.root.addEventListener('dragend', () => this.handleDragEnd());
        document.addEventListener('click', (event) => this.handleTranslationSuggestionClick(event));
        window.addEventListener('resize', () => this.hideTranslationSuggestions());
        window.addEventListener('scroll', () => this.hideTranslationSuggestions(), true);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeSettings();
            }
        });

        this.render();
    },

    defaultPersistedValues({preserveNavigation = true} = {}) {
        const defaultNavigation = String(
            (preserveNavigation ? this.persistedValues.defaultNavigationIdentifier : '')
            || this.defaultNavigationIdentifier
            || this.navigationChoices[0]?.identifier
            || 'main'
        );
        const defaultFooterNavigation = String((preserveNavigation ? this.persistedValues.defaultFooterNavigationIdentifier : '') || defaultNavigation);

        return {
            defaultNavigationIdentifier: defaultNavigation,
            defaultFooterNavigationIdentifier: defaultFooterNavigation,
            language: this.defaultLanguage,
            status: 'draft',
            'seo.robots': 'index,follow',
            'theme.accent': '#3f7fbd',
            'theme.headerBackground': '#020617',
            'theme.headerBackgroundTransparent': true,
            'theme.footerBackground': '#020617',
            'theme.footerBackgroundTransparent': false,
            'theme.contentWidth': 'template',
            'theme.headerContentWidthIncludesChrome': true,
            'theme.headerBackgroundWidthIncludesChrome': false,
            'theme.footerContentWidthIncludesChrome': true,
            'theme.footerBackgroundWidthIncludesChrome': false,
        };
    },

    applyDefaultPersistedValues() {
        Object.entries(this.defaultPersistedValues()).forEach(([key, value]) => {
            if (
                !Object.prototype.hasOwnProperty.call(this.persistedValues, key)
                || this.persistedValues[key] === ''
                || this.persistedValues[key] === null
                || typeof this.persistedValues[key] === 'undefined'
            ) {
                this.persistedValues[key] = value;
            }
        });
    },

    resetBuilderState() {
        window.clearTimeout(this.saveTimer);
        try {
            window.localStorage.removeItem(this.storageKey);
            window.localStorage.removeItem(storageKey);
        } catch (error) {}

        this.step = 'template';
        this.templateId = this.templates[0]?.id || '';
        this.selectedBlockId = null;
        this.selectedNestedPath = '';
        this.blockContent = {};
        this.customLayout = [];
        this.persistedValues = this.defaultPersistedValues({preserveNavigation: false});
        this.settingsOpen = false;
        this.previewViewport = 'desktop';
        this.previewTemplateId = null;
        this.savedId = 0;

        if (this.isCustomTemplate()) {
            this.applyTemplateCustomLayout();
        } else {
            this.syncCustomTemplateBlocks();
        }
        this.ensureTemplateDefaults();
        this.syncPageTheme();
        this.render();
    },

    applyInitialState() {
        const template = this.findTemplate(
            this.initialState.templateId
                || this.queryParams.get('template')
                || this.queryParams.get('templateId')
                || this.queryParams.get('layout')
                || this.queryParams.get('view')
                || ''
        );

        if (template) {
            this.templateId = template.id;
        }

        if (this.initialState.blockContent && typeof this.initialState.blockContent === 'object' && !Array.isArray(this.initialState.blockContent)) {
            this.blockContent = this.initialState.blockContent;
        }

        if (Array.isArray(this.initialState.customLayout)) {
            this.customLayout = this.normalizeCustomLayout(this.initialState.customLayout);
            this.syncCustomTemplateBlocks();
        }

        if (this.initialState.persistedValues && typeof this.initialState.persistedValues === 'object' && !Array.isArray(this.initialState.persistedValues)) {
            this.persistedValues = this.initialState.persistedValues;
        }

        const selectedTemplate = this.selectedTemplate();
        this.step = selectedTemplate?.layout ? 'layout' : 'template';
        this.selectedBlockId = this.step === 'layout' ? (selectedTemplate?.blocks?.[0]?.id || null) : null;
    },

    findTemplate(value) {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === '') {
            return null;
        }

        return this.templates.find((template) => [
            template.id,
            template.label,
            template.layout,
            template.view,
        ].some((candidate) => String(candidate || '').trim().toLowerCase() === normalized)) || null;
    },

    restoreState() {
        let state = null;
        try {
            const fallback = this.editMode ? null : window.localStorage.getItem(storageKey);
            state = JSON.parse(window.localStorage.getItem(this.storageKey) || fallback || 'null');
        } catch (error) {
            state = null;
        }

        if (!state || typeof state !== 'object') {
            return;
        }

        const templateExists = this.templates.some((template) => template.id === state.templateId);
        if (templateExists) {
            this.templateId = state.templateId;
        }

        if (state.step === 'template' || state.step === 'layout' || state.step === 'meta') {
            this.step = state.step;
        }

        if (previewViewports[state.previewViewport]) {
            this.previewViewport = state.previewViewport;
        }

        if (state.blockContent && typeof state.blockContent === 'object' && !Array.isArray(state.blockContent)) {
            this.blockContent = state.blockContent;
        }

        if (state.persistedValues && typeof state.persistedValues === 'object' && !Array.isArray(state.persistedValues)) {
            this.persistedValues = state.persistedValues;
        }

        if (Array.isArray(state.customLayout)) {
            this.customLayout = this.normalizeCustomLayout(state.customLayout);
            this.syncCustomTemplateBlocks();
        }

        const blockExists = (this.selectedTemplate()?.blocks || []).some((block) => block.id === state.selectedBlockId);
        if (blockExists) {
            this.selectedBlockId = state.selectedBlockId;
        } else if (this.step === 'layout') {
            this.selectedBlockId = this.selectedTemplate()?.blocks?.[0]?.id || null;
        }
        this.selectedNestedPath = this.contentAtPath(String(state.selectedNestedPath || '')) ? String(state.selectedNestedPath || '') : '';

        this.settingsOpen = Boolean(state.settingsOpen && this.selectedBlockId && this.blockContent[this.selectedBlockId]);
        this.savedId = Number(state.savedId || 0);
    },

    persistState() {
        this.collectPersistedFields();

        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify({
                step: this.step,
                templateId: this.templateId,
                previewViewport: this.previewViewport,
                selectedBlockId: this.selectedBlockId,
                selectedNestedPath: this.selectedNestedPath,
                blockContent: this.blockContent,
                customLayout: this.customLayout,
                persistedValues: this.persistedValues,
                settingsOpen: this.settingsOpen,
                savedId: this.savedId,
            }));
        } catch (error) {}

        this.scheduleRemotePersist();
    },

    collectPersistedFields() {
        this.root.querySelectorAll('[data-persist-key]').forEach((field) => {
            const key = field.dataset.persistKey || '';
            if (key === '') {
                return;
            }

            this.persistedValues[key] = this.readPersistedField(field);
        });
    },

    applyPersistedFields() {
        this.root.querySelectorAll('[data-persist-key]').forEach((field) => {
            const key = field.dataset.persistKey || '';
            if (key === '' || !(key in this.persistedValues)) {
                return;
            }

            this.writePersistedField(field, this.persistedValues[key]);
        });
    },

    readPersistedField(field) {
        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
            return field.checked;
        }

        if (field instanceof HTMLInputElement && field.type === 'radio') {
            return this.root.querySelector(`[data-persist-key="${CSS.escape(field.dataset.persistKey || '')}"]:checked`)?.value || null;
        }

        if (field instanceof HTMLSelectElement && field.multiple) {
            return Array.from(field.selectedOptions).map((option) => option.value);
        }

        return field.value ?? null;
    },

    writePersistedField(field, value) {
        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
            field.checked = Boolean(value);
            return;
        }

        if (field instanceof HTMLInputElement && field.type === 'radio') {
            field.checked = field.value === value;
            return;
        }

        if (field instanceof HTMLSelectElement && field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach((option) => {
                option.selected = value.includes(option.value);
            });
            field.dispatchEvent(new Event('change'));
            return;
        }

        if (field instanceof HTMLInputElement && field.type === 'color') {
            const fallback = field.dataset.persistKey === 'theme.headerBackground' ? '#020617' : '#3f7fbd';
            field.value = this.normalizeColor(value) || fallback;
            return;
        }

        field.value = value ?? '';
        if (field instanceof HTMLSelectElement) {
            field.dispatchEvent(new Event('change'));
        }
    },

    normalizeColor(value) {
        const color = String(value || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(color) ? color : '';
    },

    pageAccent() {
        return this.normalizeColor(this.persistedValues['theme.accent']) || '#3f7fbd';
    },

    headerBackgroundTransparent() {
        const value = this.persistedValues['theme.headerBackgroundTransparent'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    pageHeaderBackground() {
        if (this.headerBackgroundTransparent()) {
            return 'transparent';
        }

        return this.normalizeColor(this.persistedValues['theme.headerBackground']) || '#020617';
    },

    footerBackgroundTransparent() {
        const value = this.persistedValues['theme.footerBackgroundTransparent'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    pageFooterBackground() {
        if (this.footerBackgroundTransparent()) {
            return 'transparent';
        }

        return this.normalizeColor(this.persistedValues['theme.footerBackground']) || '#020617';
    },

    contentWidthKey() {
        const key = String(this.persistedValues['theme.contentWidth'] || 'template').trim();
        return contentWidthPresets[key] ? key : 'template';
    },

    contentWidthCssValue() {
        return contentWidthPresets[this.contentWidthKey()]?.css || '';
    },

    templateDefaultWidth(template = this.selectedTemplate()) {
        return {
            public: '100%',
            landing: '1120px',
            editorial: '1040px',
            'feature-grid': '1120px',
            'article-detail': '820px',
            'two-column': '960px',
            'full-bleed': '100%',
            custom: '1120px',
        }[template?.id || ''] || '1120px';
    },

    contentWidthDisplay() {
        const key = this.contentWidthKey();
        const chromeSuffix = this.headerContentWidthIncludesChrome() || this.footerContentWidthIncludesChrome() ? ` · ${this.labels.headerFooter}` : '';
        if (key === 'template') {
            return `${this.labels.contentWidthTemplate} · ${this.templateDefaultWidth()}${chromeSuffix}`;
        }

        const preset = contentWidthPresets[key];
        const label = optionLabel(preset, this.labels);
        return preset.css === '100%' ? `${label}${chromeSuffix}` : `${label} · ${preset.css}${chromeSuffix}`;
    },

    headerContentWidthIncludesChrome() {
        const value = this.persistedValues['theme.headerContentWidthIncludesChrome'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    headerBackgroundWidthIncludesChrome() {
        const value = this.persistedValues['theme.headerBackgroundWidthIncludesChrome'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    footerContentWidthIncludesChrome() {
        const value = this.persistedValues['theme.footerContentWidthIncludesChrome'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    footerBackgroundWidthIncludesChrome() {
        const value = this.persistedValues['theme.footerBackgroundWidthIncludesChrome'];
        return value === true || value === 'true' || value === 1 || value === '1';
    },

    syncPageTheme() {
        this.root.style.setProperty('--page-accent', this.pageAccent());
        this.root.style.setProperty('--admin-page-accent', this.pageAccent());
        this.root.style.setProperty('--page-header-bg', this.pageHeaderBackground());
        this.root.style.setProperty('--page-footer-bg', this.pageFooterBackground());
        this.root.style.setProperty('--page-template-default-width', this.templateDefaultWidth());
        this.root.dataset.headerContentWidthIncludesChrome = this.headerContentWidthIncludesChrome() ? 'true' : 'false';
        this.root.dataset.headerBackgroundWidthIncludesChrome = this.headerBackgroundWidthIncludesChrome() ? 'true' : 'false';
        this.root.dataset.footerContentWidthIncludesChrome = this.footerContentWidthIncludesChrome() ? 'true' : 'false';
        this.root.dataset.footerBackgroundWidthIncludesChrome = this.footerBackgroundWidthIncludesChrome() ? 'true' : 'false';
        const width = this.contentWidthCssValue();
        if (width !== '') {
            this.root.style.setProperty('--page-content-max-width', width);
        } else {
            this.root.style.removeProperty('--page-content-max-width');
        }
    },

    selectedTemplate() {
        return this.templates.find((template) => template.id === this.templateId) || this.templates[0] || null;
    },

    isCustomTemplate(template = this.selectedTemplate()) {
        return (template?.kind || 'static') === 'custom';
    },

    templateHasHeader(template = this.selectedTemplate()) {
        return (template?.blocks || []).some((block) => ['site_header'].includes(String(block?.id || '')) || block?.preview === 'navigation');
    },

    templateHasFooter(template = this.selectedTemplate()) {
        return (template?.blocks || []).some((block) => ['site_footer', 'footer'].includes(String(block?.id || '')) || block?.preview === 'footer');
    },

    storedTemplateId(template = this.selectedTemplate()) {
        if (this.isCustomTemplate(template)) {
            return template?.baseTemplateId || 'custom';
        }

        return template?.id || this.templateId;
    },

    ensureTemplateDefaults() {
        for (const block of this.selectedTemplate()?.blocks || []) {
            if (this.blockContent[block.id]) {
                continue;
            }

            const content = this.defaultContentForBlock(block);
            if (content) {
                this.blockContent[block.id] = content;
            }
        }
    },

    updateDefaultNavigation(identifier) {
        const previous = this.selectedDefaultNavigationIdentifier();
        this.persistedValues.defaultNavigationIdentifier = identifier || this.defaultNavigationIdentifier;

        for (const content of Object.values(this.blockContent)) {
            if (!content || typeof content !== 'object') {
                continue;
            }

            if (content.props?.navigationIdentifier === previous || content.type === 'aside') {
                content.props.navigationIdentifier = this.selectedDefaultNavigationIdentifier();
            }
            if (content.props?.identifier === previous || content.type === 'navigation') {
                content.props.identifier = this.selectedDefaultNavigationIdentifier();
            }
        }

        this.previewTemplateId = null;
    },

    updateDefaultFooterNavigation(identifier) {
        const previous = this.selectedFooterNavigationIdentifier();
        this.persistedValues.defaultFooterNavigationIdentifier = identifier || this.selectedDefaultNavigationIdentifier();

        for (const content of Object.values(this.blockContent)) {
            if (!content || typeof content !== 'object') {
                continue;
            }

            if (content.props?.navigationIdentifier === previous || content.type === 'footer') {
                content.props.navigationIdentifier = this.selectedFooterNavigationIdentifier();
            }
        }

        this.previewTemplateId = null;
    },

    syncPersistedControls(key, source = null) {
        this.root.querySelectorAll(`[data-persist-key="${CSS.escape(key)}"]`).forEach((field) => {
            if (field === source) {
                return;
            }
            this.writePersistedField(field, this.persistedValues[key]);
        });
    },

};
