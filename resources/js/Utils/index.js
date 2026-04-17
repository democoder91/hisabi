import numbro from 'numbro';

import i18n from '@/i18n';

export const TRANSACTION_TYPES = {
    DEBIT: 'DEBIT',
    CREDIT: 'CREDIT',
}

export const getAppCurrency = () => {
    return window ? window.AppCurrency : ''
}

/**
 * @param {number} number
 * @param {string | null | undefined} format
 */
export const formatNumber = (number, format = '(0[.]000a)') => {
    numbro.setDefaults({ thousandSeparated: true, })

    const num = numbro(number)

    if(! format) {
        return num.format()
    }

    // Don't abbreviate numbers below 1000
    if (Math.abs(number) < 1000 && format.includes('a')) {
        const plainFormat = format.replace('a', '')
        return num.format(plainFormat)
    }

    return num.format(format)
}

/**
 * @param {number | string} id
 * @param {string} animation
 * @param {(() => void) | null} callback
 */
export const animateRowItem = (id, animation = 'updated', callback = null) => {
    let rowItem = document.getElementById('item-' + id);

    if(! rowItem) { return; }
    
    rowItem.classList.remove(animation);
    
    setTimeout(() => {
        rowItem.classList.add(animation); 
        if(callback != null) {
            setTimeout(callback, 500);
        }
    }, 50);
}

export const cutString = (stringValue, upTo) => {
    if(stringValue.length > upTo) {
        return stringValue.substr(0, upTo) + '...'
    }

    return stringValue
}

export const colors = () => {
    return [
        {tailwind: 'bg-red-500', hex: '#ef4444'},
        {tailwind: 'bg-amber-500', hex: '#f59e0b'},
        {tailwind: 'bg-orange-500', hex: '#f97316'},
        {tailwind: 'bg-yellow-500', hex: '#eab308'},
        {tailwind: 'bg-green-500', hex: '#22c55e'},
        {tailwind: 'bg-lime-500', hex: '#84cc16'},
        {tailwind: 'bg-sky-500', hex: '#0ea5e9'},
        {tailwind: 'bg-teal-500', hex: '#14b8a6'},
        {tailwind: 'bg-blue-500', hex: '#3b82f6'},
        {tailwind: 'bg-indigo-500', hex: '#6366f1'},
        {tailwind: 'bg-fuchsia-500', hex: '#d946ef'},
        {tailwind: 'bg-pink-500', hex: '#ec4899'},
        {tailwind: 'bg-rose-500', hex: '#f43f5e'},
    ];
}

export const getTailwindColor = (index) => {
    return colors()[index] ? colors()[index].tailwind : "bg-gray-500";
}

export const getTransactionTypeForCategoryType = (categoryType) => {
    return categoryType === 'INCOME'
        ? TRANSACTION_TYPES.CREDIT
        : TRANSACTION_TYPES.DEBIT
}

export const isCategoryCompatibleWithTransactionType = (category, transactionType) => {
    if (!category?.type || !transactionType) {
        return true
    }

    return getTransactionTypeForCategoryType(category.type) === transactionType
}

export const isCategoryAvailableForAccount = (category, account) => {
    if (!category || !account) {
        return true
    }

    if (account.ownerId == null || category.ownerUserId == null) {
        return true
    }

    return Number(category.ownerUserId) === Number(account.ownerId)
}

export const getSharedAccountOwnerLabel = (account, formatSharedBy) => {
    if (!account || account.isOwner || !account.ownerName) {
        return ''
    }

    return formatSharedBy(account.ownerName)
}

export const getActiveLocale = () => {
    const documentLocale = typeof document !== 'undefined'
        ? document.documentElement?.lang
        : ''

    return i18n.resolvedLanguage
        || i18n.language
        || documentLocale
        || 'en'
}

export const getNameTranslations = (item) => {
    if (!item) {
        return {}
    }

    if (item.name_translations && typeof item.name_translations === 'object') {
        return item.name_translations
    }

    if (item.name && typeof item.name === 'object') {
        return item.name
    }

    if (typeof item.name === 'string' && item.name !== '') {
        return { en: item.name }
    }

    return {}
}

export const getLocalizedName = (item, locale = getActiveLocale()) => {
    if (!item) {
        return ''
    }

    const translations = getNameTranslations(item)
    const localizedName = translations?.[locale]
        ?? translations?.en
        ?? Object.values(translations).find((value) => typeof value === 'string' && value !== '')

    if (localizedName) {
        return localizedName
    }

    return typeof item.name === 'string' ? item.name : ''
}

export const withLocalizedName = (item, locale = getActiveLocale()) => {
    if (!item) {
        return item
    }

    const nameTranslations = getNameTranslations(item)

    return {
        ...item,
        name: getLocalizedName(item, locale),
        name_translations: nameTranslations,
    }
}

export const withLocalizedNames = (items = [], locale = getActiveLocale()) => {
    if (!Array.isArray(items)) {
        return []
    }

    return items.map((item) => withLocalizedName(item, locale))
}

export const getAccountOptionLabel = (account, formatSharedBy) => {
    if (!account) {
        return ''
    }

    const sharedOwnerLabel = getSharedAccountOwnerLabel(account, formatSharedBy)
    const accountName = getLocalizedName(account)

    return sharedOwnerLabel
        ? `${accountName} · ${sharedOwnerLabel}`
        : accountName
}

export const getCategoryOptionLabel = (category, categories = []) => {
    if (!category) {
        return ''
    }

    const baseLabel = getLocalizedName(category)
    const matchingCategories = categories.filter((item) => getLocalizedName(item) === baseLabel)

    if (matchingCategories.length <= 1) {
        return baseLabel
    }

    const sameOwnerMatches = matchingCategories.filter((item) => item?.ownerUserId === category.ownerUserId)
    const ownerLabel = category.ownerName ? category.ownerName : `#${category.id}`

    if (sameOwnerMatches.length > 1) {
        return `${baseLabel} · ${ownerLabel} · #${category.id}`
    }

    return `${baseLabel} · ${ownerLabel}`
}

export const isBrandCompatibleWithTransactionType = (brand, transactionType) => {
    if (!brand?.category?.type || !transactionType) {
        return true
    }

    return getTransactionTypeForCategoryType(brand.category.type) === transactionType
}

export const isCreditTransaction = (transaction) => {
    return transaction?.transaction_type === TRANSACTION_TYPES.CREDIT
}