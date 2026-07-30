// Static vocabulary the editor's autocomplete offers, plus the small pure
// helpers that operate on plain strings. Kept apart from main.js so the word
// lists can grow without pushing the editor itself further down the file.

export const knownClasses = [
    '.page-theme',
    '.page-site-header',
    '.page-site-header__brand',
    '.page-site-header__navigation',
    '.page-site-footer',
    '.page-site-footer__text',
    '.page-navigation',
    '.page-navigation--horizontal',
    '.page-navigation--vertical',
    '.page-navigation__link',
    '.page-block--surface-none',
    '.page-block--surface-soft',
    '.page-block--surface-card',
    '.page-block--surface-accent',
    '.page-template-custom-slot',
    '.page-layout-section',
];
export const cssVariables = [
    '--page-accent',
    '--page-bg',
    '--page-text',
    '--page-muted',
    '--page-border',
    '--page-card-bg',
    '--page-card-border',
    '--page-panel-soft',
    '--page-header-bg',
    '--page-header-text',
    '--page-footer-bg',
    '--page-footer-text',
];
export const tailwindColors = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
export const tailwindShades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
export const tailwindSpacing = ['0', '0.5', '1', '1.5', '2', '2.5', '3', '3.5', '4', '5', '6', '7', '8', '9', '10', '11', '12', '14', '16', '20', '24', '28', '32'];
export const tailwindUtilityWords = (() => {
    const words = [
        'block', 'inline-block', 'inline-flex', 'flex', 'grid', 'hidden',
        'relative', 'absolute', 'fixed', 'sticky', 'inset-0', 'z-10', 'z-20', 'z-50',
        'items-start', 'items-center', 'items-end', 'justify-start', 'justify-center', 'justify-between', 'justify-end',
        'content-start', 'content-center', 'self-start', 'self-center', 'self-end',
        'flex-col', 'flex-row', 'flex-wrap', 'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4',
        'w-full', 'h-full', 'min-h-screen', 'max-w-screen-xl', 'overflow-hidden', 'overflow-auto', 'overflow-y-auto',
        'rounded-none', 'rounded-sm', 'rounded', 'rounded-md', 'rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-full',
        'border', 'border-0', 'border-t', 'border-b', 'border-l', 'border-r',
        'shadow-sm', 'shadow', 'shadow-md', 'shadow-lg', 'shadow-xl', 'shadow-none',
        'text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl',
        'font-normal', 'font-medium', 'font-semibold', 'font-bold',
        'leading-none', 'leading-tight', 'leading-snug', 'leading-normal', 'leading-relaxed',
        'uppercase', 'lowercase', 'capitalize', 'tracking-wide',
        'opacity-0', 'opacity-50', 'opacity-75', 'opacity-100',
        'transition', 'transition-colors', 'transition-opacity', 'duration-150', 'duration-200', 'ease-out',
        'hover:brightness-110', 'focus:outline-none', 'focus-visible:ring-2',
    ];
    const spacingPrefixes = ['p', 'px', 'py', 'pt', 'pr', 'pb', 'pl', 'm', 'mx', 'my', 'mt', 'mr', 'mb', 'ml', 'gap', 'gap-x', 'gap-y'];
    spacingPrefixes.forEach((prefix) => tailwindSpacing.forEach((size) => words.push(`${prefix}-${size}`)));
    tailwindColors.forEach((color) => {
        tailwindShades.forEach((shade) => {
            words.push(`bg-${color}-${shade}`, `text-${color}-${shade}`, `border-${color}-${shade}`, `ring-${color}-${shade}`);
        });
    });

    return words.filter((word, index, all) => all.indexOf(word) === index);
})();
export const jsGlobals = [
    'export default {}',
    'document',
    'window',
    'console',
    'CustomEvent',
    'requestAnimationFrame',
    'setTimeout',
    'clearTimeout',
    'Object',
    'Array',
    'String',
    'Number',
    'Math',
];
export const jsTypeMembers = {
    Object: ['assign', 'create', 'defineProperty', 'entries', 'fromEntries', 'freeze', 'hasOwn', 'keys', 'seal', 'values'],
    ObjectInstance: ['constructor', 'hasOwnProperty', 'isPrototypeOf', 'propertyIsEnumerable', 'toLocaleString', 'toString', 'valueOf'],
    Array: ['at', 'concat', 'every', 'filter', 'find', 'findIndex', 'flat', 'forEach', 'includes', 'join', 'map', 'pop', 'push', 'reduce', 'slice', 'some', 'sort', 'splice'],
    String: ['charAt', 'endsWith', 'includes', 'indexOf', 'match', 'replace', 'slice', 'split', 'startsWith', 'toLowerCase', 'toUpperCase', 'trim'],
    Element: ['addEventListener', 'append', 'classList', 'closest', 'dataset', 'getAttribute', 'querySelector', 'querySelectorAll', 'remove', 'removeAttribute', 'setAttribute', 'style', 'textContent'],
    Document: ['addEventListener', 'body', 'createElement', 'documentElement', 'querySelector', 'querySelectorAll'],
    Window: ['addEventListener', 'dispatchEvent', 'innerHeight', 'innerWidth', 'localStorage', 'matchMedia', 'requestAnimationFrame'],
    Console: ['debug', 'error', 'info', 'log', 'table', 'warn'],
    Math: ['abs', 'ceil', 'floor', 'max', 'min', 'pow', 'random', 'round', 'sqrt'],
};

export const slug = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
export const hexColorPattern = /#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})(?![0-9a-fA-F])/g;
export const escapeRegExp = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
export const phpString = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
export const normalizeLineEndings = (value) => String(value || '').replace(/\r\n?/g, '\n');
