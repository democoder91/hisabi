import * as React from 'react';
import { render, screen, within } from '@testing-library/react';

import Edit from '../Edit';

jest.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (key: string, options?: Record<string, string>) => ({
            'account.sharedBy': `Shared by ${options?.name ?? ''}`,
            'account.viewOnlyTransactionAccess': 'You can view transactions on this account, but transaction changes require edit permission.',
            'transaction.amount': 'Amount',
            'transaction.date': 'Date',
            'transaction.sourceAccount': 'Source account',
            'transaction.destinationAccount': 'Destination account',
            'transaction.note': 'Note',
            'settings.preferences.effectiveCurrency': 'Effective currency',
        }[key] ?? key),
    }),
}));

jest.mock('../../../Api', () => ({
    updateTransaction: jest.fn(),
    deleteTransaction: jest.fn(),
}));

jest.mock('@/components/ui/input', () => ({
    Input: ({ value = '', onChange = () => undefined, ...props }: any) => (
        <input value={value} onChange={onChange} {...props} />
    ),
}));

jest.mock('@/components/ui/label', () => ({
    Label: ({ children, htmlFor }: any) => <label htmlFor={htmlFor}>{children}</label>,
}));

jest.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, disabled }: any) => (
        <button type="button" onClick={onClick} disabled={disabled}>{children}</button>
    ),
}));

jest.mock('@/components/ui/long-press-button', () => ({
    LongPressButton: ({ children, onLongPress }: any) => (
        <button type="button" onClick={onLongPress}>{children}</button>
    ),
}));

jest.mock('@/components/ui/dialog', () => ({
    Dialog: ({ children }: any) => <div>{children}</div>,
    DialogContent: ({ children }: any) => <div>{children}</div>,
    DialogTitle: ({ children }: any) => <div>{children}</div>,
}));

jest.mock('@/components/Global/Combobox', () => ({
    __esModule: true,
    default: ({
        label,
        items,
        initialSelectedItem,
        displayInputValue,
        displayOptionValue,
    }: any) => (
        <section aria-label={label}>
            <div data-testid={`${label}-selected`}>
                {initialSelectedItem ? displayInputValue(initialSelectedItem) : ''}
            </div>
            <ul>
                {items.map((item: any) => (
                    <li key={item.id}>{displayOptionValue(item)}</li>
                ))}
            </ul>
        </section>
    ),
}));

describe('Transaction Edit', () => {
    it('keeps the current shared accounts visible while editing', () => {
        render(
            <Edit
                transaction={{
                    id: 10,
                    amount: 1650,
                    currency: 'EGP',
                    created_at: '2026-04-19',
                    note: 'Electricity bill',
                    canEdit: true,
                    fromAccount: {
                        id: 40,
                        name: 'Shared Savings',
                        name_translations: { en: 'Shared Savings' },
                        ownerName: 'Hagar',
                        isOwner: false,
                        currency: 'EGP',
                    },
                    toAccount: {
                        id: 41,
                        name: 'Shared Utilities',
                        name_translations: { en: 'Shared Utilities' },
                        ownerName: 'Hagar',
                        isOwner: false,
                        currency: 'EGP',
                    },
                }}
                accounts={[
                    {
                        id: 1,
                        name: 'Main Wallet',
                        name_translations: { en: 'Main Wallet' },
                        canEditTransactions: true,
                        isOwner: true,
                        ownerName: null,
                        currency: 'EGP',
                    },
                    {
                        id: 2,
                        name: 'Groceries',
                        name_translations: { en: 'Groceries' },
                        canEditTransactions: true,
                        isOwner: true,
                        ownerName: null,
                        currency: 'EGP',
                    },
                ]}
                onUpdate={jest.fn()}
                onDelete={jest.fn()}
                onClose={jest.fn()}
            />,
        );

        const sourceSection = screen.getByRole('region', { name: 'Source account' });
        const destinationSection = screen.getByRole('region', { name: 'Destination account' });

        expect(within(sourceSection).getByTestId('Source account-selected')).toHaveTextContent('Shared Savings · Shared by Hagar');
        expect(within(sourceSection).getAllByText('Shared Savings · Shared by Hagar')).toHaveLength(2);

        expect(within(destinationSection).getByTestId('Destination account-selected')).toHaveTextContent('Shared Utilities · Shared by Hagar');
        expect(within(destinationSection).getAllByText('Shared Utilities · Shared by Hagar')).toHaveLength(2);
    });
});