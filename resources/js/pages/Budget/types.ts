export type BudgetTranslations = {
    en?: string;
    ar?: string;
};

export type BudgetAccount = {
    id: number;
    name: string;
    name_translations?: BudgetTranslations;
    type?: string;
    currency?: string;
    ownerUserId?: number;
    ownerName?: string | null;
};

export type BudgetRecord = {
    id: number;
    user_id: number;
    name: string;
    name_translations?: BudgetTranslations;
    amount: number;
    currency: string;
    start_at: string;
    end_at: string | null;
    saving: boolean;
    period: number;
    reoccurrence: 'CUSTOM' | 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY';
    total_spent_percentage: number;
    start_at_date: string;
    end_at_date: string;
    remaining_to_spend: number;
    total_margin_per_day: number;
    remaining_days: number;
    elapsed_days_percentage: number;
    is_saving: boolean;
    total_transactions_amount: number;
    accounts: BudgetAccount[];
};

export const budgetRecurrenceOptions: BudgetRecord['reoccurrence'][] = ['CUSTOM', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];