import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { type ColumnDef } from '@tanstack/react-table';

import Authenticated from '@/Layouts/Authenticated';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface BillingUser {
    id: number;
    name: string;
    email: string;
    availableCredits: number;
    trialEndsAt: string | null;
    isSuper: boolean;
    subscription: {
        planName: string;
        status: string;
        renewsAt: string | null;
    } | null;
}

interface GrantOption {
    id: number;
    slug: string;
    name_en: string;
    name_ar: string;
    currency: string;
    price: number;
    credits: number;
    renews_in_days?: number | null;
}

interface GrantAuditEntry {
    id: number;
    grantType: 'credits' | 'subscription';
    productName: string;
    adminUser: {
        name: string | null;
        email: string | null;
    };
    targetUser: {
        name: string | null;
        email: string | null;
    };
    createdAt: string | null;
    oldValues: {
        available_credits?: number;
    } | null;
    newValues: {
        available_credits?: number;
    } | null;
}

interface BillingUsersProps {
    auth: {
        user: {
            is_super?: boolean;
        };
    };
    users: BillingUser[];
    grantOptions: {
        creditPackages: GrantOption[];
        subscriptionPlans: GrantOption[];
    };
    filters: {
        search: string;
        per_page: number;
    };
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        hasMorePages: boolean;
    };
    recentGrantAudits: GrantAuditEntry[];
}

type GrantType = 'credits' | 'subscription';

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(amount);
}

function describeAccess(user: BillingUser, t: (key: string, options?: Record<string, unknown>) => string): string {
    if (user.subscription) {
        if (user.subscription.renewsAt) {
            return t('billing.activeUntil', { date: new Date(user.subscription.renewsAt).toLocaleDateString() });
        }

        return user.subscription.planName;
    }

    if (user.trialEndsAt) {
        return t('billing.activeUntil', { date: new Date(user.trialEndsAt).toLocaleDateString() });
    }

    return t('billing.noSubscription');
}

