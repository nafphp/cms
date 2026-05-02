import notifyAlert from '../lib/notifyJs/Modules/Alert.js';
import {initStyledSelects} from '../components/styledSelect.js';

const cardClass = 'rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:shadow-sm';
const layoutClass = 'group grid gap-3 rounded-lg border-2 border-slate-800 bg-[#0b1118] p-3 text-left transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]';
const activeLayoutClass = 'border-[var(--admin-page-accent)] bg-[#122033]';
const inputClass = 'min-h-9 w-full rounded-md border border-slate-700 bg-[#0b1017] px-3 py-2 text-sm text-slate-100 outline-none focus:border-[var(--admin-page-accent)]';
const previewFrameClass = 'pointer-events-none block aspect-video w-full rounded-md border-0 bg-slate-950 opacity-0 transition-opacity duration-200 ease-out';

const clone = (value) => JSON.parse(JSON.stringify(value));
const storageKey = 'nixcms:page-builder:draft';
const flashToastKey = 'nixcms:flash-toast';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

class PageBuilder {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config || '{}');
        this.saveUrl = root.dataset.saveUrl || this.config.saveUrl || '/admin/pages';
        this.overviewUrl = root.dataset.overviewUrl || this.config.overviewUrl || '/admin/pages';
        this.labels = {
            saved: 'Page saved.',
            couldNotSave: 'Page could not be saved.',
            ...(this.config.labels || {}),
        };
        this.templates = Array.isArray(this.config.templates) ? this.config.templates : [];
        this.contentTypes = Array.isArray(this.config.contentTypes) ? this.config.contentTypes : [];
        this.navigationChoices = Array.isArray(this.config.navigationChoices) ? this.config.navigationChoices : [];
        this.languages = Array.isArray(this.config.languages) ? this.config.languages : [];
        this.defaultLanguage = this.config.defaultLanguage || this.languages[0] || 'en';
        this.defaultNavigationIdentifier = this.config.defaultNavigationIdentifier || this.navigationChoices[0]?.identifier || 'main';
        this.queryParams = new URLSearchParams(window.location.search);
        this.initialState = this.config.initialState && typeof this.config.initialState === 'object' ? this.config.initialState : {};
        this.editMode = this.config.mode === 'edit' || this.queryParams.get('mode') === 'edit' || this.queryParams.has('template');
        this.step = 'template';
        this.templateId = this.templates[0]?.id || '';
        this.selectedBlockId = null;
        this.blockContent = {};
        this.persistedValues = {};
        this.templatesRendered = false;
        this.previewTemplateId = null;
        this.settingsOpen = false;
        this.storageKey = `${storageKey}:${window.location.pathname}`;
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
        this.contentModal = this.root.querySelector('[data-content-modal]');
        this.contentOptions = this.root.querySelector('[data-content-options]');
        this.settingsDrawer = this.root.querySelector('[data-settings-drawer]');
        this.contentSettings = this.root.querySelector('[data-content-settings]');
        this.json = this.root.querySelector('[data-builder-json]');
        this.saveButton = this.root.querySelector('[data-save-page]');
        this.metaTemplate = this.root.querySelector('[data-meta-template]');
        this.metaSummary = this.root.querySelector('[data-meta-summary]');
        this.metaPreviewTitle = this.root.querySelector('[data-meta-preview-title]');
        this.metaPreviewPath = this.root.querySelector('[data-meta-preview-path]');
        this.metaPreviewDescription = this.root.querySelector('[data-meta-preview-description]');

        this.editMode ? this.applyInitialState() : this.restoreState();
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
        this.ensureTemplateDefaults();
        this.syncPageTheme();

        this.root.addEventListener('click', (event) => this.handleClick(event));
        this.root.addEventListener('input', (event) => this.handleInput(event));
        this.root.addEventListener('change', (event) => this.handleInput(event));
        this.contentModal?.addEventListener('click', (event) => {
            if (event.target === this.contentModal) {
                this.closeContentModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeContentModal();
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

        this.step = template?.layout ? 'layout' : 'template';
        this.selectedBlockId = this.step === 'layout' ? (template?.blocks?.[0]?.id || null) : null;

        if (this.initialState.blockContent && typeof this.initialState.blockContent === 'object' && !Array.isArray(this.initialState.blockContent)) {
            this.blockContent = this.initialState.blockContent;
        }

        if (this.initialState.persistedValues && typeof this.initialState.persistedValues === 'object' && !Array.isArray(this.initialState.persistedValues)) {
            this.persistedValues = this.initialState.persistedValues;
        }
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
            state = JSON.parse(window.localStorage.getItem(this.storageKey) || window.localStorage.getItem(storageKey) || 'null');
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

        if (state.blockContent && typeof state.blockContent === 'object' && !Array.isArray(state.blockContent)) {
            this.blockContent = state.blockContent;
        }

        if (state.persistedValues && typeof state.persistedValues === 'object' && !Array.isArray(state.persistedValues)) {
            this.persistedValues = state.persistedValues;
        }

        const blockExists = (this.selectedTemplate()?.blocks || []).some((block) => block.id === state.selectedBlockId);
        if (blockExists) {
            this.selectedBlockId = state.selectedBlockId;
        } else if (this.step === 'layout') {
            this.selectedBlockId = this.selectedTemplate()?.blocks?.[0]?.id || null;
        }

        this.settingsOpen = Boolean(state.settingsOpen && this.selectedBlockId && this.blockContent[this.selectedBlockId]);
        this.savedId = Number(state.savedId || 0);
    }

    persistState() {
        if (this.editMode) {
            return;
        }

        this.collectPersistedFields();

        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify({
                step: this.step,
                templateId: this.templateId,
                selectedBlockId: this.selectedBlockId,
                blockContent: this.blockContent,
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

    syncPageTheme() {
        this.root.style.setProperty('--page-accent', this.pageAccent());
        this.root.style.setProperty('--admin-page-accent', this.pageAccent());
    }

    selectedTemplate() {
        return this.templates.find((template) => template.id === this.templateId) || this.templates[0] || null;
    }

    selectedBlock() {
        const template = this.selectedTemplate();
        return (template?.blocks || []).find((block) => block.id === this.selectedBlockId) || null;
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
        const type = block.defaultContentType || (block.id === 'site_header'
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
        const templateCard = event.target.closest('[data-template-id]');
        if (templateCard) {
            this.templateId = templateCard.dataset.templateId || this.templateId;
            this.selectedBlockId = null;
            this.blockContent = {};
            this.settingsOpen = false;
            this.ensureTemplateDefaults();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-next-step]')) {
            this.step = 'layout';
            this.selectedBlockId = this.selectedTemplate()?.blocks?.[0]?.id || null;
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
                this.settingsOpen = false;
            }
            this.persistState();
            this.render();
            return;
        }

        const editBlock = event.target.closest('[data-edit-block]');
        if (editBlock) {
            this.selectedBlockId = editBlock.dataset.editBlock || null;
            this.openSettings();
            this.persistState();
            this.render();
            return;
        }

        const chooseBlock = event.target.closest('[data-choose-block]');
        if (chooseBlock) {
            this.selectedBlockId = chooseBlock.dataset.chooseBlock || null;
            this.openContentModal();
            this.persistState();
            this.render();
            return;
        }

        const contentChoice = event.target.closest('[data-content-type]');
        if (contentChoice) {
            const type = contentChoice.dataset.contentType || '';
            this.assignContent(type);
            this.closeContentModal();
            this.openSettings();
            this.persistState();
            this.render();
            return;
        }

        if (event.target.closest('[data-close-content-modal]')) {
            this.closeContentModal();
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

        const input = event.target.closest('[data-prop-name]');
        if (!input || !this.selectedBlockId || !this.blockContent[this.selectedBlockId]) {
            return;
        }

        const name = input.dataset.propName || '';
        this.blockContent[this.selectedBlockId].props[name] = input.type === 'checkbox'
            ? input.checked
            : input.type === 'number'
                ? Number(input.value || 0)
                : input.value;

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
        if (key === 'theme.accent') {
            this.syncPageTheme();
        }
        this.renderMeta();
        this.persistState();
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
        this.contentModal?.classList.remove('hidden');
    }

    closeContentModal() {
        this.contentModal?.classList.add('hidden');
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
        this.renderBlocks();
        this.renderContentOptions();
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
                <div class="flex min-h-16 items-center gap-4 border-b border-[var(--page-border)] bg-[var(--page-header-bg)] px-6 py-4 text-[var(--page-header-text)] ${className}">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--page-accent)] text-sm font-black text-white">N</span>
                    <strong class="text-base font-bold">NixCMS</strong>
                    <div class="ml-auto">${this.renderNavigationItems(this.selectedDefaultNavigationIdentifier(), 'horizontal')}</div>
                </div>
            `;
        }

        if (block.preview === 'footer') {
            return `
                <div class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-t border-[var(--page-border)] bg-[var(--page-footer-bg)] px-6 py-4 ${className}">
                    <span class="text-xs font-medium text-[var(--page-footer-text)]">Powered by NixCMS</span>
                    <div>${this.renderNavigationItems(this.selectedDefaultNavigationIdentifier(), 'compact')}</div>
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

    renderBlocks() {
        const template = this.selectedTemplate();
        const blocks = template?.blocks || [];
        const filled = blocks.filter((block) => this.blockContent[block.id]).length;
        if (this.filledCount) {
            this.filledCount.textContent = `${filled}/${blocks.length} filled`;
        }
        if (this.blockCanvas) {
            this.blockCanvas.innerHTML = this.blockLayout(template, true);
        }
    }

    blockLayout(template, interactive) {
        const blocks = template?.blocks || [];
        if (blocks.length === 0) {
            return '<div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">No blocks defined.</div>';
        }

        const blockById = new Map(blocks.map((block) => [block.id, block]));
        const renderBlock = (id, className = '') => {
            const block = blockById.get(id);
            return block ? this.blockCard(block, interactive, template.id, className) : '';
        };

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
            'group/page-block relative grid content-center overflow-hidden rounded-xl border border-transparent p-6 text-left transition',
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
        const items = this.navigationPreviewItems(identifier, language);
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

    contentMiniPreview(content) {
        if (content.type === 'header') {
            return `
                <div class="flex min-h-14 items-center gap-4 rounded-lg border border-[var(--page-border)] bg-[var(--page-header-bg)] px-4 text-[var(--page-header-text)]">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-[var(--page-accent)] text-xs font-black text-white">${escapeHtml((content.props?.logoText || 'N').slice(0, 1))}</span>
                    <strong class="text-sm font-bold">${escapeHtml(content.props?.logoText || 'NixCMS')}</strong>
                    <div class="ml-auto">${this.renderNavigationItems(content.props?.navigationIdentifier, content.props?.presentation || 'horizontal')}</div>
                </div>
            `;
        }

        if (content.type === 'footer') {
            return `
                <div class="flex min-h-14 flex-wrap items-center justify-between gap-3 rounded-lg border border-[var(--page-border)] bg-[var(--page-footer-bg)] px-4 py-3">
                    <span class="text-xs text-[var(--page-footer-text)]">${escapeHtml(content.props?.text || 'Powered by NixCMS')}</span>
                    ${this.renderNavigationItems(content.props?.navigationIdentifier, content.props?.presentation || 'compact')}
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
            return `
                <div class="grid gap-3 rounded-lg bg-[var(--page-card-bg)] p-5 md:grid-cols-[minmax(0,1fr)_160px]">
                    <div class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase text-[var(--page-accent)]">${escapeHtml(content.props?.eyebrow || 'Hero')}</span>
                        <strong class="text-2xl font-black leading-tight text-[var(--page-text)]">${escapeHtml(content.props?.title || 'Hero title')}</strong>
                        <span class="text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || '')}</span>
                    </div>
                    <span class="hidden rounded-md border border-dashed border-[var(--page-border)] md:block"></span>
                </div>
            `;
        }

        if (['cta', 'feature', 'quote', 'stats', 'media'].includes(content.type)) {
            return `
                <div class="grid gap-2 rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-4">
                    <strong class="text-base font-bold text-[var(--page-text)]">${escapeHtml(content.props?.title || content.props?.quote || content.previewTitle || content.label)}</strong>
                    <span class="text-sm leading-relaxed text-[var(--page-muted)]">${escapeHtml(content.props?.text || content.props?.caption || content.props?.source || '')}</span>
                </div>
            `;
        }

        if (content.type === 'form' || content.type === 'contact-form') {
            return `
                <div class="grid gap-3 rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] p-4">
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

        if (content.type === 'article-header') {
            return `
                <div class="grid gap-2 rounded-md bg-slate-950/60 p-4">
                    <div class="flex flex-wrap gap-1.5 text-[10px] font-bold uppercase text-slate-500">
                        <span class="rounded bg-white/10 px-1.5 py-1">Current article</span>
                        ${content.props?.showMeta ? '<span class="rounded bg-white/10 px-1.5 py-1">Published</span>' : ''}
                    </div>
                    <strong class="text-lg font-extrabold leading-tight text-slate-50">Resolved article title</strong>
                    ${content.props?.showTeaser ? '<span class="text-xs leading-relaxed text-slate-400">Article teaser from the resolved version.</span>' : ''}
                </div>
            `;
        }

        if (content.type === 'article-body') {
            return `
                <div class="grid gap-2 rounded-md bg-slate-950/45 p-4">
                    <span class="text-xs font-semibold text-slate-400">Editor.js body from current article</span>
                    <span class="h-2.5 rounded bg-slate-700"></span>
                    <span class="h-2.5 w-10/12 rounded bg-slate-700"></span>
                    <span class="h-2.5 w-8/12 rounded bg-slate-800"></span>
                </div>
            `;
        }

        if (content.type === 'navigation') {
            return `
                <div class="rounded-md bg-slate-950/45 p-3">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-semibold text-slate-100">${escapeHtml(this.navigationChoice(content.props?.identifier)?.label || content.previewTitle || 'Navigation')}</span>
                        <span class="rounded bg-white/10 px-2 py-1 font-mono text-slate-300">${escapeHtml(content.props?.presentation || 'horizontal')}</span>
                    </div>
                    <div class="mt-3">${this.renderNavigationItems(content.props?.identifier, content.props?.presentation || 'horizontal', content.props?.language === 'current' ? '' : content.props?.language)}</div>
                </div>
            `;
        }

        return '<div class="rounded-md bg-slate-100 p-3 text-xs text-slate-500 dark:bg-slate-900">Content preview</div>';
    }

    renderContentOptions() {
        const block = this.selectedBlock();
        const types = this.compatibleContentTypes(block);
        const region = this.blockRegion(block);

        if (types.length === 0) {
            this.contentOptions.innerHTML = `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    No content blocks are available for the ${escapeHtml(region)} region.
                </div>
            `;
            return;
        }

        this.contentOptions.innerHTML = types.map((type) => `
            <button class="${cardClass}" type="button" data-content-type="${escapeHtml(type.type)}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="m-0 text-sm font-semibold">${escapeHtml(type.label)}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">${escapeHtml(type.description)}</p>
                    </div>
                    <span class="rounded-md bg-slate-900/5 px-2 py-1 text-xs font-bold dark:bg-white/10">Select</span>
                </div>
                <div class="mt-3">${this.contentMiniPreview({type: type.type, previewTitle: type.previewTitle, props: type.defaults || {}})}</div>
            </button>
        `).join('');
    }

    renderSettings() {
        const content = this.selectedBlockId ? this.blockContent[this.selectedBlockId] : null;
        const block = this.selectedBlock();

        if (!content || !block) {
            this.contentSettings.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Choose a filled block to edit its content settings.</div>';
            return;
        }

        const definition = this.contentTypeDefinition(content.type);
        const fields = Array.isArray(definition?.fields) && definition.fields.length > 0
            ? definition.fields
            : Object.keys(content.props || {}).map((key) => ({name: key, label: key}));

        this.contentSettings.innerHTML = `
            <div class="mb-4 rounded-lg bg-slate-100 p-3 dark:bg-slate-900">
                <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(block.label)}</p>
                <h3 class="m-0 text-sm font-semibold">${escapeHtml(content.label)}</h3>
            </div>
            <div class="grid gap-3">
                ${fields.map((field) => this.renderField(field.name, content.props?.[field.name] ?? '', content.type, field)).join('')}
            </div>
        `;
        initStyledSelects(this.contentSettings.querySelectorAll('select[data-styled-select]'));
    }

    renderField(key, value, contentType = '', field = null) {
        const label = field?.label || key;
        if (field?.type === 'select') {
            return this.renderSelect(key, value, field.options || [], label);
        }

        if (field?.type === 'textarea') {
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
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
        if (key === 'source') {
            return this.renderSelect(key, value, ['latest', 'featured', 'category'], label);
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
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <input class="${inputClass}" type="${typeof value === 'number' ? 'number' : 'text'}" value="${escapeHtml(value)}" data-prop-name="${escapeHtml(key)}">
            </label>
        `;
    }

    renderSelect(key, value, options, label = key) {
        const normalizedOptions = options.map((option) => {
            if (typeof option === 'object' && option !== null) {
                return {
                    label: option.label ?? option.value ?? '',
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
                } : null,
                blocks,
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
                ['Template', payload.templateId || ''],
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
        const blocks = {};
        for (const block of template?.blocks || []) {
            blocks[block.id] = this.blockContent[block.id] || null;
        }

        const title = String(this.persistedValues.title || template?.label || 'Untitled page').trim();
        const path = String(this.persistedValues.path || '/').trim();

        return {
            id: this.savedId || 0,
            identifier: this.persistedValues.identifier || '',
            title,
            path,
            language: this.persistedValues.language || this.defaultLanguage,
            status: this.persistedValues.status || 'draft',
            templateId: template?.id || this.templateId,
            blocks,
            meta: {
                version: 1,
                seo: {
                    description: this.persistedValues['seo.description'] || '',
                    robots: this.persistedValues['seo.robots'] || 'index,follow',
                    canonicalUrl: this.persistedValues['seo.canonicalUrl'] || null,
                },
                theme: {
                    accent: this.pageAccent(),
                },
                extra: {
                    defaultNavigationIdentifier: this.selectedDefaultNavigationIdentifier(),
                },
            },
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
        if (!force && (!payload.title || !payload.path || payload.path === '/')) {
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
                    selectedBlockId: this.selectedBlockId,
                    blockContent: this.blockContent,
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
