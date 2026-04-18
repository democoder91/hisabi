import * as React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { usePage } from '@inertiajs/react';

import { SidebarProvider } from '@/components/ui/sidebar';
import { AppSidebar } from '../app-sidebar';

jest.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: any) => <a href={href} {...props}>{children}</a>,
    usePage: jest.fn(),
}));

jest.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (key: string) => ({
            'navigation.dashboard': 'Dashboard',
            'navigation.nexoAi': 'Nexo AI',
            'navigation.accounts': 'Accounts',
            'navigation.transactions': 'Transactions',
            'navigation.budgets': 'Budgets',
            'navigation.categories': 'Categories',
            'navigation.billing': 'Billing',
            'navigation.billingAdmin': 'Billing Admin',
            'navigation.billingUserAccess': 'User Access',
            'navigation.aiToolLogs': 'AI Tool Logs',
        }[key] ?? key),
    }),
}));

jest.mock('@/components/Global/ApplicationLogo', () => () => <span>Logo</span>);
jest.mock('@/components/user-nav', () => ({
    UserNav: () => <div>User Nav</div>,
}));
jest.mock('@/hooks/use-mobile', () => ({
    useIsMobile: () => false,
}));

const mockedUsePage = usePage as jest.Mock;

const createRouteMock = (currentRoute: string | null = null) => (name?: string) => {
    if (!name) {
        return {
            current: (routeName: string) => routeName === currentRoute,
        };
    }

    const routeMap: Record<string, string> = {
        dashboard: '/dashboard',
        'ai.chat': '/ai',
        accounts: '/accounts',
        transactions: '/transactions',
        budgets: '/budgets',
        categories: '/categories',
        'billing.index': '/billing',
        'billing.manage': '/billing/manage',
        'billing.manage.users': '/billing/manage/users',
        'ai.tool-usage': '/ai/tool-usage',
    };

    return routeMap[name] ?? `/${name}`;
};

beforeEach(() => {
    mockedUsePage.mockReturnValue({ props: { direction: 'ltr' } });
    (global as any).route = createRouteMock();
});

afterEach(() => {
    cleanup();
    jest.clearAllMocks();
});

it('renders the Nexo AI item in the main application sidebar', () => {
    render(
        <SidebarProvider>
            <AppSidebar />
        </SidebarProvider>,
    );

    expect(screen.getByRole('link', { name: /nexo ai/i })).toHaveAttribute('href', '/ai');
    expect(screen.getByRole('link', { name: /dashboard/i })).toHaveAttribute('href', '/dashboard');
});