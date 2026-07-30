// Scanning and rewriting of `.theme-<id>` selectors inside author CSS.
//
// These walk the stylesheet character by character instead of using a regex,
// because a `.theme-x` inside a comment or a quoted string must not count as a
// selector. All of it is pure string work, which is why it lives here and is
// covered by tests/js/theme-editor-selectors.test.js.

import { escapeRegExp } from './vocabulary.js';

export function findThemeSelectorIds(css) {
    const ids = [];
    let prelude = '';
    let quote = '';
    let comment = false;
    let escapeNext = false;

    const collect = (value) => {
        findThemeSelectorIdsInPrelude(value).forEach((id) => ids.push(id));
    };

    for (let index = 0; index < css.length; index++) {
        const char = css[index];
        const next = css[index + 1] || '';

        if (comment) {
            prelude += char;
            if (char === '*' && next === '/') {
                prelude += next;
                index++;
                comment = false;
            }
            continue;
        }

        if (quote) {
            prelude += char;
            if (escapeNext) {
                escapeNext = false;
            } else if (char === '\\') {
                escapeNext = true;
            } else if (char === quote) {
                quote = '';
            }
            continue;
        }

        if (char === '/' && next === '*') {
            prelude += char + next;
            index++;
            comment = true;
            continue;
        }

        if (char === '"' || char === "'") {
            prelude += char;
            quote = char;
            continue;
        }

        if (char === '{') {
            collect(prelude);
            prelude = '';
            continue;
        }

        if (char === '}') {
            prelude = '';
            continue;
        }

        prelude += char;
    }

    return ids;
}

export function findThemeSelectorIdsInPrelude(prelude) {
    const ids = [];
    const collectPlainText = (value) => {
        const pattern = /(^|[^a-zA-Z0-9_-])\.theme-([a-z0-9][a-z0-9-]*)(?![a-zA-Z0-9_-])/g;
        let match;
        while ((match = pattern.exec(value)) !== null) {
            ids.push(match[2]);
        }
    };
    let chunk = '';
    let quote = '';
    let comment = false;
    let escapeNext = false;

    for (let index = 0; index < prelude.length; index++) {
        const char = prelude[index];
        const next = prelude[index + 1] || '';

        if (comment) {
            if (char === '*' && next === '/') {
                index++;
                comment = false;
            }
            continue;
        }

        if (quote) {
            if (escapeNext) {
                escapeNext = false;
            } else if (char === '\\') {
                escapeNext = true;
            } else if (char === quote) {
                quote = '';
            }
            continue;
        }

        if (char === '/' && next === '*') {
            collectPlainText(chunk);
            chunk = '';
            index++;
            comment = true;
            continue;
        }

        if (char === '"' || char === "'") {
            collectPlainText(chunk);
            chunk = '';
            quote = char;
            continue;
        }

        chunk += char;
    }

    collectPlainText(chunk);
    return ids;
}

export function replaceThemeSelectors(css, oldId, newId) {
    if (!oldId || !newId || oldId === newId) {
        return css;
    }

    let output = '';
    let prelude = '';
    let quote = '';
    let comment = false;
    let escapeNext = false;

    for (let index = 0; index < css.length; index++) {
        const char = css[index];
        const next = css[index + 1] || '';

        if (comment) {
            prelude += char;
            if (char === '*' && next === '/') {
                prelude += next;
                index++;
                comment = false;
            }
            continue;
        }

        if (quote) {
            prelude += char;
            if (escapeNext) {
                escapeNext = false;
            } else if (char === '\\') {
                escapeNext = true;
            } else if (char === quote) {
                quote = '';
            }
            continue;
        }

        if (char === '/' && next === '*') {
            prelude += char + next;
            index++;
            comment = true;
            continue;
        }

        if (char === '"' || char === "'") {
            prelude += char;
            quote = char;
            continue;
        }

        if (char === '{') {
            output += replaceThemeClassInSelectorPrelude(prelude, oldId, newId) + char;
            prelude = '';
            continue;
        }

        if (char === '}') {
            output += prelude + char;
            prelude = '';
            continue;
        }

        prelude += char;
    }

    return output + prelude;
}

export function replaceThemeClassInSelectorPrelude(prelude, oldId, newId) {
    const replacePlainText = (value) => {
        const pattern = new RegExp(`(^|[^a-zA-Z0-9_-])\\.theme-${escapeRegExp(oldId)}(?![a-zA-Z0-9_-])`, 'g');
        return value.replace(pattern, `$1.theme-${newId}`);
    };
    let output = '';
    let chunk = '';
    let quote = '';
    let comment = false;
    let escapeNext = false;

    for (let index = 0; index < prelude.length; index++) {
        const char = prelude[index];
        const next = prelude[index + 1] || '';

        if (comment) {
            output += char;
            if (char === '*' && next === '/') {
                output += next;
                index++;
                comment = false;
            }
            continue;
        }

        if (quote) {
            output += char;
            if (escapeNext) {
                escapeNext = false;
            } else if (char === '\\') {
                escapeNext = true;
            } else if (char === quote) {
                quote = '';
            }
            continue;
        }

        if (char === '/' && next === '*') {
            output += replacePlainText(chunk) + char + next;
            chunk = '';
            index++;
            comment = true;
            continue;
        }

        if (char === '"' || char === "'") {
            output += replacePlainText(chunk) + char;
            chunk = '';
            quote = char;
            continue;
        }

        chunk += char;
    }

    return output + replacePlainText(chunk);
}
