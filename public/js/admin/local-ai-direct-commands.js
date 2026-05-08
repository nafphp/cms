import { trimValue } from './ollama-client.js';

const normalizeCommandText = (value) => trimValue(value)
    .toLowerCase()
    .replace(/\s+/g, ' ');

const directAssistantCommands = [
    {
        name: 'pagebuilder.inspect',
        aliases: [
            'zeigemodus',
            'zeige modus',
            'show mode',
            'inspect mode',
            'inspect pagebuilder',
            'pagebuilder inspect',
        ],
        patterns: [
            /\b(?:lass|lasse)\s+mich\s+dir\s+(?:etwas|was)\s+zeigen\b/,
            /\bich\s+(?:möchte|moechte|will|würde|wuerde)\s+dir\s+(?:etwas|was)\s+zeigen\b/,
            /\bich\s+zeige\s+dir\s+(?:etwas|was)\b/,
            /\b(?:kann|könnte|koennte)\s+ich\s+dir\s+(?:etwas|was)\s+zeigen\b/,
        ],
    },
];

export const directAssistantCommandFor = (value) => {
    const text = normalizeCommandText(value);
    if (text === '') {
        return null;
    }

    return directAssistantCommands.find((command) => (
        command.aliases.some((alias) => text === alias || text.startsWith(`${alias} `) || text.includes(` ${alias}`))
        || command.patterns.some((pattern) => pattern.test(text))
    )) || null;
};
