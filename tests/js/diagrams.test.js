import assert from 'node:assert/strict';
import test from 'node:test';
import {JSDOM} from 'jsdom';

test('dashboard charts render with the bundled library without an external script host', async () => {
    const payload = {
        empty: false,
        option: {
            animation: false,
            xAxis: {type: 'category', data: ['Articles', 'Users']},
            yAxis: {type: 'value'},
            series: [{type: 'bar', data: [3, 1]}],
        },
    };
    const dom = new JSDOM(`<!doctype html><html><body>
        <figure data-nixcms-chart="inventory">
            <div class="nixcms-chart__canvas" style="width:600px;height:260px"></div>
            <script type="application/json" data-nixcms-chart-options>${JSON.stringify(payload)}</script>
        </figure>
    </body></html>`, {pretendToBeVisual: true, url: 'https://cms.example/admin'});
    const globals = {
        window: dom.window,
        document: dom.window.document,
        HTMLElement: dom.window.HTMLElement,
        ResizeObserver: class { observe() {} disconnect() {} },
    };
    const previous = new Map(Object.keys(globals).map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(globals)) {
        Object.defineProperty(globalThis, key, {value, configurable: true, writable: true});
    }
    // jsdom has no layout engine or canvas; SVG charts only need text measurement.
    dom.window.HTMLCanvasElement.prototype.getContext = () => ({measureText: (text) => ({width: String(text).length * 8})});
    const canvas = dom.window.document.querySelector('.nixcms-chart__canvas');
    canvas.getBoundingClientRect = () => ({width: 600, height: 260, x: 0, y: 0, top: 0, left: 0, right: 600, bottom: 260});
    const chart = dom.window.document.querySelector('[data-nixcms-chart]');

    try {
        // No preloaded window.echarts: exercise the production module import.
        await import('../../public/js/admin/diagrams.js');
        for (let attempt = 0; attempt < 100 && chart.dataset.nixcmsChartReady !== 'true' && !chart.classList.contains('has-render-error'); attempt++) {
            await new Promise((resolve) => setTimeout(resolve, 10));
        }
        assert.equal(chart.classList.contains('has-render-error'), false);
        assert.equal(chart.dataset.nixcmsChartReady, 'true');
        assert.ok(canvas.querySelector('svg'), 'the real ECharts renderer should produce an SVG');
        assert.match(canvas.textContent, /Articles/);
        assert.match(canvas.textContent, /Users/);
    } finally {
        dom.window.close();
        for (const [key, descriptor] of previous) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    }
});
