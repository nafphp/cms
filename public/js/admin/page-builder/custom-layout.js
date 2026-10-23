import {
    customLayoutMaxHeights,
    customLayoutOverflows,
    customLayoutPositions,
    customLayoutRegions,
    customLayoutSizes,
    customLayoutSurfaces,
    escapeHtml,
    hasOption,
    inputClass,
    layoutBlockTypes,
    layoutChildContainerStyles,
    optionOptions,
    optionValue,
    toast,
} from './shared.js';

export const customLayoutMethods = {
    announce(message) {
        const announcer = this.root.querySelector('[data-builder-announcer]');
        if (!announcer) {
            return;
        }
        announcer.textContent = '';
        window.requestAnimationFrame(() => { announcer.textContent = message; });
    },

    moveCustomSlot(id, direction) {
        const index = this.customLayout.findIndex((slot) => slot.id === id);
        const target = index + direction;
        if (index < 0 || ![-1, 1].includes(direction) || target < 0 || target >= this.customLayout.length) {
            return;
        }
        const restoreFocus = document.activeElement?.closest('[data-move-custom-slot]')?.dataset.moveCustomSlot === id;
        this.reorderCustomSlot(id, this.customLayout[target].id);
        if (restoreFocus) {
            this.root.querySelector(`[data-move-custom-slot="${CSS.escape(id)}"][data-direction="${direction}"]`)?.focus();
        }
        this.announce(this.uiText('Moved to position %d of %d').replace('%d', target + 1).replace('%d', this.customLayout.length));
    },
    syncCustomTemplateBlocks() {
        const template = this.selectedTemplate();
        if (this.isCustomTemplate(template)) {
            template.blocks = this.customLayout;
        }

        const baseTemplate = this.templates.find((item) => item.id === 'custom');
        if (baseTemplate && template?.id === 'custom') {
            baseTemplate.blocks = this.customLayout;
        }
    },

    usesCustomLayout(template = this.selectedTemplate()) {
        return this.isCustomTemplate(template);
    },

    layoutBlocks(template = this.selectedTemplate()) {
        return this.usesCustomLayout(template)
            ? this.normalizeCustomLayout(this.customLayout)
            : (template?.blocks || []);
    },

    ensureCustomLayoutFromTemplate(template = this.selectedTemplate()) {
        if (this.customLayout.length > 0) {
            return;
        }

        this.customLayout = this.normalizeCustomLayout(template?.blocks || []);
        this.syncCustomTemplateBlocks();
    },

    applyTemplateCustomLayout(template = this.selectedTemplate()) {
        if (!this.isCustomTemplate(template)) {
            return;
        }

        this.customLayout = this.normalizeCustomLayout(template?.blocks || this.customLayout);
        this.syncCustomTemplateBlocks();
    },

    normalizeCustomLayout(layout) {
        const seen = new Set();
        const uniqueSeen = new Set();
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
            const surface = optionValue(this.surfaceOptions(), slot.surface, String(slot.surface || 'none'));
            if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(id) || label === '' || seen.has(id)) {
                return slots;
            }

            seen.add(id);
            const definition = {id, label, region, size, position, maxHeight, overflow, surface, deletable: slot.deletable !== false};
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

            const uniqueType = this.uniqueTypeForSlot(definition);
            if (uniqueType !== '') {
                if (uniqueSeen.has(uniqueType)) {
                    return slots;
                }
                uniqueSeen.add(uniqueType);
            }

            slots.push(definition);
            return slots;
        }, []);
    },

    uniqueTypeForSlot(slot) {
        const acceptedTypes = Array.isArray(slot?.accepts) ? slot.accepts : [];
        const defaultContentType = String(slot?.defaultContentType || '');
        const contentType = String(this.blockContent?.[slot?.id]?.type || '');
        const type = defaultContentType || contentType || acceptedTypes[0] || '';
        const definition = this.contentTypeDefinition(type);

        return definition?.unique === true ? String(definition.type || type) : '';
    },

    hasUniqueBlockType(type) {
        const definition = this.contentTypeDefinition(type);
        if (definition?.unique !== true) {
            return false;
        }

        const uniqueType = String(definition.type || type);
        return this.customLayout.some((slot) => this.uniqueTypeForSlot(slot) === uniqueType);
    },

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
    },

    addCustomSlot(type) {
        const definition = this.contentTypeDefinition(type);
        if (!definition) {
            return;
        }
        this.ensureCustomLayoutFromTemplate();
        if (definition.type === 'header' || definition.type === 'footer') {
            this.addChromeSlot(definition.type);
            return;
        }
        if (definition.unique === true && this.hasUniqueBlockType(definition.type)) {
            toast(this.labels.duplicateSlot);
            return;
        }

        this.rememberContentType(definition.type);
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
            deletable: true,
        });
        this.syncCustomTemplateBlocks();
        this.selectedBlockId = id;
        this.blockContent[id] = this.contentFromDefinition(definition);
        this.openSettings();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
        this.announce(this.uiText('Block added'));
    },

    addChromeSlot(type) {
        const definition = this.contentTypeDefinition(type);
        if (!definition) {
            return;
        }
        this.ensureCustomLayoutFromTemplate();

        const region = type === 'footer' ? 'footer' : 'header';
        if (this.hasUniqueBlockType(type)) {
            toast(this.labels.duplicateSlot);
            return;
        }

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
            deletable: true,
        });
        this.syncCustomTemplateBlocks();
        this.selectedBlockId = id;
        this.blockContent[id] = this.contentFromDefinition(definition);
        this.openSettings();
        this.previewTemplateId = null;
        this.persistState();
        this.render();
        this.announce(this.uiText('Block added'));
    },

    updateCustomSlot(id, patch, {renderBlocks = true} = {}) {
        const slot = this.customLayout.find((item) => item.id === id);
        if (!slot) {
            return;
        }

        if (typeof patch.label === 'string') {
            slot.label = patch.label.trim() || slot.label;
        }
        if (hasOption(customLayoutRegions, patch.region)) {
            slot.region = patch.region === 'header' && this.customChromeSlotType(slot) !== 'header' ? 'main' : patch.region;
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
        if (typeof patch.surface === 'string' && patch.surface.trim() !== '') {
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
    },

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
        this.announce(this.uiText('Block removed'));
    },

    addLayoutChild(parentPath, type) {
        const parent = this.contentAtPath(parentPath);
        const definition = this.contentTypeDefinition(type);
        if (!parent || !definition || !this.isLayoutContent(parent)) {
            return;
        }

        this.rememberContentType(definition.type);
        const children = this.childrenForContent(parent);
        children.push(this.contentFromDefinition(definition));
        this.selectedNestedPath = this.pathForChild(parentPath, children.length - 1);
        this.openSettings();
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
        this.announce(this.uiText('Block added'));
    },

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
        this.announce(this.uiText('Block removed'));
    },

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
        this.announce(this.uiText('Moved to position %d of %d').replace('%d', targetIndex + 1).replace('%d', children.length));
    },

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
    },

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
    },

    renderLayoutTypeSwitcher(content) {
        if (!this.isLayoutContent(content)) {
            return '';
        }

        const options = layoutBlockTypes
            .map((type) => this.contentTypeDefinition(type))
            .filter(Boolean)
            .map((definition) => `<option value="${escapeHtml(definition.type)}" ${definition.type === content.type ? 'selected' : ''}>${escapeHtml(this.uiText(definition.label || definition.type))}</option>`)
            .join('');

        return `
            <label class="grid gap-1.5 rounded-lg border border-slate-700 bg-[#0b1017] p-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(this.labels.layoutType)}</span>
                <select class="${inputClass}" data-styled-select data-layout-type-switch>
                    ${options}
                </select>
            </label>
        `;
    },

    renderCustomSlotContentOptionsHtml() {
        const contentTypes = this.contentTypes.filter((type) => !['header', 'footer'].includes(String(type?.type || '')));

        if (contentTypes.length === 0) {
            return `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    No content blocks are available.
                </div>
            `;
        }

        return this.sortContentTypesByRecent(contentTypes).map((type) => {
            const preview = this.compactContentPreview(type);
            const region = this.defaultRegionForContentType(type);
            return `
            <button class="rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:bg-[#151d27] focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]/40" style="display:grid;min-height:150px;align-content:space-between;gap:10px;" type="button" data-custom-slot-content-type="${escapeHtml(type.type)}">
                <span style="display:grid;gap:8px;">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">${escapeHtml(this.uiText(type.label))}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(this.uiText(type.description))}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">${escapeHtml(region)}</span>
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

    renderLayoutChildContentOptionsHtml(parentPath = '') {
        const parent = this.contentAtPath(parentPath);
        if (!this.isLayoutContent(parent)) {
            return `
                <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">
                    Choose a layout block before adding children.
                </div>
            `;
        }

        return this.sortContentTypesByRecent(this.contentTypes).map((type) => {
            const preview = this.compactContentPreview(type);
            const isLayout = layoutBlockTypes.includes(type.type);
            return `
            <button class="rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:bg-[#151d27] focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]/40" style="display:grid;min-height:150px;align-content:space-between;gap:10px;" type="button" data-layout-child-content-type="${escapeHtml(type.type)}">
                <span style="display:grid;gap:8px;">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">${escapeHtml(this.uiText(type.label))}</span>
                            <span class="mt-1 block overflow-hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(this.uiText(type.description))}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/10 px-2 py-1 text-[11px] font-bold text-slate-300">${escapeHtml(isLayout ? 'layout' : 'content')}</span>
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

    renderLayoutChildrenEditor(parentPath = '') {
        const children = this.childrenAtPath(parentPath);
        const rows = children.map((child, index) => {
            const path = this.pathForChild(parentPath, index);
            const label = this.uiText(child?.label || child?.previewTitle || child?.type || 'Block');
            const type = child?.type || '';
            const containerStyleOptions = optionOptions(layoutChildContainerStyles, this.layoutChildContainerStyle(child), this.labels);
            return `
                <div class="grid gap-2 rounded-md border border-slate-700 bg-[#0b1017] p-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <strong class="block truncate text-sm text-slate-100">${escapeHtml(label)}</strong>
                            <span class="block truncate font-mono text-[11px] uppercase text-slate-500">${escapeHtml(type)}</span>
                        </div>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 text-xs text-slate-400 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-move-layout-child="${escapeHtml(path)}" data-direction="-1" aria-label="${escapeHtml(this.uiText('Move up'))}">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m18 15-6-6-6 6"></path>
                            </svg>
                        </button>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-700 text-xs text-slate-400 transition hover:border-[var(--admin-page-accent)] hover:text-white" type="button" data-move-layout-child="${escapeHtml(path)}" data-direction="1" aria-label="${escapeHtml(this.uiText('Move down'))}">
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
    },

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
    },

    handleDragOver(event) {
        const dropTarget = event.target.closest('[data-custom-slot-drop]');
        if (!dropTarget || !this.draggedCustomSlotId || dropTarget.dataset.customSlotDrop === this.draggedCustomSlotId) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTarget.classList.add('ring-2', 'ring-[var(--admin-page-accent)]/50');
    },

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
    },

    handleDragEnd() {
        this.draggedCustomSlotId = null;
        this.root.querySelectorAll('[data-custom-slot-drop]').forEach((slot) => {
            slot.classList.remove('ring-2', 'ring-[var(--admin-page-accent)]/50');
        });
    },

};
