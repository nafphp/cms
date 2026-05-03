import {PageBuilder} from './PageBuilder.js';

export const initPageBuilders = () => {
    document.querySelectorAll('[data-page-builder]').forEach((root) => {
        new PageBuilder(root).init().catch((error) => {
            console.error('Could not initialize page builder.', error);
        });
    });
};
