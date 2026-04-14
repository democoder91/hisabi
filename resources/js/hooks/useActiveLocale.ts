import { usePage } from '@inertiajs/react';

import i18n from '@/i18n';

export function useActiveLocale(): string {
    const { locale } = usePage<{ locale?: string }>().props as { locale?: string };

    return locale ?? i18n.resolvedLanguage ?? i18n.language ?? 'en';
}