export default function BillingUsers({
    auth,
    users,
    grantOptions,
    filters,
    pagination,
    recentGrantAudits,
}: BillingUsersProps) {
    const { t } = useTranslation();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<BillingUser | null>(null);
    const [grantType, setGrantType] = useState<GrantType>('subscription');

    const {
        data: filterData,
        setData: setFilterData,
        get,
        processing: filterProcessing,
    } = useForm(filters);

    const {
        data: grantData,
        setData: setGrantData,
        post,
        processing: grantProcessing,
        errors: grantErrors,
        clearErrors,
        reset,
    } = useForm({
        billing_product_id: '',
    });

    const currentOptions = useMemo(
        () => grantType === 'subscription' ? grantOptions.subscriptionPlans : grantOptions.creditPackages,
        [grantOptions.creditPackages, grantOptions.subscriptionPlans, grantType],
    );

    useEffect(() => {
        if (!dialogOpen) {
            return;
        }

        const hasSelectedProduct = currentOptions.some((option) => String(option.id) === grantData.billing_product_id);

        if (!hasSelectedProduct) {
            setGrantData('billing_product_id', currentOptions[0] ? String(currentOptions[0].id) : '');
        }
    }, [currentOptions, dialogOpen, grantData.billing_product_id, setGrantData]);

    const openGrantDialog = (user: BillingUser) => {
        const defaultType = grantOptions.subscriptionPlans.length > 0 ? 'subscription' : 'credits';
        const defaultOptions = defaultType === 'subscription' ? grantOptions.subscriptionPlans : grantOptions.creditPackages;

        setSelectedUser(user);
        setGrantType(defaultType);
        clearErrors();
        reset();
        setGrantData('billing_product_id', defaultOptions[0] ? String(defaultOptions[0].id) : '');
        setDialogOpen(true);
    };

    const closeDialog = () => {
        setDialogOpen(false);
        setSelectedUser(null);
        clearErrors();
        reset();
    };

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        get(route('billing.manage.users'), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setFilterData('search', '');

        router.get(route('billing.manage.users'), {
            search: '',
            per_page: filters.per_page,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const submitGrant = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedUser) {
            return;
        }

        post(route('billing.manage.users.grants.store', selectedUser.id), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const columns = useMemo<ColumnDef<BillingUser>[]>(() => [
        {
            id: 'user',
            header: t('billing.user'),
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <div className="flex items-center gap-2">
                        <p className="font-medium">{row.original.name}</p>
                        {row.original.isSuper && <Badge variant="secondary">{t('billing.superAdminLabel')}</Badge>}
                    </div>
                    <p className="text-muted-foreground">{row.original.email}</p>
                </div>
            ),
        },
        {
            id: 'credits',
            header: t('billing.credits'),
            cell: ({ row }) => <span className="font-medium">{row.original.availableCredits}</span>,
        },
        {
            id: 'subscription',
            header: t('billing.currentSubscription'),
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <p className="font-medium">{row.original.subscription?.planName ?? t('billing.noSubscription')}</p>
                    <p className="text-muted-foreground">{describeAccess(row.original, t)}</p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">{t('billing.actions')}</div>,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button size="sm" onClick={() => openGrantDialog(row.original)}>
                        {t('billing.manageAccess')}
                    </Button>
                </div>
            ),
        },
    ], [t]);

    const header = (
        <div className="space-y-1">
            <h2>{t('billing.userAccessTitle')}</h2>
            <p className="text-sm text-muted-foreground">{t('billing.userAccessDescription')}</p>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('billing.userAccessTitle')} />

            <Dialog open={dialogOpen} onOpenChange={(open) => !open && closeDialog()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('billing.grantDialogTitle')}</DialogTitle>
                        <DialogDescription>
                            {t('billing.grantDialogDescription', { user: selectedUser?.email ?? '-' })}
                        </DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitGrant}>
                        <div className="space-y-2">
                            <Label>{t('billing.currentAccess')}</Label>
                            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                <p className="font-medium">{selectedUser?.subscription?.planName ?? t('billing.noSubscription')}</p>
                                <p className="text-muted-foreground">{selectedUser ? describeAccess(selectedUser, t) : '-'}</p>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>{t('billing.grantType')}</Label>
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant={grantType === 'subscription' ? 'default' : 'outline'}
                                    onClick={() => setGrantType('subscription')}
                                >
                                    {t('billing.grantSubscription')}
                                </Button>
                                <Button
                                    type="button"
                                    variant={grantType === 'credits' ? 'default' : 'outline'}
                                    onClick={() => setGrantType('credits')}
                                >
                                    {t('billing.grantCredits')}
                                </Button>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="billing-product">{t('billing.selectProduct')}</Label>
                            <Select
                                value={grantData.billing_product_id}
                                onValueChange={(value) => setGrantData('billing_product_id', value)}
                            >
                                <SelectTrigger id="billing-product">
                                    <SelectValue placeholder={t('billing.selectProductPlaceholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {currentOptions.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name_en} · {formatMoney(option.price, option.currency)} · {option.credits} {t('billing.credits')}
                                            {option.renews_in_days ? ` · ${option.renews_in_days}d` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {grantErrors.billing_product_id && (
                                <p className="text-sm text-destructive">{grantErrors.billing_product_id}</p>
                            )}
                            {currentOptions.length === 0 && (
                                <p className="text-sm text-muted-foreground">{t('billing.noGrantOptions')}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>
                                {t('common.cancel')}
                            </Button>
                            <Button disabled={grantProcessing || currentOptions.length === 0} type="submit">
                                {grantProcessing ? t('billing.applyingGrant') : t('billing.applyGrant')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                            <h3 className="text-lg font-semibold">{t('billing.userAccessTitle')}</h3>
                            <p className="text-sm text-muted-foreground">{t('billing.userAccessDescription')}</p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={route('billing.manage')}>{t('billing.manageTitle')}</Link>
                        </Button>
                    </div>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle>{t('billing.userAccessTitle')}</CardTitle>
                            <CardDescription>{t('billing.userAccessSummary', { count: pagination.total })}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form className="grid gap-3 md:grid-cols-[1fr_auto_auto]" onSubmit={submitFilters}>
                                <Input
                                    value={filterData.search}
                                    placeholder={t('billing.userSearchPlaceholder')}
                                    onChange={(event) => setFilterData('search', event.target.value)}
                                />
                                <Button disabled={filterProcessing} type="submit">
                                    {t('billing.applyFilters')}
                                </Button>
                                <Button disabled={filterProcessing} onClick={clearFilters} type="button" variant="outline">
                                    {t('billing.clearFilters')}
                                </Button>
                            </form>

                            <DataTable
                                columns={columns}
                                data={users}
                                emptyMessage={t('common.noResults')}
                                getRowId={(user) => user.id}
                            />

                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-sm text-muted-foreground">
                                    {t('billing.pageSummary', {
                                        page: pagination.currentPage,
                                        lastPage: pagination.lastPage,
                                    })}
                                </p>

                                <div className="flex gap-2">
                                    <Button asChild disabled={pagination.currentPage <= 1} variant="outline">
                                        <Link href={route('billing.manage.users', {
                                            ...filters,
                                            page: Math.max(1, pagination.currentPage - 1),
                                        })}>
                                            {t('billing.previous')}
                                        </Link>
                                    </Button>
                                    <Button asChild disabled={!pagination.hasMorePages} variant="outline">
                                        <Link href={route('billing.manage.users', {
                                            ...filters,
                                            page: pagination.currentPage + 1,
                                        })}>
                                            {t('billing.next')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle>{t('billing.recentGrantAudits')}</CardTitle>
                            <CardDescription>{t('billing.recentGrantAuditsDescription')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentGrantAudits.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('billing.noGrantAudits')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {recentGrantAudits.map((audit) => (
                                        <div key={audit.id} className="rounded-lg border p-4">
                                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">{audit.productName}</p>
                                                        <Badge variant="secondary">
                                                            {audit.grantType === 'subscription' ? t('billing.grantSubscription') : t('billing.grantCredits')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('billing.grantedBy', { admin: audit.adminUser.email ?? audit.adminUser.name ?? '-' })}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('billing.grantedTo', { user: audit.targetUser.email ?? audit.targetUser.name ?? '-' })}
                                                    </p>
                                                </div>
                                                <p className="text-sm text-muted-foreground">{formatDate(audit.createdAt)}</p>
                                            </div>
                                            <div className="mt-3 flex flex-wrap gap-3 text-sm text-muted-foreground">
                                                <span>{t('billing.beforeCredits', { credits: audit.oldValues?.available_credits ?? 0 })}</span>
                                                <span>{t('billing.afterCredits', { credits: audit.newValues?.available_credits ?? 0 })}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Authenticated>
    );
}