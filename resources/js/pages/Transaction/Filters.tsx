import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FunnelIcon } from '@phosphor-icons/react';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';
import { DateRange } from 'react-day-picker';
import Combobox from '@/components/Global/Combobox';
import { getCategoryOptionLabel } from '@/Utils';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface FilterProps {
    categories: any[];
    onApply: (filters: any) => void;
    activeFilters: any;
}

export default function TransactionFilters({ categories, onApply, activeFilters }: FilterProps) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);

    const handleCategoryChange = (category: any) => {
        const updatedFilters = { ...activeFilters, categoryId: category ? category.id : '' };
        onApply(updatedFilters);
    };

    const handleTransactionTypeChange = (value: string) => {
        const updatedFilters = {
            ...activeFilters,
            transactionType: value === 'ALL' ? '' : value,
        };

        onApply(updatedFilters);
    };

    const getActiveFilterCount = () => {
        let count = 0;
        if (activeFilters.categoryId) count++;
        if (activeFilters.transactionType) count++;
        if (activeFilters.dateFrom && activeFilters.dateTo) count++;
        return count;
    };

    const filterCount = getActiveFilterCount();

    const handleDateChange = (dateRange: DateRange | undefined) => {
        if (dateRange?.from && dateRange?.to) {
            const updatedFilters = {
                ...activeFilters,
                dateFrom: dateRange.from.toISOString().split('T')[0],
                dateTo: dateRange.to.toISOString().split('T')[0],
            };
            onApply(updatedFilters);
        } else if (!dateRange) {
            const updatedFilters = {
                ...activeFilters,
                dateFrom: '',
                dateTo: '',
            };
            onApply(updatedFilters);
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

    const getSelectedCategory = () => {
        if (!activeFilters.categoryId) return undefined;
        return categories.find((c: any) => c.id == activeFilters.categoryId);
    };

    const selectedTransactionType = activeFilters.transactionType || 'ALL';
    const getCategoryLabel = (category: any) => getCategoryOptionLabel(category, categories);

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" className="gap-2 relative">
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
                        label={t('transaction.category')}
                        items={categories}
                        initialSelectedItem={getSelectedCategory()}
                        onChange={handleCategoryChange}
                        displayInputValue={(item) => item ? getCategoryLabel(item) : ''}
                        displayOptionValue={(item) => item ? getCategoryLabel(item) : ''}
                        getItemValue={(item) => item ? `${getCategoryLabel(item)} ${item.id}` : ''}
                    />

                    <div className="grid gap-2">
                        <label className="text-sm font-medium">{t('transaction.type')}</label>
                        <Select value={selectedTransactionType} onValueChange={handleTransactionTypeChange}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('transaction.allTypes')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ALL">{t('transaction.allTypes')}</SelectItem>
                                <SelectItem value="DEBIT">{t('transaction.debit')}</SelectItem>
                                <SelectItem value="CREDIT">{t('transaction.credit')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Date Filter */}
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

