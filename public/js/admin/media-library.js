import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import { initStyledSelects } from '../components/styledSelect.js';

class MediaLibrary {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.files = Array.isArray(this.config.files) ? this.config.files : [];
        this.grid = root.querySelector('[data-media-grid]');
        this.tree = root.querySelector('[data-media-tree]');
        this.input = root.querySelector('[data-media-input]');
        this.dropPick = root.querySelector('[data-media-drop-pick]');
        this.search = root.querySelector('[data-media-search]');
        this.dropzone = root.querySelector('[data-media-dropzone]');
        this.uploadVisibility = root.querySelector('[data-media-upload-visibility]');
        this.viewButtons = [...root.querySelectorAll('[data-media-view]')];
        this.statTotal = root.querySelector('[data-media-stat-total]');
        this.statBytes = root.querySelector('[data-media-stat-bytes]');
        this.typeChart = root.querySelector('[data-media-type-chart]');
        this.typeCenterValue = root.querySelector('[data-media-type-center-value]');
        this.typeCenterLabel = root.querySelector('[data-media-type-center-label]');
        this.typeLegend = root.querySelector('[data-media-type-legend]');
        this.summary = this.normalizeSummary(this.config.summary);
        this.activeTree = 'all';
        this.viewMode = this.storedViewMode();
        this.uploading = false;
    }

    init() {
        this.render();
        this.initStyledSelects();
        this.dropPick?.addEventListener('click', () => this.openFilePicker());
        this.dropPick?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.openFilePicker();
        });
        this.input?.addEventListener('change', () => {
            this.uploadFiles([...(this.input.files || [])]);
            this.input.value = '';
        });
        this.search?.addEventListener('input', () => this.renderFiles());
        this.root.addEventListener('click', (event) => this.handleClick(event));
        this.root.addEventListener('change', (event) => this.handleChange(event));
        this.root.addEventListener('blur', (event) => this.handleAltBlur(event), true);
        this.initDropzone();
    }

    initDropzone() {
        if (!this.dropzone || !this.can('upload')) {
            return;
        }

        ['dragenter', 'dragover'].forEach((type) => {
            this.dropzone.addEventListener(type, (event) => {
                event.preventDefault();
                this.dropzone.classList.add('border-accent', 'bg-[rgba(2,6,23,.58)]');
                this.dropPick?.classList.add('border-accent', 'bg-[rgba(15,23,42,.68)]');
            });
        });

        ['dragleave', 'drop'].forEach((type) => {
            this.dropzone.addEventListener(type, (event) => {
                event.preventDefault();
                this.dropzone.classList.remove('border-accent', 'bg-[rgba(2,6,23,.58)]');
                this.dropPick?.classList.remove('border-accent', 'bg-[rgba(15,23,42,.68)]');
            });
        });

        this.dropzone.addEventListener('drop', (event) => {
            this.uploadFiles([...(event.dataTransfer?.files || [])]);
        });
    }

    async uploadFiles(files) {
        if (!this.can('upload') || files.length === 0 || this.uploading) {
            return;
        }

        const validFiles = files.filter((file) => this.validateFile(file));
        if (validFiles.length === 0) {
            return;
        }

        this.uploading = true;
        this.dropPick?.setAttribute('aria-disabled', 'true');
        this.dropPick?.classList.add('pointer-events-none', 'opacity-70');

        for (const file of validFiles) {
            await this.uploadFile(file);
        }

        this.uploading = false;
        this.dropPick?.setAttribute('aria-disabled', 'false');
        this.dropPick?.classList.remove('pointer-events-none', 'opacity-70');
        this.render();
    }

    openFilePicker() {
        if (!this.can('upload') || this.uploading) {
            return;
        }

        this.input?.click();
    }

    validateFile(file) {
        const maxUploadSize = Number(this.config.maxUploadSize || 0);
        if (maxUploadSize > 0 && file.size > maxUploadSize) {
            toast(`${file.name}: ${this.label('fileTooLarge')}`);
            return false;
        }

        const extension = String(file.name || '').split('.').pop().toLowerCase();
        const allowed = Array.isArray(this.config.acceptedExtensions) ? this.config.acceptedExtensions : [];
        if (allowed.length > 0 && !allowed.includes(extension)) {
            toast(`${file.name}: ${this.label('invalidExtension')}`);
            return false;
        }

        return true;
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file, file.name);
        formData.append('visibility', this.uploadVisibilityValue());

        try {
            const response = await (window.NafCMS?.fetch || fetch)(this.url('upload'), {
                method: 'POST',
                headers: {'Accept': 'application/json'},
                body: formData,
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || data?.success === false) {
                toast(data?.reason || data?.message || this.label('error'));
                return;
            }

            const uploaded = Array.isArray(data?.files) ? data.files : (data?.media ? [data.media] : []);
            const existingIds = new Set(this.files.map((item) => String(item.id)));
            uploaded.forEach((item) => {
                if (!existingIds.has(String(item.id))) {
                    this.adjustSummary(item, 1);
                }
            });
            this.files = [
                ...uploaded,
                ...this.files.filter((existing) => !uploaded.some((item) => String(item.id) === String(existing.id))),
            ];
        } catch (error) {
            toast(this.label('error'));
        }
    }

    async handleClick(event) {
        const treeItem = event.target.closest('[data-media-tree-key]');
        if (treeItem && this.root.contains(treeItem)) {
            event.preventDefault();
            this.activeTree = String(treeItem.dataset.mediaTreeKey || 'all');
            this.render();
            return;
        }

        const viewButton = event.target.closest('[data-media-view]');
        if (viewButton && this.root.contains(viewButton)) {
            event.preventDefault();
            this.viewMode = String(viewButton.dataset.mediaView || 'grid') === 'list' ? 'list' : 'grid';
            this.storeViewMode();
            this.renderFiles();
            this.updateViewButtons();
            return;
        }

        const copy = event.target.closest('[data-media-copy]');
        if (copy && this.root.contains(copy)) {
            event.preventDefault();
            await this.copyUrl(String(copy.dataset.mediaUrl || ''));
            return;
        }

        const remove = event.target.closest('[data-media-delete]');
        if (remove && this.root.contains(remove)) {
            event.preventDefault();
            await this.deleteFile(String(remove.dataset.mediaId || ''));
        }
    }

    async handleChange(event) {
        const select = event.target.closest('[data-media-visibility]');
        if (!select || !this.root.contains(select) || !this.can('edit')) {
            return;
        }

        const id = String(select.dataset.mediaId || '');
        const file = this.files.find((item) => String(item.id) === id);
        if (!file || this.visibility(file) === String(select.value || 'public')) {
            return;
        }

        try {
            const response = await (window.NafCMS?.json || fetch)(this.url('update', {id}), {
                method: 'POST',
                body: JSON.stringify({visibility: select.value}),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || data?.success === false || !data?.media) {
                toast(data?.reason || this.label('error'));
                select.value = this.visibility(file);
                return;
            }

            this.files = this.files.map((item) => String(item.id) === id ? data.media : item);
            this.render();
            toast(this.label('updated'));
        } catch (error) {
            select.value = this.visibility(file);
            toast(this.label('error'));
        }
    }

    async handleAltBlur(event) {
        const input = event.target.closest('[data-media-alt]');
        if (!input || !this.root.contains(input) || !this.can('edit')) {
            return;
        }

        const id = String(input.dataset.mediaId || '');
        const file = this.files.find((item) => String(item.id) === id);
        if (!file || String(file.alt_text || '') === String(input.value || '')) {
            return;
        }

        try {
            const response = await (window.NafCMS?.json || fetch)(this.url('update', {id}), {
                method: 'POST',
                body: JSON.stringify({alt_text: input.value}),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || data?.success === false || !data?.media) {
                toast(data?.reason || this.label('error'));
                return;
            }

            this.files = this.files.map((item) => String(item.id) === id ? data.media : item);
            this.renderTree();
            toast(this.label('updated'));
        } catch (error) {
            toast(this.label('error'));
        }
    }

    async copyUrl(url) {
        if (url === '') {
            return;
        }

        try {
            await navigator.clipboard.writeText(url);
        } catch (error) {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.className = 'fixed -left-[9999px] top-0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        toast(this.label('copied'));
    }

    async deleteFile(id) {
        if (!this.can('delete') || id === '') {
            return;
        }

        if (!await prompt(`<div class="my-5">${escapeHtml(this.label('deleteConfirm'))}</div>`, {
            confirmButtonText: this.label('delete'),
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
        })) {
            return;
        }

        try {
            const response = await (window.NafCMS?.fetch || fetch)(this.url('delete', {id}), {
                method: 'POST',
                headers: {'Accept': 'application/json'},
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || data?.success === false) {
                toast(data?.reason || this.label('error'));
                return;
            }

            const deleted = this.files.find((item) => String(item.id) === id);
            this.files = this.files.filter((item) => String(item.id) !== id);
            if (deleted) {
                this.adjustSummary(deleted, -1);
            }
            this.render();
            toast(this.label('deleted'));
        } catch (error) {
            toast(this.label('error'));
        }
    }

    render() {
        this.renderSummary();
        this.renderTree();
        this.renderFiles();
        this.updateViewButtons();
        this.initStyledSelects();
    }

    renderSummary() {
        const summary = this.summary || this.summaryFromFiles(this.files);
        const total = Math.max(0, Number(summary.total || 0));
        const bytes = Math.max(0, Number(summary.bytes || 0));

        if (this.statTotal) {
            this.statTotal.textContent = String(total);
        }

        if (this.statBytes) {
            this.statBytes.textContent = this.formatBytes(bytes);
        }

        const entries = this.typeEntries(summary).filter((entry) => entry.count > 0);
        const dominant = this.dominantType(entries);

        if (this.typeChart) {
            this.typeChart.style.background = this.pieBackground(entries, total);
            this.typeChart.setAttribute('aria-label', this.typeAriaLabel(entries, total));
        }

        if (this.typeCenterValue) {
            this.typeCenterValue.textContent = dominant && total > 0
                ? `${Math.round((dominant.count / total) * 100)}%`
                : '0';
        }

        if (this.typeCenterLabel) {
            this.typeCenterLabel.textContent = dominant ? dominant.label : this.label('allFiles');
        }

        if (this.typeLegend) {
            this.typeLegend.innerHTML = entries.length > 0
                ? entries.map((entry) => this.typeLegendItem(entry, total)).join('')
                : `<li class="rounded-md border border-dashed border-[rgba(148,163,184,.22)] px-3 py-2 text-sm text-slate-500">${escapeHtml(this.label('empty'))}</li>`;
        }
    }

    renderTree() {
        if (!this.tree) {
            return;
        }

        const smartEntries = [
            {key: 'all', label: this.label('allFiles'), count: this.files.length, icon: 'stack'},
            {key: 'kind:image', label: this.label('images'), count: this.countBy((file) => file.kind === 'image'), icon: 'image'},
            {key: 'kind:other', label: this.label('otherFiles'), count: this.countBy((file) => file.kind !== 'image'), icon: 'file'},
            {key: 'visibility:public', label: this.label('public'), count: this.countBy((file) => this.visibility(file) === 'public'), icon: 'globe'},
            {key: 'visibility:registered', label: this.label('registered'), count: this.countBy((file) => this.visibility(file) === 'registered'), icon: 'user'},
            {key: 'visibility:private', label: this.label('private'), count: this.countBy((file) => this.visibility(file) === 'private'), icon: 'lock'},
        ];
        const folders = this.folderTree();

        this.tree.innerHTML = `
            ${this.treeGroup(this.label('smartFolders'), smartEntries)}
            ${this.treeGroup(this.label('folders'), folders)}
        `;
    }

    treeGroup(title, entries) {
        const content = entries.length > 0
            ? entries.map((entry) => this.treeItem(entry)).join('')
            : `<p class="m-0 rounded-md border border-dashed border-[rgba(148,163,184,.22)] px-3 py-2 text-xs text-slate-500">${escapeHtml(this.label('empty'))}</p>`;

        return `<section class="grid gap-2">
            <h3 class="m-0 px-2 text-xs font-semibold uppercase text-slate-500">${escapeHtml(title)}</h3>
            <div class="grid gap-1">${content}</div>
        </section>`;
    }

    treeItem(entry, depth = 0) {
        const children = Array.isArray(entry.children) ? entry.children : [];
        const active = this.activeTree === entry.key;
        const padding = 10 + (depth * 16);
        const childMarkup = children.length > 0
            ? children.map((child) => this.treeItem(child, depth + 1)).join('')
            : '';

        return `<div class="grid gap-1">
            <button type="button" class="flex min-h-9 w-full items-center gap-2 rounded-md border border-transparent py-2 pr-2 text-left text-sm text-slate-300 transition hover:border-[rgba(148,163,184,.22)] hover:bg-[rgba(148,163,184,.08)] ${active ? 'border-[rgba(148,163,184,.28)] bg-[rgba(20,184,166,.16)] text-white' : ''}" style="padding-left:${padding}px" data-media-tree-key="${escapeAttr(entry.key)}">
                ${this.iconSvg(entry.icon || 'folder')}
                <span class="min-w-0 flex-1 truncate">${escapeHtml(entry.label)}</span>
                <span class="rounded bg-[rgba(148,163,184,.14)] px-1.5 text-[11px] font-semibold text-slate-400">${Number(entry.count || 0)}</span>
            </button>
            ${childMarkup}
        </div>`;
    }

    folderTree() {
        const root = new Map();

        this.files.forEach((file) => {
            const parts = this.folderParts(file);
            let level = root;
            let path = '';

            parts.forEach((part) => {
                path = path === '' ? part : `${path}/${part}`;
                if (!level.has(part)) {
                    level.set(part, {key: `folder:${path}`, label: part, count: 0, icon: 'folder', childrenMap: new Map()});
                }
                const node = level.get(part);
                node.count += 1;
                level = node.childrenMap;
            });
        });

        const finalize = (map) => [...map.values()]
            .sort((left, right) => left.label.localeCompare(right.label))
            .map((node) => ({
                key: node.key,
                label: node.label,
                count: node.count,
                icon: 'folder',
                children: finalize(node.childrenMap),
            }));

        return finalize(root);
    }

    renderFiles() {
        if (!this.grid) {
            return;
        }

        const files = this.filteredFiles();
        if (files.length === 0) {
            this.grid.className = 'grid';
            this.grid.innerHTML = `<div class="rounded-lg border border-dashed border-[rgba(148,163,184,.26)] bg-[rgba(15,23,42,.34)] p-5 text-sm text-slate-400">${escapeHtml(this.searchTerm() === '' ? this.label('empty') : this.label('noResults'))}</div>`;
            return;
        }

        if (this.viewMode === 'list') {
            this.grid.className = 'overflow-hidden rounded-lg border border-[rgba(148,163,184,.22)] bg-[rgba(15,23,42,.44)]';
            this.grid.innerHTML = this.list(files);
            return;
        }

        this.grid.className = 'grid gap-3 sm:grid-cols-2 2xl:grid-cols-3';
        this.grid.innerHTML = files.map((file) => this.card(file)).join('');
        this.initStyledSelects();
    }

    list(files) {
        return `<table class="w-full min-w-[860px] table-fixed border-separate border-spacing-0 text-sm">
            <thead>
                <tr class="[&_th]:border-b [&_th]:border-[rgba(148,163,184,.18)] [&_th]:bg-[rgba(2,6,23,.36)] [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-xs [&_th]:font-semibold [&_th]:uppercase [&_th]:text-slate-500">
                    <th class="w-[34%]">${escapeHtml(this.label('allFiles'))}</th>
                    <th class="w-[16%]">${escapeHtml(this.label('visibility'))}</th>
                    <th class="w-[18%]">${escapeHtml(this.label('storage'))}</th>
                    <th class="w-[20%]">${escapeHtml(this.label('altText'))}</th>
                    <th class="w-[12%]"></th>
                </tr>
            </thead>
            <tbody>${files.map((file) => this.listRow(file)).join('')}</tbody>
        </table>`;
    }

    listRow(file) {
        const id = escapeAttr(file.id);
        const url = String(file.public_url || '');
        const title = String(file.original_name || file.stored_name || url);
        const preview = this.preview(file, 'h-12 w-16 rounded');
        const titleMarkup = this.isDownloadable(file)
            ? `<a class="block truncate font-semibold text-slate-100 hover:text-white" href="${escapeAttr(url)}" target="_blank" rel="noreferrer" title="${escapeAttr(title)}">${escapeHtml(title)}</a>`
            : `<span class="block truncate font-semibold text-slate-100" title="${escapeAttr(title)}">${escapeHtml(title)}</span>`;
        const altControl = this.can('edit')
            ? `<input class="${this.inputClass('min-h-9')}" type="text" value="${escapeAttr(file.alt_text || '')}" placeholder="${escapeAttr(this.label('altText'))}" data-media-alt data-media-id="${id}">`
            : `<span class="block truncate text-slate-400">${escapeHtml(file.alt_text || '')}</span>`;
        const visibilityControl = this.visibilityControl(file);

        return `<tr class="[&_td]:border-b [&_td]:border-[rgba(148,163,184,.14)] [&_td]:px-4 [&_td]:py-3">
            <td>
                <div class="flex min-w-0 items-center gap-3">
                    ${preview}
                    <div class="min-w-0">
                        ${titleMarkup}
                        <p class="m-0 truncate text-xs text-slate-500">${escapeHtml(this.fileMeta(file))}</p>
                    </div>
                </div>
            </td>
            <td>${visibilityControl}</td>
            <td><span class="block truncate text-xs text-slate-400" title="${escapeAttr(file.storage_path || '')}">${escapeHtml(file.storage_path || '')}</span></td>
            <td>${altControl}</td>
            <td><div class="flex justify-end gap-2">${this.actionButtons(file)}</div></td>
        </tr>`;
    }

    card(file) {
        const id = escapeAttr(file.id);
        const url = String(file.public_url || '');
        const title = String(file.original_name || file.stored_name || url);
        const preview = this.preview(file, 'h-full w-full');
        const previewMarkup = this.isDownloadable(file)
            ? `<a class="block aspect-[4/3] overflow-hidden border-b border-[rgba(148,163,184,.16)] bg-[rgba(2,6,23,.36)]" href="${escapeAttr(url)}" target="_blank" rel="noreferrer">${preview}</a>`
            : `<div class="block aspect-[4/3] overflow-hidden border-b border-[rgba(148,163,184,.16)] bg-[rgba(2,6,23,.36)]">${preview}</div>`;
        const altControl = this.can('edit')
            ? `<input class="${this.inputClass('min-h-9')}" type="text" value="${escapeAttr(file.alt_text || '')}" placeholder="${escapeAttr(this.label('altText'))}" data-media-alt data-media-id="${id}">`
            : `<p class="m-0 truncate text-sm text-slate-400">${escapeHtml(file.alt_text || '')}</p>`;

        return `<article class="overflow-hidden rounded-lg border border-[rgba(148,163,184,.22)] bg-[rgba(15,23,42,.62)] shadow-[0_14px_34px_rgba(0,0,0,.16)]">
            ${previewMarkup}
            <div class="grid gap-3 p-3.5">
                <div class="min-w-0">
                    <h3 class="m-0 truncate text-sm font-semibold text-white" title="${escapeAttr(title)}">${escapeHtml(title)}</h3>
                    <p class="m-0 mt-1 truncate text-xs text-slate-500">${escapeHtml(this.fileMeta(file))}</p>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_150px] gap-2 max-admin:grid-cols-1">
                    ${altControl}
                    ${this.visibilityControl(file)}
                </div>
                <div class="flex items-center gap-2">
                    <input class="${this.inputClass('min-h-9 flex-1 text-xs')}" type="text" value="${escapeAttr(url)}" readonly>
                    ${this.actionButtons(file)}
                </div>
            </div>
        </article>`;
    }

    preview(file, classes) {
        const url = String(file.public_url || '');
        if (this.visibility(file) === 'private') {
            return `<div class="${classes} grid place-items-center bg-[rgba(2,6,23,.58)] text-slate-400">
                <span class="grid place-items-center gap-2 text-xs font-bold uppercase">${this.iconSvg('lock')}${escapeHtml(file.extension || this.label('private'))}</span>
            </div>`;
        }

        if (file.kind === 'image') {
            return `<img class="${classes} object-cover" src="${escapeAttr(url)}" alt="">`;
        }

        return `<div class="${classes} grid place-items-center bg-[rgba(2,6,23,.48)] text-slate-400"><span class="text-xs font-bold uppercase">${escapeHtml(file.extension || 'file')}</span></div>`;
    }

    visibilityControl(file) {
        const id = escapeAttr(file.id);
        if (this.can('edit')) {
            return `<select class="${this.inputClass('min-h-9')}" data-media-visibility data-media-id="${id}">${this.visibilityOptions(this.visibility(file))}</select>`;
        }

        return `<span class="inline-flex min-h-8 items-center rounded-md border border-[rgba(148,163,184,.18)] px-2.5 text-xs font-semibold text-slate-400">${escapeHtml(this.visibilityLabel(this.visibility(file)))}</span>`;
    }

    actionButtons(file) {
        const id = escapeAttr(file.id);
        const url = escapeAttr(file.public_url || '');
        const deleteButton = this.can('delete')
            ? `<button class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-rose-400/30 bg-rose-500/10 text-rose-200 transition hover:bg-rose-500/18" type="button" title="${escapeAttr(this.label('delete'))}" aria-label="${escapeAttr(this.label('delete'))}" data-media-delete data-media-id="${id}"><span class="icon-cross text-[12px]" aria-hidden="true"></span></button>`
            : '';

        return `<button class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-[rgba(148,163,184,.24)] bg-[rgba(15,23,42,.70)] px-2 text-xs font-bold text-slate-200 transition hover:bg-[rgba(30,41,59,.78)]" type="button" title="${escapeAttr(this.label('copyUrl'))}" aria-label="${escapeAttr(this.label('copyUrl'))}" data-media-copy data-media-url="${url}">URL</button>${deleteButton}`;
    }

    filteredFiles() {
        const term = this.searchTerm().toLowerCase();

        return this.files
            .filter((file) => this.matchesActiveTree(file))
            .filter((file) => {
                if (term === '') {
                    return true;
                }

                return [
                    file.original_name,
                    file.stored_name,
                    file.public_url,
                    file.mime_type,
                    file.alt_text,
                    file.extension,
                    this.visibility(file),
                    file.storage_path,
                ].some((value) => String(value || '').toLowerCase().includes(term));
            });
    }

    matchesActiveTree(file) {
        const key = this.activeTree;
        if (key === 'all') {
            return true;
        }

        if (key === 'kind:image') {
            return file.kind === 'image';
        }

        if (key === 'kind:other') {
            return file.kind !== 'image';
        }

        if (key.startsWith('visibility:')) {
            return this.visibility(file) === key.slice('visibility:'.length);
        }

        if (key.startsWith('folder:')) {
            const folder = key.slice('folder:'.length);
            const path = this.fileFolderPath(file);
            return path === folder || path.startsWith(`${folder}/`);
        }

        return true;
    }

    folderParts(file) {
        const folder = this.fileFolderPath(file);
        return folder === '' ? [] : folder.split('/').filter(Boolean);
    }

    fileFolderPath(file) {
        const path = String(file.storage_path || file.file_path || '').replace(/^storage\/media\//, '');
        const parts = path.split('/').filter(Boolean);
        if (parts.length <= 1) {
            return '';
        }

        parts.pop();
        return parts.join('/');
    }

    countBy(predicate) {
        return this.files.filter(predicate).length;
    }

    normalizeSummary(source) {
        if (!source || typeof source !== 'object') {
            return this.summaryFromFiles(this.files);
        }

        const kindCounts = this.normalizedKindCounts(source.kind_counts || {});
        if (Object.values(kindCounts).every((count) => count === 0)) {
            kindCounts.image = Math.max(0, Number(source.images || 0));
            kindCounts.document = Math.max(0, Number(source.documents || 0));
            kindCounts.archive = Math.max(0, Number(source.archives || 0));
            kindCounts.file = Math.max(0, Number(source.files || 0));
        }

        const countTotal = Object.values(kindCounts).reduce((sum, count) => sum + count, 0);

        return {
            total: Math.max(0, Number(source.total || countTotal)),
            bytes: Math.max(0, Number(source.bytes || 0)),
            kind_counts: kindCounts,
            images: kindCounts.image,
            documents: kindCounts.document,
            archives: kindCounts.archive,
            files: kindCounts.file,
        };
    }

    summaryFromFiles(files) {
        return files.reduce((summary, file) => {
            const kind = this.normalizedKind(file.kind);
            const size = Math.max(0, Number(file.size_bytes || 0));

            summary.total += 1;
            summary.bytes += size;
            summary.kind_counts[kind] += 1;
            summary.images = summary.kind_counts.image;
            summary.documents = summary.kind_counts.document;
            summary.archives = summary.kind_counts.archive;
            summary.files = summary.kind_counts.file;

            return summary;
        }, {
            total: 0,
            bytes: 0,
            kind_counts: this.normalizedKindCounts({}),
            images: 0,
            documents: 0,
            archives: 0,
            files: 0,
        });
    }

    adjustSummary(file, direction) {
        const kind = this.normalizedKind(file.kind);
        const size = Math.max(0, Number(file.size_bytes || 0));
        const delta = direction >= 0 ? 1 : -1;
        const summary = this.summary || this.summaryFromFiles(this.files);

        summary.total = Math.max(0, Number(summary.total || 0) + delta);
        summary.bytes = Math.max(0, Number(summary.bytes || 0) + (size * delta));
        summary.kind_counts = this.normalizedKindCounts(summary.kind_counts || {});
        summary.kind_counts[kind] = Math.max(0, Number(summary.kind_counts[kind] || 0) + delta);
        summary.images = summary.kind_counts.image;
        summary.documents = summary.kind_counts.document;
        summary.archives = summary.kind_counts.archive;
        summary.files = summary.kind_counts.file;
        this.summary = summary;
    }

    normalizedKindCounts(counts) {
        return ['image', 'document', 'archive', 'file'].reduce((normalized, kind) => {
            normalized[kind] = Math.max(0, Number(counts?.[kind] || 0));
            return normalized;
        }, {});
    }

    normalizedKind(kind) {
        const normalized = String(kind || 'file').toLowerCase();
        return ['image', 'document', 'archive', 'file'].includes(normalized) ? normalized : 'file';
    }

    typeEntries(summary) {
        const counts = this.normalizedKindCounts(summary.kind_counts || {});

        return ['image', 'document', 'archive', 'file'].map((kind) => ({
            kind,
            count: counts[kind],
            label: this.typeLabel(kind),
            color: this.typeColor(kind),
        }));
    }

    dominantType(entries) {
        return entries.reduce((dominant, entry) => (
            !dominant || entry.count > dominant.count ? entry : dominant
        ), null);
    }

    typeLegendItem(entry, total) {
        const percent = total > 0 ? Math.round((entry.count / total) * 100) : 0;

        return `<li class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 rounded-md border border-[rgba(148,163,184,.18)] bg-[rgba(15,23,42,.44)] px-3 py-2">
            <span class="h-2.5 w-2.5 rounded-full shadow-[0_0_18px_currentColor]" style="color:${escapeAttr(entry.color)};background:${escapeAttr(entry.color)}" aria-hidden="true"></span>
            <span class="min-w-0 truncate text-sm font-semibold text-slate-200">${escapeHtml(entry.label)}</span>
            <span class="text-xs font-semibold text-slate-400">${Number(entry.count)} (${percent}%)</span>
        </li>`;
    }

    pieBackground(entries, total) {
        if (entries.length === 0 || total <= 0) {
            return 'rgba(148, 163, 184, .18)';
        }

        let cursor = 0;
        const stops = entries.map((entry, index) => {
            const start = cursor;
            const end = index === entries.length - 1
                ? 360
                : Math.min(360, cursor + (entry.count / total) * 360);
            cursor = end;

            return `${entry.color} ${start.toFixed(2)}deg ${end.toFixed(2)}deg`;
        });

        return `conic-gradient(${stops.join(', ')})`;
    }

    typeAriaLabel(entries, total) {
        if (entries.length === 0 || total <= 0) {
            return this.label('empty');
        }

        return entries
            .map((entry) => `${entry.label}: ${entry.count}`)
            .join(', ');
    }

    typeLabel(kind) {
        return String(this.config.typeLabels?.[kind] || kind);
    }

    typeColor(kind) {
        const colors = this.config.typeColors || {};
        const fallback = {
            image: '#38bdf8',
            document: '#22c55e',
            archive: '#f59e0b',
            file: '#f43f5e',
        };

        return /^#[0-9a-fA-F]{6}$/.test(String(colors[kind] || '')) ? colors[kind] : fallback[kind];
    }

    fileMeta(file) {
        return [file.mime_type, file.size_label, file.dimensions_label].filter(Boolean).join(' / ');
    }

    formatBytes(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    inputClass(extra = '') {
        return `w-full min-w-0 rounded-md border border-[rgba(148,163,184,.24)] bg-[rgba(2,6,23,.32)] px-2.5 text-sm text-slate-100 outline-none transition placeholder:text-slate-600 focus:border-accent ${extra}`;
    }

    initStyledSelects() {
        initStyledSelects(this.root.querySelectorAll('select[data-styled-select], select[data-media-visibility]'));
    }

    visibilityOptions(selected) {
        const options = this.config.visibilityOptions || {
            public: this.label('public'),
            registered: this.label('registered'),
            private: this.label('private'),
        };
        return Object.entries(options).map(([value, label]) => {
            const isSelected = String(value) === String(selected) ? ' selected' : '';
            return `<option value="${escapeAttr(value)}"${isSelected}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    visibilityLabel(value) {
        const normalized = this.normalizeVisibility(value);
        return String(this.config.visibilityOptions?.[normalized] || normalized);
    }

    visibility(file) {
        return this.normalizeVisibility(file.visibility || 'public');
    }

    uploadVisibilityValue() {
        return this.normalizeVisibility(this.uploadVisibility?.value || 'public');
    }

    normalizeVisibility(value) {
        const visibility = String(value || 'public').toLowerCase();
        if (visibility === 'admin') {
            return 'registered';
        }

        return ['public', 'registered', 'private'].includes(visibility) ? visibility : 'public';
    }

    isDownloadable(file) {
        return this.visibility(file) !== 'private' && String(file.public_url || '') !== '';
    }

    searchTerm() {
        return String(this.search?.value || '').trim();
    }

    updateViewButtons() {
        this.viewButtons.forEach((button) => {
            button.dataset.active = String(button.dataset.mediaView || '') === this.viewMode ? 'true' : 'false';
        });
    }

    storedViewMode() {
        try {
            return localStorage.getItem('nafcms:media:view') === 'list' ? 'list' : 'grid';
        } catch (error) {
            return 'grid';
        }
    }

    storeViewMode() {
        try {
            localStorage.setItem('nafcms:media:view', this.viewMode);
        } catch (error) {}
    }

    iconSvg(type) {
        const paths = {
            stack: '<path d="M5 7.5 12 4l7 3.5-7 3.5-7-3.5Z"/><path d="m5 12 7 3.5 7-3.5"/><path d="m5 16.5 7 3.5 7-3.5"/>',
            image: '<path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-11Z"/><path d="m7.5 16 3-3.2 2.3 2.3 1.5-1.5 2.7 2.4"/><path d="M9 8.5h.01"/>',
            file: '<path d="M7 4h6l4 4v12H7V4Z"/><path d="M13 4v4h4"/>',
            folder: '<path d="M4.5 7A2.5 2.5 0 0 1 7 4.5h3l1.8 2H17A2.5 2.5 0 0 1 19.5 9v6.5A2.5 2.5 0 0 1 17 18H7a2.5 2.5 0 0 1-2.5-2.5V7Z"/>',
            globe: '<path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z"/><path d="M4.5 12h15"/><path d="M12 4a12 12 0 0 1 0 16"/><path d="M12 4a12 12 0 0 0 0 16"/>',
            lock: '<path d="M7 10V8a5 5 0 0 1 10 0v2"/><path d="M5.5 10h13v10h-13V10Z"/>',
            user: '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        };

        return `<svg class="h-4 w-4 flex-none fill-none stroke-current stroke-2 text-slate-500" viewBox="0 0 24 24" aria-hidden="true">${paths[type] || paths.folder}</svg>`;
    }

    can(permission) {
        return this.config.permissions?.[permission] === true;
    }

    url(key, row = {}) {
        return String(this.config.urls?.[key] || '#').replace(/\{([a-zA-Z0-9_.-]+)\}/g, (_, name) => encodeURIComponent(String(row[name] ?? '')));
    }

    label(key) {
        return String(this.config.labels?.[key] || key);
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

document.querySelectorAll('[data-media-library]').forEach((root) => {
    new MediaLibrary(root).init();
});
