import {PageBuilder} from './PageBuilder.js';

export const initPageBuilders = () => {
    document.querySelectorAll('[data-page-builder]').forEach((root) => {
        const builder = new PageBuilder(root);
        window.NixCMS = window.NixCMS || {};
        window.NixCMS.pageBuilder = builder;

        builder.init().catch((error) => {
            console.error('Could not initialize page builder.', error);
        });
    });
};
