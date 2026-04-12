export type BudgetTranslations = {
    en?: string;
    ar?: string;
};

export type BudgetCategory = {
    id: number;
    name: string;
    name_translations?: BudgetTranslations;
    color?: string;
    icon?: string;
};

export type BudgetRecord = {
    id: number;
    user_id: number;
    name: string;
    name_translations?: BudgetTranslations;
    amount: number;
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
    categories: BudgetCategory[];
};

export const budgetRecurrenceOptions: BudgetRecord['reoccurrence'][] = ['CUSTOM', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];