import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckIcon, ChevronDownIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

import { BudgetAccount } from './types';

type CategoryMultiSelectProps = {
    accounts: BudgetAccount[];
    selectedAccountIds: number[];
    onChange: (value: number[]) => void;
};

export default function CategoryMultiSelect({
    accounts,
    selectedAccountIds,
    onChange,
}: CategoryMultiSelectProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const selectedAccounts = useMemo(() => accounts.filter((account) => selectedAccountIds.includes(account.id)), [accounts, selectedAccountIds]);

    const toggleAccount = (accountId: number) => {
        onChange(selectedAccountIds.includes(accountId)
            ? selectedAccountIds.filter((id) => id !== accountId)
            : [...selectedAccountIds, accountId]);
    };

    return (
        <div>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button variant="outline" type="button" role="combobox" aria-expanded={open} className="mt-1 flex min-h-10 h-auto w-full items-center justify-between py-2 font-normal">
                        {selectedAccounts.length > 0 ? (
                            <span className="flex min-w-0 flex-wrap gap-1">
                                {selectedAccounts.slice(0, 2).map((account) => (
                                    <Badge key={account.id} variant="secondary" className="max-w-full truncate">
                                        {account.name}
                                    </Badge>
                                ))}
                                {selectedAccounts.length > 2 ? (
                                    <Badge variant="outline">+{selectedAccounts.length - 2}</Badge>
                                ) : null}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">{t('budget.selectAccounts')}</span>
                        )}
                        <ChevronDownIcon className="size-4 text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                    <Command>
                        <CommandInput placeholder={t('account.searchAccounts')} />
                        <CommandList>
                            <CommandEmpty>{t('common.noResults')}</CommandEmpty>
                            <CommandGroup>
                                {accounts.map((account) => {
                                    const isSelected = selectedAccountIds.includes(account.id);

                                    return (
                                        <CommandItem
                                            key={account.id}
                                            value={account.name}
                                            onSelect={() => toggleAccount(account.id)}
                                            className="justify-between"
                                        >
                                            <span className="truncate">{account.name}</span>
                                            <CheckIcon className={isSelected ? 'size-4 opacity-100' : 'size-4 opacity-0'} />
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {selectedAccounts.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-1">
                    {selectedAccounts.map((account) => (
                        <Badge key={account.id} variant="outline">
                            {account.name}
                        </Badge>
                    ))}
                </div>
            ) : null}
        </div>
    );
}