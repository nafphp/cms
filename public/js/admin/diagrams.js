const chartSelector = '[data-nixcms-chart]';
let echartsLoader = null;
let retryFrame = 0;

const pendingElements = () => [...document.querySelectorAll(chartSelector)]
    .filter((element) => element instanceof HTMLElement && element.dataset.nixcmsChartReady !== 'true');

const scheduleRetry = () => {
    if (retryFrame !== 0) {
        return;
    }

    retryFrame = window.requestAnimationFrame(() => {
        retryFrame = 0;
        initDiagrams();
        window.setTimeout(() => initDiagrams(), 80);
    });
};

const loadECharts = () => {
    if (window.echarts) {
        return Promise.resolve(window.echarts);
    }

    echartsLoader ??= import('../lib/echarts/echarts.esm.min.js')
        .then((module) => module.default || module);

    return echartsLoader;
};

const readPayload = (element) => {
    const source = element.querySelector('[data-nixcms-chart-options]');
    if (!source) {
        return null;
    }

    try {
        return JSON.parse(source.textContent || '{}');
    } catch (error) {
        return null;
    }
};

const applyTheme = (payload) => {
    const option = payload.option || {};
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#e2e8f0' : '#334155';
    const mutedColor = isDark ? 'rgba(148, 163, 184, .76)' : 'rgba(71, 85, 105, .76)';

    option.textStyle = {...(option.textStyle || {}), color: textColor};
    option.tooltip = {
        borderWidth: 0,
        backgroundColor: isDark ? 'rgba(15, 23, 42, .96)' : 'rgba(255, 255, 255, .98)',
        textStyle: {color: textColor},
        extraCssText: 'box-shadow: 0 18px 44px rgba(15, 23, 42, .18); border-radius: 8px;',
        ...(option.tooltip || {}),
    };

    if (option.legend) {
        option.legend = {...option.legend, textStyle: {color: mutedColor, ...(option.legend.textStyle || {})}};
    }

    return option;
};

export const initDiagrams = () => {
    if (pendingElements().length === 0) {
        return;
    }

    loadECharts()
        .then((echarts) => {
            const elements = pendingElements();
            elements.forEach((element) => {
                if (!element.isConnected) {
                    return;
                }

                const payload = readPayload(element);
                const canvas = element.querySelector('.nixcms-chart__canvas');
                if (!payload || !(canvas instanceof HTMLElement)) {
                    return;
                }

                element.classList.toggle('is-empty', payload.empty === true);

                if (payload.empty === true) {
                    element.dataset.nixcmsChartReady = 'true';
                    return;
                }

                const canvasBox = canvas.getBoundingClientRect();
                if (canvasBox.width < 4 || canvasBox.height < 4) {
                    scheduleRetry();
                    return;
                }

                const instance = echarts.init(canvas, null, {renderer: 'svg'});
                instance.setOption(applyTheme(payload));
                element.dataset.nixcmsChartReady = 'true';

                const resize = () => {
                    if (canvas.isConnected) {
                        instance.resize();
                    }
                };
                window.requestAnimationFrame(resize);
                window.setTimeout(resize, 60);
                window.setTimeout(resize, 180);

                const resizeObserver = new ResizeObserver(resize);
                resizeObserver.observe(canvas);
            });
        })
        .catch(() => {
            pendingElements().forEach((element) => {
                element.classList.add('has-render-error');
            });
        });
};

initDiagrams();
