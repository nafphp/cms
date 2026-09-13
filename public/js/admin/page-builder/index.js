import {PageBuilder} from './PageBuilder.js';

export const initPageBuilders = () => {
    document.querySelectorAll('[data-page-builder]').forEach((root) => {
        const builder = new PageBuilder(root);
        window.NafCMS = window.NafCMS || {};
        window.NafCMS.pageBuilder = builder;

        builder.init().catch((error) => {
            console.error('Could not initialize page builder.', error);
        });
    });
};
