import { useState, useEffect, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { startOfMonth, endOfMonth } from 'date-fns';
import { DateRange } from 'react-day-picker';

import Authenticated from '@/Layouts/Authenticated';
import NoContent from '@/components/Global/NoContent';
import ValueMetric from '@/components/Domain/ValueMetric';
import TrendMetric from '@/components/Domain/TrendMetric';
import PartitionMetric from '@/components/Domain/PartitionMetric';
import CirclePackMetric from '@/components/Domain/CirclePackMetric';
import AccountStats from '@/components/Domain/AccountStats';
import SectionDivider from '@/components/Global/SectionDivider';
import Budgets from '@/components/Domain/Budgets';
import RecordTransactionButton from '@/components/Domain/RecordTransactionButton';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';
import { getAllAccounts } from '@/Api/accounts';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { withLocalizedNames } from '@/Utils';

export default function Dashboard({ auth, hasData }: any) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const [allAccounts, setAllAccounts] = useState<any[]>([]);
    const [refreshKey, setRefreshKey] = useState(0);
    const [dateRange, setDateRange] = useState<DateRange>({
        from: startOfMonth(new Date()),
        to: endOfMonth(new Date()),
    });

    useEffect(() => {
        getAllAccounts().then(({ data: accounts }) => {
            setAllAccounts(accounts.allAccounts);
        }).catch(console.error);
    }, []);

    const localizedAccounts = useMemo(() => withLocalizedNames(allAccounts, activeLocale), [allAccounts, activeLocale]);

    const handleDateChange = (newDateRange: DateRange | undefined) => {
        if (newDateRange?.from && newDateRange?.to) {
            setDateRange(newDateRange);
        }
    };

    const header = (
        <div className="flex items-center justify-between w-full">
            <h2>{t('dashboard.title')}</h2>
            <div className="flex items-center gap-2">
                <DatePickerWithRange
                    onDateChange={handleDateChange}
                    initialDate={dateRange}
                />
                <RecordTransactionButton
                    accounts={localizedAccounts}
                    onSuccess={() => setRefreshKey(prev => prev + 1)}
                />
            </div>
        </div>
    );

    const accountRelation = useMemo(() => ({
        data: localizedAccounts,
        display_using: 'name',
        foreign_key: 'id'
    }), [localizedAccounts]);

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('dashboard.title')} />

            <div className="py-4">
                <div className="max-w-7xl overflow-hidden mx-auto px-4 grid grid-cols-1 gap-4">

                    <Budgets key={`budgets-${refreshKey}`} />

                    {!hasData && <NoContent body={t('dashboard.noData')} />}

                    {hasData && (
                        <div className="grid grid-cols-1 gap-4">
                            {/* Net Worth - Full Width Trend */}
                            <div className="w-full">
                                <TrendMetric
                                    key={`netWorthTrend-${refreshKey}`}
                                    name={t('dashboard.netWorthOverTime')}
                                    metric="netWorthTrend"
                                    dateRange={dateRange}
                                />
                            </div>

                            <div className="w-full grid grid-cols-1 md:grid-cols-3 gap-4"
                            >
                                <ValueMetric
                                    key={`totalAssets-${refreshKey}`}
                                    name={t('dashboard.totalAssets')}
                                    metric="totalAssets"
                                    dateRange={dateRange}
                                />
                                <ValueMetric
                                    key={`totalLiabilities-${refreshKey}`}
                                    name={t('dashboard.totalLiabilities')}
                                    metric="totalLiabilities"
                                    dateRange={dateRange}
                                />
                                <ValueMetric
                                    key={`totalEquity-${refreshKey}`}
                                    name={t('dashboard.totalEquity')}
                                    metric="totalEquity"
                                    dateRange={dateRange}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <ValueMetric
                                    key={`totalIncome-${refreshKey}`}
                                    name={t('dashboard.totalIncome')}
                                    metric="totalIncome"
                                    dateRange={dateRange}
                                />
                                <ValueMetric
                                    key={`totalExpenses-${refreshKey}`}
                                    name={t('dashboard.totalExpenses')}
                                    metric="totalExpenses"
                                    dateRange={dateRange}
                                />

                                <TrendMetric
                                    key={`totalIncomeTrend-${refreshKey}`}
                                    name={t('dashboard.incomeOverTime')}
                                    metric="totalIncomeTrend"
                                    dateRange={dateRange}
                                />
                                <TrendMetric
                                    key={`totalExpensesTrend-${refreshKey}`}
                                    name={t('dashboard.spendingOverTime')}
                                    metric="totalExpensesTrend"
                                    dateRange={dateRange}
                                />
                            </div>


                            <SectionDivider title={t('dashboard.accountsAnalytics')} />

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="md:col-span-2">
                                    <AccountStats dateRange={dateRange} />
                                </div>
                                <PartitionMetric
                                    key={`incomePerAccount-${refreshKey}`}
                                    name={t('dashboard.incomeSources')}
                                    metric="incomePerAccount"
                                    show_currency={true}
                                    dateRange={dateRange}
                                />
                                <PartitionMetric
                                    key={`expensesPerAccount-${refreshKey}`}
                                    name={t('dashboard.spendingByAccount')}
                                    metric="expensesPerAccount"
                                    show_currency={true}
                                    dateRange={dateRange}
                                />

                                <TrendMetric
                                    key={`totalPerAccountTrend-${refreshKey}`}
                                    name={t('dashboard.overallTrendByAccount')}
                                    metric="totalPerAccountTrend"
                                    relation={accountRelation}
                                    dateRange={dateRange}
                                />
                                <TrendMetric
                                    key={`totalPerAccountDailyTrend-${refreshKey}`}
                                    name={t('dashboard.dailyTrendByAccount')}
                                    metric="totalPerAccountDailyTrend"
                                    relation={accountRelation}
                                    dateRange={dateRange}
                                    defaultToCurrentYear={false}
                                />
                            </div>

                            <SectionDivider title={t('dashboard.financeVisualization')} />

                            <div className="w-full">
                                <CirclePackMetric
                                    key={`financeVisualizationCirclePackMetric-${refreshKey}`}
                                    name={t('dashboard.financeVisualization')}
                                    metric="financeVisualizationCirclePackMetric"
                                    dateRange={dateRange}
                                />
                            </div>

                        </div>
                    )}
                </div>
            </div>
        </Authenticated>
    );
}
