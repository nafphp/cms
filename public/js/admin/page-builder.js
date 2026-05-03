import notifyAlert from '../lib/notifyJs/Modules/Alert.js';
import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import {initStyledSelects} from '../components/styledSelect.js';

const cardClass = 'rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:shadow-sm';
const layoutClass = 'group grid gap-3 rounded-lg border-2 border-slate-800 bg-[#0b1118] p-3 text-left transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]';
const activeLayoutClass = 'border-[var(--admin-page-accent)] bg-[#122033]';
const inputClass = 'min-h-9 w-full rounded-md border border-slate-700 bg-[#0b1017] px-3 py-2 text-sm text-slate-100 outline-none focus:border-[var(--admin-page-accent)]';
const previewFrameClass = 'pointer-events-none block aspect-video w-full rounded-md border-0 bg-slate-950 opacity-0 transition-opacity duration-200 ease-out';

const clone = (value) => JSON.parse(JSON.stringify(value));
const storageKey = 'nixcms:page-builder:draft';
const flashToastKey = 'nixcms:flash-toast';
const previewViewports = {
    desktop: {label: 'Desktop', labelKey: 'viewportDesktop', width: 1280},
    tablet: {label: 'Tablet', labelKey: 'viewportTablet', width: 768},
    mobile: {label: 'Mobile', labelKey: 'viewportMobile', width: 390},
};
const contentWidthPresets = {
    template: {label: 'Template default', labelKey: 'contentWidthTemplate', css: ''},
    narrow: {label: 'Narrow', labelKey: 'contentWidthNarrow', css: '760px'},
    comfortable: {label: 'Comfortable', labelKey: 'contentWidthComfortable', css: '960px'},
    wide: {label: 'Wide', labelKey: 'contentWidthWide', css: '1120px'},
    full: {label: 'Full width', labelKey: 'contentWidthFull', css: '100%'},
};
const customLayoutRegions = [
    {value: 'header', label: 'Header', labelKey: 'regionHeader'},
    {value: 'main', label: 'Main', labelKey: 'regionMain'},
    {value: 'aside', label: 'Aside', labelKey: 'regionAside'},
    {value: 'footer', label: 'Footer', labelKey: 'regionFooter'},
];
const customLayoutSizes = [
    {value: 'small', label: 'Small width', labelKey: 'sizeSmall'},
    {value: 'medium', label: 'Medium width', labelKey: 'sizeMedium'},
    {value: 'large', label: 'Large width', labelKey: 'sizeLarge'},
    {value: 'full', label: 'Full width', labelKey: 'sizeFull'},
    {value: 'aside', label: 'Aside width', labelKey: 'sizeAside'},
    {value: 'hero', label: 'Hero width', labelKey: 'sizeHero'},
];
const customLayoutPositions = [
    {value: 'stretch', label: 'Auto', labelKey: 'positionAuto'},
    {value: 'start', label: 'Left', labelKey: 'positionLeft'},
    {value: 'center', label: 'Center', labelKey: 'positionCenter'},
    {value: 'end', label: 'Right', labelKey: 'positionRight'},
];
const customLayoutMaxHeights = [
    {value: 'none', label: 'No max', labelKey: 'maxHeightNone'},
    {value: '320', label: '320 px'},
    {value: '480', label: '480 px'},
    {value: '640', label: '640 px'},
    {value: '800', label: '800 px'},
    {value: 'screen', label: 'Viewport', labelKey: 'maxHeightViewport'},
];
const customLayoutOverflows = [
    {value: 'visible', label: 'Visible', labelKey: 'overflowVisible'},
    {value: 'hidden', label: 'Hidden', labelKey: 'overflowHidden'},
    {value: 'auto', label: 'Scroll', labelKey: 'overflowScroll'},
];
const customLayoutSurfaces = [
    {value: 'none', label: 'None', labelKey: 'surfaceNone'},
    {value: 'soft', label: 'Soft', labelKey: 'surfaceSoft'},
    {value: 'card', label: 'Card', labelKey: 'surfaceCard'},
    {value: 'accent', label: 'Accent', labelKey: 'surfaceAccent'},
];
const layoutChildContainerStyles = [
    {value: 'none', label: 'None', labelKey: 'surfaceNone'},
    {value: 'border', label: 'Border', labelKey: 'containerBorder'},
    {value: 'soft', label: 'Soft', labelKey: 'containerSoft'},
    {value: 'card', label: 'Card', labelKey: 'containerCard'},
    {value: 'accent', label: 'Accent', labelKey: 'containerAccent'},
];
const layoutBlockTypes = ['section', 'stack', 'columns', 'grid'];
const chartMetricColors = ['#3b82f6', '#f59e0b', '#22c55e', '#8b5cf6', '#64748b', '#ef4444', '#06b6d4', '#f97316'];
const chartMaxMetrics = 24;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));
const hasOption = (options, value) => options.some((item) => item.value === String(value ?? ''));
const optionValue = (options, value, fallback) => hasOption(options, value) ? String(value) : fallback;
const optionLabel = (item, labels = {}) => item.labelKey && labels[item.labelKey] ? labels[item.labelKey] : item.label;
const optionOptions = (options, selected, labels = {}) => options.map((item) => `
    <option value="${escapeHtml(item.value)}" ${item.value === selected ? 'selected' : ''}>${escapeHtml(optionLabel(item, labels))}</option>
`).join('');

const languageIcon = `
    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m5 8 6 6"></path>
        <path d="m4 14 6-6 2-3"></path>
        <path d="M2 5h12"></path>
        <path d="M7 2h1"></path>
        <path d="m22 22-5-10-5 10"></path>
        <path d="M14 18h6"></path>
    </svg>
`;

