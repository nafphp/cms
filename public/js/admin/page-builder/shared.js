import notifyAlert from '../../lib/notifyJs/Modules/Alert.js';
import prompt from '../../lib/notifyJs/Modules/Prompt.js';
import toast from '../../lib/notifyJs/Modules/Toast.js';
import {initStyledSelects} from '../../components/styledSelect.js';

export const cardClass = 'rounded-lg border border-slate-700 bg-[#121820] p-3 text-left text-slate-100 transition hover:border-[var(--admin-page-accent)] hover:shadow-sm';
export const layoutClass = 'group grid gap-3 rounded-lg border-2 border-slate-800 bg-[#0b1118] p-3 text-left transition hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--admin-page-accent)]';
export const activeLayoutClass = 'border-[var(--admin-page-accent)] bg-[#122033]';
export const inputClass = 'min-h-9 w-full rounded-md border border-slate-700 bg-[#0b1017] px-3 py-2 text-sm text-slate-100 outline-none focus:border-[var(--admin-page-accent)]';
export const previewFrameClass = 'pointer-events-none block aspect-video w-full rounded-md border-0 bg-slate-950 opacity-0 transition-opacity duration-200 ease-out';

export const clone = (value) => JSON.parse(JSON.stringify(value));
export const storageKey = 'nixcms:page-builder:draft';
export const recentContentTypesKey = 'nixcms:page-builder:recent-content-types';
export const flashToastKey = 'nixcms:flash-toast';
export const previewViewports = {
    desktop: {label: 'Desktop', labelKey: 'viewportDesktop', width: 1280},
    tablet: {label: 'Tablet', labelKey: 'viewportTablet', width: 768},
    mobile: {label: 'Mobile', labelKey: 'viewportMobile', width: 390},
};
export const contentWidthPresets = {
    template: {label: 'Template default', labelKey: 'contentWidthTemplate', css: ''},
    narrow: {label: 'Narrow', labelKey: 'contentWidthNarrow', css: '760px'},
    comfortable: {label: 'Comfortable', labelKey: 'contentWidthComfortable', css: '960px'},
    wide: {label: 'Wide', labelKey: 'contentWidthWide', css: '1120px'},
    full: {label: 'Full width', labelKey: 'contentWidthFull', css: '100%'},
};
export const customLayoutRegions = [
    {value: 'header', label: 'Header', labelKey: 'regionHeader'},
    {value: 'main', label: 'Main', labelKey: 'regionMain'},
    {value: 'aside', label: 'Aside', labelKey: 'regionAside'},
    {value: 'footer', label: 'Footer', labelKey: 'regionFooter'},
];
export const customLayoutSizes = [
    {value: 'small', label: 'Small width', labelKey: 'sizeSmall'},
    {value: 'medium', label: 'Medium width', labelKey: 'sizeMedium'},
    {value: 'large', label: 'Large width', labelKey: 'sizeLarge'},
    {value: 'full', label: 'Full width', labelKey: 'sizeFull'},
    {value: 'aside', label: 'Aside width', labelKey: 'sizeAside'},
    {value: 'hero', label: 'Hero width', labelKey: 'sizeHero'},
];
export const customLayoutPositions = [
    {value: 'stretch', label: 'Auto', labelKey: 'positionAuto'},
    {value: 'start', label: 'Left', labelKey: 'positionLeft'},
    {value: 'center', label: 'Center', labelKey: 'positionCenter'},
    {value: 'end', label: 'Right', labelKey: 'positionRight'},
];
export const customLayoutMaxHeights = [
    {value: 'none', label: 'No max', labelKey: 'maxHeightNone'},
    {value: '320', label: '320 px'},
    {value: '480', label: '480 px'},
    {value: '640', label: '640 px'},
    {value: '800', label: '800 px'},
    {value: 'screen', label: 'Viewport', labelKey: 'maxHeightViewport'},
];
export const customLayoutOverflows = [
    {value: 'visible', label: 'Visible', labelKey: 'overflowVisible'},
    {value: 'hidden', label: 'Hidden', labelKey: 'overflowHidden'},
    {value: 'auto', label: 'Scroll', labelKey: 'overflowScroll'},
];
export const customLayoutSurfaces = [
    {value: 'none', label: 'None', labelKey: 'surfaceNone'},
    {value: 'soft', label: 'Soft', labelKey: 'surfaceSoft'},
    {value: 'card', label: 'Card', labelKey: 'surfaceCard'},
    {value: 'accent', label: 'Accent', labelKey: 'surfaceAccent'},
];
export const layoutChildContainerStyles = [
    {value: 'none', label: 'None', labelKey: 'surfaceNone'},
    {value: 'border', label: 'Border', labelKey: 'containerBorder'},
    {value: 'soft', label: 'Soft', labelKey: 'containerSoft'},
    {value: 'card', label: 'Card', labelKey: 'containerCard'},
    {value: 'accent', label: 'Accent', labelKey: 'containerAccent'},
];
export const layoutBlockTypes = ['section', 'stack', 'columns', 'grid'];
export const chartMetricColors = ['#3b82f6', '#f59e0b', '#22c55e', '#8b5cf6', '#64748b', '#ef4444', '#06b6d4', '#f97316'];
export const chartMaxMetrics = 24;

export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));
export const hasOption = (options, value) => options.some((item) => item.value === String(value ?? ''));
export const optionValue = (options, value, fallback) => hasOption(options, value) ? String(value) : fallback;
export const optionLabel = (item, labels = {}) => item.labelKey && labels[item.labelKey] ? labels[item.labelKey] : item.label;
export const optionOptions = (options, selected, labels = {}) => [
    ...(!hasOption(options, selected) && String(selected || '') !== '' ? [{value: String(selected), label: `${String(selected)} (missing)`}] : []),
    ...options,
].map((item) => `
    <option value="${escapeHtml(item.value)}" ${item.value === selected ? 'selected' : ''}>${escapeHtml(optionLabel(item, labels))}</option>
`).join('');

