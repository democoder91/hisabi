import { useEffect, useMemo, useState } from 'react';
import { debounce } from 'lodash';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BankIcon, ArrowElbowDownRightIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { getAccounts } from '@/Api/accounts';
import { animateRowItem, formatNumber, getAppCurrency } from '@/Utils';
import Create from './Create';
import Edit from './Edit';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LoadMore from '@/components/Global/LoadMore';

export default function Index({ auth }) {
    const { t } = useTranslation();
    const [accounts, setAccounts] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    useEffect(() => {
        setLoading(true);

        getAccounts(currentPage, searchQuery)
            .then(({ data }) => {
                setAccounts((previous) => currentPage === 1 ? data.accounts.data : [...previous, ...data.accounts.data]);
                setHasMorePages(data.accounts.paginatorInfo.hasMorePages);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [currentPage, searchQuery]);

    const handleCreate = (account) => {
        setShowCreate(false);
        setAccounts((previous) => [account, ...previous]);
        animateRowItem(account.id);
    };

    const handleUpdate = (account) => {
        setAccounts((previous) => previous.map((item) => item.id === account.id ? account : item));
        animateRowItem(account.id);
    };

    const handleDelete = (account) => {
        animateRowItem(account.id, 'deleted', () => {
            setAccounts((previous) => previous.filter((item) => item.id !== account.id));
        });
    };

    const performSearch = useMemo(() => debounce((event) => {
        setAccounts([]);
        setCurrentPage(1);
        setSearchQuery(event.target.value ?? '');
    }, 300), []);

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
                    {(accounts.length > 0 || searchQuery) && (
                        <Input placeholder={t('account.searchAccounts')} className="max-w-56" onChange={performSearch} />
                    )}

                    <div className="grid gap-2">
                        {accounts.map((account) => (
                            <Card key={account.id} className="py-0" id={`item-${account.id}`}>
                                <CardContent className="flex items-center justify-between px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <BankIcon size={20} weight="duotone" />
                                        </div>
                                        <div>
                                            <button onClick={() => setEditItem(account)} className="font-medium hover:underline">
                                                {account.name}
                                            </button>
                                            <div className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                                <ArrowElbowDownRightIcon size={10} weight="bold" />
                                                <span>{account.transactionsCount} {account.transactionsCount === 1 ? t('common.transaction') : t('common.transactions')}</span>
                                                <Badge variant="secondary" className="text-[10px] capitalize">
                                                    {account.permissionLevel === 'owner' ? t('common.owner') : `${t('common.shared')} · ${t(`common.${account.permissionLevel}`)}`}
                                                </Badge>
                                                {account.isOwner && account.sharedUsers?.length > 0 && (
                                                    <span>{t('account.sharedWithCount', { count: account.sharedUsers.length })}</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex min-w-28 flex-col items-end gap-2">
                                        <p className="text-right font-medium">
                                            {getAppCurrency()} {formatNumber(account.balance, null)}
                                        </p>
                                        {account.canViewAudit && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('accounts.audit', account.id)}>{t('account.auditTrail')}</Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}

                        <LoadMore
                            hasContent={accounts.length > 0}
                            hasMorePages={hasMorePages}
                            loading={loading}
                            onClick={() => setCurrentPage((page) => page + 1)}
                        />
                    </div>
                </div>
            </div>
        </Authenticated>
    );
}