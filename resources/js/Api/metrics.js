const formatDate = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split('T')[0];
};

const getMetric = async (endpoint, params = {}) => {
    const filteredParams = Object.fromEntries(
        Object.entries(params).filter(([_, v]) => v != null)
    );
    const searchParams = new URLSearchParams(filteredParams);
    const url = searchParams.toString()
        ? `/api/v1/metrics/${endpoint}?${searchParams}`
        : `/api/v1/metrics/${endpoint}`;

    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
};

export const getTotalIncome = (dateRange) => getMetric('total-income', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalExpenses = (dateRange) => getMetric('total-expenses', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalAssets = (dateRange) => getMetric('total-assets', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalLiabilities = (dateRange) => getMetric('total-liabilities', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalEquity = (dateRange) => getMetric('total-equity', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getNetWorth = () => getMetric('net-worth');

export const getNetWorthTrend = (dateRange) => getMetric('net-worth-trend', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalIncomeTrend = (dateRange) => getMetric('total-income-trend', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getTotalExpensesTrend = (dateRange) => getMetric('total-expenses-trend', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getAccountTrend = (dateRange, id) => getMetric('account-trend', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to), id });
export const getAccountDailyTrend = (dateRange, id) => getMetric('account-daily-trend', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to), id });

export const getExpensesByAccount = (dateRange) => getMetric('expenses-by-account', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getIncomeByAccount = (dateRange) => getMetric('income-by-account', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });

export const getTransactionsCount = (dateRange) => getMetric('transactions-count', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getHighestTransaction = (dateRange) => getMetric('highest-transaction', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getLowestTransaction = (dateRange) => getMetric('lowest-transaction', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getAverageTransaction = (dateRange) => getMetric('average-transaction', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });
export const getAccountStats = (dateRange) => getMetric('account-stats', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });

export const getCirclePack = (dateRange) => getMetric('circle-pack', { from: formatDate(dateRange?.from), to: formatDate(dateRange?.to) });

export const metricEndpoints = {
    totalIncome: getTotalIncome,
    totalExpenses: getTotalExpenses,
    totalAssets: getTotalAssets,
    totalLiabilities: getTotalLiabilities,
    totalEquity: getTotalEquity,
    netWorth: getNetWorth,
    netWorthTrend: getNetWorthTrend,
    totalIncomeTrend: getTotalIncomeTrend,
    totalExpensesTrend: getTotalExpensesTrend,
    totalPerAccountTrend: getAccountTrend,
    totalPerAccountDailyTrend: getAccountDailyTrend,
    expensesPerAccount: getExpensesByAccount,
    incomePerAccount: getIncomeByAccount,
    numberOfTransactions: getTransactionsCount,
    highestValueTransaction: getHighestTransaction,
    lowestValueTransaction: getLowestTransaction,
    averageValueTransaction: getAverageTransaction,
    accountStats: getAccountStats,
    financeVisualizationCirclePackMetric: getCirclePack,
};
