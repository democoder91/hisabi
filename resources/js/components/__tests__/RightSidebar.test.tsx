import * as React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { usePage } from '@inertiajs/react';

import RightSidebar from '../RightSidebar';

jest.mock('@inertiajs/react', () => ({
    usePage: jest.fn(),
}));

jest.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (key: string) => {
            if (key === 'ai.title') {
                return 'NexoAi';
            }

            return key;
        },
    }),
}));

jest.mock('../Global/HisabiAIChat', () => ({
    __esModule: true,
    default: ({ onClose }: { onClose: () => void }) => (
        <div>
            <span>Mock AI Chat</span>
            <button type="button" onClick={onClose}>Close panel</button>
        </div>
    ),
}));

const mockedUsePage = usePage as jest.Mock;

afterEach(() => {
    cleanup();
    jest.clearAllMocks();
});

it('anchors the floating ai launcher to the right in ltr layouts', () => {
    mockedUsePage.mockReturnValue({ props: { direction: 'ltr' } });

    render(<RightSidebar />);

    const launcher = screen.getByTestId('ai-floating-button');

    expect(launcher).toHaveClass('right-4');
    expect(launcher).not.toHaveClass('left-4');
    expect(screen.queryByText('smsParser.title')).not.toBeInTheDocument();
});

it('anchors the floating ai launcher to the left in rtl layouts and opens the panel', async () => {
    mockedUsePage.mockReturnValue({ props: { direction: 'rtl' } });

    const user = userEvent.setup();

    render(<RightSidebar />);

    const launcher = screen.getByTestId('ai-floating-button');

    expect(launcher).toHaveClass('left-4');
    expect(launcher).not.toHaveClass('right-4');

    await user.click(launcher);

    const panel = screen.getByTestId('ai-floating-panel');

    expect(panel).toHaveClass('left-4');
    expect(screen.getByText('Mock AI Chat')).toBeVisible();
    expect(screen.queryByTestId('ai-floating-button')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Close panel' }));

    expect(screen.getByTestId('ai-floating-button')).toBeVisible();
});