export const pageBuilderLabelKeys = {
    saved: "Page saved.",
    couldNotSave: "Page could not be saved.",
    cancel: "Cancel",
    resetBuilderConfirm: "Reset the current builder state and start over?",
    mediaPickerTitle: "Select media",
    mediaPickerIntro: "Upload a new image or choose an existing media item.",
    mediaUpload: "Upload image",
    mediaChooseFile: "Choose image",
    mediaLibrary: "Media library",
    mediaUse: "Use media",
    mediaNoSelection: "Please select or upload a media item.",
    mediaUploadFailed: "Upload failed.",
    mediaFileTooLarge: "File is too large.",
    mediaInvalidExtension: "File type is not allowed.",
    mediaNoFiles: "No media files yet.",
    mediaEmptyUrl: "No media selected.",
    mediaReplace: "Replace media",
    mediaSelect: "Select media",
    mediaClear: "Clear",
    visibility: "Visibility",
    altText: "Alt text",
    public: "Public",
    registered: "Registered",
    private: "Private",
    translationKey: "Translation key",
    searchCreateTranslationKey: "Search or create a translation key for this field.",
    key: "Key",
    value: "Value",
    language: "Language",
    apply: "Apply",
    addCustomBlock: "Add block",
    chooseCustomBlock: "Choose content type",
    chooseCustomBlockIntro: "Pick the content block for the new custom slot.",
    customLayout: "Custom layout",
    customSlotsCount: ":count custom slots",
    deleteSlot: "Delete slot",
    duplicateSlot: "A slot with this id already exists.",
    addHeaderBlock: "Add header",
    addFooterBlock: "Add footer",
    chromeContentWidth: "Header/footer content",
    chromeBackgroundWidth: "Header/footer background",
    headerContentWidth: "Bound header content",
    headerBackgroundWidth: "Bound header background",
    footerContentWidth: "Bound footer content",
    footerBackgroundWidth: "Bound footer background",
    headerBackground: "Header background",
    footerBackground: "Footer background",
    transparent: "Transparent",
    saveCustomLayout: "Save as layout",
    saveLayoutTitle: "Save custom layout",
    saveLayoutIntro: "Save this slot structure as a reusable layout for future pages.",
    layoutName: "Layout name",
    layoutDescription: "Description",
    layoutSaved: "Layout saved.",
    layoutNameRequired: "Please enter a layout name.",
    addChildBlock: "Add block",
    chooseChildBlock: "Choose child block",
    chooseChildBlockIntro: "Pick the content block to place inside this layout module.",
    nestedBlocks: "Nested blocks",
    backToLayoutBlock: "Back to layout block",
    editChildBlock: "Edit child block",
    removeChildBlock: "Remove child block",
    slotLabel: "Slot label",
    region: "Region",
    size: "Size",
    layoutType: "Layout type",
    container: "Container",
    position: "Position",
    maxHeight: "Max height",
    overflow: "Overflow",
    surface: "Surface",
    regionHeader: "Header",
    regionMain: "Main",
    regionAside: "Aside",
    regionFooter: "Footer",
    sizeSmall: "Small width",
    sizeMedium: "Medium width",
    sizeLarge: "Large width",
    sizeFull: "Full width",
    sizeAside: "Aside width",
    sizeHero: "Hero width",
    positionAuto: "Auto",
    positionLeft: "Left",
    positionCenter: "Center",
    positionRight: "Right",
    maxHeightNone: "No max",
    maxHeightViewport: "Viewport",
    overflowVisible: "Visible",
    overflowHidden: "Hidden",
    overflowScroll: "Scroll",
    surfaceNone: "None",
    surfaceSoft: "Soft",
    surfaceCard: "Card",
    surfaceAccent: "Accent",
    containerNone: "None",
    containerBorder: "Border",
    containerSoft: "Soft",
    containerCard: "Card",
    containerAccent: "Accent",
    content: "Content",
    headerFooter: "Header/footer",
    preview: "preview",
    viewport: "viewport",
    viewportDesktop: "Desktop",
    viewportTablet: "Tablet",
    viewportMobile: "Mobile",
    contentWidthTemplate: "Template default",
    contentWidthNarrow: "Narrow",
    contentWidthComfortable: "Comfortable",
    contentWidthWide: "Wide",
    contentWidthFull: "Full width",
    width: "Width",
    bounded: "Bounded",
    customSlots: "Custom slots",
    metrics: "Metrics",
    addMetric: "Add metric",
    removeMetric: "Remove metric",
    metricLabel: "Label",
    metricValue: "Value",
    metricColor: "Color",
    metricFallback: "Metric :number",
    statistics: "Statistics",
    addStat: "Add statistic",
    removeStat: "Remove statistic",
    statLabel: "Caption",
    statValue: "Number or value",
    statFallback: "Statistic :number",
    newLanguage: "New language",
    createLanguage: "Create language",
    chooseLanguageCode: "Choose a language code.",
    invalidLanguage: "Please choose a valid language code.",
    languageCreated: "Language created.",
    translationSaved: "Translation saved.",
    emptyTranslationKey: "Please enter a translation key.",
    storesOnlyTranslationKey: "The field stores only the translation key.",
};

export const languageIcon = `
    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m5 8 6 6"></path>
        <path d="m4 14 6-6 2-3"></path>
        <path d="M2 5h12"></path>
        <path d="M7 2h1"></path>
        <path d="m22 22-5-10-5 10"></path>
        <path d="M14 18h6"></path>
    </svg>
`;


export {initStyledSelects, notifyAlert, prompt, toast};
