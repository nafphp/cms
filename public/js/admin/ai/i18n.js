import { trimValue } from '../ollama-client.js';

let cmsTranslations = {};
let cmsTranslationsPromise = null;

export const uiLanguage = () => trimValue(document.documentElement.lang || 'en').split('-')[0] || 'en';

export const loadCmsTranslations = () => {
    if (cmsTranslationsPromise) {
        return cmsTranslationsPromise;
    }

    const language = uiLanguage();
    cmsTranslationsPromise = (window.NafCMS?.json || fetch)(`/api/admin/translations/${encodeURIComponent(language)}`, {
        method: 'GET',
        headers: {'Accept': 'application/json'},
    })
        .then((response) => response.json())
        .then((data) => {
            cmsTranslations = data?.success === true && data.entries && typeof data.entries === 'object'
                ? data.entries
                : {};
            return cmsTranslations;
        })
        .catch(() => ({}));

    return cmsTranslationsPromise;
};

export const uiText = (key, replacements = {}) => {
    let text = String(cmsTranslations[key] || key);
    Object.entries(replacements).forEach(([name, value]) => {
        text = text.replaceAll(`:${name}`, String(value ?? ''));
    });

    return text;
};

export const greetingTemplates = [
    () => uiText('Hello, how can I help you?'),
    () => uiText('Hey, where should we start?'),
    () => uiText('Hello, I am ready. What should we look at?'),
    () => uiText('Hi, what can I do for you in the CMS?'),
    () => uiText('Hello, what can I help you with?'),
    () => uiText('Nafarious here. What is going on?'),
    () => uiText('At your service. What shall we sort out?'),
    () => uiText('Ready at the console. What needs attention?'),
    () => uiText('Good to see you. Where shall we begin?'),
    () => uiText('The admin hall is open. What are we looking at?'),
    (name) => uiText('Personal greeting: ready', {name}),
    (name) => uiText('Personal greeting: admin console', {name}),
    (name) => uiText('Personal greeting: good to see you', {name}),
];

export const anonymousGreetingTemplates = [
    () => uiText('Hello, how can I help you?'),
    () => uiText('Hey, where should we start?'),
    () => uiText('Hello, I am ready. What should we look at?'),
    () => uiText('Hi, what can I do for you in the CMS?'),
    () => uiText('Hello, what can I help you with?'),
    () => uiText('Nafarious here. What is going on?'),
    () => uiText('At your service. What shall we sort out?'),
    () => uiText('Ready at the console. What needs attention?'),
    () => uiText('Good to see you. Where shall we begin?'),
    () => uiText('The admin hall is open. What are we looking at?'),
];

export const randomItem = (items) => items[Math.floor(Math.random() * items.length)] || items[0];
