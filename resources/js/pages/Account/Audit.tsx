import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { type ColumnDef } from '@tanstack/react-table';
import { ArrowLeftIcon, ClockCounterClockwiseIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { getAccountAudits } from '@/Api/accounts';
import { formatNumber } from '@/Utils';
import LoadMore from '@/components/Global/LoadMore';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { withLocalizedName } from '@/Utils';

const hiddenDiffFields = ['id'];

export default function Audit({ auth, account }) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const [audits, setAudits] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(true);
    const [loading, setLoading] = useState(false);
    const previousLocaleRef = useRef(activeLocale);

    const localizedAccount = useMemo(() => withLocalizedName(account, activeLocale), [account, activeLocale]);

    useEffect(() => {
        const localeChanged = previousLocaleRef.current !== activeLocale;

        if (localeChanged) {
            previousLocaleRef.current = activeLocale;
            setCurrentPage(1);
            setHasMorePages(true);
        }

        const pageToFetch = localeChanged ? 1 : currentPage;

        setLoading(true);

        getAccountAudits(localizedAccount.id, pageToFetch)
            .then(({ data }) => {
                setAudits((previous) => pageToFetch === 1 ? data.audits : [...previous, ...data.audits]);
                setHasMorePages(data.paginatorInfo.hasMorePages);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [activeLocale, currentPage, localizedAccount.id]);

    const header = (
        <div className="flex w-full items-center justify-between gap-3">
            <div>
                <h2>{localizedAccount.name}</h2>
                <p className="text-sm text-muted-foreground">{t('account.auditDescription')}</p>
            </div>
            <Button variant="outline" asChild>
                <Link href={route('accounts')}>
                    <ArrowLeftIcon size={16} />
                    {t('account.backToAccounts')}
                </Link>
            </Button>
        </div>
    );

    const formatAuditValue = (field, value) => {
        if (value === null || value === undefined || value === '') {
            return t('account.emptyValue');
        }

        if (field === 'amount') {
            return `${localizedAccount.currency} ${formatNumber(value, null)}`;
        }

        if (field.endsWith('_at')) {
            return new Date(value).toLocaleString();
        }

        return String(value);
    };

    const getActionLabel = (action) => {
        if (action === 'created') {
            return t('account.createdAction');
        }

        if (action === 'deleted') {
            return t('account.deletedAction');
        }

        return t('account.updatedAction');
    };

    const getActionVariant = (action) => {
        if (action === 'deleted') {
            return 'destructive';
        }

        return action === 'created' ? 'secondary' : 'outline';
    };

    const renderedAudits = useMemo(() => audits.map((audit) => {
        const subject = audit.newValues && Object.keys(audit.newValues).length > 0
            ? audit.newValues
            : audit.oldValues;

        const changedFields = (audit.changedFields ?? []).filter((field) => !hiddenDiffFields.includes(field));

        return {
            ...audit,
            subject,
            changedFields,
        };
    }), [audits]);

    const columns = useMemo<ColumnDef<any>[]>(() => [
        {
            id: 'action',
            header: t('account.action'),
            cell: ({ row }) => (
                <Badge variant={getActionVariant(row.original.action)} className="capitalize">
                    {getActionLabel(row.original.action)}
                </Badge>
            ),
        },
        {
            id: 'transaction',
            header: t('account.transactionDetails'),
            cell: ({ row }) => {
                const audit = row.original;

                return (
                    <div className="space-y-1 text-sm">
                        <p className="font-medium">
                            {audit.subject?.transaction_type ?? 'N/A'} · {formatAuditValue('amount', audit.subject?.amount)}
                        </p>
                        <p className="text-muted-foreground">
                            {audit.subject?.category_name ?? t('account.emptyValue')}
                        </p>
                        <p className="text-muted-foreground">
                            {audit.subject?.note || t('account.emptyValue')}
                        </p>
                    </div>
                );
            },
        },
        {
            id: 'performedBy',
            header: t('account.performedByLabel'),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.user?.name ?? row.original.user?.email ?? t('account.emptyValue')}
                </span>
            ),
        },
        {
            id: 'changes',
            header: t('account.changes'),
            cell: ({ row }) => {
                const audit = row.original;

                if (audit.action !== 'updated' || audit.changedFields.length === 0) {
                    return <span className="text-sm text-muted-foreground">-</span>;
                }

                return (
                    <div className="space-y-2 rounded-lg bg-muted/40 p-3">
                        {audit.changedFields.map((field) => (
                            <div key={`${audit.id}-${field}`} className="grid gap-1 text-sm">
                                <span className="font-medium capitalize">{field.replaceAll('_', ' ')}</span>
                                <span className="text-muted-foreground">{formatAuditValue(field, audit.oldValues?.[field])}</span>
                                <span>{formatAuditValue(field, audit.newValues?.[field])}</span>
                            </div>
                        ))}
                    </div>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: t('account.timestamp'),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground whitespace-nowrap">
                    {new Date(row.original.created_at).toLocaleString()}
                </span>
            ),
        },
    ], [t, renderedAudits]);

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={`${localizedAccount.name} ${t('account.auditTrail')}`} />

            <div className="p-4">
                <div className="mx-auto max-w-5xl space-y-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <ClockCounterClockwiseIcon size={18} />
                                {t('account.auditTrail')}
                            </CardTitle>
                            <CardDescription>
                                {t('account.auditSummary', {
                                    count: localizedAccount.transactionsCount,
                                    balance: `${localizedAccount.currency} ${formatNumber(localizedAccount.balance, null)}`,
                                })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={columns}
                                data={renderedAudits}
                                loading={loading}
                                loadingMessage={t('common.loading')}
                                emptyMessage={t('account.noAudits')}
                                getRowId={(audit) => audit.id}
                            />

                            {renderedAudits.length > 0 && (
                                <LoadMore
                                    hasContent={true}
                                    hasMorePages={hasMorePages}
                                    loading={loading}
                                    onClick={() => setCurrentPage((page) => page + 1)}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Authenticated>
    );
}