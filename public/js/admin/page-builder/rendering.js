import {
    activeLayoutClass,
    customLayoutMaxHeights,
    customLayoutOverflows,
    customLayoutPositions,
    customLayoutRegions,
    customLayoutSizes,
    customLayoutSurfaces,
    escapeHtml,
    initStyledSelects,
    layoutClass,
    optionOptions,
} from './shared.js';
import {initDiagrams} from '../diagrams.js';

export const renderingMethods = {
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
        this.scrollActiveTemplateIntoView();
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
        initDiagrams();
        window.requestAnimationFrame(() => initDiagrams());
    },

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
    },

    updateActiveTemplate() {
        this.templateList?.querySelectorAll('[data-template-id]').forEach((card) => {
            const active = card.dataset.templateId === this.templateId;
            card.classList.toggle('border-[var(--admin-page-accent)]', active);
            card.classList.toggle('bg-[#122033]', active);
        });
    },

    scrollActiveTemplateIntoView() {
        if (this.step !== 'template' || this.scrolledTemplateId === this.templateId) {
            return;
        }

        const active = this.templateList?.querySelector(`[data-template-id="${CSS.escape(this.templateId)}"]`);
        if (!active) {
            return;
        }

        this.scrolledTemplateId = this.templateId;
        window.requestAnimationFrame(() => {
            active.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'nearest',
            });
        });
    },

    renderBlocks() {
        const template = this.selectedTemplate();
        const blocks = template?.blocks || [];
        const filled = blocks.filter((block) => this.blockContent[block.id]).length;
        if (this.filledCount) {
            this.filledCount.textContent = `${filled}/${blocks.length} filled`;
        }
        if (this.blockCanvas) {
            this.blockCanvas.innerHTML = `<div class="${escapeHtml(this.previewThemeClassList())}">${this.blockLayout(template, true)}</div>`;
            initStyledSelects(this.blockCanvas.querySelectorAll('select[data-styled-select]'));
        }
    },

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
                            <span class="block truncate text-sm font-semibold">${escapeHtml(this.uiText(type.label))}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(this.uiText(type.description))}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">${escapeHtml(this.uiText('Select'))}</span>
                    </span>
                    <span class="overflow-hidden rounded-md border border-slate-800 bg-slate-950/45" style="display:grid;height:64px;align-content:stretch;">
                        ${preview}
                    </span>
                </span>
                <span class="block truncate font-mono text-[10px] uppercase tracking-wide text-slate-500">${escapeHtml(type.type)}</span>
            </button>
        `;
        }).join('');
    },

    renderSettings() {
        const content = this.selectedContent();
        const block = this.selectedBlock();
        const slotSettings = this.usesCustomLayout() && block ? this.renderCustomSlotSettings(block) : '';

        if (!content || !block) {
            this.contentSettings.innerHTML = `
                ${slotSettings}
                <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Choose a filled block to edit its content settings.</div>
            `;
            initStyledSelects(this.contentSettings.querySelectorAll('select[data-styled-select]'));
            return;
        }

        const definition = this.contentTypeDefinition(content.type);
        const fields = Array.isArray(definition?.fields) && definition.fields.length > 0
            ? definition.fields
            : Object.keys(content.props || {}).map((key) => ({name: key, label: key}));
        const visibleFields = fields.filter((field) => field.name !== 'children');
        const isNested = this.selectedNestedPath !== '';
        const contentIntro = this.usesCustomLayout() && !isNested
            ? ''
            : `
                <div class="mb-4 rounded-lg bg-slate-100 p-3 dark:bg-slate-900">
                    ${isNested ? `<button class="mb-2 inline-flex min-h-8 items-center rounded-md border border-slate-700 px-2.5 text-xs font-semibold text-slate-300 transition hover:border-[var(--admin-page-accent)]" type="button" data-back-to-layout-block>${escapeHtml(this.labels.backToLayoutBlock)}</button>` : ''}
                    <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(isNested ? this.labels.editChildBlock : this.uiText(block.label))}</p>
                    <h3 class="m-0 text-sm font-semibold">${escapeHtml(this.uiText(content.label))}</h3>
                </div>
            `;

        this.contentSettings.innerHTML = `
            ${slotSettings}
            ${['header', 'footer'].includes(content.type) ? this.renderChromeThemeSettings(content.type) : ''}
            ${contentIntro}
            <div class="grid gap-3">
                ${this.renderLayoutTypeSwitcher(content)}
                ${visibleFields.map((field) => this.renderField(field.name, content.props?.[field.name] ?? '', content.type, field)).join('')}
                ${this.isLayoutContent(content) ? this.renderLayoutChildrenEditor(this.selectedNestedPath || '') : ''}
            </div>
        `;
        initStyledSelects(this.contentSettings.querySelectorAll('select[data-styled-select]'));
    },

    renderCustomSlotSettings(block) {
        const id = String(block?.id || '');
        if (id === '') {
            return '';
        }

        const controlClass = 'min-h-10 w-full rounded-md border border-slate-700 bg-[#0b1017] px-3 py-2 text-sm text-slate-100 outline-none focus:border-[var(--admin-page-accent)]';
        const labelClass = 'grid gap-1.5';
        const labelTextClass = 'text-[11px] font-bold uppercase tracking-wide text-slate-500';
        const compactSelect = 'data-styled-select data-styled-select-density="compact"';
        const isHeaderChromeSlot = this.customChromeSlotType(block) === 'header';
        const regionOptions = isHeaderChromeSlot
            ? customLayoutRegions.filter((region) => region.value === 'header')
            : customLayoutRegions.filter((region) => region.value !== 'header');

        return `
            <section class="mb-4 grid gap-3 rounded-lg border border-slate-800 bg-[#111827] p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.customLayout)}</p>
                        <h3 class="m-0 mt-0.5 text-sm font-semibold text-white">${escapeHtml(this.uiText(block.label || id))}</h3>
                    </div>
                    <button class="grid h-8 w-8 place-items-center rounded-md border border-rose-900/70 bg-rose-950/20 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50" type="button" data-delete-custom-slot="${escapeHtml(id)}" aria-label="${escapeHtml(this.labels.deleteSlot)}" title="${escapeHtml(this.labels.deleteSlot)}">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                </div>
                <label class="${labelClass}">
                    <span class="${labelTextClass}">${escapeHtml(this.labels.slotLabel)}</span>
                    <input class="${controlClass}" type="text" value="${escapeHtml(block.label || '')}" data-custom-slot-label="${escapeHtml(id)}">
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.region)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-region="${escapeHtml(id)}">${optionOptions(regionOptions, this.customSlotRegion(block), this.labels)}</select>
                    </label>
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.size)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-size="${escapeHtml(id)}">${optionOptions(customLayoutSizes, this.customSlotSize(block), this.labels)}</select>
                    </label>
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.position)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-position="${escapeHtml(id)}">${optionOptions(customLayoutPositions, block.position || 'stretch', this.labels)}</select>
                    </label>
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.maxHeight)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-max-height="${escapeHtml(id)}">${optionOptions(customLayoutMaxHeights, block.maxHeight || 'none', this.labels)}</select>
                    </label>
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.overflow)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-overflow="${escapeHtml(id)}">${optionOptions(customLayoutOverflows, block.overflow || 'visible', this.labels)}</select>
                    </label>
                    <label class="${labelClass}">
                        <span class="${labelTextClass}">${escapeHtml(this.labels.surface)}</span>
                        <select class="${controlClass}" ${compactSelect} data-custom-slot-surface="${escapeHtml(id)}">${optionOptions(this.surfaceOptions(), block.surface || 'none', this.labels)}</select>
                    </label>
                </div>
            </section>
        `;
    },

    renderChromeThemeSettings(region = 'header') {
        const isHeader = region === 'header';
        return `
            <section class="mb-4 grid gap-3 rounded-lg border border-slate-800 bg-[#111827] p-3">
                <div>
                    <p class="m-0 text-[11px] font-bold uppercase tracking-wide text-slate-500">Theme</p>
                    <h3 class="m-0 mt-0.5 text-sm font-semibold text-white">${escapeHtml(isHeader ? this.labels.regionHeader : this.labels.regionFooter)}</h3>
                </div>
                ${isHeader ? `
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.headerBackground)}</span>
                        <input class="h-10 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1.5 outline-none focus:border-[var(--admin-page-accent)]" type="color" data-persist-key="theme.headerBackground" value="#020617" aria-label="${escapeHtml(this.labels.headerBackground)}">
                    </label>
                    ${this.renderSwitchField(this.labels.transparent, 'data-persist-key="theme.headerBackgroundTransparent"', Boolean(this.persistedValues['theme.headerBackgroundTransparent']))}
                    ${this.renderSwitchField(this.labels.headerContentWidth, 'data-persist-key="theme.headerContentWidthIncludesChrome"', this.headerContentWidthIncludesChrome())}
                    ${this.renderSwitchField(this.labels.headerBackgroundWidth, 'data-persist-key="theme.headerBackgroundWidthIncludesChrome"', this.headerBackgroundWidthIncludesChrome())}
                ` : `
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.footerBackground)}</span>
                        <input class="h-10 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1.5 outline-none focus:border-[var(--admin-page-accent)]" type="color" data-persist-key="theme.footerBackground" value="#020617" aria-label="${escapeHtml(this.labels.footerBackground)}">
                    </label>
                    ${this.renderSwitchField(this.labels.transparent, 'data-persist-key="theme.footerBackgroundTransparent"', Boolean(this.persistedValues['theme.footerBackgroundTransparent']))}
                    ${this.renderSwitchField(this.labels.footerContentWidth, 'data-persist-key="theme.footerContentWidthIncludesChrome"', this.footerContentWidthIncludesChrome())}
                    ${this.renderSwitchField(this.labels.footerBackgroundWidth, 'data-persist-key="theme.footerBackgroundWidthIncludesChrome"', this.footerBackgroundWidthIncludesChrome())}
                `}
            </section>
        `;
    },

};
