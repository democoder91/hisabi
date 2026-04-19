import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRange } from 'react-day-picker';
import { FunnelIcon } from '@phosphor-icons/react';

import Combobox from '@/components/Global/Combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { getAccountOptionLabel } from '@/Utils';

interface FilterProps {
    accounts: any[];
    onApply: (filters: any) => void;
    activeFilters: any;
}

export default function TransactionFilters({ accounts, onApply, activeFilters }: FilterProps) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);

    const formatSharedBy = (ownerName: string) => t('account.sharedBy', { name: ownerName });
    const getAccountLabel = (account: any) => getAccountOptionLabel(account, formatSharedBy);

    const handleAccountChange = (key: 'accountId' | 'fromAccountId' | 'toAccountId') => (account: any) => {
        onApply({
            ...activeFilters,
            [key]: account ? account.id : '',
        });
    };

    const getActiveFilterCount = () => {
        let count = 0;
        if (activeFilters.accountId) count++;
        if (activeFilters.fromAccountId) count++;
        if (activeFilters.toAccountId) count++;
        if (activeFilters.dateFrom && activeFilters.dateTo) count++;
        return count;
    };

    const handleDateChange = (dateRange: DateRange | undefined) => {
        if (dateRange?.from && dateRange?.to) {
            onApply({
                ...activeFilters,
                dateFrom: dateRange.from.toISOString().split('T')[0],
                dateTo: dateRange.to.toISOString().split('T')[0],
            });

            return;
        }

        if (!dateRange) {
            onApply({
                ...activeFilters,
                dateFrom: '',
                dateTo: '',
            });
        }
    };

    const getInitialDateRange = (): DateRange | undefined => {
        if (activeFilters.dateFrom && activeFilters.dateTo) {
            return {
                from: new Date(activeFilters.dateFrom),
                to: new Date(activeFilters.dateTo),
            };
        }

        return undefined;
    };

    const getSelectedAccount = (key: 'accountId' | 'fromAccountId' | 'toAccountId') => {
        if (!activeFilters[key]) {
            return undefined;
        }

        return accounts.find((account: any) => account.id == activeFilters[key]);
    };

    const filterCount = getActiveFilterCount();

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" className="relative gap-2">
                    <FunnelIcon className="h-4 w-4" />
                    {t('transaction.filters')}
                    {filterCount > 0 && (
                        <Badge variant="default" className="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">
                            {filterCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80" align="end">
                <div className="grid gap-4">
                    <Combobox
                        label={t('transaction.account')}
                        items={accounts}
                        initialSelectedItem={getSelectedAccount('accountId')}
                        onChange={handleAccountChange('accountId')}
                        placeholder={t('transaction.allAccounts')}
                        displayInputValue={(item) => item ? getAccountLabel(item) : ''}
                        displayOptionValue={(item) => item ? getAccountLabel(item) : ''}
                        getItemValue={(item) => item ? `${getAccountLabel(item)} ${item.id}` : ''}
                    />

                    <Combobox
                        label={t('transaction.sourceAccount')}
                        items={accounts}
                        initialSelectedItem={getSelectedAccount('fromAccountId')}
                        onChange={handleAccountChange('fromAccountId')}
                        placeholder={t('transaction.allAccounts')}
                        displayInputValue={(item) => item ? getAccountLabel(item) : ''}
                        displayOptionValue={(item) => item ? getAccountLabel(item) : ''}
                        getItemValue={(item) => item ? `${getAccountLabel(item)} ${item.id}` : ''}
                    />

                    <Combobox
                        label={t('transaction.destinationAccount')}
                        items={accounts}
                        initialSelectedItem={getSelectedAccount('toAccountId')}
                        onChange={handleAccountChange('toAccountId')}
                        placeholder={t('transaction.allAccounts')}
                        displayInputValue={(item) => item ? getAccountLabel(item) : ''}
                        displayOptionValue={(item) => item ? getAccountLabel(item) : ''}
                        getItemValue={(item) => item ? `${getAccountLabel(item)} ${item.id}` : ''}
                    />

                    <div className="grid gap-2">
                        <DatePickerWithRange
                            onDateChange={handleDateChange}
                            initialDate={getInitialDateRange()}
                        />
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
