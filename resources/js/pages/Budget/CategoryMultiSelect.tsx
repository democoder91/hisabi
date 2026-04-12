import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckIcon, ChevronDownIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

import { BudgetCategory } from './types';

type CategoryMultiSelectProps = {
    categories: BudgetCategory[];
    selectedCategoryIds: number[];
    onChange: (value: number[]) => void;
};

export default function CategoryMultiSelect({
    categories,
    selectedCategoryIds,
    onChange,
}: CategoryMultiSelectProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const selectedCategories = useMemo(() => categories.filter((category) => selectedCategoryIds.includes(category.id)), [categories, selectedCategoryIds]);

    const toggleCategory = (categoryId: number) => {
        onChange(selectedCategoryIds.includes(categoryId)
            ? selectedCategoryIds.filter((id) => id !== categoryId)
            : [...selectedCategoryIds, categoryId]);
    };

    return (
        <div>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button variant="outline" type="button" role="combobox" aria-expanded={open} className="mt-1 flex min-h-10 h-auto w-full items-center justify-between py-2 font-normal">
                        {selectedCategories.length > 0 ? (
                            <span className="flex min-w-0 flex-wrap gap-1">
                                {selectedCategories.slice(0, 2).map((category) => (
                                    <Badge key={category.id} variant="secondary" className="max-w-full truncate">
                                        {category.name}
                                    </Badge>
                                ))}
                                {selectedCategories.length > 2 ? (
                                    <Badge variant="outline">+{selectedCategories.length - 2}</Badge>
                                ) : null}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">{t('budget.selectCategories')}</span>
                        )}
                        <ChevronDownIcon className="size-4 text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                    <Command>
                        <CommandInput placeholder={t('category.searchCategories')} />
                        <CommandList>
                            <CommandEmpty>{t('common.noResults')}</CommandEmpty>
                            <CommandGroup>
                                {categories.map((category) => {
                                    const isSelected = selectedCategoryIds.includes(category.id);

                                    return (
                                        <CommandItem
                                            key={category.id}
                                            value={category.name}
                                            onSelect={() => toggleCategory(category.id)}
                                            className="justify-between"
                                        >
                                            <span className="truncate">{category.name}</span>
                                            <CheckIcon className={isSelected ? 'size-4 opacity-100' : 'size-4 opacity-0'} />
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {selectedCategories.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-1">
                    {selectedCategories.map((category) => (
                        <Badge key={category.id} variant="outline">
                            {category.name}
                        </Badge>
                    ))}
                </div>
            ) : null}
        </div>
    );
}