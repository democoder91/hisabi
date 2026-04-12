import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './en.json';
import ar from './ar.json';

export function initI18n(locale: string = 'en') {
    if (i18n.isInitialized) {
        i18n.changeLanguage(locale);
        return;
    }

    i18n
        .use(initReactI18next)
        .init({
            resources: {
                en: { translation: en },
                ar: { translation: ar },
            },
            lng: locale,
            fallbackLng: 'en',
            interpolation: {
                escapeValue: false,
            },
        });
}

export default i18n;
