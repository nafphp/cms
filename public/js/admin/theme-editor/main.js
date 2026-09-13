import {
    cssVariables,
    escapeRegExp,
    hexColorPattern,
    jsGlobals,
    jsTypeMembers,
    knownClasses,
    normalizeLineEndings,
    phpString,
    slug,
    tailwindUtilityWords,
} from './vocabulary.js';
import {
    findThemeSelectorIds,
    replaceThemeSelectors,
} from './selectors.js';

(function () {

    class ThemeEditor {
        constructor(root) {
            this.root = root;
            this.themeId = root.querySelector('input[name="id"]');
            this.surfaceList = root.querySelector('[data-surface-list]');
            this.surfaceTemplate = root.querySelector('[data-surface-template]');
            this.themeLabel = root.querySelector('[data-theme-label]');
            this.themeIdentifier = root.querySelector('[data-theme-identifier]');
            this.cssTextarea = root.querySelector('[data-theme-css]');
            this.jsTextarea = root.querySelector('[data-theme-js]');
            this.exportCode = root.querySelector('[data-theme-export]');
            this.scopeCode = root.querySelector('[data-theme-scope]');
            this.cssStatus = root.querySelector('[data-theme-css-status]');
            this.jsStatus = root.querySelector('[data-theme-js-status]');
            this.selectorRename = root.querySelector('[data-theme-selector-rename]');
            this.selectorRenameText = root.querySelector('[data-theme-selector-rename-text]');
            this.selectorRenameButton = root.querySelector('[data-theme-selector-rename-button]');
            this.resetStateButton = root.querySelector('[data-theme-reset-state]');
            this.colorPopover = root.querySelector('[data-theme-color-popover]');
            this.colorPicker = root.querySelector('[data-theme-color-picker]');
            this.cssMirror = null;
            this.jsMirror = null;
            this.storageKey = `nafcms:theme-editor:draft:${window.location.pathname}${window.location.search || ''}`;
            this.restoring = false;
            this.persistTimer = null;
            this.lastThemeId = '';
            this.pendingSelectorRename = null;
            this.cssColorTarget = null;
        }

        init() {
            this.initCodeMirror();
            this.restoreDraft();
            this.lastThemeId = this.currentThemeId();
            this.bindThemeFields();
            this.bindSurfaceRows();
            this.bindAddSurface();
            this.bindSelectorRename();
            this.bindResetState();
            this.bindColorPicker();
            this.bindTabs();
            this.bindTextareaFallbacks();
            this.root.addEventListener('submit', (event) => {
                this.cssMirror?.save();
                this.jsMirror?.save();
                this.clearDraft();
            });
            this.renumberSurfaces();
            this.refreshGeneratedThemeCode();
            this.lintAll();
        }

        initCodeMirror() {
            if (!window.CodeMirror) {
                return;
            }

            this.installHints();
            this.cssMirror = this.createMirror(this.cssTextarea, 'css');
            this.jsMirror = this.createMirror(this.jsTextarea, 'javascript');
        }

        createMirror(textarea, mode) {
            if (!textarea) {
                return null;
            }

            const mirror = window.CodeMirror.fromTextArea(textarea, {
                mode,
                lineNumbers: true,
                lineWrapping: true,
                autoCloseBrackets: true,
                matchBrackets: true,
                indentUnit: 4,
                tabSize: 4,
                extraKeys: {
                    'Ctrl-Space': () => this.showCompletion(mirror, mode, true),
                    'Cmd-Space': () => this.showCompletion(mirror, mode, true),
                },
                hintOptions: mode === 'css'
                    ? {hint: this.cssHint.bind(this)}
                    : {hint: this.jsHint.bind(this)},
            });

            mirror.getWrapperElement().classList.add('theme-code-editor');
            mirror.on('change', (_instance, change) => {
                textarea.value = mirror.getValue();
                if (textarea === this.cssTextarea) {
                    textarea.dataset.generatedDefault = mirror.getValue() === this.defaultCss(this.currentThemeId()) ? '1' : '0';
                    this.lintCss();
                    this.updateContextualColorPicker();
                } else {
                    this.lintJs();
                }
                this.persistDraft();
                this.maybeShowCompletion(mirror, mode, change);
            });

            return mirror;
        }

        bindThemeFields() {
            if (!this.themeLabel || !this.themeIdentifier) {
                return;
            }

            this.themeIdentifier.dataset.manual = this.themeIdentifier.dataset.manual || (this.themeIdentifier.value.trim() !== '' ? '1' : '0');
            this.themeIdentifier.addEventListener('input', () => {
                this.themeIdentifier.dataset.manual = this.themeIdentifier.value.trim() !== '' ? '1' : '0';
                this.themeIdentifier.value = slug(this.themeIdentifier.value);
                this.refreshGeneratedThemeCode();
                this.trackThemeIdentifierChange();
                this.lintAll();
                this.persistDraft();
            });
            this.themeLabel.addEventListener('input', () => {
                if (this.themeIdentifier.dataset.manual !== '1') {
                    this.themeIdentifier.value = slug(this.themeLabel.value);
                }
                this.refreshGeneratedThemeCode();
                this.trackThemeIdentifierChange();
                this.lintAll();
                this.persistDraft();
            });
        }

        bindSurfaceRows() {
            this.surfaceList?.querySelectorAll('[data-surface-row]').forEach((row) => this.bindSurfaceRow(row));
        }

        bindSurfaceRow(row) {
            if (row.dataset.bound === '1') {
                return;
            }
            row.dataset.bound = '1';

            const label = row.querySelector('[data-surface-label]');
            const key = row.querySelector('[data-surface-key]');
            if (!label || !key) {
                return;
            }

            key.dataset.manual = key.value.trim() !== '' ? '1' : '0';
            key.addEventListener('input', () => {
                key.dataset.manual = key.value.trim() !== '' ? '1' : '0';
                key.value = slug(key.value);
                this.refreshGeneratedThemeCode();
                this.lintAll();
                this.persistDraft();
            });
            label.addEventListener('input', () => {
                if (key.dataset.manual !== '1') {
                    key.value = slug(label.value);
                }
                this.refreshGeneratedThemeCode();
                this.lintAll();
                this.persistDraft();
            });
            row.querySelector('[data-remove-surface]')?.addEventListener('click', () => {
                row.remove();
                this.renumberSurfaces();
                this.refreshGeneratedThemeCode();
                this.lintAll();
                this.persistDraft();
            });
        }

        bindAddSurface() {
            this.root.querySelector('[data-add-surface]')?.addEventListener('click', () => {
                if (!this.surfaceTemplate || !this.surfaceList) {
                    return;
                }

                const fragment = this.surfaceTemplate.content.cloneNode(true);
                const row = fragment.querySelector('[data-surface-row]');
                this.surfaceList.appendChild(fragment);
                if (row) {
                    this.bindSurfaceRow(row);
                    row.querySelector('[data-surface-label]')?.focus();
                }
                this.renumberSurfaces();
                this.refreshGeneratedThemeCode();
                this.lintAll();
                this.persistDraft();
            });
        }

        bindSelectorRename() {
            this.selectorRenameButton?.addEventListener('click', () => {
                if (!this.pendingSelectorRename) {
                    return;
                }

                const {oldId, newId} = this.pendingSelectorRename;
                const transformed = replaceThemeSelectors(this.getCss(), oldId, newId);
                this.setCss(transformed);
                if (this.cssTextarea) {
                    this.cssTextarea.dataset.generatedDefault = transformed === this.defaultCss(this.currentThemeId()) ? '1' : '0';
                }
                this.pendingSelectorRename = null;
                this.hideSelectorRename();
                this.lintCss();
                this.persistDraft();
                this.cssMirror?.focus();
            });
        }

        bindResetState() {
            this.resetStateButton?.addEventListener('click', () => {
                this.clearDraft();
                this.hideSelectorRename();
                this.lastThemeId = this.currentThemeId();
                this.rescanThemeSelectorMismatch();
                this.lintAll();
                this.cssMirror?.focus();
            });
        }

        bindColorPicker() {
            if (!this.colorPicker || !this.colorPopover) {
                return;
            }

            const remember = () => this.rememberCssColorTarget();
            this.colorPicker.addEventListener('pointerdown', remember);
            this.colorPicker.addEventListener('focus', remember);
            this.colorPicker.addEventListener('input', () => this.insertCssColor(this.colorPicker.value));
            this.colorPicker.addEventListener('change', () => this.insertCssColor(this.colorPicker.value));
            document.addEventListener('pointerdown', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }
                if (this.colorPopover.contains(target) || this.cssMirror?.getWrapperElement().contains(target) || target === this.cssTextarea) {
                    return;
                }
                this.hideColorPicker();
            });

            this.cssMirror?.on('cursorActivity', () => this.updateContextualColorPicker());
            this.cssMirror?.on('blur', () => window.setTimeout(() => {
                if (!this.colorPopover.contains(document.activeElement)) {
                    this.hideColorPicker();
                }
            }, 120));
            this.cssTextarea?.addEventListener('input', () => this.updateContextualColorPicker());
            this.cssTextarea?.addEventListener('keyup', () => this.updateContextualColorPicker());
            this.cssTextarea?.addEventListener('mouseup', () => this.updateContextualColorPicker());
            this.updateContextualColorPicker();
        }

        bindTabs() {
            const tabs = this.root.querySelectorAll('[data-theme-tab]');
            const panels = this.root.querySelectorAll('[data-theme-panel]');
            tabs.forEach((tab) => tab.addEventListener('click', () => {
                const active = tab.dataset.themeTab || 'css';
                tabs.forEach((item) => {
                    item.classList.toggle('active', item === tab);
                    item.classList.toggle('border-[var(--admin-page-accent)]', item === tab);
                    item.classList.toggle('border-transparent', item !== tab);
                    item.classList.toggle('text-white', item === tab);
                    item.classList.toggle('text-slate-400', item !== tab);
                });
                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.themePanel !== active));
                this.cssMirror?.refresh();
                this.jsMirror?.refresh();
            }));
        }

        bindTextareaFallbacks() {
            if (!this.cssMirror && this.cssTextarea) {
                this.cssTextarea.addEventListener('input', () => {
                    this.cssTextarea.dataset.generatedDefault = this.cssTextarea.value === this.defaultCss(this.currentThemeId()) ? '1' : '0';
                    this.lintCss();
                    this.persistDraft();
                });
            }
            if (!this.jsMirror && this.jsTextarea) {
                this.jsTextarea.addEventListener('input', () => {
                    this.lintJs();
                    this.persistDraft();
                });
            }
        }

        installHints() {
            if (!window.CodeMirror) {
                return;
            }

            if (!window.CodeMirror.helpers?.hint?.nafcmsThemeCss) {
                window.CodeMirror.registerHelper('hint', 'nafcmsThemeCss', this.cssHint.bind(this));
            }
            if (!window.CodeMirror.helpers?.hint?.nafcmsThemeJs) {
                window.CodeMirror.registerHelper('hint', 'nafcmsThemeJs', this.jsHint.bind(this));
            }
        }

        maybeShowCompletion(editor, mode, change) {
            if (!change || change.origin !== '+input' || editor.state.completionActive) {
                return;
            }

            const text = String((change.text || []).join(''));
            if (!/[a-zA-Z0-9_.(-]/.test(text)) {
                return;
            }

            window.clearTimeout(editor._nafcmsCompletionTimer);
            editor._nafcmsCompletionTimer = window.setTimeout(() => this.showCompletion(editor, mode, false), 80);
        }

        showCompletion(editor, mode, forced = false) {
            if (!window.CodeMirror?.showHint) {
                return;
            }

            const options = {
                completeSingle: false,
            };
            if (mode === 'css') {
                options.hint = this.cssHint.bind(this);
            } else if (mode === 'javascript') {
                options.hint = this.jsHint.bind(this);
            }

            if (!forced) {
                const token = editor.getTokenAt(editor.getCursor());
                const value = String(token.string || '');
                if (mode === 'css' && value.length < 1) {
                    return;
                }
            }

            editor.showHint(options);
        }

        cssHint(editor) {
            const applyHint = this.tailwindApplyHint(editor);
            if (applyHint) {
                return applyHint;
            }

            const nativeHint = window.CodeMirror?.hint?.css
                ? window.CodeMirror.hint.css(editor)
                : null;
            const themeHint = this.themeCssHint(editor);
            const from = nativeHint?.from || themeHint.from;
            const to = nativeHint?.to || themeHint.to;
            const list = [
                ...(nativeHint?.list || []),
                ...themeHint.list,
            ];
            const seen = new Set();

            return {
                list: list.filter((item) => {
                    const key = typeof item === 'string' ? item : item.text;
                    if (!key || seen.has(key)) {
                        return false;
                    }
                    seen.add(key);
                    return true;
                }),
                from,
                to,
            };
        }

        tailwindApplyHint(editor) {
            const cursor = editor.getCursor();
            const line = editor.getLine(cursor.line).slice(0, cursor.ch);
            const applyStart = line.lastIndexOf('@apply');
            const semicolonStart = line.lastIndexOf(';');
            const braceStart = Math.max(line.lastIndexOf('{'), line.lastIndexOf('}'));
            if (applyStart === -1 || applyStart < semicolonStart || applyStart < braceStart) {
                return null;
            }

            const match = line.match(/(?:^|\s)([!a-zA-Z0-9_:/.[\]-]*)$/);
            const query = match ? match[1] : '';
            const from = window.CodeMirror.Pos(cursor.line, cursor.ch - query.length);
            const to = window.CodeMirror.Pos(cursor.line, cursor.ch);
            const list = tailwindUtilityWords
                .filter((word) => query === '' || word.toLowerCase().includes(query.toLowerCase()))
                .slice(0, 120)
                .map((word) => ({
                    text: word,
                    displayText: word,
                    className: 'nafcms-cm-hint-tailwind',
                }));

            return {list, from, to};
        }

        themeCssHint(editor) {
            const cursor = editor.getCursor();
            const token = editor.getTokenAt(cursor);
            const tokenText = String(token.string || '');
            const match = tokenText.match(/[.#a-zA-Z0-9_(-][.#a-zA-Z0-9_(-]*$/);
            const start = match ? token.end - match[0].length : token.start;
            const from = window.CodeMirror.Pos(cursor.line, start);
            const to = window.CodeMirror.Pos(cursor.line, token.end);
            const surfaces = this.currentSurfaces().map((surface) => `.page-block--surface-${surface.key}`);
            const snippets = [
                `.theme-${this.currentThemeId()} {\n    --page-accent: #3f7fbd;\n}`,
                `.theme-${this.currentThemeId()} .page-navigation__link {\n    color: var(--page-muted);\n}`,
                `.theme-${this.currentThemeId()} .page-site-header {\n    border-bottom: 1px solid var(--page-border);\n}`,
                `.theme-${this.currentThemeId()} .page-block--surface-card {\n    @apply rounded-xl border border-slate-200 shadow-sm;\n}`,
            ];
            const vars = cssVariables.map((name) => `var(${name})`);
            const words = [
                `.theme-${this.currentThemeId()}`,
                '@apply ',
                ...knownClasses,
                ...surfaces,
                ...vars,
                ...snippets,
            ];
            const query = (match ? match[0] : tokenText).toLowerCase();
            const list = words
                .filter((word, index, all) => all.indexOf(word) === index)
                .filter((word) => query === '' || word.toLowerCase().includes(query))
                .map((word) => ({
                    text: word,
                    displayText: word.includes('\n') ? word.split('\n')[0].replace(/\s*\{$/, ' {...}') : word,
                    className: word.includes('\n') ? 'nafcms-cm-hint-snippet' : '',
                }));

            return {list, from, to};
        }

        jsHint(editor) {
            const nativeHint = window.CodeMirror?.hint?.javascript
                ? window.CodeMirror.hint.javascript(editor)
                : null;
            const themeHint = this.themeJsHint(editor);
            const from = themeHint.from || nativeHint?.from;
            const to = themeHint.to || nativeHint?.to;
            const list = [
                ...themeHint.list,
                ...(nativeHint?.list || []),
            ];
            const seen = new Set();

            return {
                list: list.filter((item) => {
                    const key = typeof item === 'string' ? item : item.text;
                    if (!key || seen.has(key)) {
                        return false;
                    }
                    seen.add(key);
                    return true;
                }),
                from,
                to,
            };
        }

        themeJsHint(editor) {
            const propertyContext = this.jsPropertyContext(editor);
            if (propertyContext) {
                const type = this.inferJsType(editor, propertyContext.objectName);
                const words = type ? (jsTypeMembers[type] || []) : [];
                return {
                    list: words
                        .filter((word) => propertyContext.query === '' || word.toLowerCase().startsWith(propertyContext.query.toLowerCase()))
                        .map((word) => ({
                            text: word,
                            displayText: `${word} · ${type}`,
                        })),
                    from: propertyContext.from,
                    to: propertyContext.to,
                };
            }

            const cursor = editor.getCursor();
            const token = editor.getTokenAt(cursor);
            const tokenText = String(token.string || '');
            const match = tokenText.match(/[a-zA-Z_$][\w$]*$/);
            const start = match ? token.end - match[0].length : token.start;
            const query = (match ? match[0] : tokenText).toLowerCase();

            return {
                list: jsGlobals
                    .filter((word) => query === '' || word.toLowerCase().includes(query))
                    .map((word) => ({
                        text: word,
                        displayText: word,
                        className: word.includes(' ') ? 'nafcms-cm-hint-snippet' : '',
                    })),
                from: window.CodeMirror.Pos(cursor.line, start),
                to: window.CodeMirror.Pos(cursor.line, token.end),
            };
        }

        jsPropertyContext(editor) {
            const cursor = editor.getCursor();
            const line = editor.getLine(cursor.line).slice(0, cursor.ch);
            const match = line.match(/([a-zA-Z_$][\w$]*)\.([a-zA-Z_$][\w$]*)?$/);
            if (!match) {
                return null;
            }
            const query = match[2] || '';

            return {
                objectName: match[1],
                query,
                from: window.CodeMirror.Pos(cursor.line, cursor.ch - query.length),
                to: window.CodeMirror.Pos(cursor.line, cursor.ch),
            };
        }

        inferJsType(editor, objectName) {
            const directTypes = {
                Object: 'Object',
                Array: 'Array',
                String: 'String',
                Math: 'Math',
                document: 'Document',
                window: 'Window',
                console: 'Console',
            };
            if (directTypes[objectName]) {
                return directTypes[objectName];
            }

            const source = editor.getRange(window.CodeMirror.Pos(0, 0), editor.getCursor());
            const escapedName = escapeRegExp(objectName);
            const documentedType = source.match(new RegExp(`/\\*\\*\\s*@type\\s*\\{\\s*([^}]+)\\s*}\\s*\\*/\\s*(?:const|let|var)\\s+${escapedName}\\b`));
            if (documentedType) {
                return this.normalizeJsHintType(documentedType[1]);
            }

            const patterns = [
                {type: 'ObjectInstance', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*new\\s+Object\\s*\\(`)},
                {type: 'ObjectInstance', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*\\{`)},
                {type: 'Array', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*(?:new\\s+Array\\s*\\(|\\[)`)},
                {type: 'String', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*(?:new\\s+String\\s*\\(|['"\`])`)},
                {type: 'Element', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*document\\.querySelector\\s*\\(`)},
                {type: 'Document', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*document\\b`)},
                {type: 'Window', pattern: new RegExp(`\\b(?:const|let|var)\\s+${escapedName}\\s*=\\s*window\\b`)},
            ];
            const match = patterns.find((candidate) => candidate.pattern.test(source));

            return match?.type || '';
        }

        normalizeJsHintType(type) {
            const value = String(type || '').trim().replace(/\[\]$/, '');
            const map = {
                object: 'ObjectInstance',
                Object: 'ObjectInstance',
                array: 'Array',
                Array: 'Array',
                string: 'String',
                String: 'String',
                Element: 'Element',
                HTMLElement: 'Element',
                Document: 'Document',
                Window: 'Window',
                Console: 'Console',
                Math: 'Math',
            };

            return map[value] || '';
        }

        renumberSurfaces() {
            this.surfaceList?.querySelectorAll('[data-surface-row]').forEach((row, index) => {
                row.querySelector('[data-surface-label]')?.setAttribute('name', `surfaces[${index}][label]`);
                row.querySelector('[data-surface-key]')?.setAttribute('name', `surfaces[${index}][key]`);
            });
        }

        snapshot() {
            return {
                id: Number(this.themeId?.value || 0),
                label: this.themeLabel?.value || '',
                identifier: this.themeIdentifier?.value || '',
                identifierManual: this.themeIdentifier?.dataset.manual || '0',
                css: this.getCss(),
                js: this.getJs(),
                generatedDefault: this.cssTextarea?.dataset.generatedDefault || '0',
                surfaces: this.currentSurfaces(),
                savedAt: new Date().toISOString(),
            };
        }

        applySnapshot(snapshot) {
            if (!snapshot || typeof snapshot !== 'object') {
                return;
            }

            this.restoring = true;
            if (this.themeLabel) {
                this.themeLabel.value = String(snapshot.label || '');
            }
            if (this.themeId && Number(snapshot.id || 0) > 0) {
                this.themeId.value = String(Number(snapshot.id || 0));
            }
            if (this.themeIdentifier) {
                this.themeIdentifier.value = slug(String(snapshot.identifier || ''));
                this.themeIdentifier.dataset.manual = String(snapshot.identifierManual || '0') === '1' ? '1' : '0';
            }
            if (this.cssTextarea) {
                this.cssTextarea.dataset.generatedDefault = String(snapshot.generatedDefault || '0') === '1' ? '1' : '0';
            }
            if (Array.isArray(snapshot.surfaces)) {
                this.replaceSurfaces(snapshot.surfaces);
            }
            if (typeof snapshot.css === 'string') {
                this.setCss(snapshot.css);
            }
            if (typeof snapshot.js === 'string') {
                this.setJs(snapshot.js);
            }
            this.renumberSurfaces();
            this.refreshGeneratedThemeCode();
            this.lintAll();
            this.restoring = false;
        }

        replaceSurfaces(surfaces) {
            if (!this.surfaceList || !this.surfaceTemplate) {
                return;
            }

            this.surfaceList.innerHTML = '';
            surfaces.forEach((surface) => {
                const fragment = this.surfaceTemplate.content.cloneNode(true);
                const row = fragment.querySelector('[data-surface-row]');
                if (!row) {
                    return;
                }

                const label = row.querySelector('[data-surface-label]');
                const key = row.querySelector('[data-surface-key]');
                if (label) {
                    label.value = String(surface.label || '');
                }
                if (key) {
                    key.value = slug(String(surface.key || surface.label || ''));
                    key.dataset.manual = key.value.trim() !== '' ? '1' : '0';
                }
                this.surfaceList.appendChild(fragment);
                this.bindSurfaceRow(row);
            });
        }

        restoreDraft() {
            try {
                const raw = window.localStorage.getItem(this.storageKey);
                if (!raw) {
                    return;
                }

                this.applySnapshot(JSON.parse(raw));
            } catch (error) {
                window.localStorage.removeItem(this.storageKey);
            }
        }

        persistDraft() {
            if (this.restoring) {
                return;
            }

            window.clearTimeout(this.persistTimer);
            this.persistTimer = window.setTimeout(() => {
                try {
                    window.localStorage.setItem(this.storageKey, JSON.stringify(this.snapshot()));
                } catch (error) {
                    // Local storage can fail in private windows or when quota is full.
                }
            }, 120);
        }

        clearDraft() {
            try {
                window.localStorage.removeItem(this.storageKey);
            } catch (error) {
                // Ignore storage cleanup failures.
            }
        }

        currentThemeId() {
            return slug(this.themeIdentifier?.value || '') || 'my-theme';
        }

        trackThemeIdentifierChange() {
            const oldId = this.pendingSelectorRename?.oldId || this.lastThemeId || 'my-theme';
            const newId = this.currentThemeId();

            if (oldId === newId) {
                this.hideSelectorRename();
                this.lastThemeId = newId;
                return;
            }

            this.lastThemeId = newId;
            if (this.cssTextarea?.dataset.generatedDefault === '1') {
                this.hideSelectorRename();
                return;
            }

            const css = this.getCss();
            if (css !== replaceThemeSelectors(css, oldId, newId)) {
                this.pendingSelectorRename = {oldId, newId};
                this.showSelectorRename(oldId, newId);
                return;
            }

            this.hideSelectorRename();
        }

        showSelectorRename(oldId, newId) {
            if (!this.selectorRename || !this.selectorRenameText) {
                return;
            }

            this.selectorRename.classList.remove('hidden');
            this.selectorRename.classList.add('flex');
            this.selectorRenameText.textContent = `Theme identifier changed. Update CSS selectors from .theme-${oldId} to .theme-${newId}?`;
        }

        hideSelectorRename() {
            if (!this.selectorRename) {
                return;
            }

            this.pendingSelectorRename = null;
            this.selectorRename.classList.add('hidden');
            this.selectorRename.classList.remove('flex');
            if (this.selectorRenameText) {
                this.selectorRenameText.textContent = '';
            }
        }

        rememberCssColorTarget() {
            if (this.cssMirror) {
                const cursor = this.cssMirror.getCursor();
                this.cssColorTarget = {
                    type: 'codemirror',
                    selections: this.cssMirror.listSelections(),
                    colorRange: this.findColorRangeAtCodeMirrorCursor(cursor, true),
                    cursor,
                };
                return;
            }

            if (this.cssTextarea) {
                const start = this.cssTextarea.selectionStart || 0;
                const end = this.cssTextarea.selectionEnd || start;
                this.cssColorTarget = {
                    type: 'textarea',
                    start,
                    end,
                    colorRange: this.findColorRangeInText(this.cssTextarea.value, start, true),
                };
            }
        }

        updateContextualColorPicker() {
            if (!this.colorPicker || !this.colorPopover) {
                return;
            }

            const target = this.currentColorTarget();
            if (!target) {
                this.hideColorPicker();
                return;
            }

            this.cssColorTarget = target;
            const normalized = this.normalizeColorForPicker(target.colorRange?.value || '');
            if (normalized) {
                this.colorPicker.value = normalized;
            }
            this.showColorPickerAtTarget(target);
        }

        currentColorTarget() {
            if (this.cssMirror) {
                const cursor = this.cssMirror.getCursor();
                const colorRange = this.findColorRangeAtCodeMirrorCursor(cursor, true);
                if (!colorRange) {
                    return null;
                }

                return {
                    type: 'codemirror',
                    selections: this.cssMirror.listSelections(),
                    colorRange,
                    cursor,
                };
            }

            if (!this.cssTextarea) {
                return null;
            }

            const start = this.cssTextarea.selectionStart || 0;
            const colorRange = this.findColorRangeInText(this.cssTextarea.value, start, true);
            if (!colorRange) {
                return null;
            }

            return {
                type: 'textarea',
                start,
                end: this.cssTextarea.selectionEnd || start,
                colorRange,
            };
        }

        showColorPickerAtTarget(target) {
            if (!this.colorPopover) {
                return;
            }

            let left = 0;
            let top = 0;
            if (target.type === 'codemirror') {
                const coordinates = this.cssMirror.cursorCoords(target.colorRange?.from || target.cursor, 'window');
                left = coordinates.left;
                top = coordinates.bottom + 8;
            } else if (this.cssTextarea) {
                const rect = this.cssTextarea.getBoundingClientRect();
                left = rect.left + 12;
                top = rect.top + 12;
            }

            const maxLeft = Math.max(8, window.innerWidth - 128);
            this.colorPopover.style.left = `${Math.min(Math.max(8, left), maxLeft)}px`;
            this.colorPopover.style.top = `${Math.min(Math.max(8, top), Math.max(8, window.innerHeight - 56))}px`;
            this.colorPopover.classList.remove('hidden');
            this.colorPopover.classList.add('flex');
        }

        hideColorPicker() {
            if (!this.colorPopover) {
                return;
            }

            this.colorPopover.classList.add('hidden');
            this.colorPopover.classList.remove('flex');
        }

        insertCssColor(color) {
            const normalized = this.normalizeColorForPicker(color);
            if (!normalized) {
                return;
            }

            if (this.cssMirror) {
                this.insertCodeMirrorColor(normalized);
            } else {
                this.insertTextareaColor(normalized);
            }

            if (this.cssTextarea) {
                this.cssTextarea.dataset.generatedDefault = this.getCss() === this.defaultCss(this.currentThemeId()) ? '1' : '0';
            }
            this.lintCss();
            this.persistDraft();
        }

        insertCodeMirrorColor(color) {
            const target = this.cssColorTarget?.type === 'codemirror' ? this.cssColorTarget : null;
            const selection = target?.selections?.[0];
            const range = target?.colorRange || this.findColorRangeAtCodeMirrorCursor(this.cssMirror.getCursor(), true);

            if (range) {
                this.cssMirror.replaceRange(color, range.from, range.to);
                this.cssMirror.setSelection(range.from, window.CodeMirror.Pos(range.from.line, range.from.ch + color.length));
                this.cssColorTarget = {
                    type: 'codemirror',
                    selections: this.cssMirror.listSelections(),
                    colorRange: {
                        from: range.from,
                        to: window.CodeMirror.Pos(range.from.line, range.from.ch + color.length),
                        value: color,
                    },
                    cursor: range.from,
                };
                return;
            }

            if (selection && this.cssMirror.somethingSelected()) {
                this.cssMirror.replaceSelection(color);
                this.cssColorTarget = {
                    type: 'codemirror',
                    selections: this.cssMirror.listSelections(),
                    colorRange: null,
                    cursor: this.cssMirror.getCursor(),
                };
                return;
            }

            const cursor = target?.cursor || this.cssMirror.getCursor();
            this.cssMirror.replaceRange(color, cursor);
            this.cssMirror.setSelection(cursor, window.CodeMirror.Pos(cursor.line, cursor.ch + color.length));
            this.cssColorTarget = {
                type: 'codemirror',
                selections: this.cssMirror.listSelections(),
                colorRange: {
                    from: cursor,
                    to: window.CodeMirror.Pos(cursor.line, cursor.ch + color.length),
                    value: color,
                },
                cursor,
            };
        }

        insertTextareaColor(color) {
            if (!this.cssTextarea) {
                return;
            }

            const target = this.cssColorTarget?.type === 'textarea' ? this.cssColorTarget : null;
            const range = target?.colorRange || this.findColorRangeInText(this.cssTextarea.value, this.cssTextarea.selectionStart || 0, true);
            const start = range?.start ?? target?.start ?? this.cssTextarea.selectionStart ?? this.cssTextarea.value.length;
            const end = range?.end ?? target?.end ?? this.cssTextarea.selectionEnd ?? start;
            this.cssTextarea.value = `${this.cssTextarea.value.slice(0, start)}${color}${this.cssTextarea.value.slice(end)}`;
            this.cssTextarea.selectionStart = start;
            this.cssTextarea.selectionEnd = start + color.length;
            this.cssColorTarget = {
                type: 'textarea',
                start,
                end: start + color.length,
                colorRange: {start, end: start + color.length, value: color},
            };
        }

        findColorRangeAtCodeMirrorCursor(cursor, includePartial = false) {
            const line = this.cssMirror.getLine(cursor.line) || '';
            const range = this.findColorRangeInText(line, cursor.ch, includePartial);
            if (!range) {
                return null;
            }

            return {
                from: window.CodeMirror.Pos(cursor.line, range.start),
                to: window.CodeMirror.Pos(cursor.line, range.end),
                value: range.value,
            };
        }

        findColorRangeInText(text, offset, includePartial = false) {
            if (!this.isLikelyCssPropertyValue(text, offset)) {
                return null;
            }

            hexColorPattern.lastIndex = 0;
            let match;
            while ((match = hexColorPattern.exec(text)) !== null) {
                const start = match.index;
                const end = start + match[0].length;
                if (offset >= start && offset <= end) {
                    return {start, end, value: match[0]};
                }
            }

            if (includePartial) {
                const before = text.slice(0, offset);
                const partial = before.match(/#(?:[0-9a-fA-F]{0,8})$/);
                if (partial) {
                    const start = offset - partial[0].length;
                    return {start, end: offset, value: partial[0]};
                }
            }

            return null;
        }

        isLikelyCssPropertyValue(text, offset) {
            const before = text.slice(0, offset);
            const lastColon = before.lastIndexOf(':');
            if (lastColon === -1) {
                return false;
            }

            const lastSemicolon = before.lastIndexOf(';');
            const lastOpenBrace = before.lastIndexOf('{');
            const lastCloseBrace = before.lastIndexOf('}');
            return lastColon > lastSemicolon && lastColon > lastOpenBrace && lastColon > lastCloseBrace;
        }

        normalizeColorForPicker(color) {
            const value = String(color || '').trim();
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                return value.toLowerCase();
            }
            if (/^#[0-9a-fA-F]{3}$/.test(value)) {
                return `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`.toLowerCase();
            }
            if (/^#[0-9a-fA-F]{8}$/.test(value)) {
                return value.slice(0, 7).toLowerCase();
            }

            return '';
        }

        rescanThemeSelectorMismatch() {
            const currentId = this.currentThemeId();
            const css = this.getCss();
            const candidates = findThemeSelectorIds(css)
                .filter((id, index, all) => id !== currentId && all.indexOf(id) === index);
            if (candidates.length === 0) {
                return;
            }

            const oldId = candidates[0];
            if (css === replaceThemeSelectors(css, oldId, currentId)) {
                return;
            }

            this.pendingSelectorRename = {oldId, newId: currentId};
            this.showSelectorRename(oldId, currentId);
        }


        defaultCss(id) {
            return `.theme-${id} {
    --page-accent: #3f7fbd;
}

.theme-${id} .page-block--surface-card {
    border-radius: 12px;
}
`;
        }

        currentSurfaces() {
            return Array.from(this.surfaceList?.querySelectorAll('[data-surface-row]') || [])
                .map((row) => {
                    const label = row.querySelector('[data-surface-label]')?.value || '';
                    const key = slug(row.querySelector('[data-surface-key]')?.value || label);
                    return {key, label: label.trim() || key.replace(/-/g, ' ')};
                })
                .filter((surface) => surface.key !== '');
        }

        exportPhp() {
            const id = this.currentThemeId();
            const label = this.themeLabel?.value.trim() || 'My Theme';
            const lines = [
                `Theme::create('${phpString(id)}')`,
                `    ->setLabel('${phpString(label)}')`,
                `    ->addStylesheet('/css/themes/${phpString(id)}.css')`,
            ];
            this.currentSurfaces().forEach((surface) => {
                lines.push(`    ->addSurface('${phpString(surface.key)}', '${phpString(surface.label)}')`);
            });
            return `${lines.join('\n')};\n`;
        }

        refreshGeneratedThemeCode() {
            const id = this.currentThemeId();
            if (this.scopeCode) {
                this.scopeCode.textContent = `.theme-${id}`;
            }
            if (this.exportCode) {
                this.exportCode.textContent = this.exportPhp();
            }
            if (this.cssTextarea?.dataset.generatedDefault === '1') {
                this.setCss(this.defaultCss(id));
            }
        }

        getCss() {
            return this.cssMirror ? this.cssMirror.getValue() : (this.cssTextarea?.value || '');
        }

        setCss(value) {
            value = normalizeLineEndings(value);
            if (this.cssMirror) {
                if (this.cssMirror.getValue() !== value) {
                    this.cssMirror.setValue(value);
                }
            } else if (this.cssTextarea) {
                this.cssTextarea.value = value;
            }
        }

        setJs(value) {
            value = normalizeLineEndings(value);
            if (this.jsMirror) {
                if (this.jsMirror.getValue() !== value) {
                    this.jsMirror.setValue(value);
                }
            } else if (this.jsTextarea) {
                this.jsTextarea.value = value;
            }
        }

        getJs() {
            return this.jsMirror ? this.jsMirror.getValue() : (this.jsTextarea?.value || '');
        }

        lintAll() {
            this.lintCss();
            this.lintJs();
        }

        lintCss() {
            const css = this.getCss();
            const messages = [];
            const opens = (css.match(/{/g) || []).length;
            const closes = (css.match(/}/g) || []).length;
            if (opens !== closes) {
                messages.push('CSS braces look unbalanced.');
            }
            if (css.trim() !== '' && !css.includes(`.theme-${this.currentThemeId()}`)) {
                messages.push(`No .theme-${this.currentThemeId()} selector found.`);
            }
            if (/@apply\s+[^;]+;/.test(css)) {
                messages.push('Tailwind @apply was found. Theme CSS must be compiled with Tailwind before browsers can use it.');
            }
            this.currentSurfaces()
                .filter((surface) => !['none', 'soft', 'card', 'accent'].includes(surface.key))
                .forEach((surface) => {
                    if (!css.includes(`.page-block--surface-${surface.key}`)) {
                        messages.push(`Surface "${surface.key}" is registered but has no matching selector yet.`);
                    }
                });

            this.renderStatus(this.cssStatus, messages);
        }

        lintJs() {
            const js = this.getJs();
            const messages = [];
            if (js.trim() !== '') {
                try {
                    // Syntax-only approximation for module snippets; this function is not executed.
                    // eslint-disable-next-line no-new-func
                    new Function(js.replace(/\bexport\s+default\b/g, 'const __default ='));
                } catch (error) {
                    messages.push(error.message || 'JavaScript syntax could not be parsed.');
                }
            }

            this.renderStatus(this.jsStatus, messages);
        }

        renderStatus(target, messages) {
            if (!target) {
                return;
            }

            target.classList.toggle('hidden', messages.length === 0);
            target.textContent = messages.join(' ');
        }
    }

    document.querySelectorAll('[data-theme-editor]').forEach((root) => {
        const editor = new ThemeEditor(root);
        window.NafCMS = window.NafCMS || {};
        window.NafCMS.themeEditor = editor;
        editor.init();
    });
})();
