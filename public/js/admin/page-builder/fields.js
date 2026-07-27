import {
    chartMaxMetrics,
    chartMetricColors,
    customLayoutSurfaces,
    escapeHtml,
    inputClass,
    languageIcon,
    optionLabel,
} from './shared.js';

export const fieldMethods = {
    statsItems(content = this.selectedContent()) {
        const props = content?.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        const fromArray = Array.isArray(props.stats)
            ? props.stats
                .filter((item) => item && typeof item === 'object' && !Array.isArray(item))
                .map((item, index) => ({
                    value: String(item.value ?? '').trim(),
                    label: String(item.label ?? '').trim() || this.labels.statFallback.replace(':number', String(index + 1)),
                }))
                .filter((item) => item.value !== '' || item.label !== '')
            : [];

        if (fromArray.length > 0) {
            return fromArray;
        }

        const legacy = [
            {value: props.valueA, label: props.labelA},
            {value: props.valueB, label: props.labelB},
            {value: props.valueC, label: props.labelC},
        ].map((item, index) => ({
            value: String(item.value ?? '').trim(),
            label: String(item.label ?? '').trim() || this.labels.statFallback.replace(':number', String(index + 1)),
        })).filter((item) => item.value !== '' || item.label !== '');

        return legacy.length > 0 ? legacy : [
            {value: '12', label: 'Items'},
            {value: '98%', label: 'Quality'},
            {value: '24/7', label: 'Access'},
        ];
    },

    syncStatsProps(content, items) {
        if (!content) {
            return;
        }

        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        content.props.stats = items.map((item, index) => ({
            value: String(item?.value ?? '').trim(),
            label: String(item?.label ?? '').trim() || this.labels.statFallback.replace(':number', String(index + 1)),
        })).filter((item) => item.value !== '' || item.label !== '');
        delete content.props.valueA;
        delete content.props.labelA;
        delete content.props.valueB;
        delete content.props.labelB;
        delete content.props.valueC;
        delete content.props.labelC;
    },

    addStatItem() {
        const content = this.selectedContent();
        if (!content || content.type !== 'stats') {
            return;
        }

        const items = this.statsItems(content);
        items.push({
            value: '',
            label: this.labels.statFallback.replace(':number', String(items.length + 1)),
        });
        this.syncStatsProps(content, items);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    },

    removeStatItem(index) {
        const content = this.selectedContent();
        if (!content || content.type !== 'stats') {
            return;
        }

        const items = this.statsItems(content);
        if (items.length <= 1 || index < 0 || index >= items.length) {
            return;
        }

        items.splice(index, 1);
        this.syncStatsProps(content, items);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    },

    updateStatItem(index, field, value) {
        const content = this.selectedContent();
        if (!content || content.type !== 'stats' || index < 0 || !['label', 'value'].includes(field)) {
            return;
        }

        const items = this.statsItems(content);
        if (!items[index]) {
            return;
        }

        items[index][field] = String(value ?? '');
        this.syncStatsProps(content, items);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.persistState();
    },

    chartMetrics(content = this.selectedContent()) {
        const props = content?.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        const fromArray = Array.isArray(props.metrics)
            ? props.metrics
                .filter((metric) => metric && typeof metric === 'object' && !Array.isArray(metric))
                .map((metric, index) => ({
                    label: String(metric.label ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
                    value: Number(metric.value ?? 0),
                    color: this.normalizeColor(metric.color) || chartMetricColors[index % chartMetricColors.length],
                }))
            : [];

        if (fromArray.length > 0) {
            return fromArray.slice(0, chartMaxMetrics);
        }

        const highestLegacyMetric = Array.from({length: 6}, (_, index) => index + 1)
            .reduce((highest, index) => (
                props[`label${index}`] !== undefined || props[`value${index}`] !== undefined || props[`color${index}`] !== undefined
                    ? index
                    : highest
            ), 0);
        const count = Math.max(1, Math.min(chartMaxMetrics, Math.max(Number(props.metricCount || 3), highestLegacyMetric)));
        return Array.from({length: count}, (_, index) => ({
            label: String(props[`label${index + 1}`] ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
            value: Number(props[`value${index + 1}`] ?? 0),
            color: this.normalizeColor(props[`color${index + 1}`]) || chartMetricColors[index % chartMetricColors.length],
        }));
    },

    syncChartMetricProps(content, metrics) {
        if (!content) {
            return;
        }

        content.props = content.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
        const normalized = metrics.slice(0, chartMaxMetrics).map((metric, index) => ({
            label: String(metric?.label ?? '').trim() || this.labels.metricFallback.replace(':number', String(index + 1)),
            value: Number(metric?.value ?? 0),
            color: this.normalizeColor(metric?.color) || chartMetricColors[index % chartMetricColors.length],
        }));

        content.props.metrics = normalized;
        delete content.props.metricCount;
        for (let index = 1; index <= 6; index++) {
            delete content.props[`label${index}`];
            delete content.props[`value${index}`];
            delete content.props[`color${index}`];
        }
    },

    addChartMetric() {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart') {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (metrics.length >= chartMaxMetrics) {
            return;
        }

        metrics.push({
            label: this.labels.metricFallback.replace(':number', String(metrics.length + 1)),
            value: 0,
            color: chartMetricColors[metrics.length % chartMetricColors.length],
        });
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    },

    removeChartMetric(index) {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart') {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (metrics.length <= 1 || index < 0 || index >= metrics.length) {
            return;
        }

        metrics.splice(index, 1);
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.renderSettings();
        this.persistState();
    },

    updateChartMetric(index, field, value) {
        const content = this.selectedContent();
        if (!content || content.type !== 'chart' || index < 0 || !['label', 'value', 'color'].includes(field)) {
            return;
        }

        const metrics = this.chartMetrics(content);
        if (!metrics[index]) {
            return;
        }

        metrics[index][field] = field === 'value' ? Number(value || 0) : value;
        this.syncChartMetricProps(content, metrics);
        this.renderBlocks();
        this.renderPagePreview();
        this.renderJson();
        this.persistState();
    },

    renderField(key, value, contentType = '', field = null) {
        const label = (key === 'surface' || (key === 'tone' && contentType === 'section'))
            ? this.labels.surface
            : this.uiText(field?.label || key);
        if (field?.type === 'metrics') {
            return this.renderChartMetricsField(label);
        }
        if (field?.type === 'stats') {
            return this.renderStatsField(label);
        }

        if (key === 'tone' && contentType === 'section') {
            return this.renderSelect(key, value, this.surfaceOptions(), label);
        }
        if (key === 'surface') {
            return this.renderSelect(key, value, this.surfaceOptions(), label);
        }

        if (field?.type === 'select') {
            return this.renderSelect(key, value, field.options || [], label);
        }

        if (field?.type === 'textarea') {
            if (this.isTranslatableField(key, value, field)) {
                return this.renderTranslatableInput(key, value, label, true);
            }

            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        if (field?.type === 'media') {
            return this.renderMediaField(key, value, label, field);
        }

        if (field?.type === 'color') {
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <span class="grid grid-cols-[42px_minmax(0,1fr)] gap-2">
                        <input class="h-10 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1" type="color" value="${escapeHtml(this.normalizeColor(value) || '#3f7fbd')}" data-prop-name="${escapeHtml(key)}">
                        <input class="${inputClass}" type="text" value="${escapeHtml(this.normalizeColor(value) || '#3f7fbd')}" data-prop-name="${escapeHtml(key)}">
                    </span>
                </label>
            `;
        }

        if (key === 'level') {
            return this.renderSelect(key, value, ['h1', 'h2', 'h3'], label);
        }
        if (key === 'align') {
            return this.renderSelect(key, value, ['left', 'center', 'right'], label);
        }
        if (key === 'tone') {
            return this.renderSelect(key, value, ['body', 'lead', 'muted'], label);
        }
        if (key === 'presentation') {
            return this.renderSelect(key, value, contentType === 'navigation'
                ? ['horizontal', 'vertical', 'compact']
                : ['list', 'cards', 'compact'], label);
        }
        if (key === 'source' && contentType === 'articles') {
            return this.renderSelect(key, value, ['latest', 'featured', 'category', 'related'], label);
        }
        if (key === 'language') {
            return this.renderSelect(key, value, ['current', 'de', 'en', 'fr', 'es', 'it'], label);
        }

        if (field?.type === 'checkbox' || typeof value === 'boolean') {
            return this.renderSwitchField(label, `data-prop-name="${escapeHtml(key)}"`, Boolean(value));
        }

        if (key === 'text') {
            if (this.isTranslatableField(key, value, field)) {
                return this.renderTranslatableInput(key, value, label, true);
            }

            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <textarea class="${inputClass} min-h-24 resize-y" data-prop-name="${escapeHtml(key)}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        if (this.isTranslatableField(key, value, field)) {
            return this.renderTranslatableInput(key, value, label, false);
        }

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(this.uiText(label))}</span>
                <input class="${inputClass}" type="${field?.type === 'number' || typeof value === 'number' ? 'number' : 'text'}" value="${escapeHtml(value)}" ${field?.min !== undefined ? `min="${escapeHtml(field.min)}"` : ''} ${field?.max !== undefined ? `max="${escapeHtml(field.max)}"` : ''} ${field?.step !== undefined ? `step="${escapeHtml(field.step)}"` : ''} data-prop-name="${escapeHtml(key)}">
            </label>
        `;
    },

    renderSwitchField(label, attributes, checked = false) {
        return `
            <label class="admin-switch-field">
                <input class="sr-only" type="checkbox" ${checked ? 'checked' : ''} ${attributes}>
                <span class="admin-switch-field__track" aria-hidden="true">
                    <span class="admin-switch-field__thumb"></span>
                </span>
                <span class="admin-switch-field__label">${escapeHtml(label)}</span>
            </label>
        `;
    },

    renderChartMetricsField(label = this.labels.metrics) {
        const metrics = this.chartMetrics();
        const canRemove = metrics.length > 1;
        const rows = metrics.map((metric, index) => `
            <div class="grid gap-2 rounded-md border border-slate-700 bg-slate-950/35 p-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricFallback.replace(':number', String(index + 1)))}</span>
                    <button class="grid h-7 w-7 place-items-center rounded-md border border-rose-900/70 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50 disabled:cursor-not-allowed disabled:opacity-40" type="button" data-chart-metric-remove="${index}" ${canRemove ? '' : 'disabled'} aria-label="${escapeHtml(this.labels.removeMetric)}">
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                </div>
                <label class="grid gap-1">
                    <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricLabel)}</span>
                    <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="text" value="${escapeHtml(metric.label)}" data-chart-metric-index="${index}" data-chart-metric-field="label">
                </label>
                <div class="grid grid-cols-[minmax(0,1fr)_42px] gap-2">
                    <label class="grid gap-1">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricValue)}</span>
                        <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="number" step="0.1" value="${escapeHtml(metric.value)}" data-chart-metric-index="${index}" data-chart-metric-field="value">
                    </label>
                    <label class="grid gap-1">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.metricColor)}</span>
                        <input class="h-8 w-full rounded-md border border-slate-700 bg-[#0b1017] p-1" type="color" value="${escapeHtml(metric.color)}" data-chart-metric-index="${index}" data-chart-metric-field="color">
                    </label>
                </div>
            </div>
        `).join('');

        return `
            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label || this.labels.metrics)}</span>
                    <button class="inline-flex min-h-8 items-center rounded-md border border-slate-700 px-2.5 text-xs font-semibold text-slate-300 transition hover:border-[var(--admin-page-accent)] disabled:cursor-not-allowed disabled:opacity-50" type="button" data-chart-metric-add ${metrics.length >= chartMaxMetrics ? 'disabled' : ''}>
                        ${escapeHtml(this.labels.addMetric)}
                    </button>
                </div>
                <div class="grid gap-2">
                    ${rows}
                </div>
            </div>
        `;
    },

    renderStatsField(label = this.labels.statistics) {
        const items = this.statsItems();
        const canRemove = items.length > 1;
        const rows = items.map((item, index) => `
            <div class="grid gap-3 rounded-md border border-slate-700 bg-slate-950/35 p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.statFallback.replace(':number', String(index + 1)))}</span>
                    <button class="grid h-7 w-7 place-items-center rounded-md border border-rose-900/70 text-rose-300 transition hover:border-rose-500 hover:bg-rose-950/50 disabled:cursor-not-allowed disabled:opacity-40" type="button" data-stat-remove="${index}" ${canRemove ? '' : 'disabled'} aria-label="${escapeHtml(this.labels.removeStat)}">
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                </div>
                <label class="grid gap-1">
                    <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.statValue)}</span>
                    <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="text" value="${escapeHtml(item.value)}" placeholder="98%" data-stat-index="${index}" data-stat-field="value">
                </label>
                <label class="grid gap-1">
                    <span class="text-[10px] font-medium uppercase tracking-wide text-slate-500">${escapeHtml(this.labels.statLabel)}</span>
                    <input class="${inputClass} min-h-8 px-2.5 py-1.5 text-[13px]" type="text" value="${escapeHtml(item.label)}" placeholder="${escapeHtml(this.labels.statFallback.replace(':number', String(index + 1)))}" data-stat-index="${index}" data-stat-field="label">
                </label>
            </div>
        `).join('');

        return `
            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label || this.labels.statistics)}</span>
                    <button class="inline-flex min-h-8 items-center rounded-md border border-slate-700 px-2.5 text-xs font-semibold text-slate-300 transition hover:border-[var(--admin-page-accent)]" type="button" data-stat-add>
                        ${escapeHtml(this.labels.addStat)}
                    </button>
                </div>
                <div class="grid gap-2">
                    ${rows}
                </div>
            </div>
        `;
    },

    renderMediaField(key, value, label, field = null) {
        const url = String(value || '');
        const hasMedia = url !== '';
        const altTarget = String(field?.altTarget || '');
        const preview = hasMedia
            ? `<img class="h-full w-full object-cover" src="${escapeHtml(url)}" alt="">`
            : `<span class="text-xs font-semibold text-slate-500">${escapeHtml(this.labels.mediaEmptyUrl)}</span>`;

        return `
            <div class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(this.uiText(label))}</span>
                <div class="grid gap-2 rounded-md border border-slate-700 bg-[#0b1017] p-2">
                    <div class="grid h-32 place-items-center overflow-hidden rounded border border-slate-800 bg-slate-950/60">${preview}</div>
                    <input type="hidden" value="${escapeHtml(url)}" data-prop-name="${escapeHtml(key)}">
                    <div class="flex items-center gap-2">
                        <input class="${inputClass} min-w-0 flex-1 text-xs" type="text" value="${escapeHtml(url)}" readonly>
                        <button class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-700 px-3 text-xs font-semibold text-slate-200 transition hover:border-[var(--admin-page-accent)]" type="button" data-media-field="${escapeHtml(key)}" data-media-alt-target="${escapeHtml(altTarget)}">${escapeHtml(hasMedia ? this.labels.mediaReplace : this.labels.mediaSelect)}</button>
                        ${hasMedia ? `<button class="inline-flex min-h-9 items-center justify-center rounded-md border border-rose-400/30 px-3 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10" type="button" data-media-clear="${escapeHtml(key)}">${escapeHtml(this.labels.mediaClear)}</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    },

    renderSelect(key, value, options, label = key) {
        const normalizedOptions = options.map((option) => {
            if (typeof option === 'object' && option !== null) {
                return {
                    label: optionLabel(option, this.labels),
                    value: option.value ?? option.label ?? '',
                };
            }

            return {label: this.selectOptionLabel(option), value: option};
        });

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(this.uiText(label))}</span>
                <select class="${inputClass}" data-styled-select data-prop-name="${escapeHtml(key)}">
                    ${normalizedOptions.map((option) => `<option value="${escapeHtml(option.value)}" ${option.value === value ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}
                </select>
            </label>
        `;
    },

    selectOptionLabel(option) {
        const labels = {
            body: 'Body',
            lead: 'Lead',
            muted: 'Muted',
            left: 'Left',
            center: 'Center',
            right: 'Right',
            top: 'Top',
            bottom: 'Bottom',
            start: 'Start',
            end: 'End',
            stretch: 'Stretch',
            horizontal: 'Horizontal',
            vertical: 'Vertical',
            compact: 'Compact',
            list: 'List',
            cards: 'Cards',
            latest: 'Latest',
            featured: 'Featured',
            category: 'Category',
            related: 'Related',
            current: 'Current',
            blank: 'Blank',
            underline: 'Underline',
            pill: 'Pill',
            'rounded-pill': 'Rounded pill',
            filled: 'Filled',
            'filled-rounded': 'Filled rounded',
            default: 'Default',
            accent: 'Accent',
            surface: 'Surface',
        };

        return this.uiText(labels[String(option)] || String(option));
    },

    renderTranslatableInput(key, value, label, multiline = false) {
        const escapedKey = escapeHtml(key);
        const escapedValue = escapeHtml(value);
        const title = escapeHtml(this.labels.translationKey);

        if (multiline) {
            return `
                <label class="grid gap-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                    <span class="relative block">
                        <textarea class="${inputClass} min-h-24 resize-y pr-16" data-prop-name="${escapedKey}">${escapedValue}</textarea>
                        <button class="absolute right-px top-px inline-flex h-10 w-14 items-center justify-center rounded-bl-md rounded-tr-md border-b border-l border-slate-700 bg-slate-900/80 text-slate-500 transition hover:bg-slate-800 hover:text-slate-300" type="button" data-translate-prop="${escapedKey}" title="${title}" aria-label="${title}">
                            ${languageIcon}
                        </button>
                    </span>
                </label>
            `;
        }

        return `
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escapeHtml(label)}</span>
                <span class="flex min-h-9 overflow-hidden rounded-md border border-slate-700 bg-[#0b1017] focus-within:border-[var(--admin-page-accent)]">
                    <input class="min-h-9 min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-slate-100 outline-none" type="text" value="${escapedValue}" data-prop-name="${escapedKey}">
                    <button class="inline-flex min-h-9 w-14 shrink-0 items-center justify-center border-l border-slate-700 bg-slate-900/70 px-2 text-slate-500 transition hover:bg-slate-800 hover:text-slate-300" type="button" data-translate-prop="${escapedKey}" title="${title}" aria-label="${title}">
                        ${languageIcon}
                    </button>
                </span>
            </label>
        `;
    },

    isTranslatableField(key, value, field = null) {
        if (field?.translatable === false) {
            return false;
        }
        if (field?.translatable === true) {
            return true;
        }
        if (field?.type && !['text', 'textarea'].includes(field.type)) {
            return false;
        }
        if (typeof value === 'boolean' || typeof value === 'number') {
            return false;
        }

        const normalized = String(key || '').toLowerCase();
        const excluded = [
            'url',
            'href',
            'path',
            'image',
            'media',
            'color',
            'identifier',
            'language',
            'presentation',
            'align',
            'level',
            'tone',
            'limit',
            'count',
            'width',
            'height',
            'category',
            'slug',
            'query',
            'embed',
        ];

        return !excluded.some((part) => normalized.includes(part));
    },

};
