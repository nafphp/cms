import {
    escapeHtml,
    layoutChildContainerStyles,
    notifyAlert,
    optionValue,
    previewViewports,
    prompt,
    toast,
} from './shared.js';

export const eventMethods = {
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

        if (event.target.closest('[data-reset-builder]')) {
            event.preventDefault();
            if (window.confirm(this.labels.resetBuilderConfirm)) {
                this.resetBuilderState();
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

        if (event.target.closest('[data-stat-add]')) {
            event.preventDefault();
            this.addStatItem();
            return;
        }

        const removeChartMetric = event.target.closest('[data-chart-metric-remove]');
        if (removeChartMetric) {
            event.preventDefault();
            this.removeChartMetric(Number(removeChartMetric.dataset.chartMetricRemove || -1));
            return;
        }

        const removeStat = event.target.closest('[data-stat-remove]');
        if (removeStat) {
            event.preventDefault();
            this.removeStatItem(Number(removeStat.dataset.statRemove || -1));
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
    },

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

        const defaultFooterNavigation = event.target.closest('[data-default-footer-navigation]');
        if (defaultFooterNavigation) {
            this.updateDefaultFooterNavigation(defaultFooterNavigation.value || this.selectedDefaultNavigationIdentifier());
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

        const statField = event.target.closest('[data-stat-field]');
        if (statField) {
            this.updateStatItem(
                Number(statField.dataset.statIndex || -1),
                statField.dataset.statField || '',
                statField.value,
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
    },

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
    },

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
        if (key === 'themeId') {
            this.renderBlocks();
            this.renderSettings();
        }
        if ([
            'theme.contentWidth',
            'theme.headerContentWidthIncludesChrome',
            'theme.headerBackgroundWidthIncludesChrome',
            'theme.footerContentWidthIncludesChrome',
            'theme.footerBackgroundWidthIncludesChrome',
        ].includes(key)) {
            this.renderPreviewChrome();
        }
        this.renderMeta();
        this.persistState();
    },

    assignContent(type) {
        const definition = this.contentTypes.find((item) => item.type === type);
        const block = this.selectedBlock();
        if (!definition || !block || !this.selectedBlockId || !this.contentTypeSupportsBlock(definition, block)) {
            return;
        }

        this.blockContent[this.selectedBlockId] = this.contentFromDefinition(definition);
    },

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
    },

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
    },

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
    },

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
    },

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
    },

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
    },

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
    },

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
    },

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
    },

    openSettings() {
        this.settingsOpen = true;
        this.settingsDrawer?.classList.remove('w-0', 'border-transparent', 'opacity-0');
        this.settingsDrawer?.classList.add('w-[min(420px,calc(100vw-28px))]', 'border-slate-800', 'opacity-100');
    },

    closeSettings() {
        this.settingsOpen = false;
        this.settingsDrawer?.classList.add('w-0', 'border-transparent', 'opacity-0');
        this.settingsDrawer?.classList.remove('w-[min(420px,calc(100vw-28px))]', 'border-slate-800', 'opacity-100');
    },

};
