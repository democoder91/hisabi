import * as React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import InteractiveChatForm from '../InteractiveChatForm';

jest.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (key: string) => ({
            'ai.interactiveHeading': 'Additional details needed',
            'ai.interactiveDescription': 'Answer the questions below to continue.',
            'ai.interactiveTextPlaceholder': 'Type your answer',
            'ai.interactiveSelectPlaceholder': 'Select an option',
            'ai.interactiveFieldRequired': 'This field is required.',
            'ai.interactiveStructuredHint': 'Structured answers keep the same AI turn going.',
            'ai.interactiveSubmit': 'Continue AI response',
        }[key] ?? key),
    }),
}));

it('collects text, select, and multiselect answers before submitting', async () => {
    const user = userEvent.setup();
    const onSubmit = jest.fn().mockResolvedValue(undefined);

    render(
        <InteractiveChatForm
            interaction={{
                status: 'pending',
                tool_name: 'ask_user_for_input',
                tool_call_id: 'tool-call-1',
                questions: [
                    {
                        id: 'note',
                        label: 'What note should I save?',
                        type: 'text',
                    },
                    {
                        id: 'account_id',
                        label: 'Which account should I use?',
                        type: 'select',
                        options: [
                            { label: 'Checking', value: 'checking' },
                            { label: 'Cash', value: 'cash' },
                        ],
                    },
                    {
                        id: 'tags',
                        label: 'Which tags should I add?',
                        type: 'multiselect',
                        options: [
                            { label: 'Food', value: 'food' },
                            { label: 'Team', value: 'team' },
                        ],
                    },
                ],
            }}
            onSubmit={onSubmit}
        />,
    );

    await user.type(screen.getByLabelText('What note should I save?'), 'Lunch with the team');
    await user.selectOptions(screen.getByLabelText('Which account should I use?'), 'checking');
    await user.click(screen.getByRole('button', { name: 'Food' }));
    await user.click(screen.getByRole('button', { name: 'Team' }));
    await user.click(screen.getByRole('button', { name: 'Continue AI response' }));

    expect(onSubmit).toHaveBeenCalledWith({
        note: 'Lunch with the team',
        account_id: 'checking',
        tags: ['food', 'team'],
    });
});

it('filters category options to match the selected transaction type', async () => {
    const user = userEvent.setup();

    render(
        <InteractiveChatForm
            interaction={{
                status: 'pending',
                tool_name: 'ask_user_for_input',
                tool_call_id: 'tool-call-2',
                questions: [
                    {
                        id: 'transaction_type',
                        label: 'What type of transaction is this?',
                        type: 'select',
                        options: [
                            { label: 'Expense (spending)', value: 'EXPENSES' },
                            { label: 'Income (earning)', value: 'INCOME' },
                        ],
                    },
                    {
                        id: 'category_id',
                        label: 'Select a category',
                        type: 'select',
                        options: [
                            { label: 'Bills', value: '39', meta: { category_type: 'EXPENSES' } },
                            { label: 'Family Support', value: '34', meta: { category_type: 'INCOME' } },
                        ],
                    },
                ],
            }}
            onSubmit={jest.fn()}
        />,
    );

    await user.selectOptions(screen.getByLabelText('What type of transaction is this?'), 'EXPENSES');

    expect(screen.getByRole('option', { name: 'Bills' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'Family Support' })).not.toBeInTheDocument();
});