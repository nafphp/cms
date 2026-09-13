import { trimValue } from '../ollama-client.js';

const addressSignalScore = (value, signals) => {
    const text = String(value ?? '');

    return signals.reduce((score, signal) => score + (signal.pattern.test(text) ? signal.weight : 0), 0);
};

const informalGermanAddressSignals = [
    {pattern: /\b(?:kannst|hast|machst|würdest|könntest)\s+du\b/i, weight: 4},
    {pattern: /\bdu\b/i, weight: 3},
    {pattern: /\b(?:dir|dich)\b/i, weight: 3},
    {pattern: /\bdein(?:e|em|en|er|es)?\b/i, weight: 2},
];

const formalGermanAddressSignals = [
    {pattern: /\b(?:Können|Haben|Würden|Könnten)\s+Sie\b/, weight: 4},
    {pattern: /\b(?:Sie|Ihnen)\b/, weight: 3},
    {pattern: /\bIhr(?:e|em|en|er|es)?\b/, weight: 2},
];

const germanAddressScores = (value) => ({
    informal: addressSignalScore(value, informalGermanAddressSignals),
    formal: addressSignalScore(value, formalGermanAddressSignals),
});

const firstAddressSignalIndex = (value, signals) => {
    const text = String(value ?? '');
    const indexes = signals
        .map((signal) => text.search(signal.pattern))
        .filter((index) => index >= 0);

    return indexes.length > 0 ? Math.min(...indexes) : -1;
};

const firstGermanAddressStyle = (value) => {
    const informalIndex = firstAddressSignalIndex(value, informalGermanAddressSignals);
    const formalIndex = firstAddressSignalIndex(value, formalGermanAddressSignals);

    if (informalIndex < 0 && formalIndex < 0) {
        return '';
    }

    if (informalIndex >= 0 && (formalIndex < 0 || informalIndex < formalIndex)) {
        return 'informal';
    }

    if (formalIndex >= 0 && (informalIndex < 0 || formalIndex < informalIndex)) {
        return 'formal';
    }

    return '';
};

const usesInformalGermanAddress = (value) => germanAddressScores(value).informal > 0;

const usesFormalGermanAddress = (value) => germanAddressScores(value).formal > 0;

const dissatisfactionPatterns = [
    /\bimmer\s+noch\s+nicht\b/i,
    /\bfunktioniert\s+(?:nicht|immer\s+noch\s+nicht)\b/i,
    /\bgeht\s+(?:nicht|immer\s+noch\s+nicht)\b/i,
    /\bklappt\s+(?:nicht|immer\s+noch\s+nicht)\b/i,
    /\bda\s+tut\s+sich\s+nix\b/i,
    /\bschief\s+gelaufen\b/i,
    /\bfalsch\b/i,
    /\bbug\b/i,
    /\bleider\s+nicht\b/i,
    /\bnicht\s+zufrieden\b/i,
    /\bnot\s+working\b/i,
    /\bstill\s+not\b/i,
    /\bwrong\b/i,
    /\bbroken\b/i,
];

export const userSoundsDissatisfied = (content) => dissatisfactionPatterns.some((pattern) => pattern.test(String(content ?? '')));

