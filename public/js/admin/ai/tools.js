import { trimValue } from '../ollama-client.js';
import { uiText } from './i18n.js';

export const toolByName = (tools, name) => Array.isArray(tools)
    ? tools.find((tool) => trimValue(tool?.name) === trimValue(name)) || null
    : null;

export const canAutoRunToolCall = (toolCall, tools = []) => {
    const meta = toolMeta(toolByName(tools, toolCall?.name));

    return meta.autoRun === true && meta.risk === 'read' && meta.destructive !== true;
};

const sanitizeOllamaSchema = (schema) => {
    const normalized = schema && typeof schema === 'object' && !Array.isArray(schema)
        ? {...schema}
        : {};

    if (normalized.type === 'object' || normalized.properties !== undefined) {
        normalized.type = 'object';
        normalized.properties = normalized.properties && typeof normalized.properties === 'object' && !Array.isArray(normalized.properties)
            ? Object.fromEntries(Object.entries(normalized.properties).map(([key, value]) => [key, sanitizeOllamaSchema(value)]))
            : {};
        normalized.additionalProperties = normalized.additionalProperties === true;
    }

    if (normalized.type === 'array') {
        normalized.items = sanitizeOllamaSchema(normalized.items || {});
    }

    if (Array.isArray(normalized.required) && normalized.required.length === 0) {
        delete normalized.required;
    }

    return normalized;
};

export const ollamaTools = (tools) => tools
    .filter((tool) => tool && trimValue(tool.name) !== '')
    .map((tool) => ({
        type: 'function',
        function: {
            name: trimValue(tool.name),
            description: trimValue(tool.description),
            parameters: sanitizeOllamaSchema(tool.inputSchema || {
                type: 'object',
                properties: {},
                additionalProperties: false,
            }),
        },
    }));

const toolMeta = (tool) => (tool && typeof tool.meta === 'object' && tool.meta !== null) ? tool.meta : {};

const toolDisplayNames = {
    cms_admin_navigate: 'Admin navigation',
    cms_article_draft_append: 'Append article draft',
    cms_article_draft_create: 'Create article draft',
    cms_article_lookup: 'Find articles',
    cms_article_status_counts: 'Article status counts',
    cms_audit_recent: 'Activity log',
    cms_content_overview: 'Content overview',
    cms_media_summary: 'Media overview',
    cms_page_overview: 'Page overview',
    cms_pagebuilder_inspect: 'Inspect PageBuilder element',
    cms_plugin_overview: 'Plugin overview',
    cms_queue_status: 'Queue status',
    cms_system_health: 'System status',
    cms_tools_search: 'Search tools',
    cms_user_role_summary: 'Users and roles',
    external_air_quality: 'Air quality',
    external_exchange_rates: 'Exchange rates',
    external_public_holidays: 'Public holidays',
    external_trending_topics: 'Trending topics',
    external_weather_forecast: 'Weather data',
};

const prettifyToolIdentifier = (name) => trimValue(name)
    .replace(/^(cms|external)_/, '')
    .split('_')
    .filter(Boolean)
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ');

export const toolDisplayName = (toolOrName) => {
    const tool = typeof toolOrName === 'string' ? null : toolOrName;
    const name = typeof toolOrName === 'string' ? trimValue(toolOrName) : trimValue(toolOrName?.name);
    const meta = toolMeta(tool);

    const fallback = meta.title || meta.label || meta.displayName || toolDisplayNames[name] || prettifyToolIdentifier(name);

    return uiText(fallback);
};

export const sanitizeVisibleToolIdentifiers = (content, tools = []) => {
    let sanitized = String(content ?? '');
    const names = new Set([
        ...Object.keys(toolDisplayNames),
        ...tools.map((tool) => trimValue(tool?.name)).filter(Boolean),
    ]);

    names.forEach((name) => {
        const label = toolDisplayName(toolByName(tools, name) || name);
        if (name === '' || label === '') {
            return;
        }

        sanitized = sanitized.replaceAll(`\`${name}\``, label);
        sanitized = sanitized.replaceAll(name, label);
    });

    return sanitized;
};

export const localModelKeepAlive = '15m';

const normalizeToolArguments = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'string' && trimValue(value) !== '') {
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    return {};
};

export const normalizeToolCalls = (response) => {
    const calls = response?.message?.tool_calls;
    return Array.isArray(calls) ? calls
        .map((call) => ({
            name: trimValue(call?.function?.name),
            arguments: normalizeToolArguments(call?.function?.arguments),
        }))
        .filter((call) => call.name !== '')
        : [];
};

