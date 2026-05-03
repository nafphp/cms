import {
    clone,
    customLayoutRegions,
    customLayoutSurfaces,
    hasOption,
    layoutBlockTypes,
    layoutChildContainerStyles,
    optionValue,
} from './shared.js';

export const contentModelMethods = {
    selectedBlock() {
        const template = this.selectedTemplate();
        return (template?.blocks || []).find((block) => block.id === this.selectedBlockId) || null;
    },

    selectedContent() {
        if (!this.selectedBlockId || !this.blockContent[this.selectedBlockId]) {
            return null;
        }

        return this.contentAtPath(this.selectedNestedPath || '') || this.blockContent[this.selectedBlockId];
    },

    pathIndexes(path) {
        return String(path || '')
            .split('.')
            .filter((part) => part !== '')
            .map((part) => Number(part))
            .filter((index) => Number.isInteger(index) && index >= 0);
    },

    pathForChild(parentPath, index) {
        const parent = String(parentPath || '');
        return parent === '' ? String(index) : `${parent}.${index}`;
    },

    parentPath(path) {
        const parts = String(path || '').split('.').filter((part) => part !== '');
        parts.pop();
        return parts.join('.');
    },

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
    },

    childrenForContent(content) {
        if (!content || typeof content !== 'object') {
            return [];
        }
        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        content.props.children = Array.isArray(content.props.children) ? content.props.children : [];

        return content.props.children;
    },

    childrenAtPath(path = '') {
        return this.childrenForContent(this.contentAtPath(path));
    },

    isLayoutContent(content) {
        return layoutBlockTypes.includes(String(content?.type || ''));
    },

    layoutChildContainerStyle(content) {
        return optionValue(layoutChildContainerStyles, content?.props?.containerStyle, 'none');
    },

    layoutChildContainerClass(content, preview = false) {
        const style = this.layoutChildContainerStyle(content);
        return `page-layout-child page-layout-child--${style}${preview ? ' page-builder-layout-child-preview' : ''}`;
    },

    blockRegion(block = this.selectedBlock()) {
        return block?.region || block?.id || 'main';
    },

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
    },

    compatibleContentTypes(block = this.selectedBlock()) {
        return this.contentTypes.filter((type) => this.contentTypeSupportsBlock(type, block));
    },

    contentTypeDefinition(type) {
        return this.contentTypes.find((item) => item.type === type) || null;
    },

    navigationChoice(identifier, language = '') {
        const normalizedIdentifier = String(identifier || '').trim();
        const normalizedLanguage = String(language || '').trim();
        return this.navigationChoices.find((navigation) => (
            navigation.identifier === normalizedIdentifier
            && String(navigation.language || '') === normalizedLanguage
        )) || this.navigationChoices.find((navigation) => navigation.identifier === normalizedIdentifier) || this.navigationChoices[0] || null;
    },

    navigationItems(identifier, language = '') {
        const navigation = this.navigationChoice(identifier, language);
        return Array.isArray(navigation?.items) ? navigation.items : [];
    },

    selectedDefaultNavigationIdentifier() {
        return String(this.persistedValues.defaultNavigationIdentifier || this.defaultNavigationIdentifier || this.navigationChoices[0]?.identifier || 'main');
    },

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
    },

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
    },

    defaultRegionForContentType(definition) {
        const regions = Array.isArray(definition?.regions) ? definition.regions : [];
        return regions.find((region) => hasOption(customLayoutRegions, region)) || 'main';
    },

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
    },

    contentSurfaceClass(content, baseClass = 'grid gap-2', defaultSurface = 'none', paddedClass = 'p-4') {
        const surface = optionValue(customLayoutSurfaces, content?.props?.surface, defaultSurface);
        return {
            none: `${baseClass} px-1 py-2`,
            soft: `${baseClass} rounded-md bg-[var(--page-panel-soft)] ${paddedClass}`,
            card: `${baseClass} rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] ${paddedClass}`,
            accent: `${baseClass} rounded-md border border-[color-mix(in_srgb,var(--page-accent)_36%,var(--page-card-border))] bg-[color-mix(in_srgb,var(--page-accent)_12%,var(--page-card-bg))] ${paddedClass}`,
        }[surface];
    },

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
    },

};
