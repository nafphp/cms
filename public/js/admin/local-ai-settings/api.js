// Talking to the admin API and making sense of what comes back.
//
// parseModelJson and normalizeReviewItems are defensive by design: both parse
// payloads that a local model produced, so malformed input is expected traffic
// rather than an exceptional case.

import { stripModelReasoning, trimValue } from '../ollama-client.js';

export const apiJson = async (url, options = {}) => {
    const client = typeof window.NafCMS?.json === 'function'
        ? window.NafCMS.json
        : window.fetch;
    const csrfHeader = String(window.NafCMS?.csrfHeader || 'X-CSRF-Token');
    const csrfToken = typeof window.NafCMS?.csrfToken === 'function'
        ? String(window.NafCMS.csrfToken() || '')
        : '';
    const headers = {
        'Accept': 'application/json',
        ...(options.body ? {'Content-Type': 'application/json'} : {}),
        ...(options.headers || {}),
    };
    if (csrfToken !== '' && !Object.hasOwn(headers, csrfHeader)) {
        headers[csrfHeader] = csrfToken;
    }

    const response = await client.call(window, url, {
        ...options,
        headers,
    });
    const raw = await response.text().catch(() => '');
    let payload = {};
    try {
        payload = raw !== '' ? JSON.parse(raw) : {};
    } catch (error) {}
    if (!response.ok || payload.success === false) {
        throw new Error(payload.reason || payload.message || raw || `Request failed with HTTP ${response.status}.`);
    }

    return payload;
};

export const parseModelJson = (value) => {
    const cleaned = stripModelReasoning(value);
    const candidates = [
        cleaned,
        cleaned.match(/\{[\s\S]*\}/)?.[0] || '',
    ].filter(Boolean);

    for (const candidate of candidates) {
        try {
            return JSON.parse(candidate);
        } catch (error) {}
    }

    return {};
};

export const normalizeReviewItems = (items) => (Array.isArray(items) ? items : [])
    .map((item) => ({
        title: trimValue(item?.title || ''),
        problem: trimValue(item?.problem || ''),
        question: trimValue(item?.question || ''),
        solution: trimValue(item?.solution || item?.proposed_solution || item?.proposedSolution || ''),
        proposedMemory: trimValue(item?.proposed_memory || item?.proposedMemory || ''),
        sourceIds: Array.isArray(item?.source_ids || item?.sourceIds)
            ? (item.source_ids || item.sourceIds).map((id) => trimValue(id)).filter(Boolean)
            : [],
        researchNotes: [],
    }))
    .filter((item) => item.title !== '' || item.problem !== '' || item.proposedMemory !== '' || item.solution !== '');

export const randomItem = (items) => Array.isArray(items) && items.length > 0
    ? items[Math.floor(Math.random() * items.length)]
    : '';
