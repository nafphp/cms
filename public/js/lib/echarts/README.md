# Apache ECharts 5.6.0

The unmodified `dist/echarts.esm.min.js` from the official `echarts@5.6.0` npm package is shipped here with its LICENSE, NOTICE and third-party license.

Dashboard and page charts load this local asset so they work with the CMS Content Security Policy and do not require a third-party script host.

To update, obtain the intended version with `npm pack echarts@VERSION`, replace the distribution and license files, run `npm run test:builder`, and publish the assets to the host with `vendor/bin/nix nixcms:assets:publish`.
