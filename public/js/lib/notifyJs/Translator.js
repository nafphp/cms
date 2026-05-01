import de from './translation/de.json' with {type: 'json'};
import en from './translation/en.json' with {type: 'json'};

class Translator {

    #translations = {
        'de': de,
        'en': en
    };

    constructor(lang) {
        this.lang = lang;
    }

    translate(slug) {

        const languageObj = this.#translations[this.lang];

        if (languageObj[slug]) {
            return languageObj[slug];
        }

        for (const key of Object.keys(languageObj)) {

            const regex = key.replaceAll(/(\${\w+})/g, '(\\w+)');
            const match = slug.match(regex);

            if (match) {

                let keyString = languageObj[key];

                match.values().forEach((item) => {
                    if (item === slug) return;
                    keyString = keyString.replace(/(\${\w+})/, item);
                });

                return keyString;
            }

        }
    }

}

export default function translate(key, lang = 'en') {

    const translator = new Translator(lang);
    return translator.translate(key) || key;

};