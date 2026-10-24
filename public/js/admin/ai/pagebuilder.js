import { trimValue } from '../ollama-client.js';
import { createElement, normalizeGeneratedCode } from './dom.js';
import { uiText } from './i18n.js';
import { assistantRuntime } from './runtime.js';

const pageBuilderInteractionElement = (builder) => [
    builder?.previewFrame,
    builder?.previewStage,
    builder?.blockCanvas,
    builder?.root?.querySelector?.('[data-preview-frame]'),
    builder?.root?.querySelector?.('[data-preview-stage]'),
    builder?.root,
].find((candidate) => candidate instanceof HTMLElement) || null;

const pageBuilderFocusElement = (builder) => [
    builder?.blockCanvas?.querySelector?.('.page-template-shell'),
    builder?.previewFrame?.querySelector?.('.page-template-shell'),
    builder?.previewStage?.querySelector?.('.page-template-shell'),
    builder?.root?.querySelector?.('[data-block-canvas] .page-template-shell'),
    builder?.root?.querySelector?.('[data-preview-frame] .page-template-shell'),
    pageBuilderInteractionElement(builder),
].find((candidate) => candidate instanceof HTMLElement) || null;

const pageBuilderTargetFromElement = (builder, element) => {
    const pick = element?.closest?.('[data-ai-pick-block-id]');
    const pickBlockId = trimValue(pick?.dataset?.aiPickBlockId || '');
    if (pickBlockId !== '') {
        return {
            blockId: pickBlockId,
            path: trimValue(pick.dataset.aiPickPath || ''),
            element: pick,
        };
    }

    const block = element?.closest?.('[data-edit-block], [data-choose-block]');
    const blockId = trimValue(block?.dataset?.editBlock || block?.dataset?.chooseBlock || '');
    if (blockId !== '') {
        return {blockId, path: '', element: block};
    }

    return null;
};

const pageBuilderContentForTarget = (builder, target) => {
    if (!builder || !target?.blockId) {
        return null;
    }

    if (target.path !== '' && typeof builder.contentAtPath === 'function') {
        const previousBlockId = builder.selectedBlockId;
        builder.selectedBlockId = target.blockId;
        const content = builder.contentAtPath(target.path);
        builder.selectedBlockId = previousBlockId;
        return content;
    }

    return builder.blockContent?.[target.blockId] || null;
};

const pageBuilderDefinitionForContent = (builder, content) => typeof builder?.contentTypeDefinition === 'function'
    ? builder.contentTypeDefinition(content?.type || '')
    : null;

const pageBuilderBlockForTarget = (builder, target) => {
    const blocks = builder?.selectedTemplate?.()?.blocks || [];
    return blocks.find((block) => block?.id === target?.blockId) || null;
};

const pageBuilderInspectableProps = (props) => {
    if (!props || typeof props !== 'object' || Array.isArray(props)) {
        return {};
    }

    return Object.fromEntries(Object.entries(props)
        .filter(([key]) => key !== 'children')
        .slice(0, 16)
        .map(([key, value]) => [
            key,
            value && typeof value === 'object' ? JSON.stringify(value).slice(0, 500) : value,
        ]));
};

const describePageBuilderTarget = (builder, target) => {
    const content = pageBuilderContentForTarget(builder, target);
    const definition = pageBuilderDefinitionForContent(builder, content);
    const block = pageBuilderBlockForTarget(builder, target);
    const props = content?.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
    const children = Array.isArray(props.children) ? props.children : [];

    return {
        success: true,
        area: 'pageBuilder',
        blockId: target?.blockId || '',
        nestedPath: target?.path || '',
        slot: block ? {
            id: block.id || '',
            label: builder?.uiText?.(block.label || '') || block.label || '',
            region: typeof builder?.blockRegion === 'function' ? builder.blockRegion(block) : (block.region || ''),
            size: block.size || '',
            accepts: Array.isArray(block.accepts) ? block.accepts : [],
        } : null,
        content: content ? {
            type: content.type || '',
            label: builder?.uiText?.(content.label || '') || content.label || '',
            previewTitle: builder?.uiText?.(content.previewTitle || '') || content.previewTitle || '',
            props: pageBuilderInspectableProps(props),
            childCount: children.length,
            childTypes: children.map((child) => child?.type || '').filter(Boolean).slice(0, 12),
        } : null,
        definition: definition ? {
            type: definition.type || '',
            label: builder?.uiText?.(definition.label || '') || definition.label || '',
            description: builder?.uiText?.(definition.description || '') || definition.description || '',
            fields: Array.isArray(definition.fields) ? definition.fields.map((field) => field?.name || '').filter(Boolean).slice(0, 16) : [],
            regions: Array.isArray(definition.regions) ? definition.regions : [],
        } : null,
        text: trimValue(target?.element?.innerText || '').replace(/\s+/g, ' ').slice(0, 500),
    };
};

