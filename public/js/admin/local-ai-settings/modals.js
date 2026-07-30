// Modal dialogs the settings panel opens: mistake review, prompt editor, and
// the research popup. They share one Notify subclass so opening and disposing
// behaves the same for all three.

import Notify from '../../lib/notifyJs/Notify.js';
import { escapeHtml } from './dom.js';
import { labelsForLanguage } from './labels.js';

export class LocalAiSettingsModal extends Notify {
    defaultOptions = {
        role: 'dialog',
        className: '',
        onClose: null,
        bindContent: null,
        overlay: {
            enabled: true,
            mode: 'blur',
            opacity: 0.54,
            blur: '5px',
            closeOnClick: true,
        },
    };

    bind() {
        this.container.querySelectorAll('[data-local-ai-modal-close]').forEach((button) => {
            button.addEventListener('click', () => this.remove());
        });

        if (this.options.bindContent instanceof Function) {
            this.options.bindContent(this.container, this);
        }
    }

    render() {
        const className = trimValue(this.options.className);
        const content = `
            <div class="notify prompt local-ai-settings-modal ${className}">
                ${this.message}
            </div>`;

        this.container = (new DOMParser()).parseFromString(content, 'text/html').body.firstElementChild;
        document.body.appendChild(this.container);
    }

    remove() {
        if (this.isClosing) {
            return;
        }

        if (this.options.onClose instanceof Function) {
            this.options.onClose(this);
        }

        this.animateRemove();
    }
}

export const createMistakeReviewModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-review-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-review-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.reviewTitle)}</h3>
                    <p>${escapeHtml(labels.reviewIntro)}</p>
                </div>
            </div>
            <div class="local-ai-review-status" data-ai-review-status></div>
            <div class="local-ai-review-empty hidden" data-ai-review-empty></div>
            <div class="local-ai-review-body" data-ai-review-body>
                <div class="local-ai-review-card">
                    <div class="local-ai-review-count" data-ai-review-count></div>
                    <h4 data-ai-review-title></h4>
                    <p data-ai-review-problem></p>
                    <p data-ai-review-question></p>
                </div>
                <label class="local-ai-review-field">
                    <span>${escapeHtml(labels.solution)}</span>
                    <textarea data-ai-review-solution></textarea>
                </label>
                <label class="local-ai-review-field">
                    <span>${escapeHtml(labels.proposedMemory)}</span>
                    <textarea data-ai-review-memory></textarea>
                </label>
            </div>
            <div class="local-ai-review-actions">
                <div class="local-ai-review-actions__left">
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-review-previous>${escapeHtml(labels.previous)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-review-next>${escapeHtml(labels.next)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-danger" data-ai-review-delete>${escapeHtml(labels.deleteMistake)}</button>
                </div>
                <div class="local-ai-review-actions__right">
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-accent-soft" data-ai-review-research>${escapeHtml(labels.researchMore)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-review-save>${escapeHtml(labels.acceptSolution)}</button>
                </div>
            </div>
        </div>
    `, {
        className: 'local-ai-review-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        body: modal.querySelector('[data-ai-review-body]'),
        empty: modal.querySelector('[data-ai-review-empty]'),
        status: modal.querySelector('[data-ai-review-status]'),
        count: modal.querySelector('[data-ai-review-count]'),
        title: modal.querySelector('[data-ai-review-title]'),
        problem: modal.querySelector('[data-ai-review-problem]'),
        question: modal.querySelector('[data-ai-review-question]'),
        solution: modal.querySelector('[data-ai-review-solution]'),
        memory: modal.querySelector('[data-ai-review-memory]'),
        close: modal.querySelector('[data-ai-review-close]'),
        previous: modal.querySelector('[data-ai-review-previous]'),
        next: modal.querySelector('[data-ai-review-next]'),
        delete: modal.querySelector('[data-ai-review-delete]'),
        research: modal.querySelector('[data-ai-review-research]'),
        save: modal.querySelector('[data-ai-review-save]'),
    };
};

export const createPromptEditorModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-prompt-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-prompt-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.editorTitle)}</h3>
                    <p>${escapeHtml(labels.editorIntro)}</p>
                </div>
            </div>
            <div class="local-ai-prompt-toolbar">
                <div class="local-ai-prompt-tabs" data-ai-prompt-tabs></div>
                <div class="local-ai-review-status" data-ai-prompt-status></div>
            </div>
            <div class="local-ai-prompt-editor">
                <div class="local-ai-prompt-meta">
                    <strong data-ai-prompt-name></strong>
                    <span data-ai-prompt-kind></span>
                </div>
                <textarea spellcheck="false" data-ai-prompt-editor></textarea>
            </div>
            <div class="local-ai-review-actions local-ai-review-actions--end">
                <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-prompt-close-secondary data-local-ai-modal-close>${escapeHtml(labels.close)}</button>
                <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-prompt-save>${escapeHtml(labels.editorSave)}</button>
            </div>
        </div>
    `, {
        className: 'local-ai-prompt-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        tabs: modal.querySelector('[data-ai-prompt-tabs]'),
        status: modal.querySelector('[data-ai-prompt-status]'),
        name: modal.querySelector('[data-ai-prompt-name]'),
        kind: modal.querySelector('[data-ai-prompt-kind]'),
        editor: modal.querySelector('[data-ai-prompt-editor]'),
        save: modal.querySelector('[data-ai-prompt-save]'),
        close: modal.querySelector('[data-ai-prompt-close]'),
        closeSecondary: modal.querySelector('[data-ai-prompt-close-secondary]'),
    };
};

export const createResearchModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-research-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-research-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.researchTitle)}</h3>
                    <p>${escapeHtml(labels.researchIntro)}</p>
                </div>
            </div>
            <textarea data-ai-research-input placeholder="${escapeHtml(labels.researchPlaceholder)}"></textarea>
            <div class="local-ai-review-actions local-ai-review-actions--end">
                <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-research-close-secondary data-local-ai-modal-close>${escapeHtml(labels.close)}</button>
                <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-research-submit>${escapeHtml(labels.addInfo)}</button>
            </div>
        </div>
    `, {
        className: 'local-ai-research-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        input: modal.querySelector('[data-ai-research-input]'),
        close: modal.querySelector('[data-ai-research-close]'),
        closeSecondary: modal.querySelector('[data-ai-research-close-secondary]'),
        submit: modal.querySelector('[data-ai-research-submit]'),
    };
};