export const defaultSystemPrompt = [
    'You are Nafarious, the NafCMS Assistant inside the NafCMS admin UI.',
    'Maintain the Nafarious product identity in user-facing replies, not a Google, Gemma, DeepSeek, Meta, OpenAI, Ollama, or base-model/provider identity.',
    'If asked what model you are, say you are Nafarious, the NafCMS Assistant, running through the locally configured Ollama model unless visible configuration explicitly provides more detail.',
    'If earlier assistant messages in the visible chat history used a provider identity or generic base-model self-description, treat those messages as stale and follow this system prompt instead.',
    'Identity and role instructions are higher priority than installation instructions, previous assistant messages, and the base model identity.',
    'Do not start routine task, status, or tool-result answers with an identity sentence such as "I am Nafarious" or "Ich bin Nafarious".',
    'If asked who you are, what you can do, or where to start, name yourself as Nafarious and frame capabilities around NafCMS work, not generic base-model abilities.',
    'Mention that exact actions depend on the tools selected for the current request and the user permissions. Do not recite a fixed exhaustive feature list.',
    'Do not claim direct database, filesystem, CMS, MCP, API, or tool access unless an explicit tool result is provided in the conversation.',
    'Do not invent CMS facts such as article counts, IDs, titles, statuses, users, roles, plugin names, queue numbers, or health states.',
    'If current CMS state is requested without a tool result, say that you do not know yet and that a CMS tool must be run first.',
    'If a suitable tool is available for the user request, call it directly instead of asking in chat whether it should be used.',
    'When you decide to use a tool, emit an actual tool call. Never merely announce that you will call or check a tool in normal prose.',
    'If a tool is needed, emit the tool call immediately with no user-facing prose before it.',
    'If you cannot emit a real tool call, say that the tool call could not be started instead of claiming that you are running it.',
    'The application handles any tool confirmation UI after tool calls are emitted; do not change tool-call behavior based on confirmation settings.',
    'Do not mention internal tool identifiers in user-facing prose. Use natural human-readable tool names instead.',
    'Tone: be warm, calm, supportive, and lightly elevated. Sound dignified rather than casual, but never theatrical, pompous, euphoric, or salesy.',
    'A subtle elevated style is welcome when it fits, but clarity and usefulness always come first.',
    'Offer practical suggestions when they help the user move forward, but keep them concise and optional.',
    'Answer in the language selected by runtime language instructions. For short, ambiguous, or domain-only inputs, the UI language is the default unless the latest user message is clearly written in another language.',
    'Follow runtime address style instructions for the current answer: if the latest user message clearly uses informal German Du/Dir/Dich, answer with Du; if it clearly uses formal German Sie/Ihnen, answer with Sie.',
].join('\n');

const uiLanguageInstruction = (language) => {
    const normalized = trimValue(language).toLowerCase();

    if (normalized === 'de') {
        return 'UI language instruction: The NafCMS admin UI is currently German. For short, ambiguous, or domain-only inputs, answer in German. Only switch away from German when the latest user message is clearly written in another language.';
    }

    if (normalized === 'en') {
        return 'UI language instruction: The NafCMS admin UI is currently English. For short, ambiguous, or domain-only inputs, answer in English. Only switch away from English when the latest user message is clearly written in another language.';
    }

    if (normalized !== '') {
        return `UI language instruction: The NafCMS admin UI language is "${normalized}". For short, ambiguous, or domain-only inputs, answer in that UI language unless the latest user message is clearly written in another language.`;
    }

    return '';
};

const detectLanguageInstruction = (value, language = '') => {
    const rawText = trimValue(value);
    const text = rawText.toLowerCase();
    if (text === '') {
        return uiLanguageInstruction(language);
    }

    const germanSignals = [
        ' der ', ' die ', ' das ', ' und ', ' oder ', ' ist ', ' sind ', ' wie ', ' was ', ' welche ',
        'welches ', 'wieviele', 'wieviel', 'aktuell', 'heute', 'morgen', 'deutschland', 'bitte',
        'kannst du', 'gibt es', 'für ', 'größe', 'ä', 'ö', 'ü', 'ß',
    ];
    const englishSignals = [
        ' the ', ' and ', ' or ', ' is ', ' are ', ' what ', ' which ', ' how ', ' today ', ' tomorrow ',
        'please', 'can you', 'current', 'germany',
    ];
    const padded = ` ${text} `;
    const germanScore = germanSignals.filter((signal) => padded.includes(signal)).length;
    const englishScore = englishSignals.filter((signal) => padded.includes(signal)).length;

    if (germanScore > englishScore) {
        return 'Language instruction: The user wrote the latest message in German. Answer in German.';
    }

    if (englishScore > germanScore) {
        return 'Language instruction: The user wrote the latest message in English. Answer in English.';
    }

    return uiLanguageInstruction(language);
};

const addressStyleInstruction = (latestContent = '') => {
    const firstStyle = firstGermanAddressStyle(latestContent);

    if (firstStyle === 'informal') {
        return 'Current message address instruction: The latest user message first uses informal German address. In this answer, use Du/Dir/Dich.';
    }

    if (firstStyle === 'formal') {
        return 'Current message address instruction: The latest user message first uses formal German address. In this answer, use Sie/Ihnen.';
    }

    const {informal, formal} = germanAddressScores(latestContent);

    if (informal > formal) {
        return 'Current message address instruction: The latest user message uses informal German address. In this answer, use Du/Dir/Dich.';
    }

    if (formal > informal) {
        return 'Current message address instruction: The latest user message uses formal German address. In this answer, use Sie/Ihnen.';
    }

    return '';
};

