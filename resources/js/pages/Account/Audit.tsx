import { useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowLeftIcon, ClockCounterClockwiseIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { getAccountAudits } from '@/Api/accounts';
import { formatNumber, getAppCurrency } from '@/Utils';
import LoadMore from '@/components/Global/LoadMore';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';

const hiddenDiffFields = ['id'];

export default function Audit({ auth, account }) {
    const { t } = useTranslation();
    const [audits, setAudits] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(true);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);

        getAccountAudits(account.id, currentPage)
            .then(({ data }) => {
                setAudits((previous) => currentPage === 1 ? data.audits : [...previous, ...data.audits]);
                setHasMorePages(data.paginatorInfo.hasMorePages);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [account.id, currentPage]);

    const header = (
        <div className="flex w-full items-center justify-between gap-3">
            <div>
                <h2>{account.name}</h2>
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
            return `${getAppCurrency()} ${formatNumber(value, null)}`;
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

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={`${account.name} ${t('account.auditTrail')}`} />

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
                                    count: account.transactionsCount,
                                    balance: `${getAppCurrency()} ${formatNumber(account.balance, null)}`,
                                })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScrollArea className="max-h-[70vh] pr-4">
                                <div className="space-y-3">
                                    {renderedAudits.map((audit) => (
                                        <Card key={audit.id} className="border-dashed">
                                            <CardContent className="space-y-4 p-4">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge variant={getActionVariant(audit.action)} className="capitalize">
                                                                {getActionLabel(audit.action)}
                                                            </Badge>
                                                            <span className="text-sm text-muted-foreground">
                                                                {t('account.performedBy', { name: audit.user?.name ?? audit.user?.email ?? t('account.emptyValue') })}
                                                            </span>
                                                        </div>
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
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {new Date(audit.created_at).toLocaleString()}
                                                    </p>
                                                </div>

                                                {audit.action === 'updated' && audit.changedFields.length > 0 && (
                                                    <div className="space-y-2 rounded-lg bg-muted/40 p-3">
                                                        <p className="text-sm font-medium">{t('account.changedFields')}</p>
                                                        <div className="grid gap-2">
                                                            {audit.changedFields.map((field) => (
                                                                <div key={`${audit.id}-${field}`} className="grid gap-1 text-sm sm:grid-cols-[1fr_1fr_1fr] sm:items-center">
                                                                    <span className="font-medium capitalize">{field.replaceAll('_', ' ')}</span>
                                                                    <span className="text-muted-foreground">{formatAuditValue(field, audit.oldValues?.[field])}</span>
                                                                    <span>{formatAuditValue(field, audit.newValues?.[field])}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </ScrollArea>

                            {renderedAudits.length > 0 && (
                                <LoadMore
                                    hasContent={true}
                                    hasMorePages={hasMorePages}
                                    loading={loading}
                                    onClick={() => setCurrentPage((page) => page + 1)}
                                />
                            )}

                            {!loading && renderedAudits.length === 0 && (
                                <p className="pb-2 text-center text-sm text-muted-foreground">{t('account.noAudits')}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Authenticated>
    );
}