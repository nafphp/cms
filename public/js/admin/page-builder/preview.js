import {
    chartMetricColors,
    customLayoutMaxHeights,
    customLayoutOverflows,
    customLayoutPositions,
    customLayoutRegions,
    customLayoutSizes,
    customLayoutSurfaces,
    escapeHtml,
    layoutBlockTypes,
    layoutClass,
    optionLabel,
    optionValue,
    previewFrameClass,
    previewViewports,
} from './shared.js';

export const previewMethods = {
    previewFrame(template, title, className = previewFrameClass, htmlKey = 'previewHtml', dimensions = '') {
        const html = template[htmlKey] || template.previewHtml || '';
        if (!html) {
            return '<span class="grid aspect-video place-items-center text-xs text-slate-500">No preview</span>';
        }

        return `<iframe class="${className}" title="${escapeHtml(template.label)} ${escapeHtml(title)}" sandbox tabindex="-1" aria-hidden="true" scrolling="no" ${dimensions} data-template-preview-frame srcdoc="${escapeHtml(html)}"></iframe>`;
    },

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
    },

    renderTemplatePreview() {
        const template = this.selectedTemplate();
        if (!template) {
            this.templatePreview.innerHTML = '';
            this.headerNavigationControl?.classList.add('hidden');
            this.footerNavigationControl?.classList.add('hidden');
            this.previewTemplateId = null;
            return;
        }

        const previewKey = `${template.id}:${this.selectedDefaultNavigationIdentifier()}:${this.selectedFooterNavigationIdentifier()}`;
        if (this.previewTemplateId === previewKey) {
            return;
        }

        if (this.templatePreviewTitle) {
            this.templatePreviewTitle.textContent = template.label;
        }
        this.headerNavigationControl?.classList.toggle('hidden', !this.templateHasHeader(template));
        this.footerNavigationControl?.classList.toggle('hidden', !this.templateHasFooter(template));
        this.templatePreview.innerHTML = this.livePreview(template);
        this.armPreviewFrames(this.templatePreview);
        this.previewTemplateId = previewKey;
    },

    renderPreviewChrome() {
        const viewport = previewViewports[this.previewViewport] || previewViewports.desktop;
        this.root.dataset.previewViewport = this.previewViewport;

        if (this.previewFrame) {
            this.previewFrame.style.width = `${viewport.width}px`;
            this.previewFrame.style.maxWidth = '100%';
            this.previewFrame.style.setProperty('--page-builder-preview-width', `${viewport.width}px`);
        }
        if (this.previewRuler) {
            this.previewRuler.style.width = `${viewport.width}px`;
            this.previewRuler.style.maxWidth = '100%';
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
    },

    livePreview(template) {
        return `
            <div class="overflow-hidden rounded-lg border border-slate-800 bg-[#171d25]">
                <div class="max-h-[520px] overflow-auto bg-[var(--page-bg)] text-[var(--page-text)]">
                    ${this.templateDummyLayout(template)}
                </div>
                <div class="border-t border-slate-800 bg-[#151b22] py-4 text-center text-sm text-slate-500">Live-Vorschau: ${escapeHtml(template.layout || 'no layout')} · ${escapeHtml(template.view)}</div>
            </div>
        `;
    },

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
    },

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

        if (block.preview === 'cta') {
            return `
                <div class="flex min-h-24 flex-wrap items-center justify-between gap-4 rounded-xl border border-[color-mix(in_srgb,var(--page-accent)_34%,var(--page-card-border))] bg-[color-mix(in_srgb,var(--page-accent)_10%,var(--page-card-bg))] p-6 ${className}">
                    <div class="grid min-w-0 flex-1 gap-2">
                        <span class="text-lg font-extrabold leading-tight text-[var(--page-text)]">${escapeHtml(block.label)}</span>
                        <span class="block h-2.5 max-w-[320px] rounded-full bg-[var(--page-border)]"></span>
                        <span class="block h-2.5 max-w-[220px] rounded-full bg-[var(--page-panel-soft)]"></span>
                    </div>
                    <span class="block h-9 w-28 shrink-0 rounded-md bg-[var(--page-accent)]"></span>
                </div>
            `;
        }

        if (block.preview === 'navigation') {
            return `
                <div class="page-site-header border-b border-[var(--page-border)] bg-[var(--page-header-bg)] text-[var(--page-header-text)] ${className}">
                    <div class="page-template-chrome-inner flex min-h-16 items-center gap-4 py-4 pl-0 pr-8">
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
                    <div class="page-template-chrome-inner flex min-h-16 flex-wrap items-center justify-between gap-3 px-8 py-4">
                        <span class="text-xs font-medium text-[var(--page-footer-text)]">Powered by NixCMS</span>
                        <div>${this.renderNavigationItems(this.selectedFooterNavigationIdentifier(), 'compact')}</div>
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
    },

    customSlotRegion(block) {
        return optionValue(customLayoutRegions, block?.region, 'main');
    },

    customSlotSize(block) {
        return optionValue(customLayoutSizes, block?.size, 'medium');
    },

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
            const surface = optionValue(this.surfaceOptions(), block?.surface, String(block?.surface || 'none'));
            const contentType = String(this.blockContent?.[block.id]?.type || 'empty').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
            return `
                <section class="page-template-region page-template-custom-slot page-template-custom-slot--${escapeHtml(region)} page-template-custom-slot--${escapeHtml(size)} page-template-custom-slot--content-${escapeHtml(contentType)} page-template-custom-slot--position-${escapeHtml(position)} page-template-custom-slot--max-${escapeHtml(maxHeight)} page-template-custom-slot--overflow-${escapeHtml(overflow)} page-template-custom-slot--surface-${escapeHtml(surface)}" data-region="${escapeHtml(region)}" data-size="${escapeHtml(size)}" data-position="${escapeHtml(position)}" data-max-height="${escapeHtml(maxHeight)}" ${interactive ? `data-custom-slot-drop="${escapeHtml(block.id)}"` : ''}>
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
    },

    customSlotControls(block) {
        return `
            <button class="page-builder-slot-hitbox" type="button" data-edit-block="${escapeHtml(block.id)}" aria-label="Edit slot"></button>
            <div class="page-builder-slot-controls">
                <button class="page-builder-slot-controls__tab" type="button" draggable="true" data-custom-slot-drag="${escapeHtml(block.id)}" data-edit-block="${escapeHtml(block.id)}" aria-label="Reorder slot">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M8 6h.01"></path><path d="M16 6h.01"></path>
                        <path d="M8 12h.01"></path><path d="M16 12h.01"></path>
                        <path d="M8 18h.01"></path><path d="M16 18h.01"></path>
                    </svg>
                    <span>${escapeHtml(block.label || 'Slot')}</span>
                </button>
            </div>
        `;
    },

    isMobilePreview() {
        return this.previewViewport === 'mobile';
    },

    blockLayout(template, interactive) {
        const blocks = template?.blocks || [];
        if (this.isCustomTemplate(template)) {
            const hasFullBleedHeader = blocks.some((block) => {
                const content = this.blockContent[block.id] || null;
                return this.customSlotRegion(block) === 'header'
                    && content?.type === 'header'
                    && !this.headerBackgroundWidthIncludesChrome();
            });
            const toolbarMarginClass = hasFullBleedHeader ? 'mb-0' : 'mb-4';
            const toolbar = interactive ? `
                <div class="page-builder-custom-toolbar ${toolbarMarginClass} flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-[#121820] px-4 py-3">
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
    },

    blockCard(block, interactive, templateId, className = '') {
        const content = this.blockContent[block.id] || null;
        const selected = block.id === this.selectedBlockId;
        const isChromeBlock = ['header', 'footer'].includes(String(content?.type || ''));
        const minHeight = isChromeBlock ? 'min-h-0' : ({
            hero: 'min-h-44',
            large: 'min-h-36',
            medium: 'min-h-28',
            aside: 'min-h-32',
            small: 'min-h-20',
        }[block.size] || 'min-h-28');
        const actionAttr = interactive
            ? (content ? `data-edit-block="${escapeHtml(block.id)}"` : `data-choose-block="${escapeHtml(block.id)}"`)
            : '';
        const textVerticalAlign = content?.type === 'text' && ['top', 'center', 'bottom'].includes(String(content.props?.verticalAlign || ''))
            ? String(content.props.verticalAlign)
            : '';
        const contentAlignClass = {
            top: 'content-start',
            center: 'content-center',
            bottom: 'content-end',
        }[textVerticalAlign] || (isChromeBlock ? 'content-start' : 'content-center');
        const contentChrome = content
            ? 'overflow-visible focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--page-accent)]/40'
            : 'overflow-hidden bg-transparent hover:border-[var(--page-card-border)] hover:bg-[var(--page-card-bg)] focus:border-[var(--page-accent)] focus:bg-[var(--page-card-bg)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--page-accent)]/40';
        const frameClass = [
            'group/page-block relative grid w-full min-w-0 justify-self-stretch rounded-xl border border-transparent text-left transition',
            content ? `page-builder-block--${escapeHtml(content.type || 'content')}` : '',
            content?.type === 'header' && !this.headerBackgroundWidthIncludesChrome() ? 'page-builder-block--full-bleed-header' : '',
            content?.type === 'footer' && !this.footerBackgroundWidthIncludesChrome() ? 'page-builder-block--full-bleed-footer' : '',
            minHeight,
            content ? 'p-0' : 'p-6',
            interactive && !content ? 'pt-14' : '',
            contentAlignClass,
            className,
            contentChrome,
            interactive ? 'cursor-pointer' : '',
        ].filter(Boolean).join(' ');
        const blockType = content?.label || 'Empty block';
        const customChromeType = this.customChromeSlotType(block);
        const deleteAttr = customChromeType !== ''
            ? `data-delete-custom-slot="${escapeHtml(block.id)}"`
            : `data-clear-block="${escapeHtml(block.id)}"`;
        const deleteLabel = customChromeType !== ''
            ? this.labels.deleteSlot
            : 'Clear block';
        const controls = interactive ? `
            <div class="page-builder-block-controls pointer-events-auto absolute flex translate-y-1 items-center gap-2 opacity-0 transition group-hover/page-block:translate-y-0 group-hover/page-block:opacity-100 group-focus-within/page-block:translate-y-0 group-focus-within/page-block:opacity-100">
                ${content ? `<button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 bg-slate-950/80 text-xs text-[var(--admin-page-accent)] transition hover:border-[var(--admin-page-accent)]" type="button" data-edit-block="${escapeHtml(block.id)}" aria-label="Edit block">✎</button>` : ''}
                ${content ? `
                    <button class="grid h-8 w-8 place-items-center rounded-md border border-rose-900/70 bg-slate-950/80 text-rose-400 transition hover:border-rose-500 hover:bg-rose-950/50" type="button" ${deleteAttr} aria-label="${escapeHtml(deleteLabel)}" title="${escapeHtml(deleteLabel)}">
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
                    ${interactive ? `<button class="page-builder-block-hitbox" type="button" data-edit-block="${escapeHtml(block.id)}" aria-label="Edit block"></button>` : ''}
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
    },

    emptyBlockPreview(block) {
        if (block.preview === 'text') {
            return `<span class="text-base font-semibold text-[var(--page-muted)]">${escapeHtml(block.label)}</span>`;
        }

        if (block.preview === 'cta') {
            return `
                <span class="text-base font-extrabold text-[var(--page-text)]">${escapeHtml(block.label)}</span>
                <span class="mt-3 block h-2.5 max-w-[320px] rounded-full bg-[var(--page-border)]"></span>
                <span class="mt-2 block h-2.5 max-w-[220px] rounded-full bg-[var(--page-panel-soft)]"></span>
                <span class="mt-4 block h-9 w-28 rounded-md bg-[var(--page-accent)]"></span>
            `;
        }

        return `
            <span class="text-base font-semibold text-[var(--page-muted)]">${escapeHtml(block.label)}</span>
            <span class="mt-3 block h-2.5 max-w-[420px] rounded-full bg-[var(--page-border)]"></span>
            <span class="mt-2 block h-2.5 max-w-[280px] rounded-full bg-[var(--page-panel-soft)]"></span>
        `;
    },

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
    },

    renderNavigationItems(identifier, presentation = 'horizontal', language = '') {
        const items = this.navigationPreviewItems(identifier, language, this.isMobilePreview() ? 3 : 5);
        if (items.length === 0) {
            return '';
        }

        const vertical = presentation === 'vertical';
        const compact = presentation === 'compact';
        const className = vertical
            ? 'grid gap-2'
            : 'flex flex-wrap items-center gap-4';
        const itemClass = compact
            ? 'text-xs font-semibold text-[var(--page-muted)] transition hover:text-[var(--page-text)]'
            : 'text-sm font-semibold text-[var(--page-muted)] transition hover:text-[var(--page-text)]';

        return `
            <nav class="${className}" aria-label="Navigation preview">
                ${items.map((item) => `<span class="${itemClass}">${escapeHtml(item.label || item.title || item.key || 'Item')}</span>`).join('')}
            </nav>
        `;
    },

    contentMiniPreview(content, depth = 0) {
        content = content && typeof content === 'object' ? content : {type: '', props: {}};
        if (layoutBlockTypes.includes(content.type)) {
            const children = Array.isArray(content.props?.children) ? content.props.children : [];
            const childPreview = children.slice(0, 6).map((child) => `
                <div class="${this.layoutChildContainerClass(child, true)}">
                    ${depth > 2 ? `<span class="text-xs text-[var(--page-muted)]">${escapeHtml(child?.label || child?.type || 'Block')}</span>` : this.contentMiniPreview(child, depth + 1)}
                </div>
            `).join('');
            const empty = '<div class="rounded-md border border-dashed border-[var(--page-border)] p-3 text-center text-xs text-[var(--page-muted)]" style="grid-column:1 / -1;">Empty layout module</div>';
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
                        <strong class="text-sm font-bold text-[var(--page-text)]">${escapeHtml(this.uiText(content.label || content.previewTitle || content.type))}</strong>
                        <span class="rounded bg-white/10 px-2 py-1 text-[11px] font-bold uppercase text-[var(--page-muted)]">${escapeHtml(content.type)}</span>
                    </div>
                    <div class="${layoutClass}"${layoutStyle}>${childPreview || empty}</div>
                </div>
            `;
        }

        if (content.type === 'header') {
            const logoImage = String(content.props?.logoImage || '');
            const logoText = String(content.props?.logoText || 'NixCMS');
            const showLogoMark = Boolean(content.props?.showLogoMark);
            const dividerClass = content.props?.showDivider ? 'border-b border-[var(--page-border)]' : '';
            const desktopNavigation = this.renderNavigationItems(content.props?.navigationIdentifier, content.props?.presentation || 'horizontal');
            const mobileNavigation = desktopNavigation !== '' ? this.renderMobileNavigationPreview(content.props?.navigationIdentifier) : '';
            return `
                <div class="page-site-header ${dividerClass} bg-[var(--page-header-bg)] text-[var(--page-header-text)]">
                    <div class="page-template-chrome-inner flex min-h-16 items-center gap-4 py-4 pl-0 pr-8">
                        <span class="inline-flex min-h-10 items-center gap-3 text-[var(--page-header-text)]">
                            ${logoImage
                                ? `<img class="h-8 w-auto max-w-36 object-contain" src="${escapeHtml(logoImage)}" alt="">`
                                : showLogoMark ? `<span class="grid h-8 w-8 place-items-center rounded-md bg-[var(--page-accent)] text-xs font-black text-white">${escapeHtml(logoText.slice(0, 1) || 'N')}</span>` : ''}
                            <strong class="text-base font-bold">${escapeHtml(logoText)}</strong>
                        </span>
                        ${desktopNavigation !== '' ? `<div class="page-desktop-navigation ml-auto">${desktopNavigation}</div>` : ''}
                        ${mobileNavigation !== '' ? `<div class="page-mobile-navigation ml-auto">${mobileNavigation}</div>` : ''}
                    </div>
                </div>
            `;
        }

        if (content.type === 'footer') {
            const compact = this.isMobilePreview();
            return `
                <div class="page-site-footer border-t border-[var(--page-border)] bg-[var(--page-footer-bg)]">
                    <div class="page-template-chrome-inner flex min-h-16 flex-wrap ${compact ? 'items-start' : 'items-center justify-between'} gap-3 px-8 py-4">
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
                    <span class="text-xl font-extrabold leading-tight text-[var(--page-text)]">${escapeHtml(content.props?.text || this.uiText(content.previewTitle || 'Headline'))}</span>
                </div>
            `;
        }

        if (content.type === 'text') {
            const tone = ['body', 'lead', 'muted'].includes(String(content.props?.tone || '')) ? String(content.props.tone) : 'body';
            const align = ['left', 'center', 'right'].includes(String(content.props?.align || '')) ? String(content.props.align) : 'left';
            const verticalAlign = ['top', 'center', 'bottom'].includes(String(content.props?.verticalAlign || '')) ? String(content.props.verticalAlign) : 'top';
            const toneClass = {
                body: 'text-base text-[var(--page-text)]',
                lead: 'text-lg font-medium text-[var(--page-text)]',
                muted: 'text-sm text-[var(--page-muted)]',
            }[tone];
            const alignClass = {
                left: 'text-left',
                center: 'text-center mx-auto',
                right: 'text-right ml-auto',
            }[align];
            const verticalClass = {
                top: 'self-start',
                center: 'self-center',
                bottom: 'self-end',
            }[verticalAlign];

            return `
                <div class="grid px-1 py-2">
                    <p class="m-0 max-w-[58ch] leading-relaxed ${toneClass} ${alignClass} ${verticalClass}">${escapeHtml(content.props?.text || this.uiText(content.previewTitle || 'Text block'))}</p>
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
            const image = String(content.props?.image || '');
            const surfaceClass = this.contentSurfaceClass(content, 'grid min-h-[420px] items-center gap-8 overflow-hidden', 'card', 'p-8');
            return `
                <section class="${surfaceClass} ${compact ? '' : 'grid-cols-[minmax(0,1fr)_minmax(280px,.72fr)]'}">
                    <div class="grid gap-5">
                        ${content.props?.eyebrow ? `<p class="m-0 text-xs font-bold uppercase tracking-wide text-[var(--page-accent)]">${escapeHtml(this.uiText(content.props.eyebrow))}</p>` : ''}
                        <h1 class="m-0 text-[clamp(2.4rem,5vw,4.6rem)] font-black leading-none text-[var(--page-text)]">${escapeHtml(this.uiText(content.props?.title || 'Hero title'))}</h1>
                        ${content.props?.text ? `<p class="m-0 max-w-[62ch] text-lg leading-relaxed text-[var(--page-muted)]">${escapeHtml(this.uiText(content.props.text))}</p>` : ''}
                        ${content.props?.buttonLabel ? `<span class="inline-flex min-h-11 w-max items-center rounded-md bg-[var(--page-accent)] px-5 text-sm font-bold text-white">${escapeHtml(this.uiText(content.props.buttonLabel))}</span>` : ''}
                    </div>
                    ${compact ? '' : image
                        ? `<img class="aspect-[4/3] w-full rounded-lg object-cover" src="${escapeHtml(image)}" alt="">`
                        : '<div class="aspect-[4/3] rounded-lg border border-dashed border-[var(--page-border)] bg-[var(--page-bg)]"></div>'}
                </section>
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
                        : `<div class="grid gap-3">
                            ${labels.map((label, index) => `<span class="grid grid-cols-[minmax(84px,auto)_minmax(0,1fr)_42px] items-center gap-3 text-[13px] text-[var(--page-muted)]">
                                <span class="truncate">${escapeHtml(label)}</span>
                                <span class="h-3 overflow-hidden rounded-full bg-[rgba(148,163,184,.16)]"><span class="block h-full rounded-full" style="width:${Math.max(4, (values[index] / max) * 100)}%;background:${escapeHtml(colors[index])};"></span></span>
                                <span class="text-right">${escapeHtml(values[index])}</span>
                            </span>`).join('')}
                        </div>`;
            const surface = String(content.props?.surface || 'none').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
            const chartHeight = type === 'metric-bars'
                ? Math.max(180, Math.min(Number(content.props?.height || 320), 72 + Math.max(1, count) * 28))
                : Math.max(220, Math.min(Number(content.props?.height || 320), 720));

            return `
                <figure class="nixcms-chart nixcms-chart--surface-${escapeHtml(surface)}">
                    <div class="nixcms-chart__header">
                        <div>
                            ${content.props?.title ? `<h3 class="nixcms-chart__title">${escapeHtml(this.uiText(content.props.title))}</h3>` : ''}
                            ${content.props?.subtitle ? `<p class="nixcms-chart__subtitle">${escapeHtml(this.uiText(content.props.subtitle))}</p>` : ''}
                        </div>
                    </div>
                    <div class="nixcms-chart__surface" style="min-height:${chartHeight}px;">
                        <div class="nixcms-chart__canvas" style="height:${chartHeight}px;display:grid;align-content:center;">
                            ${chartPreview}
                        </div>
                    </div>
                </figure>
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

        if (content.type === 'stats') {
            const items = this.statsItems(content);
            const columns = Math.min(Math.max(items.length, 1), 4);
            return `
                <div class="grid gap-3" style="grid-template-columns:repeat(${columns},minmax(0,1fr));">
                    ${items.map((item) => `
                        <div class="${this.contentSurfaceClass(content, 'grid gap-1', 'card', 'p-4')}">
                            <strong class="text-2xl font-black leading-tight text-[var(--page-text)]">${escapeHtml(item.value || '0')}</strong>
                            <span class="text-xs font-semibold text-[var(--page-muted)]">${escapeHtml(this.uiText(item.label))}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (['cta', 'feature', 'quote', 'media'].includes(content.type)) {
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
                <div class="${this.contentSurfaceClass(content, 'grid gap-3')}">
                    <div class="flex w-max max-w-full items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-100">${escapeHtml(this.navigationChoice(content.props?.identifier)?.label || content.previewTitle || 'Navigation')}</span>
                        <span class="rounded bg-white/10 px-2 py-1 font-mono text-slate-300">${escapeHtml(content.props?.presentation || 'horizontal')}</span>
                    </div>
                    <div>${this.renderNavigationItems(content.props?.identifier, content.props?.presentation || 'horizontal', content.props?.language === 'current' ? '' : content.props?.language)}</div>
                </div>
            `;
        }

        return '<div class="rounded-md bg-slate-100 p-3 text-xs text-slate-500 dark:bg-slate-900">Content preview</div>';
    },

    renderMobileNavigationPreview(identifier, language = '') {
        const items = this.navigationPreviewItems(identifier, language, 6);
        return `
            <button class="page-mobile-menu-trigger" type="button" aria-label="${escapeHtml(this.uiText('Open navigation'))}" tabindex="-1">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="page-mobile-navigation-drawer page-builder-mobile-navigation-drawer" aria-hidden="true">
                <div class="page-mobile-navigation-drawer__head">
                    <strong>${escapeHtml(this.uiText('Mobile navigation'))}</strong>
                    <button class="page-mobile-navigation-drawer__close" type="button" aria-label="${escapeHtml(this.uiText('Close navigation'))}" tabindex="-1">
                        <span></span>
                        <span></span>
                    </button>
                </div>
                <nav class="page-mobile-navigation-drawer__nav" aria-label="${escapeHtml(this.uiText('Mobile navigation'))}">
                    ${items.map((item) => `<span class="page-builder-mobile-navigation-link">${escapeHtml(item.label || item.title || item.key || 'Item')}</span>`).join('')}
                </nav>
            </div>
        `;
    },

};
