import {pageBuilderLabelKeys, storageKey} from './shared.js';
import {stateMethods} from './state.js';
import {contentModelMethods} from './content-model.js';
import {customLayoutMethods} from './custom-layout.js';
import {eventMethods} from './events.js';
import {previewMethods} from './preview.js';
import {renderingMethods} from './rendering.js';
import {fieldMethods} from './fields.js';
import {translationMethods} from './translations.js';
import {mediaMethods} from './media.js';
import {persistenceMethods} from './persistence.js';

export class PageBuilder {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config || '{}');
        this.saveUrl = root.dataset.saveUrl || this.config.saveUrl || '/admin/pages';
        this.layoutSaveUrl = root.dataset.layoutSaveUrl || this.config.layoutSaveUrl || '/api/admin/page-layouts';
        this.overviewUrl = root.dataset.overviewUrl || this.config.overviewUrl || '/admin/pages';
        this.labelKeys = {
            ...pageBuilderLabelKeys,
            ...(this.config.labelKeys || {}),
        };
        this.labels = {
            ...pageBuilderLabelKeys,
            ...(this.config.labels || {}),
        };
        this.media = {
            urls: {},
            acceptedExtensions: [],
            maxUploadSize: 0,
            canUpload: false,
            visibilityOptions: {
                public: this.labels.public,
                registered: this.labels.registered,
                private: this.labels.private,
            },
            ...(this.config.media || {}),
        };
        this.templates = Array.isArray(this.config.templates) ? this.config.templates : [];
        this.contentTypes = Array.isArray(this.config.contentTypes) ? this.config.contentTypes : [];
        this.themes = Array.isArray(this.config.themes) ? this.config.themes : [];
        this.themeSurfaces = this.config.themeSurfaces && typeof this.config.themeSurfaces === 'object' ? this.config.themeSurfaces : {};
        this.navigationChoices = Array.isArray(this.config.navigationChoices) ? this.config.navigationChoices : [];
        this.languages = Array.isArray(this.config.languages) ? this.config.languages : [];
        this.languageOptions = this.config.languageOptions && typeof this.config.languageOptions === 'object' ? this.config.languageOptions : {};
        this.defaultLanguage = this.config.defaultLanguage || this.languages[0] || 'en';
        this.uiLanguage = this.normalizeLanguageCode(this.config.uiLanguage) || document.documentElement.lang || this.defaultLanguage;
        this.translationSuggestionsByLanguage = this.normalizeTranslationSuggestionsByLanguage(this.config.translationSuggestions || {});
        this.translationEndpoint = this.config.translationEndpoint || '/api/admin/translations/{language}';
        this.translationReadEndpoint = this.config.translationReadEndpoint || this.translationEndpoint;
        this.translationEntriesByLanguage = {};
        this.loadedLabelLanguage = '';
        this.loadingLabelLanguage = '';
        if (this.config.translationEntries && typeof this.config.translationEntries === 'object' && !Array.isArray(this.config.translationEntries)) {
            this.translationEntriesByLanguage[this.uiLanguage] = this.config.translationEntries;
            this.applyPageBuilderLabels(this.config.translationEntries);
            this.loadedLabelLanguage = this.uiLanguage;
        }
        this.translationSuggestBox = null;
        this.activeTranslationSuggestInput = null;
        this.defaultNavigationIdentifier = this.config.defaultNavigationIdentifier || this.navigationChoices[0]?.identifier || 'main';
        this.queryParams = new URLSearchParams(window.location.search);
        this.initialState = this.config.initialState && typeof this.config.initialState === 'object' ? this.config.initialState : {};
        this.editMode = this.config.mode === 'edit' || this.queryParams.get('mode') === 'edit' || this.queryParams.has('template');
        this.step = 'template';
        this.templateId = this.templates[0]?.id || '';
        this.selectedBlockId = null;
        this.selectedNestedPath = '';
        this.blockContent = {};
        this.customLayout = [];
        this.persistedValues = {};
        this.templatesRendered = false;
        this.scrolledTemplateId = null;
        this.previewTemplateId = null;
        this.settingsOpen = false;
        this.previewViewport = 'desktop';
        this.draggedCustomSlotId = null;
        this.storageKey = `${storageKey}:${window.location.pathname}${window.location.search || ''}`;
        this.saveTimer = null;
        this.saving = false;
        this.savedId = Number(this.initialState.id || 0);
        this.activeStageId = 0;
    }

    currentThemeId() {
        return String(this.persistedValues.themeId || 'default').trim() || 'default';
    }

    surfaceOptions() {
        const options = this.themeSurfaces[this.currentThemeId()] || this.themeSurfaces.default || [];
        return Array.isArray(options) && options.length > 0
            ? options
            : [
                {value: 'none', label: this.labels.surfaceNone || 'None'},
                {value: 'soft', label: this.labels.surfaceSoft || 'Soft'},
                {value: 'card', label: this.labels.surfaceCard || 'Card'},
                {value: 'accent', label: this.labels.surfaceAccent || 'Accent'},
            ];
    }

    themeLabel(id) {
        const theme = this.themes.find((item) => String(item.value || '') === String(id || ''));
        return theme?.label || id || 'default';
    }
}

Object.assign(
    PageBuilder.prototype,
    stateMethods,
    contentModelMethods,
    customLayoutMethods,
    eventMethods,
    previewMethods,
    renderingMethods,
    fieldMethods,
    translationMethods,
    mediaMethods,
    persistenceMethods,
);