class PageBuilder {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config || '{}');
        this.saveUrl = root.dataset.saveUrl || this.config.saveUrl || '/admin/pages';
        this.layoutSaveUrl = root.dataset.layoutSaveUrl || this.config.layoutSaveUrl || '/api/admin/page-layouts';
        this.overviewUrl = root.dataset.overviewUrl || this.config.overviewUrl || '/admin/pages';
        this.labels = {
            saved: 'Page saved.',
            couldNotSave: 'Page could not be saved.',
            cancel: 'Cancel',
            mediaPickerTitle: 'Select media',
            mediaPickerIntro: 'Upload a new image or choose an existing media item.',
            mediaUpload: 'Upload image',
            mediaChooseFile: 'Choose image',
            mediaLibrary: 'Media library',
            mediaUse: 'Use media',
            mediaNoSelection: 'Please select or upload a media item.',
            mediaUploadFailed: 'Upload failed.',
            mediaFileTooLarge: 'File is too large.',
            mediaInvalidExtension: 'File type is not allowed.',
            mediaNoFiles: 'No media files yet.',
            mediaEmptyUrl: 'No media selected.',
            mediaReplace: 'Replace media',
            mediaSelect: 'Select media',
            mediaClear: 'Clear',
            visibility: 'Visibility',
            altText: 'Alt text',
            public: 'Public',
            registered: 'Registered',
            private: 'Private',
            translationKey: 'Translation key',
            searchCreateTranslationKey: 'Search or create a translation key for this field.',
            key: 'Key',
            value: 'Value',
            language: 'Language',
            apply: 'Apply',
            addCustomBlock: 'Block hinzufügen',
            chooseCustomBlock: 'Choose content type',
            chooseCustomBlockIntro: 'Pick the content block for the new custom slot.',
            customLayout: 'Custom layout',
            customSlotsCount: ':count custom slots',
            deleteSlot: 'Delete slot',
            duplicateSlot: 'A slot with this id already exists.',
            addHeaderBlock: 'Header hinzufügen',
            addFooterBlock: 'Footer hinzufügen',
            chromeContentWidth: 'Header/footer content',
            chromeBackgroundWidth: 'Header/footer background',
            saveCustomLayout: 'Save as layout',
            saveLayoutTitle: 'Save custom layout',
            saveLayoutIntro: 'Save this slot structure as a reusable layout for future pages.',
            layoutName: 'Layout name',
            layoutDescription: 'Description',
            layoutSaved: 'Layout saved.',
            layoutNameRequired: 'Please enter a layout name.',
            addChildBlock: 'Block hinzufügen',
            chooseChildBlock: 'Choose child block',
            chooseChildBlockIntro: 'Pick the content block to place inside this layout module.',
            nestedBlocks: 'Nested blocks',
            backToLayoutBlock: 'Back to layout block',
            editChildBlock: 'Edit child block',
            removeChildBlock: 'Remove child block',
            layoutType: 'Layout type',
            container: 'Container',
            position: 'Position',
            maxHeight: 'Max height',
            overflow: 'Overflow',
            surface: 'Surface',
            regionHeader: 'Header',
            regionMain: 'Main',
            regionAside: 'Aside',
            regionFooter: 'Footer',
            sizeSmall: 'Small width',
            sizeMedium: 'Medium width',
            sizeLarge: 'Large width',
            sizeFull: 'Full width',
            sizeAside: 'Aside width',
            sizeHero: 'Hero width',
            positionAuto: 'Auto',
            positionLeft: 'Left',
            positionCenter: 'Center',
            positionRight: 'Right',
            maxHeightNone: 'No max',
            maxHeightViewport: 'Viewport',
            overflowVisible: 'Visible',
            overflowHidden: 'Hidden',
            overflowScroll: 'Scroll',
            surfaceNone: 'None',
            surfaceSoft: 'Soft',
            surfaceCard: 'Card',
            surfaceAccent: 'Accent',
            containerNone: 'None',
            containerBorder: 'Border',
            containerSoft: 'Soft',
            containerCard: 'Card',
            containerAccent: 'Accent',
            content: 'Content',
            headerFooter: 'Header/footer',
            preview: 'preview',
            viewport: 'viewport',
            viewportDesktop: 'Desktop',
            viewportTablet: 'Tablet',
            viewportMobile: 'Mobile',
            contentWidthTemplate: 'Template default',
            contentWidthNarrow: 'Narrow',
            contentWidthComfortable: 'Comfortable',
            contentWidthWide: 'Wide',
            contentWidthFull: 'Full width',
            width: 'Width',
            bounded: 'Bounded',
            customSlots: 'Custom slots',
            metrics: 'Metrics',
            addMetric: 'Add metric',
            removeMetric: 'Remove metric',
            metricLabel: 'Label',
            metricValue: 'Value',
            metricColor: 'Color',
            metricFallback: 'Metric :number',
            newLanguage: 'New language',
            createLanguage: 'Create language',
            chooseLanguageCode: 'Choose a language code.',
            invalidLanguage: 'Please choose a valid language code.',
            languageCreated: 'Language created.',
            translationSaved: 'Translation saved.',
            emptyTranslationKey: 'Please enter a translation key.',
            storesOnlyTranslationKey: 'The field stores only the translation key.',
            ...(this.config.labels || {}),
        };
        this.media = {
            urls: {},
            acceptedExtensions: [],
            maxUploadSize: 0,
            canUpload: false,
            visibilityOptions: {
                public: this.labels.public,
                registered: this.labels.registered,
                private: this.labels.private,
            },
            ...(this.config.media || {}),
        };
        this.templates = Array.isArray(this.config.templates) ? this.config.templates : [];
        this.contentTypes = Array.isArray(this.config.contentTypes) ? this.config.contentTypes : [];
        this.navigationChoices = Array.isArray(this.config.navigationChoices) ? this.config.navigationChoices : [];
        this.languages = Array.isArray(this.config.languages) ? this.config.languages : [];
        this.languageOptions = this.config.languageOptions && typeof this.config.languageOptions === 'object' ? this.config.languageOptions : {};
        this.defaultLanguage = this.config.defaultLanguage || this.languages[0] || 'en';
        this.translationSuggestionsByLanguage = this.normalizeTranslationSuggestionsByLanguage(this.config.translationSuggestions || {});
        this.translationEndpoint = this.config.translationEndpoint || '/api/admin/translations/{language}';
        this.translationSuggestBox = null;
        this.activeTranslationSuggestInput = null;
        this.defaultNavigationIdentifier = this.config.defaultNavigationIdentifier || this.navigationChoices[0]?.identifier || 'main';
        this.queryParams = new URLSearchParams(window.location.search);
        this.initialState = this.config.initialState && typeof this.config.initialState === 'object' ? this.config.initialState : {};
        this.editMode = this.config.mode === 'edit' || this.queryParams.get('mode') === 'edit' || this.queryParams.has('template');
        this.step = 'template';
        this.templateId = this.templates[0]?.id || '';
        this.selectedBlockId = null;
        this.selectedNestedPath = '';
        this.blockContent = {};
        this.customLayout = [];
        this.persistedValues = {};
        this.templatesRendered = false;
        this.previewTemplateId = null;
        this.settingsOpen = false;
        this.previewViewport = 'desktop';
        this.draggedCustomSlotId = null;
        this.storageKey = `${storageKey}:${window.location.pathname}${window.location.search || ''}`;
        this.saveTimer = null;
        this.saving = false;
        this.savedId = Number(this.initialState.id || 0);
    }

    init() {
        this.stepNumber = this.root.querySelector('[data-step-number]');
        this.stepTitle = this.root.querySelector('[data-step-title]');
        this.stepSubtitle = this.root.querySelector('[data-step-subtitle]');
        this.backButton = this.root.querySelector('[data-back-step]');
        this.nextButton = this.root.querySelector('[data-next-step]');
        this.templateList = this.root.querySelector('[data-template-list]');
        this.templatePreview = this.root.querySelector('[data-template-preview]');
        this.templateView = this.root.querySelector('[data-template-view]');
        this.templatePreviewTitle = this.root.querySelector('[data-template-preview-title]');
        this.blockCanvas = this.root.querySelector('[data-block-canvas]');
        this.filledCount = this.root.querySelector('[data-filled-count]');
        this.pagePreview = this.root.querySelector('[data-page-preview]');
        this.settingsDrawer = this.root.querySelector('[data-settings-drawer]');
        this.contentSettings = this.root.querySelector('[data-content-settings]');
        this.json = this.root.querySelector('[data-builder-json]');
        this.previewStage = this.root.querySelector('[data-preview-stage]');
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
        if (!this.persistedValues.defaultNavigationIdentifier) {
            this.persistedValues.defaultNavigationIdentifier = this.defaultNavigationIdentifier;
        }
        if (!this.persistedValues.language) {
            this.persistedValues.language = this.defaultLanguage;
        }
        if (!this.persistedValues.status) {
            this.persistedValues.status = 'draft';
        }
        if (!this.persistedValues['seo.robots']) {
            this.persistedValues['seo.robots'] = 'index,follow';
        }
        if (!this.persistedValues['theme.accent']) {
            this.persistedValues['theme.accent'] = '#3f7fbd';
        }
        if (!this.persistedValues['theme.contentWidth']) {
            this.persistedValues['theme.contentWidth'] = 'template';
        }
        if (!Object.prototype.hasOwnProperty.call(this.persistedValues, 'theme.contentWidthIncludesChrome')) {
            this.persistedValues['theme.contentWidthIncludesChrome'] = true;
        }
        if (!Object.prototype.hasOwnProperty.call(this.persistedValues, 'theme.contentWidthIncludesChromeBackground')) {
            this.persistedValues['theme.contentWidthIncludesChromeBackground'] = false;
        }
        this.ensureTemplateDefaults();
        this.syncPageTheme();

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
    }

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
    }

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
    }

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
    }

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
    }

    collectPersistedFields() {
        this.root.querySelectorAll('[data-persist-key]').forEach((field) => {
            const key = field.dataset.persistKey || '';
            if (key === '') {
                return;
            }

            this.persistedValues[key] = this.readPersistedField(field);
        });
    }

    applyPersistedFields() {
        this.root.querySelectorAll('[data-persist-key]').forEach((field) => {
            const key = field.dataset.persistKey || '';
            if (key === '' || !(key in this.persistedValues)) {
                return;
            }

            this.writePersistedField(field, this.persistedValues[key]);
        });
    }

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
    }

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
            field.value = this.normalizeColor(value) || '#3f7fbd';
            return;
        }

        field.value = value ?? '';
        if (field instanceof HTMLSelectElement) {
            field.dispatchEvent(new Event('change'));
        }
    }

    normalizeColor(value) {
        const color = String(value || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(color) ? color : '';
    }

    pageAccent() {
        return this.normalizeColor(this.persistedValues['theme.accent']) || '#3f7fbd';
    }

    contentWidthKey() {
        const key = String(this.persistedValues['theme.contentWidth'] || 'template').trim();
        return contentWidthPresets[key] ? key : 'template';
    }

    contentWidthCssValue() {
        return contentWidthPresets[this.contentWidthKey()]?.css || '';
    }

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
    }

    contentWidthDisplay() {
        const key = this.contentWidthKey();
        const chromeSuffix = this.contentWidthIncludesChrome() ? ` · ${this.labels.headerFooter}` : '';
        if (key === 'template') {
            return `${this.labels.contentWidthTemplate} · ${this.templateDefaultWidth()}${chromeSuffix}`;
        }

        const preset = contentWidthPresets[key];
        const label = optionLabel(preset, this.labels);
        return preset.css === '100%' ? `${label}${chromeSuffix}` : `${label} · ${preset.css}${chromeSuffix}`;
    }

    contentWidthIncludesChrome() {
        const value = this.persistedValues['theme.contentWidthIncludesChrome'];
        return value === true || value === 'true' || value === 1 || value === '1';
    }

    contentWidthIncludesChromeBackground() {
        const value = this.persistedValues['theme.contentWidthIncludesChromeBackground'];
        return value === true || value === 'true' || value === 1 || value === '1';
    }

    syncPageTheme() {
        this.root.style.setProperty('--page-accent', this.pageAccent());
        this.root.style.setProperty('--admin-page-accent', this.pageAccent());
        this.root.style.setProperty('--page-template-default-width', this.templateDefaultWidth());
        this.root.dataset.contentWidthIncludesChrome = this.contentWidthIncludesChrome() ? 'true' : 'false';
        this.root.dataset.contentWidthIncludesChromeBackground = this.contentWidthIncludesChromeBackground() ? 'true' : 'false';
        const width = this.contentWidthCssValue();
        if (width !== '') {
            this.root.style.setProperty('--page-content-max-width', width);
        } else {
            this.root.style.removeProperty('--page-content-max-width');
        }
    }

    selectedTemplate() {
        return this.templates.find((template) => template.id === this.templateId) || this.templates[0] || null;
    }

    isCustomTemplate(template = this.selectedTemplate()) {
        return (template?.kind || 'static') === 'custom';
    }

    storedTemplateId(template = this.selectedTemplate()) {
        if (this.isCustomTemplate(template)) {
            return template?.baseTemplateId || 'custom';
        }

        return template?.id || this.templateId;
    }

    syncCustomTemplateBlocks() {
        const template = this.selectedTemplate();
        if (this.isCustomTemplate(template)) {
            template.blocks = this.customLayout;
        }

        const baseTemplate = this.templates.find((item) => item.id === 'custom');
        if (baseTemplate && template?.id === 'custom') {
            baseTemplate.blocks = this.customLayout;
        }
    }

    applyTemplateCustomLayout(template = this.selectedTemplate()) {
        if (!this.isCustomTemplate(template)) {
            return;
        }

        this.customLayout = this.normalizeCustomLayout(template?.blocks || this.customLayout);
        this.syncCustomTemplateBlocks();
    }

    normalizeCustomLayout(layout) {
        const seen = new Set();
        return (Array.isArray(layout) ? layout : []).reduce((slots, slot) => {
            if (!slot || typeof slot !== 'object' || Array.isArray(slot)) {
                return slots;
            }

            const id = String(slot.id || '').trim();
            const label = String(slot.label || '').trim();
            const region = optionValue(customLayoutRegions, slot.region, 'main');
            const size = optionValue(customLayoutSizes, slot.size, 'medium');
            const position = optionValue(customLayoutPositions, slot.position, 'stretch');
            const maxHeight = optionValue(customLayoutMaxHeights, slot.maxHeight, 'none');
            const overflow = optionValue(customLayoutOverflows, slot.overflow, 'visible');
            const surface = optionValue(customLayoutSurfaces, slot.surface, 'none');
            if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(id) || label === '' || seen.has(id)) {
                return slots;
            }

            seen.add(id);
            const definition = {id, label, region, size, position, maxHeight, overflow, surface};
            const accepts = Array.isArray(slot.accepts)
                ? [...new Set(slot.accepts.map((type) => String(type || '').trim()).filter((type) => /^[a-z0-9][a-z0-9_-]{0,63}$/.test(type)))]
                : [];
            if (accepts.length > 0) {
                definition.accepts = accepts;
            }
            const defaultContentType = String(slot.defaultContentType || '').trim();
            if (/^[a-z0-9][a-z0-9_-]{0,63}$/.test(defaultContentType)) {
                definition.defaultContentType = defaultContentType;
            }

            slots.push(definition);
            return slots;
        }, []);
    }

    selectedBlock() {
        const template = this.selectedTemplate();
        return (template?.blocks || []).find((block) => block.id === this.selectedBlockId) || null;
    }

    selectedContent() {
        if (!this.selectedBlockId || !this.blockContent[this.selectedBlockId]) {
            return null;
        }

        return this.contentAtPath(this.selectedNestedPath || '') || this.blockContent[this.selectedBlockId];
    }

    pathIndexes(path) {
        return String(path || '')
            .split('.')
            .filter((part) => part !== '')
            .map((part) => Number(part))
            .filter((index) => Number.isInteger(index) && index >= 0);
    }

    pathForChild(parentPath, index) {
        const parent = String(parentPath || '');
        return parent === '' ? String(index) : `${parent}.${index}`;
    }

    parentPath(path) {
        const parts = String(path || '').split('.').filter((part) => part !== '');
        parts.pop();
        return parts.join('.');
    }

    contentAtPath(path) {
        let content = this.selectedBlockId ? this.blockContent[this.selectedBlockId] : null;
        if (!content) {
            return null;
        }

        for (const index of this.pathIndexes(path)) {
            const children = Array.isArray(content?.props?.children) ? content.props.children : [];
            content = children[index] || null;
            if (!content) {
                return null;
            }
        }

        return content;
    }

    childrenForContent(content) {
        if (!content || typeof content !== 'object') {
            return [];
        }
        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        content.props.children = Array.isArray(content.props.children) ? content.props.children : [];

        return content.props.children;
    }

    childrenAtPath(path = '') {
        return this.childrenForContent(this.contentAtPath(path));
    }

    isLayoutContent(content) {
        return layoutBlockTypes.includes(String(content?.type || ''));
    }

    layoutChildContainerStyle(content) {
        return optionValue(layoutChildContainerStyles, content?.props?.containerStyle, 'none');
    }

    layoutChildContainerClass(content, preview = false) {
        const style = this.layoutChildContainerStyle(content);
        return `page-layout-child page-layout-child--${style}${preview ? ' page-builder-layout-child-preview' : ''}`;
    }

    blockRegion(block = this.selectedBlock()) {
        return block?.region || block?.id || 'main';
    }

    contentTypeSupportsBlock(type, block = this.selectedBlock(), template = this.selectedTemplate()) {
        const regions = Array.isArray(type?.regions) ? type.regions : [];
        const acceptedTypes = Array.isArray(block?.accepts) ? block.accepts : [];
        const templateKinds = Array.isArray(type?.templateKinds) ? type.templateKinds : [];
        const contentTypes = Array.isArray(type?.contentTypes) ? type.contentTypes : [];

        if (regions.length > 0 && !regions.includes(this.blockRegion(block))) {
            return false;
        }

        if (acceptedTypes.length > 0 && !acceptedTypes.includes(type?.type)) {
            return false;
        }

        if (templateKinds.length > 0 && !templateKinds.includes(template?.kind || 'static')) {
            return false;
        }

        if (contentTypes.length > 0 && !contentTypes.includes(template?.contentType || '')) {
            return false;
        }

        return true;
    }

    compatibleContentTypes(block = this.selectedBlock()) {
        return this.contentTypes.filter((type) => this.contentTypeSupportsBlock(type, block));
    }

    contentTypeDefinition(type) {
        return this.contentTypes.find((item) => item.type === type) || null;
    }

    navigationChoice(identifier, language = '') {
        const normalizedIdentifier = String(identifier || '').trim();
        const normalizedLanguage = String(language || '').trim();
        return this.navigationChoices.find((navigation) => (
            navigation.identifier === normalizedIdentifier
            && String(navigation.language || '') === normalizedLanguage
        )) || this.navigationChoices.find((navigation) => navigation.identifier === normalizedIdentifier) || this.navigationChoices[0] || null;
    }

    navigationItems(identifier, language = '') {
        const navigation = this.navigationChoice(identifier, language);
        return Array.isArray(navigation?.items) ? navigation.items : [];
    }

    selectedDefaultNavigationIdentifier() {
        return String(this.persistedValues.defaultNavigationIdentifier || this.defaultNavigationIdentifier || this.navigationChoices[0]?.identifier || 'main');
    }

    defaultContentForBlock(block) {
        const acceptedTypes = Array.isArray(block?.accepts) ? block.accepts : [];
        const type = block.defaultContentType || acceptedTypes[0] || (block.id === 'site_header'
            ? 'header'
            : block.id === 'site_footer'
                ? 'footer'
                : null);

        if (!type) {
            return null;
        }

        const definition = this.contentTypeDefinition(type);
        if (!definition) {
            return null;
        }

        return this.contentFromDefinition(definition);
    }

    contentFromDefinition(definition) {
        const props = clone(definition.defaults || {});
        const navigationIdentifier = this.selectedDefaultNavigationIdentifier();

        if ('navigationIdentifier' in props) {
            props.navigationIdentifier = navigationIdentifier;
        }
        if ('identifier' in props) {
            props.identifier = navigationIdentifier;
        }

        return {
            type: definition.type,
            label: definition.label,
            previewTitle: definition.previewTitle,
            props,
        };
    }

    defaultRegionForContentType(definition) {
        const regions = Array.isArray(definition?.regions) ? definition.regions : [];
        return regions.find((region) => hasOption(customLayoutRegions, region)) || 'main';
    }

    defaultSizeForCustomSlot(definition, region) {
        if (definition?.type === 'hero') {
            return 'hero';
        }
        if (region === 'aside') {
            return 'aside';
        }
        if (region === 'header' || region === 'footer') {
            return 'full';
        }

        return 'medium';
    }

    uniqueCustomSlotId(label, fallbackType = 'block') {
        const base = String(label || fallbackType || 'block')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 48) || `slot_${Date.now()}`;
        let id = base.startsWith('slot_') ? base : `slot_${base}`;
        const existing = new Set(this.customLayout.map((slot) => slot.id));
        let index = 2;
        while (existing.has(id)) {
            id = `${base.startsWith('slot_') ? base : `slot_${base}`}_${index}`;
            index += 1;
        }

        return id;
    }

    addCustomSlot(type) {
        const definition = this.contentTypeDefinition(type);
        if (!definition) {
            return;
        }

        const id = this.uniqueCustomSlotId(definition.label, definition.type);
        if (this.customLayout.some((slot) => slot.id === id)) {
            toast(this.labels.duplicateSlot);
            return;
        }

        const region = this.defaultRegionForContentType(definition);
        this.customLayout.push({
            id,
            label: definition.label || 'Content block',
            region,
            size: this.defaultSizeForCustomSlot(definition, region),
            position: 'stretch',
            maxHeight: 'none',
            overflow: 'visible',
            surface: 'none',
            accepts: [definition.type],
            defaultContentType: definition.type,
        });
        this.syncCustomTemplateBlocks();
        this.selectedBlockId = id;
        this.blockContent[id] = this.contentFromDefinition(definition);
        this.openSettings();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
    }

    addChromeSlot(type) {
        const definition = this.contentTypeDefinition(type);
        if (!definition) {
            return;
        }

        const region = type === 'footer' ? 'footer' : 'header';
        const id = this.uniqueCustomSlotId(definition.label, type);
        this.customLayout.push({
            id,
            label: definition.label || type,
            region,
            size: 'full',
            position: 'stretch',
            maxHeight: 'none',
            overflow: 'visible',
            surface: 'none',
            accepts: [type],
            defaultContentType: type,
        });
        this.syncCustomTemplateBlocks();
        this.selectedBlockId = id;
        this.blockContent[id] = this.contentFromDefinition(definition);
        this.openSettings();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
    }

    updateCustomSlot(id, patch, {renderBlocks = true} = {}) {
        const slot = this.customLayout.find((item) => item.id === id);
        if (!slot) {
            return;
        }

        if (typeof patch.label === 'string') {
            slot.label = patch.label.trim() || slot.label;
        }
        if (hasOption(customLayoutRegions, patch.region)) {
            slot.region = patch.region;
        }
        if (hasOption(customLayoutSizes, patch.size)) {
            slot.size = patch.size;
        }
        if (hasOption(customLayoutPositions, patch.position)) {
            slot.position = patch.position;
        }
        if (hasOption(customLayoutMaxHeights, patch.maxHeight)) {
            slot.maxHeight = String(patch.maxHeight);
        }
        if (hasOption(customLayoutOverflows, patch.overflow)) {
            slot.overflow = patch.overflow;
        }
        if (hasOption(customLayoutSurfaces, patch.surface)) {
            slot.surface = patch.surface;
        }

        this.customLayout = this.normalizeCustomLayout(this.customLayout);
        this.syncCustomTemplateBlocks();
        this.previewTemplateId = null;
        if (renderBlocks) {
            this.renderBlocks();
            this.renderTemplatePreview();
            this.renderPagePreview();
            this.renderSettings();
        }
        this.renderJson();
        this.renderMeta();
        this.persistState();
    }

    deleteCustomSlot(id) {
        this.customLayout = this.customLayout.filter((slot) => slot.id !== id);
        delete this.blockContent[id];
        if (this.selectedBlockId === id) {
            this.selectedBlockId = this.customLayout[0]?.id || null;
            this.settingsOpen = false;
        }
        this.syncCustomTemplateBlocks();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
    }

    addLayoutChild(parentPath, type) {
        const parent = this.contentAtPath(parentPath);
        const definition = this.contentTypeDefinition(type);
        if (!parent || !definition || !this.isLayoutContent(parent)) {
            return;
        }

        const children = this.childrenForContent(parent);
        children.push(this.contentFromDefinition(definition));
        this.selectedNestedPath = this.pathForChild(parentPath, children.length - 1);
        this.openSettings();
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    deleteLayoutChild(path) {
        const parts = this.pathIndexes(path);
        if (parts.length === 0) {
            return;
        }

        const index = parts.pop();
        const parent = this.contentAtPath(parts.join('.'));
        const children = this.childrenForContent(parent);
        if (index < 0 || index >= children.length) {
            return;
        }

        children.splice(index, 1);
        this.selectedNestedPath = parts.join('.');
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    moveLayoutChild(path, direction) {
        const parts = this.pathIndexes(path);
        if (parts.length === 0 || ![-1, 1].includes(direction)) {
            return;
        }

        const index = parts.pop();
        const parentPath = parts.join('.');
        const children = this.childrenAtPath(parentPath);
        const targetIndex = index + direction;
        if (index < 0 || targetIndex < 0 || index >= children.length || targetIndex >= children.length) {
            return;
        }

        const [child] = children.splice(index, 1);
        children.splice(targetIndex, 0, child);
        this.selectedNestedPath = this.pathForChild(parentPath, targetIndex);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    replaceSelectedLayoutType(type) {
        const current = this.selectedContent();
        const definition = this.contentTypeDefinition(type);
        if (!this.isLayoutContent(current) || !definition || !layoutBlockTypes.includes(definition.type) || definition.type === current.type) {
            return;
        }

        const previousProps = current.props && typeof current.props === 'object' && !Array.isArray(current.props) ? current.props : {};
        const children = this.childrenForContent(current);
        const replacement = this.contentFromDefinition(definition);
        replacement.props.children = children;

        for (const key of ['gap', 'align']) {
            if (key in replacement.props && key in previousProps) {
                replacement.props[key] = previousProps[key];
            }
        }

        if ('columns' in replacement.props) {
            replacement.props.columns = Math.max(2, Math.min(4, children.length || Number(replacement.props.columns || 2)));
        }

        if (this.selectedNestedPath === '') {
            this.blockContent[this.selectedBlockId] = replacement;
            const slot = this.customLayout.find((item) => item.id === this.selectedBlockId);
            if (slot) {
                slot.accepts = [definition.type];
                slot.defaultContentType = definition.type;
            }
        } else {
            const parts = this.pathIndexes(this.selectedNestedPath);
            const index = parts.pop();
            const siblings = this.childrenAtPath(parts.join('.'));
            if (index < 0 || index >= siblings.length) {
                return;
            }
            siblings[index] = replacement;
        }

        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    reorderCustomSlot(sourceId, targetId) {
        if (!sourceId || !targetId || sourceId === targetId) {
            return;
        }

        const sourceIndex = this.customLayout.findIndex((slot) => slot.id === sourceId);
        const targetIndex = this.customLayout.findIndex((slot) => slot.id === targetId);
        if (sourceIndex < 0 || targetIndex < 0) {
            return;
        }

        const [slot] = this.customLayout.splice(sourceIndex, 1);
        this.customLayout.splice(targetIndex, 0, slot);
        this.syncCustomTemplateBlocks();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
    }

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
    }

    updateDefaultNavigation(identifier) {
        const previous = this.selectedDefaultNavigationIdentifier();
        this.persistedValues.defaultNavigationIdentifier = identifier || this.defaultNavigationIdentifier;

        for (const content of Object.values(this.blockContent)) {
            if (!content || typeof content !== 'object') {
                continue;
            }

            if (content.props?.navigationIdentifier === previous || ['header', 'footer', 'aside'].includes(content.type)) {
                content.props.navigationIdentifier = this.selectedDefaultNavigationIdentifier();
            }
            if (content.props?.identifier === previous || content.type === 'navigation') {
                content.props.identifier = this.selectedDefaultNavigationIdentifier();
            }
        }

        this.previewTemplateId = null;
    }

    handleClick(event) {
        const translationField = event.target.closest('[data-translate-prop]');
        if (translationField) {
            event.preventDefault();
            this.openTranslationPicker(translationField.dataset.translateProp || '');
            return;
        }

        const mediaField = event.target.closest('[data-media-field]');
        if (mediaField) {
            event.preventDefault();
            this.openMediaPicker(mediaField.dataset.mediaField || '', mediaField.dataset.mediaAltTarget || '');
            return;
        }

        const mediaClear = event.target.closest('[data-media-clear]');
        if (mediaClear) {
            event.preventDefault();
            const field = mediaClear.dataset.mediaClear || '';
            if (field !== '') {
                this.setBlockProp(field, '');
            }
            return;
        }

        const previewMode = event.target.closest('[data-preview-mode]');
        if (previewMode) {
            const mode = previewMode.dataset.previewMode || 'desktop';
            if (previewViewports[mode]) {
                this.previewViewport = mode;
                this.persistState();
                this.render();
            }
            return;
        }

        if (event.target.closest('[data-add-custom-slot]')) {
            event.preventDefault();
            this.openCustomSlotModal();
            return;
        }

        const addChromeSlot = event.target.closest('[data-add-chrome-slot]');
        if (addChromeSlot) {
            event.preventDefault();
            this.addChromeSlot(addChromeSlot.dataset.addChromeSlot || 'header');
            return;
        }

        const deleteCustomSlot = event.target.closest('[data-delete-custom-slot]');
        if (deleteCustomSlot) {
            event.preventDefault();
            this.deleteCustomSlot(deleteCustomSlot.dataset.deleteCustomSlot || '');
            return;
        }

        if (event.target.closest('[data-add-layout-child]')) {
            event.preventDefault();
            this.openLayoutChildModal(this.selectedNestedPath || '');
            return;
        }

        if (event.target.closest('[data-chart-metric-add]')) {
            event.preventDefault();
            this.addChartMetric();
            return;
        }

        const removeChartMetric = event.target.closest('[data-chart-metric-remove]');
        if (removeChartMetric) {
            event.preventDefault();
            this.removeChartMetric(Number(removeChartMetric.dataset.chartMetricRemove || -1));
            return;
        }

        const editLayoutChild = event.target.closest('[data-edit-layout-child]');
        if (editLayoutChild) {
            event.preventDefault();
            this.selectedNestedPath = editLayoutChild.dataset.editLayoutChild || '';
            this.openSettings();
            this.persistState();
            this.renderSettings();
            return;
        }

        const deleteLayoutChild = event.target.closest('[data-delete-layout-child]');
        if (deleteLayoutChild) {
            event.preventDefault();
            this.deleteLayoutChild(deleteLayoutChild.dataset.deleteLayoutChild || '');
            return;
        }

        const moveLayoutChild = event.target.closest('[data-move-layout-child]');
        if (moveLayoutChild) {
            event.preventDefault();
            this.moveLayoutChild(moveLayoutChild.dataset.moveLayoutChild || '', Number(moveLayoutChild.dataset.direction || 0));
            return;
        }

        const backToLayoutBlock = event.target.closest('[data-back-to-layout-block]');
        if (backToLayoutBlock) {
            event.preventDefault();
            this.selectedNestedPath = this.parentPath(this.selectedNestedPath);
            this.persistState();
            this.renderSettings();
            return;
        }

        const templateCard = event.target.closest('[data-template-id]');
        if (templateCard) {
            this.templateId = templateCard.dataset.templateId || this.templateId;
            this.applyTemplateCustomLayout();
            this.selectedBlockId = null;
            this.selectedNestedPath = '';
            this.blockContent = {};
            this.settingsOpen = false;
            this.ensureTemplateDefaults();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-save-custom-layout]')) {
            event.preventDefault();
            this.openSaveCustomLayoutPrompt();
            return;
        }

        if (event.target.closest('[data-next-step]')) {
            this.step = 'layout';
            this.selectedBlockId = this.selectedTemplate()?.blocks?.[0]?.id || null;
            this.selectedNestedPath = '';
            this.ensureTemplateDefaults();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-next-meta]')) {
            this.step = 'meta';
            this.settingsOpen = false;
            this.collectPersistedFields();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-back-layout]')) {
            this.step = 'layout';
            this.selectedBlockId = this.selectedBlockId || this.selectedTemplate()?.blocks?.[0]?.id || null;
            this.selectedNestedPath = '';
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-back-step]')) {
            this.step = 'template';
            this.settingsOpen = false;
            this.persistState();
            this.render();
            return;
        }

        const clearBlock = event.target.closest('[data-clear-block]');
        if (clearBlock) {
            delete this.blockContent[clearBlock.dataset.clearBlock || ''];
            if (clearBlock.dataset.clearBlock === this.selectedBlockId) {
                this.selectedNestedPath = '';
                this.settingsOpen = false;
            }
            this.persistState();
            this.render();
            return;
        }

        const editBlock = event.target.closest('[data-edit-block]');
        if (editBlock) {
            this.selectedBlockId = editBlock.dataset.editBlock || null;
            this.selectedNestedPath = '';
            this.openSettings();
            this.persistState();
            this.render();
            return;
        }

        const chooseBlock = event.target.closest('[data-choose-block]');
        if (chooseBlock) {
            this.selectedBlockId = chooseBlock.dataset.chooseBlock || null;
            this.selectedNestedPath = '';
            this.persistState();
            this.render();
            this.openContentModal();
            return;
        }

        const contentChoice = event.target.closest('[data-content-type]');
        if (contentChoice) {
            const type = contentChoice.dataset.contentType || '';
            this.assignContent(type);
            this.selectedNestedPath = '';
            this.openSettings();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-close-settings]')) {
            this.closeSettings();
            this.persistState();
        }

        if (event.target.closest('[data-save-page]')) {
            this.collectPersistedFields();
            this.remotePersist({force: true});
        }
    }

    handleInput(event) {
        this.handlePersistedInput(event);

        const defaultNavigation = event.target.closest('[data-default-navigation]');
        if (defaultNavigation) {
            this.updateDefaultNavigation(defaultNavigation.value || this.defaultNavigationIdentifier);
            this.renderBlocks();
            this.renderTemplatePreview();
            this.renderPagePreview();
            this.renderJson();
            this.persistState();
            return;
        }

        const customSlotLabel = event.target.closest('[data-custom-slot-label]');
        if (customSlotLabel) {
            this.updateCustomSlot(
                customSlotLabel.dataset.customSlotLabel || '',
                {label: customSlotLabel.value || ''},
                {renderBlocks: event.type === 'change'}
            );
            return;
        }

        const customSlotRegion = event.target.closest('[data-custom-slot-region]');
        if (customSlotRegion) {
            this.updateCustomSlot(customSlotRegion.dataset.customSlotRegion || '', {region: customSlotRegion.value || 'main'});
            return;
        }

        const customSlotSize = event.target.closest('[data-custom-slot-size]');
        if (customSlotSize) {
            this.updateCustomSlot(customSlotSize.dataset.customSlotSize || '', {size: customSlotSize.value || 'medium'});
            return;
        }

        const customSlotPosition = event.target.closest('[data-custom-slot-position]');
        if (customSlotPosition) {
            this.updateCustomSlot(customSlotPosition.dataset.customSlotPosition || '', {position: customSlotPosition.value || 'stretch'});
            return;
        }

        const customSlotMaxHeight = event.target.closest('[data-custom-slot-max-height]');
        if (customSlotMaxHeight) {
            const id = customSlotMaxHeight.dataset.customSlotMaxHeight || '';
            const slot = this.customLayout.find((item) => item.id === id);
            const maxHeight = customSlotMaxHeight.value || 'none';
            this.updateCustomSlot(id, {
                maxHeight,
                ...(maxHeight !== 'none' && (!slot?.overflow || slot.overflow === 'visible') ? {overflow: 'auto'} : {}),
            });
            return;
        }

        const customSlotOverflow = event.target.closest('[data-custom-slot-overflow]');
        if (customSlotOverflow) {
            this.updateCustomSlot(customSlotOverflow.dataset.customSlotOverflow || '', {overflow: customSlotOverflow.value || 'visible'});
            return;
        }

        const customSlotSurface = event.target.closest('[data-custom-slot-surface]');
        if (customSlotSurface) {
            this.updateCustomSlot(customSlotSurface.dataset.customSlotSurface || '', {surface: customSlotSurface.value || 'none'});
            return;
        }

        const layoutChildContainerStyle = event.target.closest('[data-layout-child-container-style]');
        if (layoutChildContainerStyle) {
            const child = this.contentAtPath(layoutChildContainerStyle.dataset.layoutChildContainerStyle || '');
            if (child) {
                child.props = child.props && typeof child.props === 'object' && !Array.isArray(child.props) ? child.props : {};
                child.props.containerStyle = optionValue(layoutChildContainerStyles, layoutChildContainerStyle.value, 'none');
                this.renderBlocks();
                this.renderPagePreview();
                this.renderJson();
                this.renderSettings();
                this.persistState();
            }
            return;
        }

        const layoutTypeSwitch = event.target.closest('[data-layout-type-switch]');
        if (layoutTypeSwitch) {
            this.replaceSelectedLayoutType(layoutTypeSwitch.value || '');
            return;
        }

        const chartMetricField = event.target.closest('[data-chart-metric-field]');
        if (chartMetricField) {
            this.updateChartMetric(
                Number(chartMetricField.dataset.chartMetricIndex || -1),
                chartMetricField.dataset.chartMetricField || '',
                chartMetricField.type === 'number' ? Number(chartMetricField.value || 0) : chartMetricField.value,
            );
            return;
        }

        const input = event.target.closest('[data-prop-name]');
        if (!input || !this.selectedContent()) {
            return;
        }

        const name = input.dataset.propName || '';
        this.setBlockProp(name, input.type === 'checkbox'
            ? input.checked
            : input.type === 'number'
                ? Number(input.value || 0)
                : input.value);
    }

    setBlockProp(name, value, {renderSettings = false} = {}) {
        const content = this.selectedContent();
        if (!name || !content) {
            return;
        }

        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        content.props[name] = value;
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.persistState();
        if (renderSettings) {
            this.renderSettings();
        }
    }

    chartMetrics(content = this.selectedContent()) {
        const props = content?.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        const fromArray = Array.isArray(props.metrics)
            ? props.metrics
                .filter((metric) => metric && typeof metric === 'object' && !Array.isArray(metric))
                .map((metric, index) => ({
                    label: String(metric.label ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
                    value: Number(metric.value ?? 0),
                    color: this.normalizeColor(metric.color) || chartMetricColors[index % chartMetricColors.length],
                }))
            : [];

        if (fromArray.length > 0) {
            return fromArray.slice(0, chartMaxMetrics);
        }

        const highestLegacyMetric = Array.from({length: 6}, (_, index) => index + 1)
            .reduce((highest, index) => (
                props[`label${index}`] !== undefined || props[`value${index}`] !== undefined || props[`color${index}`] !== undefined
                    ? index
                    : highest
            ), 0);
        const count = Math.max(1, Math.min(chartMaxMetrics, Math.max(Number(props.metricCount || 3), highestLegacyMetric)));
        return Array.from({length: count}, (_, index) => ({
            label: String(props[`label${index + 1}`] ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
            value: Number(props[`value${index + 1}`] ?? 0),
            color: this.normalizeColor(props[`color${index + 1}`]) || chartMetricColors[index % chartMetricColors.length],
        }));
    }

    syncChartMetricProps(content, metrics) {
        if (!content) {
            return;
        }

        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        const normalized = metrics.slice(0, chartMaxMetrics).map((metric, index) => ({
            label: String(metric?.label ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
            value: Number(metric?.value ?? 0),
            color: this.normalizeColor(metric?.color) || chartMetricColors[index % chartMetricColors.length],
        }));

        content.props.metrics = normalized;
        content.props.metricCount = normalized.length;
        for (let index = 0; index < Math.min(6, normalized.length); index++) {
            const metric = normalized[index];
            content.props[`label${index + 1}`] = metric.label;
            content.props[`value${index + 1}`] = metric.value;
            content.props[`color${index + 1}`] = metric.color;
        }
    }

    addChartMetric() {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart') {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (metrics.length >= chartMaxMetrics) {
            return;
        }

        metrics.push({
            label: this.labels.metricFallback.replace(':number', String(metrics.length + 1)),
            value: 0,
            color: chartMetricColors[metrics.length % chartMetricColors.length],
        });
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    removeChartMetric(index) {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart') {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (metrics.length <= 1 || index < 0 || index >= metrics.length) {
            return;
        }

        metrics.splice(index, 1);
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    }

    updateChartMetric(index, field, value) {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart' || index < 0 || !['label', 'value', 'color'].includes(field)) {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (!metrics[index]) {
            return;
        }

        metrics[index][field] = field === 'value' ? Number(value || 0) : value;
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.persistState();
    }

    handlePersistedInput(event) {
        const field = event.target.closest('[data-persist-key]');
        if (!field) {
            return;
        }

        const key = field.dataset.persistKey || '';
        if (key === '') {
            return;
        }

        this.persistedValues[key] = this.readPersistedField(field);
        this.syncPersistedControls(key, field);
        if (key.startsWith('theme.')) {
            this.syncPageTheme();
        }
        if (key === 'theme.contentWidth' || key === 'theme.contentWidthIncludesChrome' || key === 'theme.contentWidthIncludesChromeBackground') {
            this.renderPreviewChrome();
        }
        this.renderMeta();
        this.persistState();
    }

    renderLayoutTypeSwitcher(content) {
        if (!this.isLayoutContent(content)) {
            return '';
        }

        const options = layoutBlockTypes
            .map((type) => this.contentTypeDefinition(type))
            .filter(Boolean)
            .map((definition) => `<option value="${escapeHtml(definition.type)}" ${definition.type === content.type ? 'selected' : ''}>${escapeHtml(definition.label || definition.type)}</option>`)
            .join('');

        return `
            <label class="grid gap-1.5 rounded-lg border border-slate-700 bg-[#0b1017] p-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(this.labels.layoutType)}</span>
                <select class="${inputClass}" data-styled-select data-layout-type-switch>
                    ${options}
                </select>
            </label>
        `;
    }

    syncPersistedControls(key, source = null) {
        this.root.querySelectorAll(`[data-persist-key="${CSS.escape(key)}"]`).forEach((field) => {
            if (field === source) {
                return;
            }
            this.writePersistedField(field, this.persistedValues[key]);
        });
    }

    assignContent(type) {
        const definition = this.contentTypes.find((item) => item.type === type);
        const block = this.selectedBlock();
        if (!definition || !block || !this.selectedBlockId || !this.contentTypeSupportsBlock(definition, block)) {
            return;
        }

        this.blockContent[this.selectedBlockId] = this.contentFromDefinition(definition);
    }

    openContentModal() {
        const block = this.selectedBlock();
        const promptPromise = prompt(`
            <div class="page-builder-content-prompt">
                <div>
                    <h2 class="m-0 text-base font-semibold text-[var(--notify-text)]">Choose block</h2>
                    <p class="mt-1 text-sm text-[var(--notify-muted)]">Pick the content block rendered at this template position.</p>
                </div>
                <div class="mt-4 overflow-auto pr-1" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;max-height:min(620px,calc(100vh - 260px));" data-content-options>
                    ${this.renderContentOptionsHtml(block)}
                </div>
            </div>
        `, {
            confirmButtonText: this.labels.cancel,
            showCancelButton: false,
            style: {width: 'min(920px, calc(100vw - 32px))', maxWidth: 'min(920px, calc(100vw - 32px))'},
        });

        window.setTimeout(() => this.bindContentPrompt(document.querySelector('.page-builder-content-prompt')), 0);

        return promptPromise;
    }

    bindContentPrompt(container) {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        container.querySelectorAll('[data-content-type]').forEach((button) => {
            button.addEventListener('click', () => {
                this.assignContent(button.getAttribute('data-content-type') || '');
                this.openSettings();
                this.persistState();
                this.render();
                container.closest('.notify')?.querySelector('.close')?.click();
            });
        });
    }

    async openSaveCustomLayoutPrompt() {
        if (!this.isCustomTemplate() || this.customLayout.length === 0) {
            return;
        }

        const template = this.selectedTemplate();
        const defaultName = String(template?.layoutPresetId ? template.label : `${this.persistedValues.title || 'Custom'} layout`).trim();
        const promptResult = prompt(`
            <div>${escapeHtml(this.labels.saveLayoutTitle)}</div>
            <div class="notify-form">
                <p class="notify-form-intro">${escapeHtml(this.labels.saveLayoutIntro)}</p>
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.labels.layoutName)}</span>
                    <input type="text" value="${escapeHtml(defaultName)}" data-page-layout-name>
                </label>
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.labels.layoutDescription)}</span>
                    <textarea rows="3" data-page-layout-description>${escapeHtml(String(template?.description || ''))}</textarea>
                </label>
            </div>
        `, {
            confirmButtonText: this.labels.saveCustomLayout,
            cancelButtonText: this.labels.cancel,
            showCancelButton: true,
            confirmCallback: async (promptInstance) => {
                const nameInput = promptInstance.container.querySelector('[data-page-layout-name]');
                const descriptionInput = promptInstance.container.querySelector('[data-page-layout-description]');
                const name = String(nameInput?.value || '').trim();
                if (name === '') {
                    toast(this.labels.layoutNameRequired);
                    nameInput?.focus();
                    return true;
                }

                const saved = await this.saveCustomLayoutPreset(name, String(descriptionInput?.value || '').trim());
                if (!saved) {
                    return true;
                }

                promptInstance.promise.resolve(true);
                promptInstance.remove();
                return true;
            },
        });

        await promptResult;
    }

    async saveCustomLayoutPreset(name, description = '') {
        if (!this.layoutSaveUrl) {
            return false;
        }

        try {
            const request = window.NixCMS?.json || fetch;
            const response = await request(this.layoutSaveUrl, {
                method: 'POST',
                body: JSON.stringify({
                    name,
                    description,
                    slots: this.normalizeCustomLayout(this.customLayout),
                }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success === false || !result.template) {
                throw new Error(result.reason || this.labels.couldNotSave);
            }

            this.upsertTemplate(result.template);
            this.templateId = result.template.id;
            this.applyTemplateCustomLayout();
            this.templatesRendered = false;
            this.previewTemplateId = null;
            toast(this.labels.layoutSaved);
            this.persistState();
            this.render();
            return true;
        } catch (error) {
            notifyAlert(escapeHtml(error.message || this.labels.couldNotSave));
            return false;
        }
    }

    upsertTemplate(template) {
        if (!template || typeof template !== 'object' || !template.id) {
            return;
        }

        const index = this.templates.findIndex((item) => item.id === template.id);
        if (index >= 0) {
            this.templates[index] = template;
            return;
        }

        this.templates.push(template);
    }

    openCustomSlotModal() {
        const promptPromise = prompt(`
            <div class="page-builder-content-prompt">
                <div>
                    <h2 class="m-0 text-base font-semibold text-[var(--notify-text)]">${escapeHtml(this.labels.chooseCustomBlock)}</h2>
                    <p class="mt-1 text-sm text-[var(--notify-muted)]">${escapeHtml(this.labels.chooseCustomBlockIntro)}</p>
                </div>
                <div class="mt-4 overflow-auto pr-1" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;max-height:min(620px,calc(100vh - 260px));" data-content-options>
                    ${this.renderCustomSlotContentOptionsHtml()}
                </div>
            </div>
        `, {
            confirmButtonText: this.labels.cancel,
            showCancelButton: false,
            style: {width: 'min(920px, calc(100vw - 32px))', maxWidth: 'min(920px, calc(100vw - 32px))'},
        });

        window.setTimeout(() => this.bindCustomSlotPrompt(document.querySelector('.page-builder-content-prompt')), 0);

        return promptPromise;
    }

    bindCustomSlotPrompt(container) {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        container.querySelectorAll('[data-custom-slot-content-type]').forEach((button) => {
            button.addEventListener('click', () => {
                this.addCustomSlot(button.getAttribute('data-custom-slot-content-type') || '');
                container.closest('.notify')?.querySelector('.close')?.click();
            });
        });
    }

    openLayoutChildModal(parentPath = '') {
        const promptPromise = prompt(`
            <div class="page-builder-content-prompt">
                <div>
                    <h2 class="m-0 text-base font-semibold text-[var(--notify-text)]">${escapeHtml(this.labels.chooseChildBlock)}</h2>
                    <p class="mt-1 text-sm text-[var(--notify-muted)]">${escapeHtml(this.labels.chooseChildBlockIntro)}</p>
                </div>
                <div class="mt-4 overflow-auto pr-1" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;max-height:min(620px,calc(100vh - 260px));" data-content-options>
                    ${this.renderLayoutChildContentOptionsHtml(parentPath)}
                </div>
            </div>
        `, {
            confirmButtonText: this.labels.cancel,
            showCancelButton: false,
            style: {width: 'min(920px, calc(100vw - 32px))', maxWidth: 'min(920px, calc(100vw - 32px))'},
        });

        window.setTimeout(() => this.bindLayoutChildPrompt(document.querySelector('.page-builder-content-prompt'), parentPath), 0);

        return promptPromise;
    }

    bindLayoutChildPrompt(container, parentPath = '') {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        container.querySelectorAll('[data-layout-child-content-type]').forEach((button) => {
            button.addEventListener('click', () => {
                this.addLayoutChild(parentPath, button.getAttribute('data-layout-child-content-type') || '');
                container.closest('.notify')?.querySelector('.close')?.click();
            });
        });
    }

    handleDragStart(event) {
        const handle = event.target.closest('[data-custom-slot-drag]');
        if (!handle) {
            return;
        }

        const id = handle.dataset.customSlotDrag || '';
        if (id === '') {
            return;
        }

        this.draggedCustomSlotId = id;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', id);
    }

    handleDragOver(event) {
        const dropTarget = event.target.closest('[data-custom-slot-drop]');
        if (!dropTarget || !this.draggedCustomSlotId || dropTarget.dataset.customSlotDrop === this.draggedCustomSlotId) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTarget.classList.add('ring-2', 'ring-[var(--admin-page-accent)]/50');
    }

    handleDrop(event) {
        const dropTarget = event.target.closest('[data-custom-slot-drop]');
        if (!dropTarget) {
            return;
        }

        event.preventDefault();
        const sourceId = this.draggedCustomSlotId || event.dataTransfer.getData('text/plain');
        const targetId = dropTarget.dataset.customSlotDrop || '';
        this.handleDragEnd();
        this.reorderCustomSlot(sourceId, targetId);
    }

    handleDragEnd() {
        this.draggedCustomSlotId = null;
        this.root.querySelectorAll('[data-custom-slot-drop]').forEach((slot) => {
            slot.classList.remove('ring-2', 'ring-[var(--admin-page-accent)]/50');
        });
    }

    openSettings() {
        this.settingsOpen = true;
        this.settingsDrawer?.classList.remove('w-0', 'border-transparent', 'opacity-0');
        this.settingsDrawer?.classList.add('w-[min(420px,calc(100vw-28px))]', 'border-slate-800', 'opacity-100');
    }

    closeSettings() {
        this.settingsOpen = false;
        this.settingsDrawer?.classList.add('w-0', 'border-transparent', 'opacity-0');
        this.settingsDrawer?.classList.remove('w-[min(420px,calc(100vw-28px))]', 'border-slate-800', 'opacity-100');
    }

    render() {
        this.syncPageTheme();
        this.root.querySelectorAll('[data-step]').forEach((section) => {
            section.classList.toggle('hidden', section.dataset.step !== this.step);
        });

        if (this.stepNumber) {
            this.stepNumber.textContent = this.step === 'template' ? '1' : '2';
        }
        if (this.stepTitle) {
            this.stepTitle.textContent = this.step === 'template' ? 'Choose template' : 'Connect content';
        }
        if (this.stepSubtitle) {
            this.stepSubtitle.textContent = this.step === 'template'
                ? 'Select the physical template before connecting content.'
                : 'Click a template block, choose content, then tune its props.';
        }
        this.backButton?.classList.toggle('hidden', this.step === 'template');
        this.nextButton?.classList.toggle('hidden', this.step !== 'template');

        this.renderTemplatesOnce();
        this.updateActiveTemplate();
        this.renderTemplatePreview();
        this.renderPreviewChrome();
        this.renderBlocks();
        this.renderSettings();
        this.renderPagePreview();
        this.renderMeta();
        this.renderJson();
        if (this.settingsOpen) {
            this.openSettings();
        } else {
            this.closeSettings();
        }
        this.applyPersistedFields();
        this.renderPreviewChrome();
        this.renderMeta();
    }

    renderTemplatesOnce() {
        if (this.templatesRendered) {
            return;
        }

        this.templateList.innerHTML = this.templates.map((template) => `
            <button class="${layoutClass} ${template.id === this.templateId ? activeLayoutClass : ''}" type="button" data-template-id="${escapeHtml(template.id)}">
                <span class="grid min-w-0 gap-2">
                    <span class="flex min-w-0 items-start justify-between gap-3">
                        <span class="block text-base font-semibold text-white">${escapeHtml(template.label)}</span>
                        <span class="flex shrink-0 flex-wrap justify-end gap-1">
                            ${template.kind === 'dynamic' ? `<span class="rounded-md bg-[var(--admin-page-accent)]/20 px-2 py-1 text-[11px] font-semibold text-[var(--admin-page-accent)]">${escapeHtml(template.contentType || 'dynamic')}</span>` : ''}
                            ${template.kind === 'custom' ? `<span class="rounded-md bg-emerald-500/15 px-2 py-1 text-[11px] font-semibold text-emerald-300">${escapeHtml(this.labels.customLayout)}</span>` : ''}
                            <span class="rounded-md bg-white/5 px-2 py-1 text-[11px] font-semibold text-slate-500">${escapeHtml((template.blocks || []).length)} Blocks</span>
                        </span>
                    </span>
                    <span class="block text-sm leading-relaxed text-slate-500">${escapeHtml(template.description)}</span>
                    ${template.routePattern ? `<code class="block w-max max-w-full rounded bg-slate-950 px-2 py-1 text-[11px] text-slate-500">${escapeHtml(template.routePattern)}</code>` : ''}
                    <span class="flex flex-wrap gap-1.5 pt-1">
                        ${(template.blocks || []).slice(0, 4).map((block) => `<span class="rounded bg-slate-900 px-2 py-1 text-[11px] font-medium text-slate-500">${escapeHtml(block.label)}</span>`).join('')}
                    </span>
                </span>
            </button>
        `).join('');
        this.templatesRendered = true;
    }

    updateActiveTemplate() {
        this.templateList?.querySelectorAll('[data-template-id]').forEach((card) => {
            const active = card.dataset.templateId === this.templateId;
            card.classList.toggle('border-[var(--admin-page-accent)]', active);
            card.classList.toggle('bg-[#122033]', active);
        });
    }

    previewFrame(template, title, className = previewFrameClass, htmlKey = 'previewHtml', dimensions = '') {
        const html = template[htmlKey] || template.previewHtml || '';
        if (!html) {
            return '<span class="grid aspect-video place-items-center text-xs text-slate-500">No preview</span>';
        }

        return `<iframe class="${className}" title="${escapeHtml(template.label)} ${escapeHtml(title)}" sandbox tabindex="-1" aria-hidden="true" scrolling="no" ${dimensions} data-template-preview-frame srcdoc="${escapeHtml(html)}"></iframe>`;
    }

    armPreviewFrames(container) {
        container?.querySelectorAll('[data-template-preview-frame]').forEach((frame) => {
            const reveal = () => {
                frame.classList.remove('opacity-0');
                frame.classList.add('opacity-100');
            };

            frame.addEventListener('load', reveal, {once: true});
            window.setTimeout(() => {
                try {
                    if (frame.contentDocument?.readyState === 'complete') {
                        reveal();
                    }
                } catch (error) {}
            }, 120);
            window.setTimeout(reveal, 700);
        });
    }

    renderTemplatePreview() {
        const template = this.selectedTemplate();
        if (!template) {
            this.templatePreview.innerHTML = '';
            this.templateView.textContent = '';
            this.previewTemplateId = null;
            return;
        }

        const previewKey = `${template.id}:${this.selectedDefaultNavigationIdentifier()}`;
        if (this.previewTemplateId === previewKey) {
            return;
        }

        this.templateView.textContent = template.layout ? `${template.layout} · ${template.view}` : template.view;
        if (this.templatePreviewTitle) {
            this.templatePreviewTitle.textContent = template.label;
        }
        this.templatePreview.innerHTML = this.livePreview(template);
        this.armPreviewFrames(this.templatePreview);
        this.previewTemplateId = previewKey;
    }

    renderPreviewChrome() {
        const viewport = previewViewports[this.previewViewport] || previewViewports.desktop;
        this.root.dataset.previewViewport = this.previewViewport;

        if (this.previewFrame) {
            this.previewFrame.style.width = `${viewport.width}px`;
            this.previewFrame.style.maxWidth = '100%';
        }
        if (this.previewStage) {
            this.previewStage.style.maxWidth = this.previewViewport === 'desktop' ? '1380px' : `${Math.min(viewport.width + 48, 1380)}px`;
        }
        if (this.previewTitle) {
            this.previewTitle.textContent = `${optionLabel(viewport, this.labels)} ${this.labels.preview}`;
        }
        if (this.previewViewportLabel) {
            this.previewViewportLabel.textContent = `${optionLabel(viewport, this.labels)} · ${viewport.width}px ${this.labels.viewport}`;
        }
        if (this.contentWidthLabel) {
            this.contentWidthLabel.textContent = `${this.labels.content}: ${this.contentWidthDisplay()}`;
        }

        this.root.querySelectorAll('[data-preview-mode]').forEach((button) => {
            const active = button.dataset.previewMode === this.previewViewport;
            button.classList.toggle('bg-[var(--admin-page-accent)]', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('shadow-[0_8px_18px_rgba(0,0,0,.22)]', active);
            button.classList.toggle('text-slate-400', !active);
        });
    }

    livePreview(template) {
        return `
            <div class="overflow-hidden rounded-lg border border-slate-800 bg-[#171d25]">
                <div class="max-h-[520px] overflow-auto bg-[var(--page-bg)] text-[var(--page-text)]">
                    ${this.templateDummyLayout(template)}
                </div>
                <div class="border-t border-slate-800 bg-[#151b22] py-4 text-center text-sm text-slate-500">Live-Vorschau: ${escapeHtml(template.layout || 'no layout')} · ${escapeHtml(template.view)}</div>
            </div>
        `;
    }

    templateDummyLayout(template) {
        const blocks = template?.blocks || [];
        if (blocks.length === 0) {
            return '<div class="rounded-lg border border-dashed border-slate-700 p-8 text-center text-sm text-slate-500">No blocks defined.</div>';
        }

        const blockById = new Map(blocks.map((block) => [block.id, block]));
        const renderBlock = (id, className = '') => {
            const block = blockById.get(id);
            return block ? this.templateDummyBlock(block, className) : '';
        };

        if (this.isCustomTemplate(template)) {
            return this.customLayoutShell(blocks, (block) => this.templateDummyBlock(block));
        }

        if (template.id === 'landing') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--landing">
                        <section class="page-template-region">${renderBlock('hero')}</section>
                        <section class="page-template-region">${renderBlock('main')}</section>
                        <section class="page-template-region">${renderBlock('footer')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'editorial') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--editorial">
                        <section class="page-template-region">${renderBlock('intro')}</section>
                        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(160px,.36fr)]">
                            <section class="page-template-region">${renderBlock('content')}</section>
                            <aside class="page-template-region">${renderBlock('aside')}</aside>
                        </div>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'two-column') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--two-column">
                        <div class="grid gap-5 md:grid-cols-2">
                            <section class="page-template-region">${renderBlock('primary')}</section>
                            <section class="page-template-region">${renderBlock('secondary')}</section>
                        </div>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'feature-grid') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--feature-grid">
                        <section class="page-template-region">${renderBlock('intro')}</section>
                        <div class="grid gap-5 md:grid-cols-3">
                            <section class="page-template-region">${renderBlock('feature_a')}</section>
                            <section class="page-template-region">${renderBlock('feature_b')}</section>
                            <section class="page-template-region">${renderBlock('feature_c')}</section>
                        </div>
                        <section class="page-template-region">${renderBlock('cta')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'article-detail') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--article-detail">
                        <header class="page-template-region">${renderBlock('header')}</header>
                        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(220px,.34fr)]">
                            <article class="page-template-region">${renderBlock('body')}</article>
                            <aside class="page-template-region">${renderBlock('aside')}</aside>
                        </div>
                        <section class="page-template-region">${renderBlock('related')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'full-bleed') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--full-bleed">
                        <section class="page-template-region grid content-center">${renderBlock('stage')}</section>
                        <section class="page-template-region page-template-support">${renderBlock('support')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        return `
            <main class="page-template-main page-template-main--landing">
                ${blocks.map((block) => this.templateDummyBlock(block)).join('')}
            </main>
        `;
    }

    templateDummyBlock(block, className = '') {
        const minHeight = {
            hero: 'min-h-[220px]',
            large: 'min-h-40',
            medium: 'min-h-28',
            aside: 'min-h-44',
            small: 'min-h-24',
        }[block.size] || 'min-h-28';

        if (block.preview === 'heading') {
            return `
                <div class="grid min-h-24 content-center p-6 ${className}">
                    <span class="text-2xl font-extrabold leading-tight text-[var(--page-text)]">${escapeHtml(block.label)}</span>
                </div>
            `;
        }

        if (block.preview === 'hero') {
            return `
                <section class="grid items-center p-6 ${className}" style="grid-template-columns: minmax(0, 1fr) minmax(190px, .58fr); gap: clamp(3rem, 8vw, 5.5rem); min-height: 250px;">
                    <div class="grid gap-4">
                        <span class="text-3xl font-extrabold leading-tight text-[var(--page-text)]">${escapeHtml(block.label)}</span>
                        <span class="block h-2.5 max-w-[320px] rounded-full bg-[var(--page-border)]"></span>
                        <span class="block h-2.5 max-w-[220px] rounded-full bg-[var(--page-panel-soft)]"></span>
                        <span class="mt-2 block h-9 w-28 rounded-md bg-[var(--page-accent)]"></span>
                    </div>
                    <div class="grid place-items-center justify-self-end overflow-hidden rounded-xl border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-3 shadow-[0_16px_38px_rgba(0,0,0,.20)]" style="width: min(100%, 290px); min-height: 158px;">
                        <div class="grid h-full w-full place-items-center rounded-lg border border-dashed border-[var(--page-border)] bg-[var(--page-bg)]" style="min-height: 130px; background-image: linear-gradient(135deg, color-mix(in srgb, var(--page-accent) 18%, transparent), rgba(148, 163, 184, .055) 45%, color-mix(in srgb, var(--page-bg) 90%, black));">
                            <svg aria-hidden="true" class="h-14 w-14 text-[var(--page-muted)]/80" viewBox="0 0 96 96" fill="none">
                                <rect x="14" y="18" width="68" height="60" rx="8" stroke="currentColor" stroke-width="5"></rect>
                                <circle cx="34" cy="38" r="8" fill="currentColor" opacity=".55"></circle>
                                <path d="M22 68l19-20 14 14 9-10 10 16H22z" fill="currentColor" opacity=".45"></path>
                                <path d="M22 24l52 48" stroke="currentColor" stroke-width="5" stroke-linecap="round" opacity=".75"></path>
                            </svg>
                        </div>
                    </div>
                </section>
            `;
        }

        if (block.preview === 'text') {
            return `
                <div class="grid min-h-20 content-center p-6 ${className}">
                    <p class="m-0 max-w-[58ch] text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(block.label)}</p>
                </div>
            `;
        }

        if (block.preview === 'navigation') {
            return `
                <div class="page-site-header border-b border-[var(--page-border)] bg-[var(--page-header-bg)] text-[var(--page-header-text)] ${className}">
                    <div class="page-template-chrome-inner flex min-h-16 items-center gap-4 px-6 py-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--page-accent)] text-sm font-black text-white">N</span>
                        <strong class="text-base font-bold">NixCMS</strong>
                        <div class="ml-auto">${this.renderNavigationItems(this.selectedDefaultNavigationIdentifier(), 'horizontal')}</div>
                    </div>
                </div>
            `;
        }

        if (block.preview === 'footer') {
            return `
                <div class="page-site-footer border-t border-[var(--page-border)] bg-[var(--page-footer-bg)] ${className}">
                    <div class="page-template-chrome-inner flex min-h-16 flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <span class="text-xs font-medium text-[var(--page-footer-text)]">Powered by NixCMS</span>
                        <div>${this.renderNavigationItems(this.selectedDefaultNavigationIdentifier(), 'compact')}</div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="${minHeight} grid content-center gap-3 rounded-xl border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-6 shadow-[0_18px_48px_rgba(0,0,0,.24)] ${className}">
                <span class="text-lg font-bold text-[var(--page-text)]">${escapeHtml(block.label)}</span>
                <span class="block h-2.5 max-w-[460px] rounded-full bg-[var(--page-border)]"></span>
                <span class="block h-2.5 max-w-[320px] rounded-full bg-[var(--page-panel-soft)]"></span>
            </div>
        `;
    }

    customSlotRegion(block) {
        return optionValue(customLayoutRegions, block?.region, 'main');
    }

    customSlotSize(block) {
        return optionValue(customLayoutSizes, block?.size, 'medium');
    }

    customLayoutShell(blocks, renderBlock, {interactive = false} = {}) {
        const blockGroups = customLayoutRegions.reduce((groups, region) => {
            groups[region.value] = blocks.filter((block) => this.customSlotRegion(block) === region.value);
            return groups;
        }, {});
        const renderSlot = (block) => {
            const region = this.customSlotRegion(block);
            const size = this.customSlotSize(block);
            const position = optionValue(customLayoutPositions, block?.position, 'stretch');
            const maxHeight = optionValue(customLayoutMaxHeights, block?.maxHeight, 'none');
            const overflow = optionValue(customLayoutOverflows, block?.overflow, 'visible');
            const surface = optionValue(customLayoutSurfaces, block?.surface, 'none');
            return `
                <section class="page-template-region page-template-custom-slot page-template-custom-slot--${escapeHtml(region)} page-template-custom-slot--${escapeHtml(size)} page-template-custom-slot--position-${escapeHtml(position)} page-template-custom-slot--max-${escapeHtml(maxHeight)} page-template-custom-slot--overflow-${escapeHtml(overflow)} page-template-custom-slot--surface-${escapeHtml(surface)}" data-region="${escapeHtml(region)}" data-size="${escapeHtml(size)}" data-position="${escapeHtml(position)}" data-max-height="${escapeHtml(maxHeight)}" ${interactive ? `data-custom-slot-drop="${escapeHtml(block.id)}"` : ''}>
                    ${interactive ? this.customSlotControls(block) : ''}
                    ${renderBlock(block)}
                </section>
            `;
        };
        const renderRegion = (region) => blockGroups[region].map(renderSlot).join('');
        const main = blockGroups.main;
        const aside = blockGroups.aside;
        const mainContent = main.length === 0 && aside.length === 0
            ? '<section class="page-template-region page-template-custom-empty"></section>'
            : aside.length > 0
                ? `<div class="page-template-custom-grid">
                    <div class="page-template-custom-stack">${main.map(renderSlot).join('')}</div>
                    <aside class="page-template-custom-stack">${aside.map(renderSlot).join('')}</aside>
                </div>`
                : `<div class="page-template-custom-stack">${main.map(renderSlot).join('')}</div>`;

        return `
            <div class="page-template-shell page-template-shell--custom">
                ${blockGroups.header.length > 0 ? `<div class="page-template-custom-region page-template-custom-region--header">${renderRegion('header')}</div>` : ''}
                <main class="page-template-main page-template-main--custom">${mainContent}</main>
                ${blockGroups.footer.length > 0 ? `<div class="page-template-custom-region page-template-custom-region--footer">${renderRegion('footer')}</div>` : ''}
            </div>
        `;
    }

    customSlotControls(block) {
        const position = optionValue(customLayoutPositions, block?.position, 'stretch');
        const maxHeight = optionValue(customLayoutMaxHeights, block?.maxHeight, 'none');
        const overflow = optionValue(customLayoutOverflows, block?.overflow, 'visible');
        const surface = optionValue(customLayoutSurfaces, block?.surface, 'none');
        const regionOptions = optionOptions(customLayoutRegions, this.customSlotRegion(block), this.labels);
        const sizeOptions = optionOptions(customLayoutSizes, this.customSlotSize(block), this.labels);
        const positionOptions = optionOptions(customLayoutPositions, position, this.labels);
        const maxHeightOptions = optionOptions(customLayoutMaxHeights, maxHeight, this.labels);
        const overflowOptions = optionOptions(customLayoutOverflows, overflow, this.labels);
        const surfaceOptions = optionOptions(customLayoutSurfaces, surface, this.labels);
        const controlClass = 'min-h-8 w-full rounded-md border border-slate-700 bg-[#0b1017] px-2.5 py-1.5 text-[13px] text-slate-100 outline-none focus:border-[var(--admin-page-accent)]';
        const compactSelect = 'data-styled-select data-styled-select-density="compact"';

        return `
            <div class="page-builder-slot-controls">
                <button class="page-builder-slot-controls__tab" type="button" draggable="true" data-custom-slot-drag="${escapeHtml(block.id)}" aria-label="Reorder slot">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M8 6h.01"></path><path d="M16 6h.01"></path>
                        <path d="M8 12h.01"></path><path d="M16 12h.01"></path>
                        <path d="M8 18h.01"></path><path d="M16 18h.01"></path>
                    </svg>
                    <span>${escapeHtml(block.label || 'Slot')}</span>
                </button>
                <div class="page-builder-slot-controls__panel">
                    <div style="display:grid;grid-template-columns:minmax(240px,1fr) minmax(132px,156px) minmax(146px,176px) 28px;gap:8px;align-items:center;">
                        <label class="min-w-0">
                            <span class="sr-only">Slot label</span>
                            <input class="${controlClass}" type="text" value="${escapeHtml(block.label)}" data-custom-slot-label="${escapeHtml(block.id)}">
                        </label>
                        <label class="min-w-0">
                            <span class="sr-only">Region</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-region="${escapeHtml(block.id)}">${regionOptions}</select>
                        </label>
                        <label class="min-w-0">
                            <span class="sr-only">Size</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-size="${escapeHtml(block.id)}">${sizeOptions}</select>
                        </label>
                        <button class="rounded-md border border-rose-900/70 bg-rose-950/20 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50" style="display:grid;width:28px;height:28px;place-items:center;justify-self:end;" type="button" data-delete-custom-slot="${escapeHtml(block.id)}" aria-label="${escapeHtml(this.labels.deleteSlot)}">
                            <svg aria-hidden="true" style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"></path>
                                <path d="M8 6V4h8v2"></path>
                                <path d="M19 6l-1 14H6L5 6"></path>
                                <path d="M10 11v5"></path>
                                <path d="M14 11v5"></path>
                            </svg>
                        </button>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin-top:6px;">
                        <label class="min-w-0">
                            <span class="block uppercase tracking-wide text-slate-600" style="margin-bottom:2px;font-size:8px;font-weight:500;line-height:1.1;opacity:.72;">${escapeHtml(this.labels.position)}</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-position="${escapeHtml(block.id)}">${positionOptions}</select>
                        </label>
                        <label class="min-w-0">
                            <span class="block uppercase tracking-wide text-slate-600" style="margin-bottom:2px;font-size:8px;font-weight:500;line-height:1.1;opacity:.72;">${escapeHtml(this.labels.maxHeight)}</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-max-height="${escapeHtml(block.id)}">${maxHeightOptions}</select>
                        </label>
                        <label class="min-w-0">
                            <span class="block uppercase tracking-wide text-slate-600" style="margin-bottom:2px;font-size:8px;font-weight:500;line-height:1.1;opacity:.72;">${escapeHtml(this.labels.overflow)}</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-overflow="${escapeHtml(block.id)}">${overflowOptions}</select>
                        </label>
                        <label class="min-w-0">
                            <span class="block uppercase tracking-wide text-slate-600" style="margin-bottom:2px;font-size:8px;font-weight:500;line-height:1.1;opacity:.72;">${escapeHtml(this.labels.surface)}</span>
                            <select class="${controlClass}" ${compactSelect} data-custom-slot-surface="${escapeHtml(block.id)}">${surfaceOptions}</select>
                        </label>
                    </div>
                </div>
            </div>
        `;
    }

    renderBlocks() {
        const template = this.selectedTemplate();
        const blocks = template?.blocks || [];
        const filled = blocks.filter((block) => this.blockContent[block.id]).length;
        if (this.filledCount) {
            this.filledCount.textContent = `${filled}/${blocks.length} filled`;
        }
        if (this.blockCanvas) {
            this.blockCanvas.innerHTML = this.blockLayout(template, true);
            initStyledSelects(this.blockCanvas.querySelectorAll('select[data-styled-select]'));
        }
    }

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
    }

    normalizeTranslationSuggestions(suggestions) {
        return (Array.isArray(suggestions) ? suggestions : [])
            .map((suggestion) => ({
                key: String(suggestion?.key || ''),
                value: String(suggestion?.value || ''),
            }))
            .filter((suggestion) => suggestion.key !== '');
    }

    translationLanguage() {
        const language = String(this.persistedValues.language || this.defaultLanguage || this.languages[0] || 'en');
        return language === 'current' ? this.defaultLanguage : language;
    }

    currentTranslationSuggestions(language = this.translationLanguage()) {
        const normalized = this.normalizeLanguageCode(language) || language || this.translationLanguage();
        if (!this.translationSuggestionsByLanguage[normalized] && Array.isArray(this.translationSuggestionsByLanguage[''])) {
            return this.translationSuggestionsByLanguage[''];
        }
        this.translationSuggestionsByLanguage[normalized] = Array.isArray(this.translationSuggestionsByLanguage[normalized])
            ? this.translationSuggestionsByLanguage[normalized]
            : [];

        return this.translationSuggestionsByLanguage[normalized];
    }

    findTranslationSuggestion(language, key) {
        const normalizedKey = String(key || '').trim();
        if (normalizedKey === '') {
            return null;
        }

        return this.currentTranslationSuggestions(language).find((suggestion) => suggestion.key === normalizedKey) || null;
    }

    setTranslationSuggestion(language, key, value) {
        const suggestions = this.currentTranslationSuggestions(language);
        const existing = suggestions.find((suggestion) => suggestion.key === key);
        if (existing) {
            existing.value = value;
            return;
        }

        suggestions.push({key, value});
        suggestions.sort((a, b) => a.key.localeCompare(b.key));
    }

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
    }

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
    }

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
    }

    hideTranslationSuggestions() {
        this.activeTranslationSuggestInput = null;
        if (!this.translationSuggestBox) {
            return;
        }
        this.translationSuggestBox.classList.add('hidden');
        this.translationSuggestBox.innerHTML = '';
    }

    ensureTranslationSuggestBox() {
        if (this.translationSuggestBox instanceof HTMLElement) {
            return this.translationSuggestBox;
        }

        this.translationSuggestBox = document.createElement('div');
        this.translationSuggestBox.className = 'fixed z-50 hidden max-h-64 overflow-auto rounded-md border border-slate-300 bg-white p-1 text-sm shadow-xl dark:border-slate-700 dark:bg-slate-950';
        this.translationSuggestBox.dataset.pageTranslationSuggestBox = 'true';
        document.body.appendChild(this.translationSuggestBox);

        return this.translationSuggestBox;
    }

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
    }

    isMobilePreview() {
        return this.previewViewport === 'mobile';
    }

    blockLayout(template, interactive) {
        const blocks = template?.blocks || [];
        if (this.isCustomTemplate(template)) {
            const toolbar = interactive ? `
                <div class="page-builder-custom-toolbar mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-[#121820] px-4 py-3">
                    <div class="grid gap-0.5">
                        <span class="text-[11px] font-bold uppercase text-slate-500">${escapeHtml(this.labels.customLayout)}</span>
                        <strong class="text-sm font-semibold text-white">${escapeHtml(this.labels.customSlotsCount.replace(':count', String(blocks.length)))}</strong>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-700 px-3 text-xs font-bold text-slate-200 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-save-custom-layout>
                            ${escapeHtml(this.labels.saveCustomLayout)}
                        </button>
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-700 px-3 text-xs font-bold text-slate-200 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-add-chrome-slot="header">
                            ${escapeHtml(this.labels.addHeaderBlock)}
                        </button>
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-700 px-3 text-xs font-bold text-slate-200 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-add-chrome-slot="footer">
                            ${escapeHtml(this.labels.addFooterBlock)}
                        </button>
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md bg-[var(--admin-page-accent)] px-3 text-xs font-bold text-white transition hover:brightness-110" type="button" data-add-custom-slot>
                            ${escapeHtml(this.labels.addCustomBlock)}
                        </button>
                    </div>
                </div>
            ` : '';

            return `${toolbar}${this.customLayoutShell(
                blocks,
                (block) => this.blockCard(block, interactive, template.id),
                {interactive}
            )}`;
        }

        if (blocks.length === 0) {
            return '<div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">No blocks defined.</div>';
        }

        const blockById = new Map(blocks.map((block) => [block.id, block]));
        const renderBlock = (id, className = '') => {
            const block = blockById.get(id);
            return block ? this.blockCard(block, interactive, template.id, className) : '';
        };
        const twoColumnGrid = this.isMobilePreview()
            ? 'grid gap-5'
            : 'grid gap-5 grid-cols-2';
        const editorialGrid = this.isMobilePreview()
            ? 'grid gap-5'
            : 'grid gap-5 grid-cols-[minmax(0,1fr)_minmax(180px,.36fr)]';
        const articleGrid = this.isMobilePreview()
            ? 'grid gap-5'
            : 'grid gap-5 grid-cols-[minmax(0,1fr)_minmax(220px,.34fr)]';
        const featureGrid = this.isMobilePreview()
            ? 'grid gap-5'
            : this.previewViewport === 'tablet'
                ? 'grid gap-5 grid-cols-2'
                : 'grid gap-5 grid-cols-3';

        if (template.id === 'landing') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--landing">
                        <section class="page-template-region">${renderBlock('hero')}</section>
                        <section class="page-template-region">${renderBlock('main')}</section>
                        <section class="page-template-region">${renderBlock('footer')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'editorial') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--editorial">
                        <section class="page-template-region">${renderBlock('intro')}</section>
                        <div class="${editorialGrid}">
                            <section class="page-template-region">${renderBlock('content')}</section>
                            <aside class="page-template-region">${renderBlock('aside')}</aside>
                        </div>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'two-column') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--two-column">
                        <div class="${twoColumnGrid}">
                            <section class="page-template-region">${renderBlock('primary')}</section>
                            <section class="page-template-region">${renderBlock('secondary')}</section>
                        </div>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'feature-grid') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--feature-grid">
                        <section class="page-template-region">${renderBlock('intro')}</section>
                        <div class="${featureGrid}">
                            <section class="page-template-region">${renderBlock('feature_a')}</section>
                            <section class="page-template-region">${renderBlock('feature_b')}</section>
                            <section class="page-template-region">${renderBlock('feature_c')}</section>
                        </div>
                        <section class="page-template-region">${renderBlock('cta')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'article-detail') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--article-detail">
                        <header class="page-template-region">${renderBlock('header')}</header>
                        <div class="${articleGrid}">
                            <article class="page-template-region">${renderBlock('body')}</article>
                            <aside class="page-template-region">${renderBlock('aside')}</aside>
                        </div>
                        <section class="page-template-region">${renderBlock('related')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        if (template.id === 'full-bleed') {
            return `
                <div class="page-template-shell">
                    ${renderBlock('site_header')}
                    <main class="page-template-main page-template-main--full-bleed">
                        <section class="page-template-region grid content-center">${renderBlock('stage')}</section>
                        <section class="page-template-region page-template-support">${renderBlock('support')}</section>
                    </main>
                    ${renderBlock('site_footer')}
                </div>
            `;
        }

        return `
            <main class="page-template-main page-template-main--landing">
                ${blocks.map((block) => this.blockCard(block, interactive, template.id)).join('')}
            </main>
        `;
    }

    blockCard(block, interactive, templateId, className = '') {
        const content = this.blockContent[block.id] || null;
        const selected = block.id === this.selectedBlockId;
        const minHeight = {
            hero: 'min-h-44',
            large: 'min-h-36',
            medium: 'min-h-28',
            aside: 'min-h-32',
            small: 'min-h-20',
        }[block.size] || 'min-h-28';
        const actionAttr = interactive
            ? (content ? `data-edit-block="${escapeHtml(block.id)}"` : `data-choose-block="${escapeHtml(block.id)}"`)
            : '';
        const frameClass = [
            'group/page-block relative grid w-full min-w-0 justify-self-stretch content-center overflow-hidden rounded-xl border border-transparent p-6 text-left transition',
            minHeight,
            className,
            'bg-transparent hover:border-[var(--page-card-border)] hover:bg-[var(--page-card-bg)] focus:border-[var(--page-accent)] focus:bg-[var(--page-card-bg)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--page-accent)]/40',
            interactive ? 'cursor-pointer' : '',
        ].filter(Boolean).join(' ');
        const blockType = content?.label || 'Empty block';
        const controls = interactive ? `
            <div class="pointer-events-auto absolute right-3 top-3 flex translate-y-1 items-center gap-2 opacity-0 transition group-hover/page-block:translate-y-0 group-hover/page-block:opacity-100 group-focus-within/page-block:translate-y-0 group-focus-within/page-block:opacity-100">
                ${content ? `<button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 bg-slate-950/80 text-xs text-[var(--admin-page-accent)] transition hover:border-[var(--admin-page-accent)]" type="button" data-edit-block="${escapeHtml(block.id)}" aria-label="Edit block">✎</button>` : ''}
                ${content ? `
                    <button class="grid h-8 w-8 place-items-center rounded-md border border-rose-900/70 bg-slate-950/80 text-rose-400 transition hover:border-rose-500 hover:bg-rose-950/50" type="button" data-clear-block="${escapeHtml(block.id)}" aria-label="Clear block">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                ` : ''}
            </div>
        ` : '';
        const meta = `
            <div class="pointer-events-none absolute left-3 top-3 rounded-md border border-slate-700/80 bg-slate-950/80 px-2 py-1 opacity-0 transition group-hover/page-block:opacity-100 group-focus-within/page-block:opacity-100">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(block.label)} · ${escapeHtml(this.blockRegion(block))} · ${escapeHtml(blockType)}</span>
            </div>
        `;

        if (content) {
            return `
                <article class="${frameClass}" ${actionAttr} tabindex="${interactive ? '0' : '-1'}">
                    ${meta}
                    ${controls}
                    ${this.contentMiniPreview(content)}
                </article>
            `;
        }

        return `
            <button class="${frameClass}" type="button" ${actionAttr}>
                ${meta}
                ${this.emptyBlockPreview(block)}
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg text-[var(--admin-page-accent)] opacity-0 transition group-hover/page-block:opacity-70 group-focus-visible/page-block:opacity-70">✎</span>
            </button>
        `;
    }

    emptyBlockPreview(block) {
        if (block.preview === 'text') {
            return `<span class="text-base font-semibold text-[var(--page-muted)]">${escapeHtml(block.label)}</span>`;
        }

        return `
            <span class="text-base font-semibold text-[var(--page-muted)]">${escapeHtml(block.label)}</span>
            <span class="mt-3 block h-2.5 max-w-[420px] rounded-full bg-[var(--page-border)]"></span>
            <span class="mt-2 block h-2.5 max-w-[280px] rounded-full bg-[var(--page-panel-soft)]"></span>
        `;
    }

    navigationPreviewItems(identifier, language = '', limit = 5) {
        const items = this.navigationItems(identifier, language).slice(0, limit);
        if (items.length === 0) {
            return [
                {label: 'Home'},
                {label: 'News'},
                {label: 'Pages'},
            ];
        }

        return items;
    }

    renderNavigationItems(identifier, presentation = 'horizontal', language = '') {
        const items = this.navigationPreviewItems(identifier, language, this.isMobilePreview() ? 3 : 5);
        const vertical = presentation === 'vertical';
        const compact = presentation === 'compact';
        const className = vertical
            ? 'grid gap-1.5'
            : 'flex flex-wrap items-center gap-1.5';
        const itemClass = compact
            ? 'rounded px-2 py-1 text-[11px] font-semibold text-[var(--page-muted)]'
            : 'rounded-md bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-[var(--page-muted)]';

        return `
            <nav class="${className}" aria-label="Navigation preview">
                ${items.map((item) => `<span class="${itemClass}">${escapeHtml(item.label || item.title || item.key || 'Item')}</span>`).join('')}
            </nav>
        `;
    }

    contentSurfaceClass(content, baseClass = 'grid gap-2', defaultSurface = 'none', paddedClass = 'p-4') {
        const surface = optionValue(customLayoutSurfaces, content?.props?.surface, defaultSurface);
        return {
            none: `${baseClass} px-1 py-2`,
            soft: `${baseClass} rounded-md bg-[var(--page-panel-soft)] ${paddedClass}`,
            card: `${baseClass} rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] ${paddedClass}`,
            accent: `${baseClass} rounded-md border border-[color-mix(in_srgb,var(--page-accent)_36%,var(--page-card-border))] bg-[color-mix(in_srgb,var(--page-accent)_12%,var(--page-card-bg))] ${paddedClass}`,
        }[surface];
    }

    contentMiniPreview(content, depth = 0) {
        content = content && typeof content === 'object' ? content : {type: '', props: {}};
        if (layoutBlockTypes.includes(content.type)) {
            const children = Array.isArray(content.props?.children) ? content.props.children : [];
            const childPreview = children.slice(0, 6).map((child) => `
                <div class="${this.layoutChildContainerClass(child, true)}">
                    ${depth > 2 ? `<span class="text-xs text-[var(--page-muted)]">${escapeHtml(child?.label || child?.type || 'Block')}</span>` : this.contentMiniPreview(child, depth + 1)}
                </div>
            `).join('');
            const empty = '<div class="rounded-md border border-dashed border-[var(--page-border)] p-3 text-center text-xs text-[var(--page-muted)]">Empty layout module</div>';
            const columns = content.type === 'columns' ? Math.max(2, Math.min(4, Number(content.props?.columns || 2))) : Math.max(2, Math.min(4, Number(content.props?.columns || 3)));
            const layoutClass = content.type === 'stack' || content.type === 'section'
                ? 'grid gap-2'
                : 'grid gap-2';
            const layoutStyle = content.type === 'stack' || content.type === 'section' || this.isMobilePreview()
                ? ''
                : ` style="grid-template-columns:repeat(${columns},minmax(0,1fr));"`;

            return `
                <div class="grid gap-2 px-1 py-2">
                    <div class="flex items-center justify-between gap-3">
                        <strong class="text-sm font-bold text-[var(--page-text)]">${escapeHtml(content.label || content.previewTitle || content.type)}</strong>
                        <span class="rounded bg-white/10 px-2 py-1 text-[11px] font-bold uppercase text-[var(--page-muted)]">${escapeHtml(content.type)}</span>
                    </div>
                    <div class="${layoutClass}"${layoutStyle}>${childPreview || empty}</div>
                </div>
            `;
        }

        if (content.type === 'header') {
            const compact = this.isMobilePreview();
            const logoImage = String(content.props?.logoImage || '');
            const logoText = String(content.props?.logoText || 'NixCMS');
            return `
                <div class="page-site-header rounded-lg border border-[var(--page-border)] bg-[var(--page-header-bg)] text-[var(--page-header-text)]">
                    <div class="page-template-chrome-inner ${compact ? 'grid' : 'flex'} min-h-14 items-center gap-4 px-4 py-3">
                        ${logoImage
                            ? `<img class="h-8 w-auto max-w-36 object-contain" src="${escapeHtml(logoImage)}" alt="">`
                            : `<span class="grid h-8 w-8 place-items-center rounded-md bg-[var(--page-accent)] text-xs font-black text-white">${escapeHtml(logoText.slice(0, 1) || 'N')}</span>`}
                        <strong class="text-sm font-bold">${escapeHtml(logoText)}</strong>
                        <div class="${compact ? '' : 'ml-auto'}">${this.renderNavigationItems(content.props?.navigationIdentifier, compact ? 'compact' : (content.props?.presentation || 'horizontal'))}</div>
                    </div>
                </div>
            `;
        }

        if (content.type === 'footer') {
            const compact = this.isMobilePreview();
            return `
                <div class="page-site-footer rounded-lg border border-[var(--page-border)] bg-[var(--page-footer-bg)]">
                    <div class="page-template-chrome-inner flex min-h-14 flex-wrap ${compact ? 'items-start' : 'items-center justify-between'} gap-3 px-4 py-3">
                        <span class="text-xs text-[var(--page-footer-text)]">${escapeHtml(content.props?.text || 'Powered by NixCMS')}</span>
                        ${this.renderNavigationItems(content.props?.navigationIdentifier, content.props?.presentation || 'compact')}
                    </div>
                </div>
            `;
        }

        if (content.type === 'aside') {
            return `
                <aside class="grid gap-3 rounded-lg border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-4">
                    <div>
                        <h3 class="m-0 text-sm font-bold text-[var(--page-text)]">${escapeHtml(content.props?.title || 'Aside')}</h3>
                        <p class="m-0 mt-1 text-xs leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || '')}</p>
                    </div>
                    ${this.renderNavigationItems(content.props?.navigationIdentifier, 'vertical')}
                </aside>
            `;
        }

        if (content.type === 'heading') {
            return `
                <div class="px-1 py-2">
                    <span class="text-xl font-extrabold leading-tight text-[var(--page-text)]">${escapeHtml(content.props?.text || content.previewTitle || 'Headline')}</span>
                </div>
            `;
        }

        if (content.type === 'text') {
            return `
                <div class="px-1 py-2">
                    <p class="m-0 max-w-[58ch] text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || content.previewTitle || 'Text block')}</p>
                </div>
            `;
        }

        if (content.type === 'articles') {
            return `
                <div class="rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-3">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-semibold">${escapeHtml(content.previewTitle)}</span>
                        <span class="rounded bg-white px-2 py-1 font-mono dark:bg-slate-800">${escapeHtml(content.props.presentation)}</span>
                    </div>
                    <div class="mt-2 rounded bg-white/70 px-2 py-1 font-mono text-[11px] text-slate-500 dark:bg-slate-800/70">${escapeHtml(content.props.detailPath || '/articles/{slug}')}</div>
                    <div class="mt-2 grid gap-1.5">
                        <span class="h-2 rounded bg-slate-300 dark:bg-slate-700"></span>
                        <span class="h-2 w-10/12 rounded bg-slate-300 dark:bg-slate-700"></span>
                        <span class="h-2 w-8/12 rounded bg-slate-300 dark:bg-slate-700"></span>
                    </div>
                </div>
            `;
        }

        if (content.type === 'hero') {
            const compact = this.isMobilePreview();
            return `
                <div class="grid gap-3 rounded-lg bg-[var(--page-card-bg)] p-5 ${compact ? '' : 'grid-cols-[minmax(0,1fr)_160px]'}">
                    <div class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase text-[var(--page-accent)]">${escapeHtml(content.props?.eyebrow || 'Hero')}</span>
                        <strong class="text-2xl font-black leading-tight text-[var(--page-text)]">${escapeHtml(content.props?.title || 'Hero title')}</strong>
                        <span class="text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || '')}</span>
                    </div>
                    <span class="${compact ? 'hidden' : 'block'} rounded-md border border-dashed border-[var(--page-border)]"></span>
                </div>
            `;
        }

        if (content.type === 'chart') {
            const metrics = this.chartMetrics(content);
            const count = metrics.length;
            const type = content.props?.chartType || 'metric-bars';
            const labels = metrics.map((metric) => metric.label);
            const values = metrics.map((metric) => Number(metric.value || 0));
            const colors = metrics.map((metric, index) => this.normalizeColor(metric.color) || chartMetricColors[index % chartMetricColors.length]);
            const max = Math.max(...values, 1);
            const linePoints = values.map((value, index) => ({
                x: count === 1 ? 160 : 28 + ((index / (count - 1)) * 264),
                y: 112 - ((value / max) * 74),
            }));
            const linePath = linePoints.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');
            const lastLinePoint = linePoints[linePoints.length - 1] || {x: 292, y: 118};
            const firstLinePoint = linePoints[0] || {x: 28, y: 118};
            const areaPath = `${linePath} L ${lastLinePoint.x.toFixed(1)} 118 L ${firstLinePoint.x.toFixed(1)} 118 Z`;
            const pieSegments = labels.map((label, index) => {
                const offset = labels.slice(0, index).reduce((sum, _, colorIndex) => sum + Math.max(6, values[colorIndex]), 0);
                const total = values.reduce((sum, value) => sum + Math.max(6, value), 0) || 1;
                const dash = (Math.max(6, values[index]) / total) * 100;
                return `<circle cx="50" cy="50" r="${type === 'pie' ? 28 : 24}" fill="none" stroke="${escapeHtml(colors[index])}" stroke-width="${type === 'pie' ? 56 : 18}" stroke-dasharray="${dash} ${100 - dash}" stroke-dashoffset="${100 - ((offset / total) * 100)}" pathLength="100" transform="rotate(-90 50 50)"><title>${escapeHtml(label)}</title></circle>`;
            }).join('');

            const chartPreview = type === 'line'
                ? `<div class="rounded-lg border border-white/5 bg-slate-950/20 px-3 py-3">
                    <svg class="h-32 w-full" viewBox="0 0 320 132" aria-hidden="true">
                        <path d="M28 118H300" stroke="rgba(148,163,184,.20)" stroke-width="1"></path>
                        <path d="M28 90H300" stroke="rgba(148,163,184,.13)" stroke-width="1"></path>
                        <path d="M28 62H300" stroke="rgba(148,163,184,.13)" stroke-width="1"></path>
                        <path d="M28 34H300" stroke="rgba(148,163,184,.13)" stroke-width="1"></path>
                        <path d="${areaPath}" fill="${escapeHtml(colors[0])}" opacity=".12"></path>
                        <path d="${linePath}" fill="none" stroke="${escapeHtml(colors[0])}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                        ${linePoints.map((point, index) => `<circle cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="4.2" fill="var(--page-card-bg)" stroke="${escapeHtml(colors[index])}" stroke-width="3"><title>${escapeHtml(labels[index])}: ${escapeHtml(values[index])}</title></circle>`).join('')}
                    </svg>
                    <div class="mt-1 flex justify-between gap-2 px-6 text-[10px] text-[var(--page-muted)]">
                        ${labels.map((label) => `<span class="truncate">${escapeHtml(label)}</span>`).join('')}
                    </div>
                </div>`
                : type === 'bar'
                    ? `<div class="flex min-h-28 items-end gap-2 border-b border-white/10 px-1">
                        ${labels.map((label, index) => `<span class="grid flex-1 content-end gap-1 text-center" title="${escapeHtml(label)}">
                            <span class="mx-auto block w-full rounded-t" style="height:${Math.max(18, (values[index] / max) * 104)}px;background:${escapeHtml(colors[index])};"></span>
                            <span class="truncate text-[10px] text-[var(--page-muted)]">${escapeHtml(label)}</span>
                        </span>`).join('')}
                    </div>`
                    : type === 'doughnut' || type === 'pie'
                        ? `<div class="flex min-h-28 items-center gap-4">
                            <svg class="h-28 w-28 shrink-0" viewBox="0 0 100 100" aria-hidden="true">
                                <circle cx="50" cy="50" r="${type === 'pie' ? 28 : 24}" fill="${type === 'pie' ? colors[0] : 'rgba(148,163,184,.12)'}"></circle>
                                ${pieSegments}
                                ${type === 'doughnut' ? '<circle cx="50" cy="50" r="15" fill="var(--page-card-bg)"></circle>' : ''}
                            </svg>
                            <div class="grid min-w-0 flex-1 gap-1.5">
                                ${labels.map((label, index) => `<span class="grid grid-cols-[10px_minmax(0,1fr)_34px] items-center gap-2 text-[11px] text-[var(--page-muted)]">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background:${escapeHtml(colors[index])};"></span>
                                    <span class="truncate">${escapeHtml(label)}</span>
                                    <span class="text-right font-mono">${escapeHtml(values[index])}</span>
                                </span>`).join('')}
                            </div>
                        </div>`
                        : `<div class="grid min-h-28 gap-1.5">
                            ${labels.map((label, index) => `<span class="grid grid-cols-[86px_minmax(0,1fr)_34px] items-center gap-2 text-[11px] text-[var(--page-muted)]">
                                <span class="truncate">${escapeHtml(label)}</span>
                                <span class="h-2 overflow-hidden rounded-full bg-white/10"><span class="block h-full rounded-full" style="width:${Math.max(4, (values[index] / max) * 100)}%;background:${escapeHtml(colors[index])};"></span></span>
                                <span class="text-right font-mono">${escapeHtml(values[index])}</span>
                            </span>`).join('')}
                        </div>`;

            return `
                <div class="${this.contentSurfaceClass(content, 'grid gap-3')}">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <strong class="block truncate text-base font-bold text-[var(--page-text)]">${escapeHtml(content.props?.title || content.previewTitle || 'Diagram')}</strong>
                            <span class="mt-1 block truncate text-xs text-[var(--page-muted)]">${escapeHtml(content.props?.subtitle || type)}</span>
                        </div>
                        <span class="shrink-0 rounded bg-white/10 px-2 py-1 text-[11px] font-bold uppercase text-[var(--page-muted)]">${escapeHtml(type.replace('-', ' '))}</span>
                    </div>
                    ${chartPreview}
                </div>
            `;
        }

        if (content.type === 'image') {
            const src = String(content.props?.src || '');
            return `
                <figure class="m-0 grid gap-2 rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-3">
                    ${src
                        ? `<img class="aspect-video w-full rounded object-cover" src="${escapeHtml(src)}" alt="">`
                        : '<span class="grid aspect-video place-items-center rounded border border-dashed border-[var(--page-border)] text-xs text-[var(--page-muted)]">Image</span>'}
                    <figcaption class="truncate text-xs text-[var(--page-muted)]">${escapeHtml(content.props?.caption || content.props?.alt || '')}</figcaption>
                </figure>
            `;
        }

        if (content.type === 'embed') {
            return `
                <div class="grid gap-2 rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-4">
                    <div class="grid aspect-video place-items-center rounded border border-dashed border-[var(--page-border)] bg-slate-950/10">
                        <span class="text-xs font-semibold text-[var(--page-muted)]">External embed</span>
                    </div>
                    <strong class="truncate text-sm text-[var(--page-text)]">${escapeHtml(content.props?.title || 'External content')}</strong>
                    <span class="truncate font-mono text-[11px] text-[var(--page-muted)]">${escapeHtml(content.props?.url || '')}</span>
                </div>
            `;
        }

        if (content.type === 'map') {
            return `
                <div class="grid gap-2 rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-4">
                    <div class="relative grid aspect-video place-items-center overflow-hidden rounded bg-slate-950/20">
                        <span class="absolute inset-x-0 top-1/3 h-px bg-[var(--page-border)]"></span>
                        <span class="absolute inset-y-0 left-1/3 w-px bg-[var(--page-border)]"></span>
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--page-accent)] text-xs font-black text-white">Map</span>
                    </div>
                    <strong class="truncate text-sm text-[var(--page-text)]">${escapeHtml(content.props?.title || 'Map')}</strong>
                    <span class="truncate text-xs text-[var(--page-muted)]">${escapeHtml(content.props?.query || content.props?.embedUrl || '')}</span>
                </div>
            `;
        }

        if (['cta', 'feature', 'quote', 'stats', 'media'].includes(content.type)) {
            return `
                <div class="${this.contentSurfaceClass(content)}">
                    <strong class="text-base font-bold text-[var(--page-text)]">${escapeHtml(content.props?.title || content.props?.quote || content.previewTitle || content.label)}</strong>
                    <span class="text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || content.props?.caption || content.props?.source || '')}</span>
                </div>
            `;
        }

        if (content.type === 'form' || content.type === 'contact-form') {
            return `
                <div class="${this.contentSurfaceClass(content, 'grid gap-3', 'none')}">
                    <div>
                        <strong class="text-base font-bold text-[var(--page-text)]">${escapeHtml(content.props?.title || 'Form')}</strong>
                        <div class="mt-1 text-xs font-mono text-[var(--page-muted)]">${escapeHtml(content.props?.formIdentifier || 'contact')}</div>
                    </div>
                    <span class="h-9 rounded border border-[var(--page-border)]"></span>
                    <span class="h-16 rounded border border-[var(--page-border)]"></span>
                    <span class="h-9 w-28 rounded bg-[var(--page-accent)]"></span>
                </div>
            `;
        }

        if (content.type === 'article-title') {
            return `
                <div class="grid gap-2 rounded-md bg-slate-950/60 p-4">
                    <strong class="text-lg font-extrabold leading-tight text-slate-50">Resolved article title</strong>
                    ${content.props?.showTeaser ? '<span class="text-xs leading-relaxed text-slate-400">Article teaser from the resolved version.</span>' : ''}
                </div>
            `;
        }

        if (content.type === 'article-content') {
            return `
                <div class="grid gap-2 rounded-md bg-slate-950/45 p-4">
                    <span class="text-xs font-semibold text-slate-400">Editor.js body from current article</span>
                    <span class="h-2.5 rounded bg-slate-700"></span>
                    <span class="h-2.5 w-10/12 rounded bg-slate-700"></span>
                    <span class="h-2.5 w-8/12 rounded bg-slate-800"></span>
                </div>
            `;
        }

        if (content.type === 'article-meta') {
            return `
                <div class="${this.contentSurfaceClass(content, 'grid gap-2 text-xs')}">
                    <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Language</span><strong class="text-slate-100">Current</strong></div>
                    <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Published</span><strong class="text-slate-100">Date</strong></div>
                    ${content.props?.showTags ? '<div class="flex flex-wrap gap-1.5 pt-1"><span class="rounded-full bg-white/10 px-2 py-1 text-slate-300">tag</span><span class="rounded-full bg-white/10 px-2 py-1 text-slate-300">topic</span></div>' : ''}
                </div>
            `;
        }

        if (content.type === 'article-related') {
            return `
                <div class="rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-3">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-semibold">${escapeHtml(content.props?.title || content.previewTitle || 'Related articles')}</span>
                        <span class="rounded bg-white px-2 py-1 font-mono dark:bg-slate-800">${escapeHtml(content.props?.presentation || 'cards')}</span>
                    </div>
                    <div class="mt-2 grid gap-1.5">
                        <span class="h-2 rounded bg-slate-300 dark:bg-slate-700"></span>
                        <span class="h-2 w-10/12 rounded bg-slate-300 dark:bg-slate-700"></span>
                        <span class="h-2 w-8/12 rounded bg-slate-300 dark:bg-slate-700"></span>
                    </div>
                </div>
            `;
        }

        if (content.type === 'navigation') {
            return `
                <div class="grid gap-3 px-1 py-2">
                    <div class="flex w-max max-w-full items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-100">${escapeHtml(this.navigationChoice(content.props?.identifier)?.label || content.previewTitle || 'Navigation')}</span>
                        <span class="rounded bg-white/10 px-2 py-1 font-mono text-slate-300">${escapeHtml(content.props?.presentation || 'horizontal')}</span>
                    </div>
                    <div>${this.renderNavigationItems(content.props?.identifier, content.props?.presentation || 'horizontal', content.props?.language === 'current' ? '' : content.props?.language)}</div>
                </div>
            `;
        }

        return '<div class="rounded-md bg-slate-100 p-3 text-xs text-slate-500 dark:bg-slate-900">Content preview</div>';
    }

    renderContentOptionsHtml(block = this.selectedBlock()) {
        const types = this.compatibleContentTypes(block);
        const region = this.blockRegion(block);

        if (types.length === 0) {
            return `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    No content blocks are available for the ${escapeHtml(region)} region.
                </div>
            `;
        }

        return types.map((type) => {
            const preview = this.compactContentPreview(type);
            return `
            <button class="rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:bg-[#151d27] focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]/40" style="display:grid;min-height:150px;align-content:space-between;gap:10px;" type="button" data-content-type="${escapeHtml(type.type)}">
                <span style="display:grid;gap:8px;">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">${escapeHtml(type.label)}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(type.description)}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">Select</span>
                    </span>
                    <span class="overflow-hidden rounded-md border border-slate-800 bg-slate-950/45 p-2" style="display:grid;height:48px;align-content:center;">
                        ${preview}
                    </span>
                </span>
                <span class="block truncate font-mono text-[10px] uppercase tracking-wide text-slate-500">${escapeHtml(type.type)}</span>
            </button>
        `;
        }).join('');
    }

    renderCustomSlotContentOptionsHtml() {
        if (this.contentTypes.length === 0) {
            return `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    No content blocks are available.
                </div>
            `;
        }

        return this.contentTypes.map((type) => {
            const preview = this.compactContentPreview(type);
            const region = this.defaultRegionForContentType(type);
            return `
            <button class="rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:bg-[#151d27] focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]/40" style="display:grid;min-height:150px;align-content:space-between;gap:10px;" type="button" data-custom-slot-content-type="${escapeHtml(type.type)}">
                <span style="display:grid;gap:8px;">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">${escapeHtml(type.label)}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(type.description)}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">${escapeHtml(region)}</span>
                    </span>
                    <span class="overflow-hidden rounded-md border border-slate-800 bg-slate-950/45 p-2" style="display:grid;height:48px;align-content:center;">
                        ${preview}
                    </span>
                </span>
                <span class="block truncate font-mono text-[10px] uppercase tracking-wide text-slate-500">${escapeHtml(type.type)}</span>
            </button>
        `;
        }).join('');
    }

    renderLayoutChildContentOptionsHtml(parentPath = '') {
        const parent = this.contentAtPath(parentPath);
        if (!this.isLayoutContent(parent)) {
            return `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    Choose a layout block before adding children.
                </div>
            `;
        }

        return this.contentTypes.map((type) => {
            const preview = this.compactContentPreview(type);
            const isLayout = layoutBlockTypes.includes(type.type);
            return `
            <button class="rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:bg-[#151d27] focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]/40" style="display:grid;min-height:150px;align-content:space-between;gap:10px;" type="button" data-layout-child-content-type="${escapeHtml(type.type)}">
                <span style="display:grid;gap:8px;">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">${escapeHtml(type.label)}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(type.description)}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">${escapeHtml(isLayout ? 'layout' : 'content')}</span>
                    </span>
                    <span class="overflow-hidden rounded-md border border-slate-800 bg-slate-950/45 p-2" style="display:grid;height:48px;align-content:center;">
                        ${preview}
                    </span>
                </span>
                <span class="block truncate font-mono text-[10px] uppercase tracking-wide text-slate-500">${escapeHtml(type.type)}</span>
            </button>
        `;
        }).join('');
    }

    compactContentPreview(type) {
        const contentType = String(type?.type || '');
        const props = type?.defaults || {};

        if (contentType.includes('article')) {
            return `
                <span class="grid gap-1.5">
                    <span class="h-2.5 w-8/12 rounded bg-slate-500"></span>
                    <span class="h-2 w-full rounded bg-slate-700"></span>
                    <span class="h-2 w-10/12 rounded bg-slate-800"></span>
                </span>
            `;
        }

        if (['image', 'media', 'hero'].includes(contentType)) {
            return `
                <span class="grid h-full grid-cols-[minmax(0,1fr)_42px] items-center gap-2">
                    <span class="grid gap-1.5">
                        <span class="h-2.5 w-9/12 rounded bg-slate-500"></span>
                        <span class="h-2 w-full rounded bg-slate-700"></span>
                    </span>
                    <span class="h-10 rounded border border-dashed border-slate-700 bg-slate-900"></span>
                </span>
            `;
        }

        if (contentType === 'chart' || contentType === 'stats') {
            return `
                <span class="flex h-full items-end gap-1.5">
                    <span class="h-5 flex-1 rounded-t bg-sky-500/70"></span>
                    <span class="h-8 flex-1 rounded-t bg-emerald-500/70"></span>
                    <span class="h-3 flex-1 rounded-t bg-amber-500/70"></span>
                    <span class="h-10 flex-1 rounded-t bg-violet-500/70"></span>
                </span>
            `;
        }

        if (contentType === 'navigation' || contentType === 'header' || contentType === 'footer') {
            return `
                <span class="flex items-center gap-1.5">
                    <span class="h-6 w-6 rounded bg-[var(--admin-page-accent)]"></span>
                    <span class="h-2 flex-1 rounded bg-slate-700"></span>
                    <span class="h-2 w-8 rounded bg-slate-800"></span>
                </span>
            `;
        }

        return `
            <span class="grid gap-1.5">
                <span class="h-2.5 w-7/12 rounded bg-slate-500"></span>
                <span class="h-2 w-full rounded bg-slate-700"></span>
                <span class="h-2 w-9/12 rounded bg-slate-800"></span>
            </span>
        `;
    }

    renderSettings() {
        const content = this.selectedContent();
        const block = this.selectedBlock();

        if (!content || !block) {
            this.contentSettings.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Choose a filled block to edit its content settings.</div>';
            return;
        }

        const definition = this.contentTypeDefinition(content.type);
        const fields = Array.isArray(definition?.fields) && definition.fields.length > 0
            ? definition.fields
            : Object.keys(content.props || {}).map((key) => ({name: key, label: key}));
        const visibleFields = fields.filter((field) => field.name !== 'children');
        const isNested = this.selectedNestedPath !== '';

        this.contentSettings.innerHTML = `
            <div class="mb-4 rounded-lg bg-slate-100 p-3 dark:bg-slate-900">
                ${isNested ? `<button class="mb-2 inline-flex min-h-8 items-center rounded-md border border-slate-700 px-2.5 text-xs font-semibold text-slate-300 transition hover:border-[var(--admin-page-accent)]" type="button" data-back-to-layout-block>${escapeHtml(this.labels.backToLayoutBlock)}</button>` : ''}
                <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(isNested ? this.labels.editChildBlock : block.label)}</p>
                <h3 class="m-0 text-sm font-semibold">${escapeHtml(content.label)}</h3>
            </div>
            <div class="grid gap-3">
                ${this.renderLayoutTypeSwitcher(content)}
                ${visibleFields.map((field) => this.renderField(field.name, content.props?.[field.name] ?? '', content.type, field)).join('')}
                ${this.isLayoutContent(content) ? this.renderLayoutChildrenEditor(this.selectedNestedPath || '') : ''}
            </div>
        `;
        initStyledSelects(this.contentSettings.querySelectorAll('select[data-styled-select]'));
    }

    renderLayoutChildrenEditor(parentPath = '') {
        const children = this.childrenAtPath(parentPath);
        const rows = children.map((child, index) => {
            const path = this.pathForChild(parentPath, index);
            const label = child?.label || child?.previewTitle || child?.type || 'Block';
            const type = child?.type || '';
            const containerStyleOptions = optionOptions(layoutChildContainerStyles, this.layoutChildContainerStyle(child), this.labels);
            return `
                <div class="grid gap-2 rounded-md border border-slate-700 bg-[#0b1017] p-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <strong class="block truncate text-sm text-slate-100">${escapeHtml(label)}</strong>
                            <span class="block truncate font-mono text-[11px] uppercase text-slate-500">${escapeHtml(type)}</span>
                        </div>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 text-xs text-slate-400 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-move-layout-child="${escapeHtml(path)}" data-direction="-1" aria-label="Move up">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m18 15-6-6-6 6"></path>
                            </svg>
                        </button>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 text-xs text-slate-400 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-move-layout-child="${escapeHtml(path)}" data-direction="1" aria-label="Move down">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 text-xs text-[var(--admin-page-accent)] transition hover:border-[var(--admin-page-accent)]" type="button" data-edit-layout-child="${escapeHtml(path)}" aria-label="${escapeHtml(this.labels.editChildBlock)}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                            </svg>
                        </button>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-rose-900/70 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50" type="button" data-delete-layout-child="${escapeHtml(path)}" aria-label="${escapeHtml(this.labels.removeChildBlock)}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"></path>
                                <path d="M8 6V4h8v2"></path>
                                <path d="M19 6l-1 14H6L5 6"></path>
                                <path d="M10 11v5"></path>
                                <path d="M14 11v5"></path>
                            </svg>
                        </button>
                    </div>
                    <label class="grid gap-1.5">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.container)}</span>
                        <select class="min-h-8 w-full rounded-md border border-slate-700 bg-[#0b1017] px-2.5 py-1.5 text-[13px] text-slate-100 outline-none focus:border-[var(--admin-page-accent)]" data-styled-select data-styled-select-density="compact" data-layout-child-container-style="${escapeHtml(path)}">
                            ${containerStyleOptions}
                        </select>
                    </label>
                    <div class="${this.layoutChildContainerClass(child, true)}">
                        ${this.contentMiniPreview(child)}
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="mt-2 grid gap-3 rounded-lg border border-slate-700 bg-slate-950/45 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.nestedBlocks)}</p>
                        <p class="m-0 mt-0.5 text-xs text-slate-500">${escapeHtml(children.length)} blocks</p>
                    </div>
                    <button class="inline-flex min-h-9 items-center justify-center rounded-md bg-[var(--admin-page-accent)] px-3 text-xs font-bold text-white transition hover:brightness-110" type="button" data-add-layout-child>
                        ${escapeHtml(this.labels.addChildBlock)}
                    </button>
                </div>
                ${rows || '<div class="rounded-md border border-dashed border-slate-700 p-4 text-center text-xs text-slate-500">No child blocks yet.</div>'}
            </div>
        `;
    }

    renderField(key, value, contentType = '', field = null) {
        const label = (key === 'surface' || (key === 'tone' && contentType === 'section'))
            ? this.labels.surface
            : (field?.label || key);
        if (field?.type === 'metrics') {
            return this.renderChartMetricsField(label);
        }

        if (key === 'tone' && contentType === 'section') {
            return this.renderSelect(key, value, customLayoutSurfaces, label);
        }
        if (key === 'surface') {
            return this.renderSelect(key, value, customLayoutSurfaces, label);
        }

        if (field?.type === 'select') {
            return this.renderSelect(key, value, field.options || [], label);
        }

        if (field?.type === 'textarea') {
            if (this.isTranslatableField(key, value, field)) {
                return this.renderTranslatableInput(key, value, label, true);
            }

            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        if (field?.type === 'media') {
            return this.renderMediaField(key, value, label, field);
        }

        if (field?.type === 'color') {
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <span class="grid grid-cols-[42px_minmax(0,1fr)] gap-2">
                        <input class="h-10 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1" type="color" value="${escapeHtml(this.normalizeColor(value) || '#3f7fbd')}" data-prop-name="${escapeHtml(key)}">
                        <input class="${inputClass}" type="text" value="${escapeHtml(this.normalizeColor(value) || '#3f7fbd')}" data-prop-name="${escapeHtml(key)}">
                    </span>
                </label>
            `;
        }

        if (key === 'level') {
            return this.renderSelect(key, value, ['h1', 'h2', 'h3'], label);
        }
        if (key === 'align') {
            return this.renderSelect(key, value, ['left', 'center', 'right'], label);
        }
        if (key === 'tone') {
            return this.renderSelect(key, value, ['body', 'lead', 'muted'], label);
        }
        if (key === 'presentation') {
            return this.renderSelect(key, value, contentType === 'navigation'
                ? ['horizontal', 'vertical', 'compact']
                : ['list', 'cards', 'compact'], label);
        }
        if (key === 'source' && contentType === 'articles') {
            return this.renderSelect(key, value, ['latest', 'featured', 'category', 'related'], label);
        }
        if (key === 'language') {
            return this.renderSelect(key, value, ['current', 'de', 'en', 'fr', 'es', 'it'], label);
        }

        if (field?.type === 'checkbox' || typeof value === 'boolean') {
            return `
                <label class="flex items-center gap-2 rounded-md border border-slate-700 bg-[#0b1017] px-3 py-2">
                    <input class="h-4 w-4 rounded border-slate-600 bg-slate-950 accent-[var(--admin-page-accent)]" type="checkbox" ${value ? 'checked' : ''} data-prop-name="${escapeHtml(key)}">
                    <span class="text-xs font-semibold text-slate-400">${escapeHtml(label)}</span>
                </label>
            `;
        }

        if (key === 'text') {
            if (this.isTranslatableField(key, value, field)) {
                return this.renderTranslatableInput(key, value, label, true);
            }

            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        if (this.isTranslatableField(key, value, field)) {
            return this.renderTranslatableInput(key, value, label, false);
        }

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <input class="${inputClass}" type="${field?.type === 'number' || typeof value === 'number' ? 'number' : 'text'}" value="${escapeHtml(value)}" ${field?.min !== undefined ? `min="${escapeHtml(field.min)}"` : ''} ${field?.max !== undefined ? `max="${escapeHtml(field.max)}"` : ''} ${field?.step !== undefined ? `step="${escapeHtml(field.step)}"` : ''} data-prop-name="${escapeHtml(key)}">
            </label>
        `;
    }

    renderChartMetricsField(label = this.labels.metrics) {
        const metrics = this.chartMetrics();
        const canRemove = metrics.length > 1;
        const rows = metrics.map((metric, index) => `
            <div class="grid gap-2 rounded-md border border-slate-700 bg-slate-950/35 p-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricFallback.replace(':number', String(index + 1)))}</span>
                    <button class="grid h-7 w-7 place-items-center rounded-md border border-rose-900/70 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50 disabled:cursor-not-allowed disabled:opacity-40" type="button" data-chart-metric-remove="${index}" ${canRemove ? '' : 'disabled'} aria-label="${escapeHtml(this.labels.removeMetric)}">
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                </div>
                <label class="grid gap-1">
                    <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricLabel)}</span>
                    <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="text" value="${escapeHtml(metric.label)}" data-chart-metric-index="${index}" data-chart-metric-field="label">
                </label>
                <div class="grid grid-cols-[minmax(0,1fr)_42px] gap-2">
                    <label class="grid gap-1">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricValue)}</span>
                        <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="number" step="0.1" value="${escapeHtml(metric.value)}" data-chart-metric-index="${index}" data-chart-metric-field="value">
                    </label>
                    <label class="grid gap-1">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricColor)}</span>
                        <input class="h-8 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1" type="color" value="${escapeHtml(metric.color)}" data-chart-metric-index="${index}" data-chart-metric-field="color">
                    </label>
                </div>
            </div>
        `).join('');

        return `
            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label || this.labels.metrics)}</span>
                    <button class="inline-flex min-h-8 items-center rounded-md border border-slate-700 px-2.5 text-xs font-semibold text-slate-300 transition hover:border-[var(--admin-page-accent)] disabled:cursor-not-allowed disabled:opacity-50" type="button" data-chart-metric-add ${metrics.length >= chartMaxMetrics ? 'disabled' : ''}>
                        ${escapeHtml(this.labels.addMetric)}
                    </button>
                </div>
                <div class="grid gap-2">
                    ${rows}
                </div>
            </div>
        `;
    }

    renderMediaField(key, value, label, field = null) {
        const url = String(value || '');
        const hasMedia = url !== '';
        const altTarget = String(field?.altTarget || '');
        const preview = hasMedia
            ? `<img class="h-full w-full object-cover" src="${escapeHtml(url)}" alt="">`
            : `<span class="text-xs font-semibold text-slate-500">${escapeHtml(this.labels.mediaEmptyUrl)}</span>`;

        return `
            <div class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <div class="grid gap-2 rounded-md border border-slate-700 bg-[#0b1017] p-2">
                    <div class="grid h-32 place-items-center overflow-hidden rounded border border-slate-800 bg-slate-950/60">${preview}</div>
                    <input type="hidden" value="${escapeHtml(url)}" data-prop-name="${escapeHtml(key)}">
                    <div class="flex items-center gap-2">
                        <input class="${inputClass} min-w-0 flex-1 text-xs" type="text" value="${escapeHtml(url)}" readonly>
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-700 px-3 text-xs font-semibold text-slate-200 transition hover:border-[var(--admin-page-accent)]" type="button" data-media-field="${escapeHtml(key)}" data-media-alt-target="${escapeHtml(altTarget)}">${escapeHtml(hasMedia ? this.labels.mediaReplace : this.labels.mediaSelect)}</button>
                        ${hasMedia ? `<button class="inline-flex min-h-9 items-center justify-center rounded-md border border-rose-400/30 px-3 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10" type="button" data-media-clear="${escapeHtml(key)}">${escapeHtml(this.labels.mediaClear)}</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    renderSelect(key, value, options, label = key) {
        const normalizedOptions = options.map((option) => {
            if (typeof option === 'object' && option !== null) {
                return {
                    label: optionLabel(option, this.labels),
                    value: option.value ?? option.label ?? '',
                };
            }

            return {label: option, value: option};
        });

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <select class="${inputClass}" data-styled-select data-prop-name="${escapeHtml(key)}">
                    ${normalizedOptions.map((option) => `<option value="${escapeHtml(option.value)}" ${option.value === value ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}
                </select>
            </label>
        `;
    }

    renderTranslatableInput(key, value, label, multiline = false) {
        const escapedKey = escapeHtml(key);
        const escapedValue = escapeHtml(value);
        const title = escapeHtml(this.labels.translationKey);

        if (multiline) {
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <span class="relative block">
                        <textarea class="${inputClass} min-h-24 resize-y pr-16" data-prop-name="${escapedKey}">${escapedValue}</textarea>
                        <button class="absolute right-px top-px inline-flex h-10 w-14 items-center justify-center rounded-bl-md rounded-tr-md border-b border-l border-slate-700 bg-slate-900/80 text-slate-500 transition hover:bg-slate-800 hover:text-slate-300" type="button" data-translate-prop="${escapedKey}" title="${title}" aria-label="${title}">
                            ${languageIcon}
                        </button>
                    </span>
                </label>
            `;
        }

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <span class="flex min-h-9 overflow-hidden rounded-md border border-slate-700 bg-[#0b1017] focus-within:border-[var(--admin-page-accent)]">
                    <input class="min-h-9 min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-slate-100 outline-none" type="text" value="${escapedValue}" data-prop-name="${escapedKey}">
                    <button class="inline-flex min-h-9 w-14 shrink-0 items-center justify-center border-l border-slate-700 bg-slate-900/70 px-2 text-slate-500 transition hover:bg-slate-800 hover:text-slate-300" type="button" data-translate-prop="${escapedKey}" title="${title}" aria-label="${title}">
                        ${languageIcon}
                    </button>
                </span>
            </label>
        `;
    }

    isTranslatableField(key, value, field = null) {
        if (field?.translatable === false) {
            return false;
        }
        if (field?.translatable === true) {
            return true;
        }
        if (field?.type && !['text', 'textarea'].includes(field.type)) {
            return false;
        }
        if (typeof value === 'boolean' || typeof value === 'number') {
            return false;
        }

        const normalized = String(key || '').toLowerCase();
        const excluded = [
            'url',
            'href',
            'path',
            'image',
            'media',
            'color',
            'identifier',
            'language',
            'presentation',
            'align',
            'level',
            'tone',
            'limit',
            'count',
            'width',
            'height',
            'category',
            'slug',
            'query',
            'embed',
        ];

        return !excluded.some((part) => normalized.includes(part));
    }

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
    }

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
    }

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
    }

    translationLanguageOptions(selectedLanguage = this.translationLanguage()) {
        const languages = [...this.languages];
        if (!languages.includes(selectedLanguage) && selectedLanguage !== '') {
            languages.unshift(selectedLanguage);
        }

        return languages.map((language) => `
            <option value="${escapeHtml(language)}" ${language === selectedLanguage ? 'selected' : ''}>${escapeHtml(this.labelForLanguage(language))}</option>
        `).join('');
    }

    bindTranslationPrompt(modal) {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        const languageSelect = modal.querySelector('[data-page-translation-language]');
        const keyInput = modal.querySelector('[data-page-translation-key]');
        const valueInput = modal.querySelector('[data-page-translation-value]');
        initStyledSelects(modal.querySelectorAll('select[data-styled-select]'));
        keyInput?.focus({preventScroll: true});

        languageSelect?.addEventListener('change', () => {
            const existing = this.findTranslationSuggestion(languageSelect.value, keyInput?.value);
            if (existing && valueInput instanceof HTMLInputElement && valueInput.value.trim() === '') {
                valueInput.value = existing.value;
            }
            this.showTranslationSuggestions(this.activeTranslationSuggestInput || keyInput);
        });
        keyInput?.addEventListener('focus', () => this.showTranslationSuggestions(keyInput));
        keyInput?.addEventListener('input', () => {
            const existing = this.findTranslationSuggestion(languageSelect?.value || this.translationLanguage(), keyInput.value);
            if (existing && valueInput instanceof HTMLInputElement && valueInput.value.trim() === '') {
                valueInput.value = existing.value;
            }
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
    }

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
    }

    languageCreationOptions(selectedLanguage = '') {
        return [
            `<option value="" ${selectedLanguage === '' ? 'selected' : ''} disabled>${escapeHtml(this.labels.language)}</option>`,
            ...Object.entries(this.languageOptions).map(([language, label]) => `
                <option value="${escapeHtml(language)}" ${language === selectedLanguage ? 'selected' : ''}>${escapeHtml(label)} (${escapeHtml(language)})</option>
            `),
        ].join('');
    }

    async createLanguage(language) {
        language = this.normalizeLanguageCode(language);
        if (language === null) {
            toast(this.labels.invalidLanguage);
            return false;
        }

        const response = await (window.NixCMS?.json || fetch)(this.translationEndpointFor(language), {
            method: 'POST',
            body: JSON.stringify({entries: {}}),
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
    }

    async saveTranslation(language, key, value) {
        const entries = Object.fromEntries(this.currentTranslationSuggestions(language).map((suggestion) => [suggestion.key, suggestion.value]));
        entries[key] = value;
        const response = await (window.NixCMS?.json || fetch)(this.translationEndpointFor(language), {
            method: 'POST',
            body: JSON.stringify({entries}),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            toast(result.reason || this.labels.couldNotSave);
            return false;
        }

        this.setTranslationSuggestion(language, key, value);
        return true;
    }

    translationEndpointFor(language) {
        return String(this.translationEndpoint || '/api/admin/translations/{language}')
            .replace('{language}', encodeURIComponent(language));
    }

    normalizeLanguageCode(value) {
        const language = String(value || '').trim().toLowerCase();
        return /^[a-z]{2,3}(?:-[a-z0-9]{2,4})?$/.test(language) && language.length <= 8 ? language : null;
    }

    labelForLanguage(language) {
        return String(language || '').toUpperCase();
    }

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
    }

    async openMediaPicker(propName, altTarget = '') {
        const content = this.selectedContent();
        if (!propName || !content) {
            return;
        }

        let selected = null;
        const currentUrl = String(content.props?.[propName] || '');
        const mediaItems = await this.fetchMediaItems();
        const visibilityOptions = this.media.visibilityOptions || {
            public: this.labels.public,
            registered: this.labels.registered,
            private: this.labels.private,
        };

        const promptResult = prompt(`
            <div class="media-picker-modal notify-form">
                <div>
                    <div>${escapeHtml(this.labels.mediaPickerTitle)}</div>
                    <p class="notify-form-intro">${escapeHtml(this.labels.mediaPickerIntro)}</p>
                </div>
                <div class="notify-form-grid">
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.visibility)}</span>
                        <select data-page-media-visibility>
                            ${Object.entries(visibilityOptions).map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('')}
                        </select>
                    </label>
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.altText)}</span>
                        <input type="text" value="${escapeHtml(String(content.props?.[altTarget] || ''))}" data-page-media-alt>
                    </label>
                </div>
                <div class="media-picker-upload ${this.media.canUpload ? '' : 'hidden'}">
                    <input class="hidden" type="file" accept="${escapeHtml(this.mediaAcceptAttribute())}" data-page-media-file>
                    <button class="media-picker-drop" type="button" data-page-media-upload>
                        <span class="media-picker-icon" aria-hidden="true">↑</span>
                        <span>${escapeHtml(this.labels.mediaChooseFile)}</span>
                    </button>
                </div>
                <div class="media-picker-selected" data-page-media-selected>${currentUrl ? escapeHtml(currentUrl) : escapeHtml(this.labels.mediaEmptyUrl)}</div>
                <div>
                    <div class="notify-section-head"><span>${escapeHtml(this.labels.mediaLibrary)}</span><span>${mediaItems.length}</span></div>
                    <div class="media-picker-grid" data-page-media-library>
                        ${this.renderMediaPickerItems(mediaItems, currentUrl)}
                    </div>
                </div>
            </div>
        `, {
            confirmButtonText: this.labels.mediaUse,
            cancelButtonText: this.labels.cancel,
            showCancelButton: true,
            style: {width: 'min(920px, calc(100vw - 32px))', maxWidth: 'min(920px, calc(100vw - 32px))'},
            confirmCallback: (promptInstance) => {
                const container = promptInstance.container;
                const url = String(selected?.public_url || currentUrl || '').trim();
                if (url === '') {
                    toast(this.labels.mediaNoSelection);
                    return true;
                }

                this.setBlockProp(propName, url, {renderSettings: true});
                if (altTarget !== '') {
                    const alt = String(container.querySelector('[data-page-media-alt]')?.value || '').trim();
                    if (alt !== '') {
                        this.setBlockProp(altTarget, alt, {renderSettings: true});
                    }
                }

                promptInstance.promise.resolve(true);
                promptInstance.remove();
                return true;
            },
        });

        window.setTimeout(() => {
            const modal = document.querySelector('.media-picker-modal');
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            const notify = modal.closest('.notify');
            const selectedLabel = modal.querySelector('[data-page-media-selected]');
            const setSelected = (media) => {
                selected = media;
                if (selectedLabel) {
                    selectedLabel.textContent = String(media?.public_url || this.labels.mediaEmptyUrl);
                }
                modal.querySelectorAll('[data-page-media-choice]').forEach((item) => {
                    item.classList.toggle('is-selected', String(item.getAttribute('data-page-media-choice') || '') === String(media?.public_url || ''));
                });
                const altInput = modal.querySelector('[data-page-media-alt]');
                if (altInput instanceof HTMLInputElement && String(altInput.value || '') === '') {
                    altInput.value = String(media?.alt_text || '');
                }
            };

            const bindChoices = () => {
                modal.querySelectorAll('[data-page-media-choice]').forEach((item) => {
                    item.addEventListener('click', () => {
                        setSelected({
                            public_url: String(item.getAttribute('data-page-media-choice') || ''),
                            alt_text: String(item.getAttribute('data-page-media-alt-value') || ''),
                        });
                    });
                });
            };

            const fileInput = modal.querySelector('[data-page-media-file]');
            const uploadButton = modal.querySelector('[data-page-media-upload]');
            uploadButton?.addEventListener('click', () => {
                if (fileInput instanceof HTMLInputElement) {
                    fileInput.click();
                }
            });

            fileInput?.addEventListener('change', async () => {
                if (!(fileInput instanceof HTMLInputElement) || !fileInput.files?.[0]) {
                    return;
                }

                const file = fileInput.files[0];
                if (!this.validateMediaUpload(file)) {
                    fileInput.value = '';
                    return;
                }

                uploadButton?.setAttribute('disabled', 'disabled');
                try {
                    const formData = new FormData();
                    formData.append('file', file, file.name);
                    formData.append('visibility', String(modal.querySelector('[data-page-media-visibility]')?.value || 'public'));
                    formData.append('alt_text', String(modal.querySelector('[data-page-media-alt]')?.value || ''));
                    formData.append('collection', 'pages');

                    const response = await (window.NixCMS?.fetch || fetch)(String(this.media.urls?.upload || ''), {
                        method: 'POST',
                        headers: {'Accept': 'application/json'},
                        body: formData,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || data?.success === false || !data?.media) {
                        toast(data?.reason || this.labels.mediaUploadFailed);
                        return;
                    }

                    setSelected(data.media);
                    const library = modal.querySelector('[data-page-media-library]');
                    if (library) {
                        mediaItems.unshift(data.media);
                        library.innerHTML = this.renderMediaPickerItems(mediaItems, String(data.media.public_url || ''));
                        bindChoices();
                    }
                } catch (error) {
                    toast(this.labels.mediaUploadFailed);
                } finally {
                    uploadButton?.removeAttribute('disabled');
                    fileInput.value = '';
                }
            });

            bindChoices();
            if (currentUrl !== '') {
                setSelected({public_url: currentUrl, alt_text: ''});
            }
            if (notify instanceof HTMLElement) {
                notify.classList.add('media-picker-notify');
            }
        }, 0);

        return await promptResult;
    }

    validateMediaUpload(file) {
        const maxUploadSize = Number(this.media.maxUploadSize || 0);
        if (maxUploadSize > 0 && file.size > maxUploadSize) {
            toast(this.labels.mediaFileTooLarge);
            return false;
        }

        const extension = String(file.name || '').split('.').pop().toLowerCase();
        const allowed = Array.isArray(this.media.acceptedExtensions) ? this.media.acceptedExtensions : [];
        const imageAllowed = allowed.filter((item) => ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(item).toLowerCase()));
        if (imageAllowed.length > 0 && !imageAllowed.includes(extension)) {
            toast(this.labels.mediaInvalidExtension);
            return false;
        }

        return true;
    }

    renderMediaPickerItems(items, currentUrl = '') {
        if (!Array.isArray(items) || items.length === 0) {
            return `<div class="media-picker-empty">${escapeHtml(this.labels.mediaNoFiles)}</div>`;
        }

        return items
            .filter((item) => item.kind === 'image')
            .map((item) => {
                const url = String(item.public_url || '');
                const title = String(item.original_name || item.stored_name || url);
                const active = url !== '' && url === currentUrl;
                return `
                    <button class="media-picker-item ${active ? 'is-selected' : ''}" type="button" data-page-media-choice="${escapeHtml(url)}" data-page-media-alt-value="${escapeHtml(item.alt_text || '')}">
                        <span class="media-picker-thumb">${url ? `<img src="${escapeHtml(url)}" alt="">` : ''}</span>
                        <span class="media-picker-name">${escapeHtml(title)}</span>
                    </button>
                `;
            })
            .join('') || `<div class="media-picker-empty">${escapeHtml(this.labels.mediaNoFiles)}</div>`;
    }

    async fetchMediaItems() {
        const url = String(this.media.urls?.list || '');
        if (url === '') {
            return [];
        }

        try {
            const response = await (window.NixCMS?.fetch || fetch)(url);
            const data = await response.json();
            return Array.isArray(data?.rows) ? data.rows : [];
        } catch (error) {
            return [];
        }
    }

    mediaAcceptAttribute() {
        const extensions = Array.isArray(this.media.acceptedExtensions) ? this.media.acceptedExtensions : [];
        const imageExtensions = extensions.filter((extension) => ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(extension).toLowerCase()));
        return imageExtensions.length > 0 ? imageExtensions.map((extension) => `.${extension}`).join(',') : 'image/*';
    }

    renderPagePreview() {
        const template = this.selectedTemplate();
        if (this.pagePreview) {
            this.pagePreview.innerHTML = this.blockLayout(template, false).replaceAll('Empty content block', 'Unfilled block');
        }
    }

    renderJson() {
        const template = this.selectedTemplate();
        const blocks = {};
        for (const block of template?.blocks || []) {
            blocks[block.id] = this.blockContent[block.id] || null;
        }

        if (this.json) {
            this.json.textContent = JSON.stringify({
                template: template ? {
                    id: template.id,
                    view: template.view,
                    kind: template.kind || 'static',
                    contentType: template.contentType || null,
                    routePattern: template.routePattern || null,
                    contentWidth: this.contentWidthKey(),
                    contentWidthIncludesChrome: this.contentWidthIncludesChrome(),
                    contentWidthIncludesChromeBackground: this.contentWidthIncludesChromeBackground(),
                } : null,
                blocks,
                meta: {
                    custom_layout: this.isCustomTemplate(template) || this.customLayout.length > 0
                        ? this.normalizeCustomLayout(this.customLayout)
                        : [],
                },
            }, null, 2);
        }
    }

    renderMeta() {
        const template = this.selectedTemplate();
        if (this.metaTemplate) {
            this.metaTemplate.value = template ? `${template.id} · ${template.view || ''}` : '';
        }

        const payload = this.pagePayload();
        if (this.metaSummary) {
            const rows = [
                ['Title', payload.title || 'Untitled page'],
                ['Path', payload.path || '/'],
                ['Language', String(payload.language || '').toUpperCase()],
                ['Status', payload.status || 'draft'],
                ['Accent', payload.meta?.theme?.accent || '#3f7fbd'],
                [this.labels.width, this.contentWidthDisplay()],
                [this.labels.chromeContentWidth, this.contentWidthIncludesChrome() ? this.labels.bounded : this.labels.contentWidthFull],
                [this.labels.chromeBackgroundWidth, this.contentWidthIncludesChromeBackground() ? this.labels.bounded : this.labels.contentWidthFull],
                ['Template', payload.templateId || ''],
                ...(this.isCustomTemplate(template) || this.customLayout.length > 0 ? [[this.labels.customSlots, String(this.customLayout.length)]] : []),
            ];
            this.metaSummary.innerHTML = rows.map(([label, value]) => `
                <div class="grid grid-cols-[92px_minmax(0,1fr)] gap-3">
                    <dt class="text-xs font-semibold text-slate-500">${escapeHtml(label)}</dt>
                    <dd class="min-w-0 truncate text-slate-200">${escapeHtml(value)}</dd>
                </div>
            `).join('');
        }

        if (this.metaPreviewTitle) {
            this.metaPreviewTitle.textContent = payload.title || 'Untitled page';
        }
        if (this.metaPreviewPath) {
            this.metaPreviewPath.textContent = `${payload.path || '/'} · ${String(payload.language || '').toUpperCase()}`;
        }
        if (this.metaPreviewDescription) {
            this.metaPreviewDescription.textContent = payload.meta?.seo?.description || 'No description set.';
        }
    }

    pagePayload() {
        const template = this.selectedTemplate();
        const templateBlocks = this.isCustomTemplate(template)
            ? this.normalizeCustomLayout(this.customLayout)
            : (template?.blocks || []);
        const blocks = {};
        for (const block of templateBlocks) {
            blocks[block.id] = this.blockContent[block.id] || null;
        }

        const title = String(this.persistedValues.title || template?.label || 'Untitled page').trim();
        const path = String(this.persistedValues.path || '/').trim();
        const meta = {
            version: 1,
            seo: {
                description: this.persistedValues['seo.description'] || '',
                robots: this.persistedValues['seo.robots'] || 'index,follow',
                canonicalUrl: this.persistedValues['seo.canonicalUrl'] || null,
            },
            theme: {
                accent: this.pageAccent(),
                contentWidth: this.contentWidthKey(),
                contentWidthIncludesChrome: this.contentWidthIncludesChrome(),
                contentWidthIncludesChromeBackground: this.contentWidthIncludesChromeBackground(),
            },
            extra: {
                defaultNavigationIdentifier: this.selectedDefaultNavigationIdentifier(),
            },
        };
        if (this.isCustomTemplate(template) || this.customLayout.length > 0) {
            meta.custom_layout = templateBlocks;
            if (Number(template?.layoutPresetId || 0) > 0) {
                meta.custom_layout_ref = Number(template.layoutPresetId);
                meta.custom_layout_name = template.label || '';
            }
        }

        return {
            id: this.savedId || 0,
            identifier: this.persistedValues.identifier || '',
            title,
            path,
            language: this.persistedValues.language || this.defaultLanguage,
            status: this.persistedValues.status || 'draft',
            templateId: this.storedTemplateId(template),
            blocks,
            meta,
        };
    }

    scheduleRemotePersist() {
        if (this.editMode || !this.saveUrl || this.step === 'template') {
            return;
        }

        window.clearTimeout(this.saveTimer);
        this.saveTimer = window.setTimeout(() => this.remotePersist({force: false}), 800);
    }

    async remotePersist({force = false} = {}) {
        if (!this.saveUrl || this.saving) {
            return;
        }

        const payload = this.pagePayload();
        const explicitPath = String(this.persistedValues.path || '').trim();
        if (!force && (!payload.title || !payload.path || !explicitPath)) {
            return;
        }

        this.saving = true;
        this.saveButton?.setAttribute('disabled', 'disabled');

        try {
            const request = window.NixCMS?.json || fetch;
            const response = await request(this.saveUrl, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success === false) {
                const messages = Object.values(result.errors || {}).flat().filter(Boolean);
                throw new Error(result.reason || messages.join('\n') || this.labels.couldNotSave);
            }

            this.savedId = Number(result.id || this.savedId || 0);
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

            if (force) {
                try {
                    window.localStorage.removeItem(this.storageKey);
                    window.sessionStorage.setItem(flashToastKey, this.labels.saved);
                } catch (error) {}

                window.location.assign(this.overviewUrl);
            }
        } catch (error) {
            if (force) {
                notifyAlert(escapeHtml(error.message || this.labels.couldNotSave));
            }
        } finally {
            this.saving = false;
            this.saveButton?.removeAttribute('disabled');
        }
    }
}

export const initPageBuilders = () => {
    document.querySelectorAll('[data-page-builder]').forEach((root) => {
        new PageBuilder(root).init();
    });
};
