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

it('filters source and destination account options so the same account cannot be selected twice', async () => {
    const user = userEvent.setup();

    render(
        <InteractiveChatForm
            interaction={{
                status: 'pending',
                tool_name: 'ask_user_for_input',
                tool_call_id: 'tool-call-2',
                questions: [
                    {
                        id: 'from_account_id',
                        label: 'Which account should fund this transaction?',
                        type: 'select',
                        options: [
                            { label: 'Checking', value: '1' },
                            { label: 'Cash', value: '2' },
                        ],
                    },
                    {
                        id: 'to_account_id',
                        label: 'Which account should receive this transaction?',
                        type: 'select',
                        options: [
                            { label: 'Checking', value: '1' },
                            { label: 'Cash', value: '2' },
                        ],
                    },
                ],
            }}
            onSubmit={jest.fn()}
        />,
    );

    const fromAccountSelect = screen.getByLabelText('Which account should fund this transaction?') as HTMLSelectElement;
    const toAccountSelect = screen.getByLabelText('Which account should receive this transaction?') as HTMLSelectElement;

    await user.selectOptions(fromAccountSelect, '1');

    expect(Array.from(toAccountSelect.options).map((option) => option.value)).toEqual(['', '2']);

    await user.selectOptions(fromAccountSelect, '');
    await user.selectOptions(toAccountSelect, '2');

    expect(Array.from(fromAccountSelect.options).map((option) => option.value)).toEqual(['', '1']);
});