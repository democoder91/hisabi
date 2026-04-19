import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRange } from 'react-day-picker';

import { getAccountStats } from '@/Api/metrics';
import { Card, CardContent } from '@/components/ui/card';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { formatNumber, getAppCurrency } from '@/Utils';

interface AccountStatsProps {
    dateRange: DateRange | undefined;
}

interface AccountStat {
    name: string;
    amount: number;
}

interface MostUsedAccountStat {
    name: string;
    count: number;
}

interface StatsState {
    mostUsedAccount: MostUsedAccountStat | null;
    highestSpendingAccount: AccountStat | null;
    highestIncomeAccount: AccountStat | null;
}

function AccountStats({ dateRange }: AccountStatsProps) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const [stats, setStats] = useState<StatsState>({
        mostUsedAccount: null,
        highestSpendingAccount: null,
        highestIncomeAccount: null,
    });
    const [currency, setCurrency] = useState(getAppCurrency());
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!dateRange?.from || !dateRange?.to) {
            return;
        }

        setLoading(true);
        getAccountStats(dateRange)
            .then(({ data }) => {
                setStats({
                    mostUsedAccount: data.mostUsedAccount,
                    highestSpendingAccount: data.highestSpendingAccount,
                    highestIncomeAccount: data.highestIncomeAccount,
                });
                setCurrency(data.currency ?? getAppCurrency());
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [activeLocale, dateRange]);

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card className="py-0">
                <CardContent className="px-6 py-4">
                    <div className="text-sm text-muted-foreground mb-2">{t('dashboard.highestIncomeSource')}</div>
                    {loading ? (
                        <div className="h-6 w-24 bg-muted animate-pulse rounded"></div>
                    ) : (
                        <div>
                            {stats.highestIncomeAccount ? (
                                <div>
                                    <div className="font-semibold">{stats.highestIncomeAccount.name}</div>
                                    <span className="text-muted-foreground">
                                        {currency} {formatNumber(stats.highestIncomeAccount.amount)}
                                    </span>
                                </div>
                            ) : '-'}
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card className="py-0">
                <CardContent className="px-6 py-4">
                    <div className="text-sm text-muted-foreground mb-2">{t('dashboard.highestExpenseDestination')}</div>
                    {loading ? (
                        <div className="h-6 w-24 bg-muted animate-pulse rounded"></div>
                    ) : (
                        <div>
                            {stats.highestSpendingAccount ? (
                                <div>
                                    <div className="font-semibold">{stats.highestSpendingAccount.name}</div>
                                    <span className="text-muted-foreground">
                                        {currency} {formatNumber(stats.highestSpendingAccount.amount)}
                                    </span>
                                </div>
                            ) : '-'}
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card className="py-0">
                <CardContent className="px-6 py-4">
                    <div className="text-sm text-muted-foreground mb-2">{t('dashboard.mostActiveAccount')}</div>
                    {loading ? (
                        <div className="h-6 w-20 bg-muted animate-pulse rounded"></div>
                    ) : (
                        <div>
                            {stats.mostUsedAccount ? (
                                <div>
                                    <div className="font-semibold">{stats.mostUsedAccount.name}</div>
                                    <span className="text-muted-foreground">
                                        {formatNumber(stats.mostUsedAccount.count)} {t('dashboard.transactionsLabel')}
                                    </span>
                                </div>
                            ) : '-'}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default AccountStats;