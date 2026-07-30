import {
    clone,
    customLayoutRegions,
    customLayoutSurfaces,
    hasOption,
    layoutBlockTypes,
    layoutChildContainerStyles,
    optionValue,
    recentContentTypesKey,
} from './shared.js';

export const contentModelMethods = {
    selectedBlock() {
        const template = this.selectedTemplate();
        return this.layoutBlocks(template).find((block) => block.id === this.selectedBlockId) || null;
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

    customChromeSlotType(block = this.selectedBlock()) {
        if (!this.usesCustomLayout() || !block) {
            return '';
        }

        const region = typeof this.customSlotRegion === 'function' ? this.customSlotRegion(block) : this.blockRegion(block);
        const acceptedTypes = Array.isArray(block?.accepts) ? block.accepts : [];
        const defaultContentType = String(block?.defaultContentType || '');
        const contentType = String(this.blockContent?.[block.id]?.type || '');

        if (region === 'header' && (defaultContentType === 'header' || contentType === 'header' || acceptedTypes.includes('header'))) {
            return 'header';
        }
        if (region === 'footer' && (defaultContentType === 'footer' || contentType === 'footer' || acceptedTypes.includes('footer'))) {
            return 'footer';
        }

        return '';
    },

    contentTypeSupportsBlock(type, block = this.selectedBlock(), template = this.selectedTemplate()) {
        const regions = Array.isArray(type?.regions) ? type.regions : [];
        const acceptedTypes = Array.isArray(block?.accepts) ? block.accepts : [];
        const templateKinds = Array.isArray(type?.templateKinds) ? type.templateKinds : [];
        const contentTypes = Array.isArray(type?.contentTypes) ? type.contentTypes : [];
        const chromeTypes = {
            header: ['header'],
            footer: ['footer', 'navigation', 'text'],
        };
        const customRegion = this.usesCustomLayout(template) && typeof this.customSlotRegion === 'function'
            ? this.customSlotRegion(block)
            : '';

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

        if (customRegion in chromeTypes && !chromeTypes[customRegion].includes(type?.type)) {
            return false;
        }

        return true;
    },

    recentContentTypes() {
        try {
            const values = JSON.parse(window.localStorage.getItem(recentContentTypesKey) || '[]');
            return Array.isArray(values)
                ? values.map((value) => String(value || '').trim()).filter((value) => value !== '')
                : [];
        } catch (error) {
            return [];
        }
    },

    rememberContentType(type) {
        const normalized = String(type || '').trim();
        if (normalized === '') {
            return;
        }

        const recent = [
            normalized,
            ...this.recentContentTypes().filter((value) => value !== normalized),
        ].slice(0, 12);

        try {
            window.localStorage.setItem(recentContentTypesKey, JSON.stringify(recent));
        } catch (error) {}
    },

    sortContentTypesByRecent(types) {
        const rank = new Map(this.recentContentTypes().map((type, index) => [type, index]));
        if (rank.size === 0) {
            return types;
        }

        return types
            .map((type, index) => ({type, index, rank: rank.has(type?.type) ? rank.get(type.type) : Number.MAX_SAFE_INTEGER}))
            .sort((left, right) => left.rank - right.rank || left.index - right.index)
            .map((entry) => entry.type);
    },

    compatibleContentTypes(block = this.selectedBlock()) {
        return this.sortContentTypesByRecent(this.contentTypes.filter((type) => this.contentTypeSupportsBlock(type, block)));
    },

    contentTypeDefinition(type) {
        return this.contentTypes.find((item) => item.type === type) || null;
    },

    navigationChoice(identifier, language = '') {
        const normalizedIdentifier = String(identifier || '').trim();
        if (normalizedIdentifier === '') {
            return null;
        }

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

    selectedFooterNavigationIdentifier() {
        return String(this.persistedValues.defaultFooterNavigationIdentifier || this.selectedDefaultNavigationIdentifier());
    },

    normalizeNavigationSelections() {
        const identifiers = new Set(this.navigationChoices
            .map((navigation) => String(navigation?.identifier || '').trim())
            .filter(Boolean));
        const configuredDefault = String(this.defaultNavigationIdentifier || '').trim();
        const fallback = identifiers.has(configuredDefault)
            ? configuredDefault
            : String(this.navigationChoices[0]?.identifier || 'main');

        if (!identifiers.has(String(this.persistedValues.defaultNavigationIdentifier || '').trim())) {
            this.persistedValues.defaultNavigationIdentifier = fallback;
        }
        if (!identifiers.has(String(this.persistedValues.defaultFooterNavigationIdentifier || '').trim())) {
            this.persistedValues.defaultFooterNavigationIdentifier = this.selectedDefaultNavigationIdentifier();
        }

        const normalizeContent = (content) => {
            if (!content || typeof content !== 'object' || Array.isArray(content)) {
                return;
            }

            const props = content.props;
            if (!props || typeof props !== 'object' || Array.isArray(props)) {
                return;
            }

            if ('navigationIdentifier' in props) {
                const identifier = String(props.navigationIdentifier || '').trim();
                const permitsEmpty = content.type === 'header';
                if ((!permitsEmpty || identifier !== '') && !identifiers.has(identifier)) {
                    props.navigationIdentifier = content.type === 'footer'
                        ? this.selectedFooterNavigationIdentifier()
                        : this.selectedDefaultNavigationIdentifier();
                }
            }
            if (content.type === 'navigation' && !identifiers.has(String(props.identifier || '').trim())) {
                props.identifier = this.selectedDefaultNavigationIdentifier();
            }

            if (Array.isArray(props.children)) {
                props.children.forEach(normalizeContent);
            }
        };

        Object.values(this.blockContent).forEach(normalizeContent);
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

        return this.contentFromDefinition(definition, block);
    },

    contentFromDefinition(definition, block = null) {
        const props = clone(definition.defaults || {});
        let navigationIdentifier = this.selectedDefaultNavigationIdentifier();
        if (definition.type === 'header') {
            navigationIdentifier = String(props.navigationIdentifier || this.selectedDefaultNavigationIdentifier());
        } else if (definition.type === 'footer' || block?.id === 'site_footer') {
            navigationIdentifier = this.selectedFooterNavigationIdentifier();
        }

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
        if (definition?.type === 'header' && regions.includes('header')) {
            return 'header';
        }
        if (definition?.type === 'footer' && regions.includes('footer')) {
            return 'footer';
        }
        if (regions.includes('main')) {
            return 'main';
        }

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
        const surface = optionValue(this.surfaceOptions(), content?.props?.surface, String(content?.props?.surface || defaultSurface));
        const classes = {
            none: `${baseClass} px-1 py-2`,
            soft: `${baseClass} rounded-md bg-[var(--page-panel-soft)] ${paddedClass}`,
            card: `${baseClass} rounded-md border border-[var(--page-card-border)] bg-[var(--page-card-bg)] ${paddedClass}`,
            accent: `${baseClass} rounded-md border border-[color-mix(in_srgb,var(--page-accent)_36%,var(--page-card-border))] bg-[color-mix(in_srgb,var(--page-accent)_12%,var(--page-card-bg))] ${paddedClass}`,
        };

        const safeSurface = String(surface || 'none').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
        return classes[surface] || `${baseClass} page-block--surface-${safeSurface} ${paddedClass}`;
    },

    compactContentPreview(type) {
        const contentType = String(type?.type || '');
        const props = type?.defaults || {};
        const scene = (content, background = 'linear-gradient(135deg,rgba(15,23,42,.98),rgba(2,6,23,.92))') => `
            <span style="position:relative;display:block;width:100%;height:100%;min-height:64px;overflow:hidden;background:${background};">
                ${content}
            </span>
        `;
        const layer = (style, content = '') => `<span style="position:absolute;display:block;${style}">${content}</span>`;
        const bar = (left, top, width, height = 5, color = 'rgba(100,116,139,.95)') => (
            layer(`left:${left};top:${top};width:${width};height:${height}px;border-radius:999px;background:${color};`)
        );
        const card = (left, top, width, height, color = 'rgba(15,23,42,.9)', extra = '') => (
            layer(`left:${left};top:${top};width:${width};height:${height};border:1px solid rgba(71,85,105,.9);border-radius:9px;background:${color};${extra}`)
        );
        const textScene = (strong = false) => scene(`
            ${bar('16px', strong ? '16px' : '18px', strong ? '52%' : '42%', strong ? 10 : 7, 'rgba(148,163,184,.95)')}
            ${bar('16px', strong ? '33px' : '32px', '82%', 6, 'rgba(71,85,105,.95)')}
            ${bar('16px', strong ? '45px' : '43px', strong ? '66%' : '58%', 6, 'rgba(30,41,59,.98)')}
        `);

        if (contentType === 'columns') {
            const columnCount = Math.max(2, Math.min(4, Number(props.columns || 2)));
            return scene(`
                ${layer(`left:12px;right:12px;top:10px;height:44px;display:grid;grid-template-columns:repeat(${columnCount},minmax(0,1fr));gap:8px;`, Array.from({length: columnCount}).map((_, index) => `
                    <span style="display:grid;align-content:end;gap:5px;min-width:0;border:1px solid rgba(71,85,105,.95);border-radius:10px;background:${index === 0 ? 'rgba(30,64,175,.35)' : 'rgba(15,23,42,.86)'};padding:9px;">
                        <span style="display:block;width:76%;height:6px;border-radius:999px;background:rgba(148,163,184,.88);"></span>
                        <span style="display:block;width:100%;height:5px;border-radius:999px;background:rgba(51,65,85,.95);"></span>
                    </span>
                `).join(''))}
            `);
        }

        if (contentType === 'grid') {
            return scene(`
                ${layer('left:14px;right:14px;top:11px;height:42px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-template-rows:repeat(2,minmax(0,1fr));gap:7px;', Array.from({length: 6}).map((_, index) => `
                    <span style="border:1px solid rgba(71,85,105,.9);border-radius:8px;background:${index % 3 === 0 ? 'rgba(59,130,246,.34)' : 'rgba(15,23,42,.9)'};"></span>
                `).join(''))}
            `);
        }

        if (contentType === 'article-title' || contentType === 'heading') {
            return textScene(true);
        }

        if (contentType === 'article-meta') {
            return scene(`
                ${layer('left:14px;top:16px;width:58px;height:24px;border-radius:999px;background:rgba(59,130,246,.28);border:1px solid rgba(96,165,250,.5);')}
                ${layer('left:84px;top:16px;width:70px;height:24px;border-radius:999px;background:rgba(15,23,42,.92);border:1px solid rgba(71,85,105,.9);')}
                ${layer('left:166px;top:16px;width:58px;height:24px;border-radius:999px;background:rgba(15,23,42,.92);border:1px solid rgba(71,85,105,.9);')}
                ${bar('236px', '25px', '90px', 6, 'rgba(51,65,85,.95)')}
            `);
        }

        if (contentType === 'articles' || contentType === 'article-related') {
            return scene(`
                ${Array.from({length: 3}).map((_, index) => {
                    const left = 14 + index * 30;
                    return `
                        ${card(`${left}%`, '11px', '25%', '42px', 'rgba(15,23,42,.88)')}
                        ${bar(`${left + 4}%`, '25px', '11%', 6, 'rgba(148,163,184,.9)')}
                        ${bar(`${left + 4}%`, '39px', '16%', 5, 'rgba(51,65,85,.95)')}
                    `;
                }).join('')}
            `);
        }

        if (contentType.includes('article')) {
            return textScene();
        }

        if (contentType === 'hero') {
            return scene(`
                ${layer('right:14px;top:10px;width:74px;height:44px;border-radius:14px;background:linear-gradient(135deg,rgba(59,130,246,.45),rgba(14,165,233,.08));')}
                ${bar('18px', '17px', '46%', 10, 'rgba(226,232,240,.94)')}
                ${bar('18px', '34px', '64%', 6, 'rgba(100,116,139,.95)')}
                ${layer('left:18px;top:47px;width:58px;height:13px;border-radius:999px;background:var(--admin-page-accent);')}
            `, 'linear-gradient(135deg,rgba(30,64,175,.7),rgba(2,6,23,.96))');
        }

        if (contentType === 'image' || contentType === 'media') {
            return scene(`
                ${layer('left:13px;top:12px;width:82px;height:40px;border-radius:10px;border:1px dashed rgba(125,211,252,.42);background:linear-gradient(135deg,rgba(14,165,233,.3),rgba(15,23,42,.96));')}
                ${layer('left:25px;top:36px;width:28px;height:14px;border-radius:5px;background:rgba(14,165,233,.34);clip-path:polygon(0 100%,45% 20%,72% 68%,100% 38%,100% 100%);')}
                ${bar('124px', '20px', '44%', 7, 'rgba(148,163,184,.9)')}
                ${bar('124px', '36px', '58%', 6, 'rgba(51,65,85,.95)')}
                ${bar('124px', '49px', '34%', 5, 'rgba(30,41,59,.98)')}
            `);
        }

        if (contentType === 'chart') {
            return scene(`
                ${layer('left:14px;bottom:8px;width:18%;height:28px;border-radius:7px 7px 0 0;background:#0ea5e9;')}
                ${layer('left:31%;bottom:8px;width:18%;height:42px;border-radius:7px 7px 0 0;background:#10b981;')}
                ${layer('left:50%;bottom:8px;width:18%;height:18px;border-radius:7px 7px 0 0;background:#f59e0b;')}
                ${layer('left:69%;bottom:8px;width:18%;height:48px;border-radius:7px 7px 0 0;background:#8b5cf6;')}
            `);
        }

        if (contentType === 'stats') {
            return scene(`
                ${Array.from({length: 3}).map((_, index) => {
                    const left = 13 + index * 33;
                    return `
                        ${card(`${left}%`, '12px', '25%', '48px', 'rgba(15,23,42,.9)')}
                        ${bar(`${left + 4}%`, '27px', '9%', 10, 'rgba(226,232,240,.92)')}
                        ${bar(`${left + 4}%`, '45px', '15%', 5, 'rgba(71,85,105,.95)')}
                    `;
                }).join('')}
            `);
        }

        if (contentType === 'header') {
            return scene(`
                ${layer('left:0;top:0;width:100%;height:64px;background:linear-gradient(90deg,rgba(37,99,235,.85),rgba(30,64,175,.82));')}
                ${bar('18px', '27px', '52px', 10, 'rgba(255,255,255,.9)')}
                ${bar('calc(100% - 150px)', '29px', '38px', 7, 'rgba(255,255,255,.7)')}
                ${bar('calc(100% - 99px)', '29px', '34px', 7, 'rgba(255,255,255,.46)')}
                ${bar('calc(100% - 52px)', '29px', '32px', 7, 'rgba(255,255,255,.46)')}
            `);
        }

        if (contentType === 'footer') {
            return scene(`
                ${layer('left:0;bottom:0;width:100%;height:44px;border-top:1px solid rgba(71,85,105,.8);background:rgba(2,6,23,.96);')}
                ${bar('18px', '35px', '74px', 7, 'rgba(100,116,139,.9)')}
                ${bar('calc(100% - 110px)', '35px', '72px', 7, 'rgba(148,163,184,.78)')}
            `);
        }

        if (contentType === 'navigation') {
            return scene(`
                ${layer('left:18px;top:19px;width:58px;height:26px;border-radius:999px;background:rgba(59,130,246,.32);border:1px solid rgba(96,165,250,.5);')}
                ${layer('left:86px;top:19px;width:54px;height:26px;border-radius:999px;background:rgba(15,23,42,.92);border:1px solid rgba(71,85,105,.9);')}
                ${layer('left:150px;top:19px;width:54px;height:26px;border-radius:999px;background:rgba(15,23,42,.92);border:1px solid rgba(71,85,105,.9);')}
                ${layer('left:214px;top:19px;width:54px;height:26px;border-radius:999px;background:rgba(15,23,42,.92);border:1px solid rgba(71,85,105,.9);')}
            `);
        }

        if (contentType === 'form' || contentType === 'contact-form') {
            return scene(`
                ${card('16px', '12px', '68%', '11px', 'rgba(2,6,23,.86)', 'border-radius:999px;')}
                ${card('16px', '29px', '68%', '11px', 'rgba(2,6,23,.86)', 'border-radius:999px;')}
                ${card('16px', '46px', '48%', '11px', 'rgba(2,6,23,.86)', 'border-radius:999px;')}
                ${layer('right:18px;top:29px;width:54px;height:17px;border-radius:999px;background:var(--admin-page-accent);')}
            `);
        }

        if (contentType === 'map') {
            return scene(`
                ${layer('left:-20px;top:8px;width:130%;height:1px;background:rgba(96,165,250,.28);transform:rotate(18deg);')}
                ${layer('left:-20px;top:42px;width:130%;height:1px;background:rgba(16,185,129,.22);transform:rotate(-14deg);')}
                ${layer('left:50%;top:50%;width:22px;height:22px;transform:translate(-50%,-50%) rotate(45deg);border-radius:16px 16px 16px 3px;background:var(--admin-page-accent);box-shadow:0 0 0 8px rgba(59,130,246,.12);')}
            `, 'linear-gradient(135deg,rgba(15,23,42,.96),rgba(2,6,23,.92))');
        }

        if (contentType === 'embed') {
            return scene(`
                ${card('12px', '9px', 'calc(100% - 24px)', '48px', 'rgba(2,6,23,.78)')}
                ${layer('left:20px;top:18px;width:8px;height:8px;border-radius:999px;background:rgba(100,116,139,.9);')}
                ${layer('left:34px;top:18px;width:8px;height:8px;border-radius:999px;background:rgba(100,116,139,.9);')}
                ${layer('left:48px;top:18px;width:8px;height:8px;border-radius:999px;background:rgba(100,116,139,.9);')}
                ${layer('left:22px;top:34px;width:calc(100% - 44px);height:16px;border:1px solid rgba(71,85,105,.9);border-radius:999px;background:rgba(15,23,42,.9);display:grid;place-items:center;color:rgba(148,163,184,.88);font-size:12px;font-weight:800;', '&lt;/&gt;')}
            `);
        }

        if (contentType === 'cta') {
            return scene(`
                ${bar('18px', '19px', '48%', 9, 'rgba(226,232,240,.94)')}
                ${bar('18px', '37px', '66%', 6, 'rgba(71,85,105,.95)')}
                ${layer('right:20px;top:24px;width:76px;height:24px;border-radius:999px;background:var(--admin-page-accent);')}
            `);
        }

        if (contentType === 'feature' || contentType === 'aside') {
            return scene(`
                ${layer('left:16px;top:15px;width:34px;height:34px;border-radius:10px;background:rgba(59,130,246,.3);border:1px solid rgba(96,165,250,.5);')}
                ${bar('64px', '19px', '56%', 7, 'rgba(148,163,184,.95)')}
                ${bar('64px', '34px', '72%', 6, 'rgba(71,85,105,.95)')}
                ${bar('64px', '47px', '50%', 6, 'rgba(30,41,59,.98)')}
            `);
        }

        if (contentType === 'quote') {
            return scene(`
                ${layer('left:16px;top:0;font-size:66px;line-height:1;color:var(--admin-page-accent);font-weight:900;opacity:.9;', '"')}
                ${bar('70px', '22px', '70%', 8, 'rgba(148,163,184,.9)')}
                ${bar('70px', '40px', '58%', 7, 'rgba(71,85,105,.95)')}
            `);
        }

        if (contentType === 'section') {
            return scene(`
                ${card('18px', '12px', 'calc(100% - 36px)', '44px', 'rgba(15,23,42,.88)')}
                ${bar('34px', '29px', '38%', 8, 'rgba(148,163,184,.95)')}
                ${bar('34px', '46px', '68%', 6, 'rgba(71,85,105,.95)')}
            `);
        }

        if (contentType === 'stack') {
            return scene(`
                ${card('16px', '8px', 'calc(100% - 32px)', '14px', 'rgba(30,41,59,.84)')}
                ${card('16px', '27px', 'calc(100% - 32px)', '14px', 'rgba(15,23,42,.92)')}
                ${card('16px', '46px', 'calc(100% - 32px)', '14px', 'rgba(30,41,59,.84)')}
            `);
        }

        return textScene();
    },

};
