import { useEffect, useMemo, useState } from 'react';
import { debounce } from 'lodash';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { type ColumnDef } from '@tanstack/react-table';
import { BankIcon, ArrowElbowDownRightIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { getAccounts } from '@/Api/accounts';
import { getCurrencySettings } from '@/Api/settings';
import { animateRowItem, formatNumber, getSharedAccountOwnerLabel } from '@/Utils';
import Create from './Create';
import Edit from './Edit';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import LoadMore from '@/components/Global/LoadMore';
import { withLocalizedNames } from '@/Utils';

declare const route: any;

type AccountFilters = {
    currency: string;
    access: string;
};

type CurrencyOption = {
    value: string;
    label: string;
};

export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = urlParams.get('search') || '';
    const initialFilters = {
        currency: urlParams.get('currency') || '',
        access: urlParams.get('access') || '',
    };
    const [accounts, setAccounts] = useState<any[]>([]);
    const [currencies, setCurrencies] = useState<CurrencyOption[]>([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(true);
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [filters, setFilters] = useState(initialFilters);
    const [loading, setLoading] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    useEffect(() => {
        getCurrencySettings()
            .then((payload) => setCurrencies(payload.options.currencies))
            .catch(console.error);
    }, []);

    useEffect(() => {
        setLoading(true);

        getAccounts(currentPage, searchQuery, filters)
            .then(({ data }) => {
                setAccounts((previous) => currentPage === 1 ? data.accounts.data : [...previous, ...data.accounts.data]);
                setHasMorePages(data.accounts.paginatorInfo.hasMorePages);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [currentPage, searchQuery, filters]);

    const handleCreate = (account: any) => {
        setShowCreate(false);
        setAccounts((previous) => [account, ...previous]);
        animateRowItem(account.id);
    };

    const handleUpdate = (account: any) => {
        setAccounts((previous) => previous.map((item) => item.id === account.id ? account : item));
        animateRowItem(account.id);
    };

    const handleDelete = (account: any) => {
        animateRowItem(account.id, 'deleted', () => {
            setAccounts((previous) => previous.filter((item) => item.id !== account.id));
        });
    };

    const syncUrl = (nextSearchQuery: string, nextFilters: AccountFilters) => {
        const url = new URL(window.location.href);

        if (nextSearchQuery) {
            url.searchParams.set('search', nextSearchQuery);
        } else {
            url.searchParams.delete('search');
        }

        if (nextFilters.currency) {
            url.searchParams.set('currency', nextFilters.currency);
        } else {
            url.searchParams.delete('currency');
        }

        if (nextFilters.access) {
            url.searchParams.set('access', nextFilters.access);
        } else {
            url.searchParams.delete('access');
        }

        window.history.pushState({}, '', url);
    };

    const performSearch = useMemo(() => debounce((event: React.ChangeEvent<HTMLInputElement>) => {
        const nextSearchQuery = event.target.value ?? '';

        setAccounts([]);
        setCurrentPage(1);
        setSearchQuery(nextSearchQuery);
        syncUrl(nextSearchQuery, filters);
    }, 300), [filters]);

    const handleFilterChange = (key: keyof AccountFilters, value: string) => {
        const normalizedValue = value === 'ALL' ? '' : value;
        const nextFilters = {
            ...filters,
            [key]: normalizedValue,
        };

        setAccounts([]);
        setCurrentPage(1);
        setFilters(nextFilters);
        syncUrl(searchQuery, nextFilters);
    };

    const localizedAccounts = useMemo(() => withLocalizedNames(accounts, activeLocale), [accounts, activeLocale]);
    const hasActiveFilters = Boolean(searchQuery || filters.currency || filters.access);

    const columns = useMemo<ColumnDef<any>[]>(() => [
        {
            accessorKey: 'name',
            header: t('account.name'),
            cell: ({ row }) => {
                const account = row.original;
                const sharedOwnerLabel = getSharedAccountOwnerLabel(account, (ownerName: string) => t('account.sharedBy', { name: ownerName }));

                return (
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <BankIcon size={20} weight="duotone" />
                        </div>
                        <div className="space-y-1">
                            <p className="font-medium">{account.name}</p>
                            <div className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                <ArrowElbowDownRightIcon size={10} weight="bold" />
                                <span>
                                    {account.transactionsCount} {account.transactionsCount === 1 ? t('common.transaction') : t('common.transactions')}
                                </span>
                            </div>
                            {sharedOwnerLabel && (
                                <p className="text-xs text-muted-foreground">{sharedOwnerLabel}</p>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'balance',
            header: t('account.balance'),
            cell: ({ row }) => (
                <p className="font-medium whitespace-nowrap">
                    {row.original.currency} {formatNumber(row.original.balance, null)}
                </p>
            ),
        },
        {
            id: 'sharing',
            header: t('account.sharingTab'),
            cell: ({ row }) => {
                const account = row.original;

                return (
                    <div className="space-y-2">
                        <Badge variant="secondary" className="text-[10px] capitalize">
                            {account.permissionLevel === 'owner'
                                ? t('common.owner')
                                : `${t('common.shared')} · ${t(`common.${account.permissionLevel}`)}`}
                        </Badge>
                        <p className="text-xs text-muted-foreground">
                            {account.isOwner && account.sharedUsers?.length > 0
                                ? t('account.sharedWithCount', { count: account.sharedUsers.length })
                                : ' - '}
                        </p>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">{t('common.actions')}</div>,
            cell: ({ row }) => {
                const account = row.original;

                return (
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" onClick={() => setEditItem(account)}>
                            {t('common.edit')}
                        </Button>
                        {account.canViewAudit && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('accounts.audit', account.id)}>{t('account.auditTrail')}</Link>
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ], [t]);

    const header = (
        <div className="flex items-center justify-between w-full">
            <h2>{t('account.title')}</h2>
            <Button onClick={() => setShowCreate(true)}>{t('account.createAccount')}</Button>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('account.title')} />

            <Create open={showCreate} onClose={() => setShowCreate(false)} onCreate={handleCreate} />
            <Edit account={editItem} onClose={() => setEditItem(null)} onDelete={handleDelete} onUpdate={handleUpdate} />

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-4">
                    {(accounts.length > 0 || hasActiveFilters) && (
                        <div className="flex flex-wrap gap-2">
                            <Input
                                placeholder={t('account.searchAccounts')}
                                className="max-w-56"
                                defaultValue={searchQuery}
                                onChange={performSearch}
                            />
                            <Select value={filters.access || 'ALL'} onValueChange={(value) => handleFilterChange('access', value)}>
                                <SelectTrigger className="w-full sm:w-[180px]">
                                    <SelectValue placeholder={t('account.access')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('account.allAccess')}</SelectItem>
                                    <SelectItem value="owned">{t('account.ownedOnly')}</SelectItem>
                                    <SelectItem value="shared">{t('account.sharedOnly')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={filters.currency || 'ALL'} onValueChange={(value) => handleFilterChange('currency', value)}>
                                <SelectTrigger className="w-full sm:w-[180px]">
                                    <SelectValue placeholder={t('settings.nav.currencies')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('account.allCurrencies')}</SelectItem>
                                    {currencies.map((currency) => (
                                        <SelectItem key={currency.value} value={currency.value}>{currency.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <DataTable
                        columns={columns}
                        data={localizedAccounts}
                        loading={loading}
                        loadingMessage={t('common.loading')}
                        emptyMessage={t('common.noResults')}
                        getRowId={(account) => account.id}
                    />

                    {accounts.length > 0 && (
                        <LoadMore
                            hasContent={accounts.length > 0}
                            hasMorePages={hasMorePages}
                            loading={loading}
                            onClick={() => setCurrentPage((page) => page + 1)}
                        />
                    )}
                </div>
            </div>
        </Authenticated>
    );
}