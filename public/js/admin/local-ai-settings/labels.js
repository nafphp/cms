// UI strings for the settings panel, picked by the document language.
//
// These are not routed through the PHP translator on purpose: the panel renders
// its modals client-side, long after the server response, and shipping the two
// label sets is cheaper than a round trip for every string.

import { trimValue } from '../ollama-client.js';

export const uiLanguage = () => trimValue(document.documentElement.lang || navigator.language || 'en').slice(0, 2).toLowerCase();

export const labelsForLanguage = () => {
    const de = uiLanguage() === 'de';

    return de ? {
        reviewTitle: 'Fehler analysieren',
        reviewIntro: 'Ich schaue mir die offenen Punkte an und schlage kurze Memory-Einträge vor.',
        reviewButton: 'Fehler analysieren',
        close: 'Schließen',
        previous: 'Zurück',
        next: 'Weiter',
        acceptSolution: 'Das ist die Lösung',
        deleteMistake: 'Fehler löschen',
        researchMore: 'Weiter recherchieren',
        addInfo: 'Info ergänzen',
        solution: 'Mögliche Lösung',
        proposedMemory: 'Vorgeschlagener Memory-Satz',
        researchTitle: 'Weitere Infos ergänzen',
        researchIntro: 'Ergänze Kontext, Widerspruch oder was noch nicht passt. Das Modell arbeitet daraus einen neuen Lösungsvorschlag.',
        researchPlaceholder: 'Was fehlt noch? Was soll Nixorious beim nächsten Vorschlag beachten?',
        empty: 'Juhuu, keine Fehler mehr.',
        emptyMessages: [
            'Juhuu, keine Fehler mehr.',
            'Alles sauber. Nixorious darf kurz durchatmen.',
            'Keine offenen Punkte mehr. Sieht gut aus.',
            'Fehlerliste leer. Sehr angenehm.',
        ],
        loading: 'Analysiere Mistakes mit dem lokalen Modell...',
        researching: 'Arbeite Zusatzinfos in den Lösungsvorschlag ein...',
        saving: 'Speichere Memory-Eintrag...',
        saved: 'Gelöst: Memory aktualisiert und Mistake entfernt.',
        deleted: 'Fehler gelöscht.',
        deleting: 'Lösche Fehler...',
        failed: 'Analyse fehlgeschlagen.',
        chooseModel: 'Bitte zuerst ein lokales Chat-Modell auswählen.',
        editorTitle: 'Prompt-Dateien bearbeiten',
        editorIntro: 'Bearbeite die lokalen Markdown-Dateien direkt. MISTAKES.md bleibt Logmaterial und wird nicht in den Chat-Prompt geladen.',
        editorSave: 'Datei speichern',
        editorLoading: 'Lade Prompt-Dateien...',
        editorSaving: 'Speichere Prompt-Datei...',
        editorSaved: 'Prompt-Datei gespeichert.',
        editorFailed: 'Prompt-Dateien konnten nicht geladen werden.',
        promptActive: 'Aktiver Prompt',
        promptSupport: 'Arbeitsdatei',
    } : {
        reviewTitle: 'Analyze mistakes',
        reviewIntro: 'I will review the open points and suggest short memory entries.',
        reviewButton: 'Analyze',
        close: 'Close',
        previous: 'Previous',
        next: 'Next',
        acceptSolution: 'This is the solution',
        deleteMistake: 'Delete mistake',
        researchMore: 'Research further',
        addInfo: 'Add info',
        solution: 'Possible solution',
        proposedMemory: 'Suggested memory sentence',
        researchTitle: 'Add more information',
        researchIntro: 'Add context, disagreement, or what is still missing. The model will produce a better solution proposal.',
        researchPlaceholder: 'What is missing? What should Nixorious consider in the next proposal?',
        empty: 'Hooray, no mistakes left.',
        emptyMessages: [
            'Hooray, no mistakes left.',
            'All clear. Nixorious may breathe for a second.',
            'No open points left. Looking good.',
            'The mistake list is empty. Pleasant.',
        ],
        loading: 'Analyzing mistakes with the local model...',
        researching: 'Applying the additional information to the solution...',
        saving: 'Saving memory entry...',
        saved: 'Resolved: Memory updated and mistake removed.',
        deleted: 'Mistake deleted.',
        deleting: 'Deleting mistake...',
        failed: 'Analysis failed.',
        chooseModel: 'Choose a local chat model first.',
        editorTitle: 'Edit prompt files',
        editorIntro: 'Edit the local Markdown files directly. MISTAKES.md remains working log material and is not loaded into the chat prompt.',
        editorSave: 'Save file',
        editorLoading: 'Loading prompt files...',
        editorSaving: 'Saving prompt file...',
        editorSaved: 'Prompt file saved.',
        editorFailed: 'Could not load prompt files.',
        promptActive: 'Active prompt',
        promptSupport: 'Working file',
    };
};