export const pageBuilderInspectResultText = (result) => {
    if (!result || typeof result !== 'object') {
        return uiText('Show mode ended, but no element data was returned.');
    }

    if (result.cancelled) {
        return result.message || uiText('Show mode cancelled.');
    }

    if (result.success !== true) {
        return result.error || result.message || uiText('Show mode could not be started.');
    }

    const content = result.content || {};
    const definition = result.definition || {};
    const slot = result.slot || {};
    const lines = [
        uiText('Show mode selection:'),
        '',
        `- ${uiText('Block ID')}: ${result.blockId || '-'}`,
        result.nestedPath ? `- ${uiText('Nested path')}: ${result.nestedPath}` : '',
        `- ${uiText('Slot/region')}: ${slot.label || slot.region || '-'}`,
        `- ${uiText('Type')}: ${content.type || definition.type || '-'}`,
        `- ${uiText('Label')}: ${content.label || definition.label || '-'}`,
        definition.description ? `- ${uiText('Description')}: ${definition.description}` : '',
        Number.isFinite(content.childCount) ? `- ${uiText('Children')}: ${content.childCount}` : '',
        Array.isArray(content.childTypes) && content.childTypes.length > 0 ? `- ${uiText('Child types')}: ${content.childTypes.join(', ')}` : '',
        result.text ? `- ${uiText('Visible text')}: ${result.text}` : '',
    ].filter(Boolean);

    return lines.join('\n');
};

