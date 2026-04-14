import numbro from 'numbro';

export const TRANSACTION_TYPES = {
    DEBIT: 'DEBIT',
    CREDIT: 'CREDIT',
}

export const getAppCurrency = () => {
    return window ? window.AppCurrency : ''
}

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

    const participantUserIds = Array.isArray(account.participantUserIds)
        ? account.participantUserIds.map((id) => Number(id))
        : []

    if (participantUserIds.length === 0 || category.ownerUserId == null) {
        return true
    }

    return participantUserIds.includes(Number(category.ownerUserId))
}

export const getSharedAccountOwnerLabel = (account, formatSharedBy) => {
    if (!account || account.isOwner || !account.ownerName) {
        return ''
    }

    return formatSharedBy(account.ownerName)
}

export const getAccountOptionLabel = (account, formatSharedBy) => {
    if (!account) {
        return ''
    }

    const sharedOwnerLabel = getSharedAccountOwnerLabel(account, formatSharedBy)

    return sharedOwnerLabel
        ? `${account.name} · ${sharedOwnerLabel}`
        : account.name
}

export const getCategoryOptionLabel = (category, categories = []) => {
    if (!category) {
        return ''
    }

    const baseLabel = category.name ?? ''
    const matchingCategories = categories.filter((item) => item?.name === baseLabel)

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