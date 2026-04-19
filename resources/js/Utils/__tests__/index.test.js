import {
  cutString,
  formatNumber,
  getAccountOptionLabel,
  getLocalizedName,
  getSharedAccountOwnerLabel,
  withLocalizedName,
} from '../';

jest.mock('@/i18n', () => ({
  __esModule: true,
  default: {
    resolvedLanguage: 'en',
    language: 'en',
  },
}));

it('formatNumber', () => {
  expect(formatNumber(2000)).toBe('2k')
  expect(formatNumber(-2000)).toBe('(2k)')
  expect(formatNumber(-1530)).toBe('(1.530k)')
  expect(formatNumber(-1530, '0[.]00a')).toBe('-1.53k')
});

it('cutString', () => {
    expect(cutString('saleem', 2)).toBe('sa...')
    expect(cutString('saleem', 6)).toBe('saleem')
});

it('formats shared account owner labels', () => {
  const sharedAccount = {
    name: 'Joint Wallet',
    isOwner: false,
    ownerName: 'Mina',
  };

  expect(getSharedAccountOwnerLabel(sharedAccount, (name) => `Shared by ${name}`)).toBe('Shared by Mina');
  expect(getAccountOptionLabel(sharedAccount, (name) => `Shared by ${name}`)).toBe('Joint Wallet · Shared by Mina');
  expect(getAccountOptionLabel({ name: 'Checking', isOwner: true, ownerName: 'You' }, (name) => `Shared by ${name}`)).toBe('Checking');
});

it('prefers the active locale when localizing translatable names', () => {
  const category = {
    name: 'Groceries',
    name_translations: {
      en: 'Groceries',
      ar: 'البقالة',
    },
  };

  expect(getLocalizedName(category, 'ar')).toBe('البقالة');
  expect(withLocalizedName(category, 'ar').name).toBe('البقالة');
});

it('falls back to english when the active locale is missing', () => {
  const account = {
    name_translations: {
      en: 'Travel Fund',
    },
  };

  expect(getLocalizedName(account, 'ar')).toBe('Travel Fund');
});