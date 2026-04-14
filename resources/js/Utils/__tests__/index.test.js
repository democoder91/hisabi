import {
  cutString,
  formatNumber,
  getAccountOptionLabel,
  getCategoryOptionLabel,
  getLocalizedName,
  getSharedAccountOwnerLabel,
  isCategoryAvailableForAccount,
  withLocalizedName,
} from '../';

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

it('filters categories by account participants', () => {
  const account = { participantUserIds: [3, 8] };

  expect(isCategoryAvailableForAccount({ ownerUserId: 8 }, account)).toBe(true);
  expect(isCategoryAvailableForAccount({ ownerUserId: 10 }, account)).toBe(false);
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

it('disambiguates duplicate category labels', () => {
  const categories = [
    { id: 5, name: 'Groceries', ownerUserId: 2, ownerName: 'Mina' },
    { id: 7, name: 'Groceries', ownerUserId: 3, ownerName: 'Omar' },
    { id: 8, name: 'Groceries', ownerUserId: 3, ownerName: 'Omar' },
    { id: 9, name: 'Fuel', ownerUserId: 3, ownerName: 'Omar' },
  ];

  expect(getCategoryOptionLabel(categories[0], categories)).toBe('Groceries · Mina');
  expect(getCategoryOptionLabel(categories[1], categories)).toBe('Groceries · Omar · #7');
  expect(getCategoryOptionLabel(categories[3], categories)).toBe('Fuel');
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