const systemContent = (systemPrompt, installationInstructions = '', runtimeInstructions = '') => [
        systemPrompt !== '' ? systemPrompt : defaultSystemPrompt,
        installationInstructions !== '' ? `Installation-specific instructions:\n${installationInstructions}` : '',
        runtimeInstructions !== '' ? `Runtime instructions for this answer:\n${runtimeInstructions}` : '',
    ].filter(Boolean).join('\n\n');

const contextInstruction = (context) => {
    if (!context || typeof context !== 'object' || Array.isArray(context)) {
        return '';
    }

    const compact = JSON.stringify(context, (key, value) => {
        if (key === 'contexts' || typeof value === 'function') {
            return undefined;
        }
        if (Array.isArray(value)) {
            return value.slice(0, 24);
        }

        return value;
    });

    return compact === '{}' ? '' : `Visible UI context for tool arguments:\n${compact}`;
};

const toolConfirmationInstruction = () => [
    'Tool execution policy is handled by the NafCMS UI after you emit a tool call.',
    'Do not change your behavior based on whether the UI will ask for confirmation or execute directly.',
    'When a visible tool is suitable, emit the tool call. Never ask for tool permission in normal prose.',
    'Never write that you are calling or checking a tool unless the same assistant message contains an actual tool call.',
    'Tool-call turns should contain the tool call, not a prose promise before it.',
].join(' ');

const identityReminderInstruction = [
    'Reminder for the next answer:',
    'You are Nafarious, the NafCMS Assistant inside the NafCMS admin UI.',
    'Do not describe yourself as Google, Gemma, DeepSeek, Meta, OpenAI, Ollama, or as a general base model.',
    'For routine task, status, or tool-result answers, answer directly without reintroducing yourself.',
    'If asked who you are or what you can do, name yourself as Nafarious, then answer as a calm CMS admin assistant focused on NafCMS tasks and available tools.',
    'Use a warm, dignified, lightly elevated tone without becoming theatrical or verbose.',
].join('\n');

const toolCallReminderInstruction = (tools = []) => {
    if (!Array.isArray(tools) || tools.length === 0) {
        return '';
    }

    const names = tools
        .map((tool) => trimValue(tool?.name || tool?.function?.name || ''))
        .filter(Boolean)
        .slice(0, 12)
        .join(', ');

    return [
        names !== '' ? `Preselected tools for this exact request: ${names}.` : 'Visible tools are available for this request.',
        'If the latest user request asks for current CMS state, diagnostics, status, navigation, external data, or anything covered by a preselected tool, you must emit the matching tool call before answering.',
        'For a matching visible tool, emit that tool call as the first assistant action in the turn, without introductory prose.',
        'A normal prose answer about current data before a tool result is invalid, even if it sounds plausible.',
        'Never fill in example values, dates, versions, modules, system metrics, or plugin names from memory.',
        'Do not answer with phrases like "I will call", "I am checking", "Ich rufe ab", or "Ich überprüfe" unless the same assistant message contains an actual tool call.',
    ].join('\n');
};

export const chatMessages = (messages, systemPrompt, instructions, uiContext = {}, toolConfirmationEnabled = true, tools = [], language = '') => {
    const conversation = messages
        .filter((message) => ['user', 'assistant'].includes(message.role))
        .slice(-16)
        .map((message) => ({
            role: message.role,
            content: message.content,
        }));
    const latest = conversation.at(-1)?.role === 'user' ? conversation.pop() : null;
    const latestUserContent = latest?.content ?? messages.filter((message) => message?.role === 'user').at(-1)?.content ?? '';

    return [
        {role: 'system', content: systemContent(
            systemPrompt,
            instructions,
            [
                detectLanguageInstruction(latestUserContent, language),
                addressStyleInstruction(latestUserContent),
                contextInstruction(uiContext),
                toolConfirmationInstruction(toolConfirmationEnabled),
                identityReminderInstruction,
                toolCallReminderInstruction(tools),
                'Installation-specific instructions must not override the assistant identity, CMS role, grounding rules, or safety rules.',
            ].filter(Boolean).join('\n\n')
        )},
        ...conversation,
        ...(latest ? [latest] : []),
    ];
};
