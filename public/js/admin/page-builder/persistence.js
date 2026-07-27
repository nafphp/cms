import {
    escapeHtml,
    flashToastKey,
    notifyAlert,
} from './shared.js';

export const persistenceMethods = {
    renderPagePreview() {
        const template = this.selectedTemplate();
        if (this.pagePreview) {
            this.pagePreview.innerHTML = `<div class="${escapeHtml(this.previewThemeClassList())}">${this.blockLayout(template, false).replaceAll('Empty content block', 'Unfilled block')}</div>`;
        }
    },

    renderJson() {
        const template = this.selectedTemplate();
        const blocks = {};
        for (const block of this.layoutBlocks(template)) {
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
                    headerContentWidthIncludesChrome: this.headerContentWidthIncludesChrome(),
                    headerBackgroundWidthIncludesChrome: this.headerBackgroundWidthIncludesChrome(),
                    footerContentWidthIncludesChrome: this.footerContentWidthIncludesChrome(),
                    footerBackgroundWidthIncludesChrome: this.footerBackgroundWidthIncludesChrome(),
                } : null,
                blocks,
                meta: {
                    custom_layout: this.usesCustomLayout(template)
                        ? this.normalizeCustomLayout(this.customLayout)
                        : [],
                },
            }, null, 2);
        }
    },

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
                ['Theme', this.themeLabel(payload.themeId || 'default')],
                ['Accent', payload.meta?.theme?.accent || '#3f7fbd'],
                [this.labels.headerBackground, this.headerBackgroundTransparent() ? this.labels.transparent : (payload.meta?.theme?.headerBackground || '#020617')],
                [this.labels.footerBackground, this.footerBackgroundTransparent() ? this.labels.transparent : (payload.meta?.theme?.footerBackground || '#020617')],
                [this.labels.width, this.contentWidthDisplay()],
                [this.labels.headerContentWidth, this.headerContentWidthIncludesChrome() ? this.labels.bounded : this.labels.contentWidthFull],
                [this.labels.headerBackgroundWidth, this.headerBackgroundWidthIncludesChrome() ? this.labels.bounded : this.labels.contentWidthFull],
                [this.labels.footerContentWidth, this.footerContentWidthIncludesChrome() ? this.labels.bounded : this.labels.contentWidthFull],
                [this.labels.footerBackgroundWidth, this.footerBackgroundWidthIncludesChrome() ? this.labels.bounded : this.labels.contentWidthFull],
                ['Template', payload.templateId || ''],
                ...(this.usesCustomLayout(template) ? [[this.labels.customSlots, String(this.layoutBlocks(template).length)]] : []),
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
    },

    pagePayload() {
        const template = this.selectedTemplate();
        const templateBlocks = this.layoutBlocks(template);
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
                headerBackground: this.normalizeColor(this.persistedValues['theme.headerBackground']) || '#020617',
                headerBackgroundTransparent: this.headerBackgroundTransparent(),
                footerBackground: this.normalizeColor(this.persistedValues['theme.footerBackground']) || '#020617',
                footerBackgroundTransparent: this.footerBackgroundTransparent(),
                contentWidth: this.contentWidthKey(),
                headerContentWidthIncludesChrome: this.headerContentWidthIncludesChrome(),
                headerBackgroundWidthIncludesChrome: this.headerBackgroundWidthIncludesChrome(),
                footerContentWidthIncludesChrome: this.footerContentWidthIncludesChrome(),
                footerBackgroundWidthIncludesChrome: this.footerBackgroundWidthIncludesChrome(),
            },
            extra: {
                defaultNavigationIdentifier: this.selectedDefaultNavigationIdentifier(),
                defaultFooterNavigationIdentifier: this.selectedFooterNavigationIdentifier(),
            },
        };
        if (this.usesCustomLayout(template)) {
            meta.custom_layout = templateBlocks;
            if (Number(template?.layoutPresetId || 0) > 0) {
                meta.custom_layout_ref = Number(template.layoutPresetId);
                meta.custom_layout_name = template.label || '';
            }
        }

        return {
            builderVersion: 1,
            id: this.savedId || 0,
            identifier: this.persistedValues.identifier || '',
            title,
            path,
            language: this.persistedValues.language || this.defaultLanguage,
            status: this.persistedValues.status || 'draft',
            themeId: this.persistedValues.themeId || 'default',
            templateId: this.storedTemplateId(template),
            blocks,
            meta,
        };
    },

    scheduleRemotePersist() {
        if (this.editMode || !this.saveUrl || this.step === 'template') {
            return;
        }

        window.clearTimeout(this.saveTimer);
        this.saveTimer = window.setTimeout(() => this.remotePersist({force: false}), 800);
    },

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
    },

};