const ensurePageBuilderInspectStyles = () => {
    if (document.getElementById('nafcms-ai-pagebuilder-inspect-style')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'nafcms-ai-pagebuilder-inspect-style';
    style.textContent = `
        body.nafcms-ai-pagebuilder-inspecting {
            cursor: crosshair;
        }
        body.nafcms-ai-pagebuilder-inspecting *,
        body.nafcms-ai-pagebuilder-inspecting *::before,
        body.nafcms-ai-pagebuilder-inspecting *::after {
            cursor: crosshair !important;
        }
        .nafcms-ai-pagebuilder-inspect-dim {
            position: fixed;
            z-index: 9989;
            pointer-events: none;
            background: rgba(2, 6, 23, .54);
            backdrop-filter: blur(5px) saturate(.78);
            -webkit-backdrop-filter: blur(5px) saturate(.78);
        }
        .nafcms-ai-pagebuilder-inspect-focus {
            position: fixed;
            z-index: 9990;
            pointer-events: none;
            border: 2px solid rgba(96, 165, 250, .58);
            border-radius: 16px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .52), 0 0 0 999px rgba(2, 6, 23, .08), 0 24px 90px rgba(59, 130, 246, .28);
        }
        .nafcms-ai-pagebuilder-inspect-cursor {
            position: fixed;
            left: 0;
            top: 0;
            z-index: 9995;
            pointer-events: none;
            width: 48px;
            height: 48px;
            margin: -24px 0 0 -24px;
            border: 2px solid rgba(186, 230, 253, .98);
            border-radius: 999px;
            background: radial-gradient(circle at center, rgba(240, 249, 255, .18) 0 12%, rgba(14, 165, 233, .08) 13% 48%, transparent 49%);
            box-shadow:
                0 0 0 8px rgba(14, 165, 233, .18),
                0 0 28px rgba(56, 189, 248, .78),
                0 0 72px rgba(59, 130, 246, .44),
                inset 0 0 0 12px rgba(14, 165, 233, .08);
            opacity: 0;
            transform: translate3d(0, 0, 0) scale(.88);
            transition: opacity .08s ease;
            will-change: transform, opacity;
        }
        .nafcms-ai-pagebuilder-inspect-cursor::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 6px;
            height: 6px;
            transform: translate(-50%, -50%);
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, .28), 0 0 18px rgba(186, 230, 253, .98);
        }
        .nafcms-ai-pagebuilder-inspect-hint {
            position: fixed;
            left: 50%;
            top: 18px;
            z-index: 9994;
            max-width: min(560px, calc(100vw - 32px));
            transform: translateX(-50%);
            border: 1px solid rgba(96, 165, 250, .46);
            border-radius: 10px;
            background: rgba(15, 23, 42, .96);
            color: #e5e7eb;
            padding: 10px 13px;
            font: 600 13px/1.35 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            box-shadow: 0 18px 44px rgba(2, 6, 23, .42);
        }
        .nafcms-ai-pagebuilder-inspect-outline {
            position: fixed;
            z-index: 9993;
            pointer-events: none;
            border: 2px solid rgba(56, 189, 248, .96);
            border-radius: 12px;
            box-shadow: 0 0 0 6px rgba(14, 165, 233, .18), 0 20px 48px rgba(2, 6, 23, .28);
            transition: opacity .08s ease;
            will-change: transform, width, height, opacity;
        }
    `;
    document.head.appendChild(style);
};

const setBox = (node, box) => {
    node.style.left = `${Math.max(0, box.left)}px`;
    node.style.top = `${Math.max(0, box.top)}px`;
    node.style.width = `${Math.max(0, box.width)}px`;
    node.style.height = `${Math.max(0, box.height)}px`;
};

const paddedViewportBox = (element, padding = 18) => {
    const box = element.getBoundingClientRect();

    return {
        left: Math.max(0, box.left - padding),
        top: Math.max(0, box.top - padding),
        right: Math.min(window.innerWidth, box.right + padding),
        bottom: Math.min(window.innerHeight, box.bottom + padding),
    };
};

export const startPageBuilderInspectMode = ({hint = ''} = {}) => new Promise((resolve) => {
    const builder = window.NafCMS?.pageBuilder;
    const focus = pageBuilderFocusElement(builder);
    const interaction = pageBuilderInteractionElement(builder);
    if (!builder || !(focus instanceof HTMLElement) || !(interaction instanceof HTMLElement)) {
        resolve({success: false, error: uiText('PageBuilder is not active on the current page.')});
        return;
    }

    ensurePageBuilderInspectStyles();

    const drawerWasOpen = Boolean(builder.settingsOpen);
    if (drawerWasOpen && typeof builder.closeSettings === 'function') {
        builder.closeSettings();
    }
    document.dispatchEvent(new CustomEvent('nafcms:assistant-pick-mode', {detail: {active: true}}));

    const hintNode = createElement(
        'div',
        'nafcms-ai-pagebuilder-inspect-hint',
        trimValue(hint) || uiText('Show mode active: Click a block or element in the PageBuilder. Escape cancels.')
    );
    const dimTop = createElement('div', 'nafcms-ai-pagebuilder-inspect-dim');
    const dimRight = createElement('div', 'nafcms-ai-pagebuilder-inspect-dim');
    const dimBottom = createElement('div', 'nafcms-ai-pagebuilder-inspect-dim');
    const dimLeft = createElement('div', 'nafcms-ai-pagebuilder-inspect-dim');
    const focusFrame = createElement('div', 'nafcms-ai-pagebuilder-inspect-focus');
    const cursorRing = createElement('div', 'nafcms-ai-pagebuilder-inspect-cursor');
    const outline = createElement('div', 'nafcms-ai-pagebuilder-inspect-outline');
    outline.style.opacity = '0';
    document.body.classList.add('nafcms-ai-pagebuilder-inspecting');
    document.body.append(dimTop, dimRight, dimBottom, dimLeft, focusFrame, hintNode, cursorRing, outline);
    let cursorX = 0;
    let cursorY = 0;
    let cursorVisible = false;
    let cursorFrame = 0;
    let outlineTarget = null;
    let outlineFrame = 0;

    const updateSpotlight = () => {
        const box = paddedViewportBox(focus, 18);
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        setBox(dimTop, {left: 0, top: 0, width: viewportWidth, height: box.top});
        setBox(dimRight, {left: box.right, top: box.top, width: viewportWidth - box.right, height: box.bottom - box.top});
        setBox(dimBottom, {left: 0, top: box.bottom, width: viewportWidth, height: viewportHeight - box.bottom});
        setBox(dimLeft, {left: 0, top: box.top, width: box.left, height: box.bottom - box.top});
        setBox(focusFrame, {left: box.left, top: box.top, width: box.right - box.left, height: box.bottom - box.top});
    };

    const paintCursor = () => {
        cursorFrame = 0;
        cursorRing.style.opacity = cursorVisible ? '1' : '0';
        cursorRing.style.transform = `translate3d(${cursorX}px, ${cursorY}px, 0) scale(${cursorVisible ? 1 : 0.88})`;
    };

    const scheduleCursorPaint = () => {
        if (cursorFrame === 0) {
            cursorFrame = window.requestAnimationFrame(paintCursor);
        }
    };

    const paintOutline = () => {
        outlineFrame = 0;
        if (!(outlineTarget instanceof HTMLElement)) {
            outline.style.opacity = '0';
            return;
        }

        const box = outlineTarget.getBoundingClientRect();
        outline.style.opacity = '1';
        outline.style.transform = `translate3d(${Math.max(0, box.left)}px, ${Math.max(0, box.top)}px, 0)`;
        outline.style.width = `${Math.max(0, box.width)}px`;
        outline.style.height = `${Math.max(0, box.height)}px`;
    };

    const scheduleOutlinePaint = () => {
        if (outlineFrame === 0) {
            outlineFrame = window.requestAnimationFrame(paintOutline);
        }
    };

    const cleanup = (result) => {
        document.body.classList.remove('nafcms-ai-pagebuilder-inspecting');
        if (cursorFrame !== 0) {
            window.cancelAnimationFrame(cursorFrame);
        }
        if (outlineFrame !== 0) {
            window.cancelAnimationFrame(outlineFrame);
        }
        [dimTop, dimRight, dimBottom, dimLeft, focusFrame, hintNode, cursorRing, outline].forEach((node) => node.remove());
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('pointerdown', blockPageBuilderInteraction, true);
        document.removeEventListener('mousedown', blockPageBuilderInteraction, true);
        document.removeEventListener('mouseup', blockPageBuilderInteraction, true);
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('dblclick', blockPageBuilderInteraction, true);
        document.removeEventListener('keydown', onKeyDown, true);
        window.removeEventListener('resize', updateSpotlight, true);
        window.removeEventListener('scroll', updateSpotlight, true);
        if (drawerWasOpen && typeof builder.openSettings === 'function') {
            builder.openSettings();
        }
        document.dispatchEvent(new CustomEvent('nafcms:assistant-pick-mode', {detail: {active: false}}));
        resolve(result);
    };

    const moveOutline = (element) => {
        outlineTarget = element instanceof HTMLElement ? element : null;
        scheduleOutlinePaint();
    };

    const eventInsideBuilder = (event) => interaction.contains(event.target);

    const onMove = (event) => {
        cursorX = event.clientX;
        cursorY = event.clientY;
        cursorVisible = true;
        scheduleCursorPaint();
        const target = pageBuilderTargetFromElement(builder, event.target);
        moveOutline(target?.element || null);
    };

    const blockPageBuilderInteraction = (event) => {
        if (eventInsideBuilder(event)) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    const onClick = (event) => {
        if (eventInsideBuilder(event)) {
            event.preventDefault();
            event.stopPropagation();
        }

        const target = pageBuilderTargetFromElement(builder, event.target);
        if (!target) {
            return;
        }

        builder.selectedBlockId = target.blockId;
        builder.selectedNestedPath = target.path || '';
        builder.renderSettings?.();
        cleanup(describePageBuilderTarget(builder, target));
    };

    const onKeyDown = (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        event.preventDefault();
        cleanup({success: false, cancelled: true, message: uiText('Show mode cancelled.')});
    };

    document.addEventListener('mousemove', onMove, true);
    document.addEventListener('pointerdown', blockPageBuilderInteraction, true);
    document.addEventListener('mousedown', blockPageBuilderInteraction, true);
    document.addEventListener('mouseup', blockPageBuilderInteraction, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('dblclick', blockPageBuilderInteraction, true);
    document.addEventListener('keydown', onKeyDown, true);
    window.addEventListener('resize', updateSpotlight, true);
    window.addEventListener('scroll', updateSpotlight, true);
    updateSpotlight();
});

export const registerPageBuilderAssistantActions = () => {
    const builder = window.NafCMS?.pageBuilder;
    const runtime = assistantRuntime();
    if (!builder || builder.__assistantActionsRegistered) {
        return;
    }

    builder.__assistantActionsRegistered = true;
    runtime.registerContext('pageBuilder', () => ({
        area: 'pageBuilder',
        pageId: builder.savedId ? String(builder.savedId) : '',
        layoutId: builder.templateId || '',
        selectedBlockId: builder.selectedBlockId || '',
        selectedRegion: builder.selectedBlock?.()?.region || '',
        availableBlockModules: Array.isArray(builder.contentTypes)
            ? builder.contentTypes.filter((type) => type?.insertable !== false).map((type) => type.type || type.id || '').filter(Boolean)
            : [],
        availableBlockModuleDefinitions: Array.isArray(builder.contentTypes)
            ? builder.contentTypes.filter((type) => type?.insertable !== false).map((type) => ({
                type: type.type || type.id || '',
                label: typeof builder.uiText === 'function' ? builder.uiText(type.label || '') : (type.label || ''),
                description: typeof builder.uiText === 'function' ? builder.uiText(type.description || '') : (type.description || ''),
                regions: Array.isArray(type.regions) ? type.regions : [],
                unique: type.unique === true,
                fields: Array.isArray(type.fields) ? type.fields.map((field) => field?.name || '').filter(Boolean) : [],
            })).filter((type) => trimValue(type.type) !== '')
            : [],
        theme: typeof builder.currentThemeId === 'function' ? builder.currentThemeId() : '',
        pagePayload: typeof builder.pagePayload === 'function' ? builder.pagePayload() : null,
    }));
    runtime.register('pagebuilder.inspectElement', {
        description: 'Starts PageBuilder show mode and returns information about the clicked block or nested element',
        parameters: {
            hint: 'string',
        },
        handler: (payload = {}) => startPageBuilderInspectMode(payload),
    });
    runtime.register('pagebuilder.addBlock', {
        description: 'Adds a block to the current page builder view',
        parameters: {
            region: 'string',
            blockType: 'string',
            config: 'object',
        },
        handler: ({region = '', blockType = '', config = {}} = {}) => {
            if (typeof builder.addCustomSlot !== 'function') {
                throw new Error('The current page builder cannot add custom blocks.');
            }

            builder.addCustomSlot(blockType);
            if (builder.selectedBlockId && config && typeof config === 'object' && !Array.isArray(config)) {
                builder.blockContent[builder.selectedBlockId] = {
                    ...(builder.blockContent[builder.selectedBlockId] || {}),
                    props: {
                        ...(builder.blockContent[builder.selectedBlockId]?.props || {}),
                        ...config,
                    },
                };
            }
            if (builder.selectedBlockId && region && typeof builder.updateCustomSlot === 'function') {
                builder.updateCustomSlot(builder.selectedBlockId, {region});
            }
            builder.persistState?.();
            builder.render?.();

            return {success: true, blockId: builder.selectedBlockId};
        },
    });
    runtime.register('pagebuilder.updateBlock', {
        description: 'Updates a block in the current page builder view',
        parameters: {
            blockId: 'string',
            config: 'object',
        },
        handler: ({blockId = '', config = {}} = {}) => {
            const id = String(blockId || builder.selectedBlockId || '');
            if (id === '' || !builder.blockContent[id]) {
                throw new Error('The requested block is not available in the current page builder.');
            }
            if (!config || typeof config !== 'object' || Array.isArray(config)) {
                throw new Error('Block update config must be an object.');
            }

            builder.blockContent[id] = {
                ...builder.blockContent[id],
                props: {
                    ...(builder.blockContent[id]?.props || {}),
                    ...config,
                },
            };
            builder.selectedBlockId = id;
            builder.persistState?.();
            builder.render?.();

            return {success: true, blockId: id};
        },
    });
    runtime.register('pagebuilder.setTheme', {
        description: 'Sets the current page builder page theme',
        parameters: {
            themeId: 'string',
        },
        handler: ({themeId = ''} = {}) => {
            const id = trimValue(themeId);
            if (id === '') {
                throw new Error('Theme id is required.');
            }

            builder.persistedValues.themeId = id;
            builder.persistState?.();
            builder.render?.();

            return {success: true, themeId: id};
        },
    });
};
