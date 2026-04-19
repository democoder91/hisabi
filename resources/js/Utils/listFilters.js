const normalizeQuery = (value = '') => value.trim().toLowerCase()

const matchesTranslatedName = (item, searchQuery) => {
    const normalizedQuery = normalizeQuery(searchQuery)

    if (!normalizedQuery) {
        return true
    }

    const translations = item?.name_translations && typeof item.name_translations === 'object'
        ? Object.values(item.name_translations)
        : []

    const names = [item?.name, ...translations]
        .filter((value) => typeof value === 'string')
        .map((value) => value.toLowerCase())

    return names.some((value) => value.includes(normalizedQuery))
}

export const filterBudgets = (budgets = [], filters = {}) => budgets.filter((budget) => {
    if (!matchesTranslatedName(budget, filters.searchQuery)) {
        return false
    }

    if (filters.budgetType === 'saving' && !budget.saving) {
        return false
    }

    if (filters.budgetType === 'spending' && budget.saving) {
        return false
    }

    if (filters.recurrence && budget.reoccurrence !== filters.recurrence) {
        return false
    }

    if (filters.currency && budget.currency !== filters.currency) {
        return false
    }

    if (filters.accountId) {
        return (budget.accounts ?? []).some((account) => String(account.id) === String(filters.accountId))
    }

    return true
})

export const filterCategories = (categories = [], filters = {}) => categories.filter((category) => {
    if (!matchesTranslatedName(category, filters.searchQuery)) {
        return false
    }

    if (filters.activity === 'used' && Number(category.transactionsCount ?? 0) === 0) {
        return false
    }

    if (filters.activity === 'unused' && Number(category.transactionsCount ?? 0) > 0) {
        return false
    }

    return true
})