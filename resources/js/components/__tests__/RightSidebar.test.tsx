import * as React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

import RightSidebar from '../RightSidebar';

afterEach(() => {
    cleanup();
    jest.clearAllMocks();
});

it('does not render a floating ai launcher', () => {
    render(<RightSidebar />);

    expect(screen.queryByTestId('ai-floating-button')).not.toBeInTheDocument();
});