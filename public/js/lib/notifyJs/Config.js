const defaultConfig = {
    animation: 'pop',
    closeOnEscape: true,
    lockScroll: true,
    theme: {
        accent: null,
        surface: null,
        text: null,
        muted: null,
        border: null,
    },
    overlay: {
        enabled: true,
        mode: 'blur',
        opacity: 0.42,
        blur: '4px',
        closeOnClick: true,
    },
};

let configuredOptions = {};

function isPlainObject(value) {
    return Object.prototype.toString.call(value) === '[object Object]';
}

export function mergeNotifyOptions(...optionSets) {
    return optionSets.reduce((merged, options) => {
        if (!isPlainObject(options)) {
            return merged;
        }

        Object.entries(options).forEach(([key, value]) => {
            if (isPlainObject(value) && isPlainObject(merged[key])) {
                merged[key] = mergeNotifyOptions(merged[key], value);
                return;
            }

            merged[key] = value;
        });

        return merged;
    }, {});
}

export function configureNotify(options = {}) {
    configuredOptions = mergeNotifyOptions(configuredOptions, options);
    return getNotifyConfig();
}

export function getNotifyConfig() {
    const windowConfig = typeof window !== 'undefined'
        ? (window.notifyJsConfig || window.NotifyJsConfig || {})
        : {};

    return mergeNotifyOptions(defaultConfig, configuredOptions, windowConfig);
}

if (typeof window !== 'undefined') {
    window.NotifyJs = {
        ...(window.NotifyJs || {}),
        configure: configureNotify,
        getConfig: getNotifyConfig,
    };
}

export default {
    configure: configureNotify,
    getConfig: getNotifyConfig,
};