export const toolArgumentsLabel = (args) => {
    const json = JSON.stringify(args || {}, null, 2);
    return json === '{}' ? uiText('No arguments') : json;
};

const toolCallsLabel = (toolCalls, tools = []) => toolCalls
    .map((toolCall) => toolDisplayName(toolByName(tools, toolCall.name) || toolCall.name))
    .filter(Boolean)
    .join(', ');

export const toolActivityLine = (toolCalls, tools = [], status = 'angefragt') => {
    const label = toolCallsLabel(toolCalls, tools);

    return label === '' ? '' : uiText('Tool call :status: :tool', {status: uiText(status), tool: label});
};

const fallbackValue = (value) => {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? uiText('yes') : uiText('no');
    }

    if (typeof value === 'number' || typeof value === 'string') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.length === 0
            ? '[]'
            : JSON.stringify(value);
    }

    return JSON.stringify(value);
};

const flattenResultRows = (value, path = '') => {
    if (value === null || value === undefined) {
        return [[path || 'value', fallbackValue(value)]];
    }

    if (typeof value !== 'object') {
        return [[path || 'value', fallbackValue(value)]];
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return [[path || 'value', '[]']];
        }

        return value.flatMap((entry, index) => flattenResultRows(entry, `${path}[${index}]`));
    }

    const entries = Object.entries(value);
    if (entries.length === 0) {
        return [[path || 'value', '{}']];
    }

    return entries.flatMap(([key, entry]) => flattenResultRows(entry, path === '' ? key : `${path}.${key}`));
};

const toolResultMarkdown = (result) => {
    if (!result || typeof result !== 'object' || Array.isArray(result)) {
        return fallbackValue(result);
    }

    const rows = flattenResultRows(result);
    if (rows.length === 0) {
        return '-';
    }

    return [
        `| ${uiText('Field')} | ${uiText('Value')} |`,
        '| --- | --- |',
        ...rows.map(([key, value]) => `| ${key} | ${String(value).replace(/\|/g, '\\|')} |`),
    ].join('\n');
};

export const toolResultsGroundingMessage = (results, tools = []) => {
    if (!Array.isArray(results) || results.length === 0) {
        return '';
    }

    const toolDescriptions = results
        .map((entry) => {
            const tool = toolByName(tools, entry.tool);
            const description = trimValue(tool?.description);

            return description === ''
                ? ''
                : `${toolDisplayName(tool || entry.tool)}: ${description}`;
        })
        .filter(Boolean);

    return [
        'TOOL RESULT ANSWER MODE.',
        'The tool result JSON below is the authoritative and complete source of truth for the next answer.',
        'Use the returned tool data come what may. Do not answer from memory, training data, assumptions, or generic CMS knowledge.',
        'Every factual claim in the answer must be grounded in a value that appears in the tool result JSON below.',
        'Never write placeholders such as "(date/time)", "8.x", "not specified", "nicht angegeben", "scheint", or "impliziert" when the JSON contains concrete values.',
        'If a scalar value exists in the JSON, quote or summarize that exact value instead of replacing it with vague phrases like current, successful, available, active, or loaded.',
        'If the JSON does not contain a field, omit that field unless the user explicitly asked whether it is absent.',
        'Prefer Markdown tables for structured diagnostic data.',
        '',
        ...(toolDescriptions.length > 0 ? [
            'Relevant tool descriptions and answer guidance:',
            ...toolDescriptions,
            '',
        ] : []),
        'Flattened authoritative tool result values:',
        toolResultsMarkdownTable(results, tools),
        '',
        'Raw authoritative tool result JSON:',
        JSON.stringify(results.map((entry) => ({
            tool: toolDisplayName(toolByName(tools, entry.tool) || entry.tool),
            result: entry.result ?? null,
        })), null, 2),
        '',
        'Now answer the user using only the authoritative tool result values above.',
    ].join('\n');
};

export const toolResultsMarkdownTable = (results, tools = []) => {
    if (!Array.isArray(results) || results.length === 0) {
        return uiText('Ollama returned an empty response.');
    }

    return [
        uiText('I retrieved the requested data. Here is a compact overview:'),
        '',
        ...results.map((entry) => [
            `**${uiText('Tool')}:** ${toolDisplayName(toolByName(tools, entry.tool) || entry.tool)}`,
            '',
            toolResultMarkdown(entry.result ?? null),
        ].join('\n')),
    ].join('\n');
};
