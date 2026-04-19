import { filterBudgets, filterCategories } from '../listFilters'

it('filters budgets by text, type, currency, recurrence, and account', () => {
  const budgets = [
    {
      id: 1,
      name: 'Emergency Budget',
      name_translations: { en: 'Emergency Budget', ar: 'ميزانية الطوارئ' },
      saving: false,
      reoccurrence: 'MONTHLY',
      currency: 'USD',
      accounts: [{ id: 10, name: 'Housing' }],
    },
    {
      id: 2,
      name: 'Savings Goal',
      name_translations: { en: 'Savings Goal', ar: 'هدف الادخار' },
      saving: true,
      reoccurrence: 'YEARLY',
      currency: 'EUR',
      accounts: [{ id: 20, name: 'Savings' }],
    },
  ]

  expect(filterBudgets(budgets, {
    searchQuery: 'طوارئ',
    budgetType: 'spending',
    recurrence: 'MONTHLY',
    currency: 'USD',
    accountId: '10',
  }).map((budget) => budget.id)).toEqual([1])
})

it('filters categories by translated name and usage state', () => {
  const categories = [
    {
      id: 1,
      name: 'Groceries',
      name_translations: { en: 'Groceries', ar: 'البقالة' },
      transactionsCount: 3,
    },
    {
      id: 2,
      name: 'Travel',
      name_translations: { en: 'Travel', ar: 'السفر' },
      transactionsCount: 0,
    },
  ]

  expect(filterCategories(categories, { searchQuery: 'بقالة', activity: 'used' }).map((category) => category.id)).toEqual([1])
  expect(filterCategories(categories, { activity: 'unused' }).map((category) => category.id)).toEqual([2])
})