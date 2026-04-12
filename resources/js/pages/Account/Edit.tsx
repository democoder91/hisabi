import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { deleteAccount, inviteAccountShare, revokeAccountShare, searchAccountShareableUsers, updateAccount, updateAccountSharePermission } from '@/Api/accounts';
import { getCurrencySettings } from '@/Api/settings';
import { ChevronsUpDownIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LongPressButton } from '@/components/ui/long-press-button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';

type CurrencyOption = {
    value: string;
    label: string;
};

type AccountTranslations = {
    en?: string;
    ar?: string;
};

type SharedUser = {
    id: number;
    name: string;
    email: string;
    permissionLevel: string;
};

type ShareableUser = {
    id: number;
    name: string | null;
    email: string;
};

type AccountRecord = {
    id: number;
    name: string;
    name_translations?: AccountTranslations;
    balance: number;
    currency: string;
    canManage: boolean;
    permissionLevel: string;
    sharedUsers?: SharedUser[];
};

type EditAccountProps = {
    account: AccountRecord | null;
    onClose: () => void;
    onDelete: (account: AccountRecord) => void;
    onUpdate: (account: AccountRecord) => void;
};

export default function Edit({ account, onClose, onDelete, onUpdate }: EditAccountProps) {
    const { t } = useTranslation();
    const [currentAccount, setCurrentAccount] = useState<AccountRecord | null>(null);
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState<'en' | 'ar'>('en');
    const [balance, setBalance] = useState('0');
    const [currency, setCurrency] = useState('');
    const [currencies, setCurrencies] = useState<CurrencyOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [shareSearch, setShareSearch] = useState('');
    const [selectedShareUser, setSelectedShareUser] = useState<ShareableUser | null>(null);
    const [shareResults, setShareResults] = useState<ShareableUser[]>([]);
    const [shareSearchLoading, setShareSearchLoading] = useState(false);
    const [shareSearchOpen, setShareSearchOpen] = useState(false);
    const [sharePermission, setSharePermission] = useState('view');
    const [shareLoading, setShareLoading] = useState(false);
    const [updatingShareId, setUpdatingShareId] = useState<number | null>(null);
    const shareSearchRequestId = useRef(0);
    const selectedCurrencyLabel = currencies.find((item) => item.value === currency)?.label ?? currency;

    useEffect(() => {
        if (!account) {
            return;
        }

        setCurrentAccount(account);
        setNameEn(account.name_translations?.en ?? account.name ?? '');
        setNameAr(account.name_translations?.ar ?? '');
        setNameLang('en');
        setBalance(String(account.balance ?? 0));
        setCurrency(account.currency ?? '');
        setShareSearch('');
        setSelectedShareUser(null);
        setShareResults([]);
        setShareSearchLoading(false);
        setShareSearchOpen(false);
        setSharePermission('view');
        setShareLoading(false);
        setLoading(false);
    }, [account]);

    useEffect(() => {
        if (!account) {
            return;
        }

        getCurrencySettings()
            .then((payload) => setCurrencies(payload.options.currencies))
            .catch(console.error);
    }, [account]);

    const canManage = currentAccount?.canManage ?? false;

    useEffect(() => {
        if (!currentAccount || !canManage) {
            setShareResults([]);
            setShareSearchLoading(false);
            setShareSearchOpen(false);

            return;
        }

        const query = shareSearch.trim();

        if (query === '' || selectedShareUser?.email === query) {
            shareSearchRequestId.current += 1;
            setShareResults([]);
            setShareSearchLoading(false);
            setShareSearchOpen(false);

            return;
        }

        const requestId = shareSearchRequestId.current + 1;
        shareSearchRequestId.current = requestId;
        setShareSearchLoading(true);
        setShareSearchOpen(true);

        const timeoutId = window.setTimeout(() => {
            searchAccountShareableUsers({
                id: currentAccount.id,
                query,
            })
                .then(({ data }) => {
                    if (shareSearchRequestId.current !== requestId) {
                        return;
                    }

                    setShareResults(data.users);
                })
                .catch((error) => {
                    if (shareSearchRequestId.current !== requestId) {
                        return;
                    }

                    setShareResults([]);
                    console.error(error);
                })
                .finally(() => {
                    if (shareSearchRequestId.current === requestId) {
                        setShareSearchLoading(false);
                    }
                });
        }, 300);

        return () => window.clearTimeout(timeoutId);
    }, [canManage, currentAccount, selectedShareUser, shareSearch]);

    const handleUpdate = () => {
        if (!currentAccount || loading || !nameEn.trim()) {
            return;
        }

        setLoading(true);

        updateAccount({
            id: currentAccount.id,
            name: {
                en: nameEn.trim(),
                ar: nameAr.trim(),
            },
            balance: Number(balance || 0),
            currency,
        })
            .then(({ data }) => {
                setCurrentAccount(data.updateAccount);
                onUpdate(data.updateAccount);
                onClose();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    const handleDelete = () => {
        if (!currentAccount) {
            return;
        }

        deleteAccount(currentAccount.id)
            .then(({ data }) => {
                onDelete(data.deleteAccount);
                onClose();
            })
            .catch(console.error);
    };

    const handleInvite = () => {
        if (!currentAccount || !selectedShareUser || shareLoading) {
            return;
        }

        setShareLoading(true);

        inviteAccountShare({
            id: currentAccount.id,
            email: selectedShareUser.email,
            permissionLevel: sharePermission,
        })
            .then(({ data }) => {
                setCurrentAccount(data.account);
                onUpdate(data.account);
                setShareSearch('');
                setSelectedShareUser(null);
                setShareResults([]);
                setShareSearchOpen(false);
            })
            .catch(console.error)
            .finally(() => setShareLoading(false));
    };

    const handleShareUserSelect = (user: ShareableUser) => {
        shareSearchRequestId.current += 1;
        setSelectedShareUser(user);
        setShareSearch(user.email);
        setShareResults([]);
        setShareSearchLoading(false);
        setShareSearchOpen(false);
    };

    const handleSharePermissionUpdate = (shareUserId: number, permissionLevel: string) => {
        if (!currentAccount) {
            return;
        }

        setUpdatingShareId(shareUserId);

        updateAccountSharePermission({
            id: currentAccount.id,
            shareUserId,
            permissionLevel,
        })
            .then(({ data }) => {
                setCurrentAccount(data.account);
                onUpdate(data.account);
            })
            .catch(console.error)
            .finally(() => setUpdatingShareId(null));
    };

    const handleShareRevoke = (shareUserId: number) => {
        if (!currentAccount) {
            return;
        }

        setUpdatingShareId(shareUserId);

        revokeAccountShare({
            id: currentAccount.id,
            shareUserId,
        })
            .then(({ data }) => {
                setCurrentAccount(data.account);
                onUpdate(data.account);
            })
            .catch(console.error)
            .finally(() => setUpdatingShareId(null));
    };

    return (
        <Dialog open={!!account} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('account.editTitle')}</DialogTitle>
                {currentAccount && (
                    <div className="space-y-4">
                        {!canManage && (
                            <div className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                {t('account.ownerOnlySettings')}
                            </div>
                        )}

                        <Tabs defaultValue="details">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="details">{t('account.detailsTab')}</TabsTrigger>
                                <TabsTrigger value="sharing" disabled={!canManage}>{t('account.sharingTab')}</TabsTrigger>
                            </TabsList>

                            <TabsContent value="details" className="space-y-4 pt-2">
                                <div>
                                    <Label>{t('account.name')}</Label>
                                    <Tabs value={nameLang} onValueChange={(value) => setNameLang(value as 'en' | 'ar')} className="mt-1">
                                        <TabsList className="grid w-full grid-cols-2">
                                            <TabsTrigger value="en">{t('account.lang_en')}</TabsTrigger>
                                            <TabsTrigger value="ar">{t('account.lang_ar')}</TabsTrigger>
                                        </TabsList>
                                        {nameLang === 'en' ? (
                                            <Input
                                                id="account-name-en"
                                                value={nameEn}
                                                className="mt-2"
                                                placeholder={t('account.namePlaceholder_en')}
                                                disabled={!canManage}
                                                onChange={(event) => setNameEn(event.target.value)}
                                                dir="ltr"
                                            />
                                        ) : (
                                            <Input
                                                id="account-name-ar"
                                                value={nameAr}
                                                className="mt-2"
                                                placeholder={t('account.namePlaceholder_ar')}
                                                disabled={!canManage}
                                                onChange={(event) => setNameAr(event.target.value)}
                                                dir="rtl"
                                            />
                                        )}
                                    </Tabs>
                                </div>
                                <div>
                                    <Label htmlFor="account-balance">{t('account.balance')}</Label>
                                    <Input
                                        id="account-balance"
                                        type="number"
                                        step="0.01"
                                        value={balance}
                                        disabled={!canManage}
                                        className="mt-1"
                                        onChange={(event) => setBalance(event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="account-currency">{t('settings.nav.currencies')}</Label>
                                    <Select value={currency} onValueChange={setCurrency} disabled={!canManage}>
                                        <SelectTrigger id="account-currency" className="mt-1">
                                            <SelectValue placeholder={t('settings.preferences.currencyPlaceholder')}>
                                                {selectedCurrencyLabel}
                                            </SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((item) => (
                                                <SelectItem key={item.value} value={item.value}>
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Badge variant="secondary" className="capitalize">
                                        {currentAccount.permissionLevel === 'owner' ? t('common.owner') : `${t('common.shared')} · ${t(`common.${currentAccount.permissionLevel}`)}`}
                                    </Badge>
                                    {!canManage && <span>{t('account.transactionsOnlyAccess')}</span>}
                                </div>

                                {canManage && (
                                    <div className="flex justify-end gap-2">
                                        <LongPressButton onLongPress={handleDelete}>{t('common.holdToDelete')}</LongPressButton>
                                        <Button onClick={handleUpdate} disabled={loading || !nameEn.trim() || !currency}>
                                            {t('common.update')}
                                        </Button>
                                    </div>
                                )}
                            </TabsContent>

                            <TabsContent value="sharing" className="space-y-4 pt-2">
                                <div className="grid gap-3 rounded-lg border p-3">
                                    <div>
                                        <Label htmlFor="share-email">{t('account.inviteByEmail')}</Label>
                                        <div className="relative mt-1">
                                            <Input
                                                id="share-email"
                                                type="text"
                                                value={shareSearch}
                                                autoComplete="off"
                                                className="pe-10"
                                                onFocus={() => {
                                                    if (shareSearch.trim() && !selectedShareUser) {
                                                        setShareSearchOpen(true);
                                                    }
                                                }}
                                                onBlur={() => {
                                                    window.setTimeout(() => setShareSearchOpen(false), 150);
                                                }}
                                                onChange={(event) => {
                                                    const value = event.target.value;

                                                    setShareSearch(value);
                                                    setShareSearchOpen(Boolean(value.trim()));

                                                    if (selectedShareUser?.email !== value) {
                                                        setSelectedShareUser(null);
                                                    }
                                                }}
                                                placeholder={t('account.searchUsersPlaceholder')}
                                            />
                                            <ChevronsUpDownIcon className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            {shareSearchOpen && !selectedShareUser && !!shareSearch.trim() ? (
                                                <div className="absolute z-50 mt-2 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md">
                                                    <Command shouldFilter={false}>
                                                        <CommandList>
                                                            {shareSearchLoading ? (
                                                                <div className="px-3 py-2 text-sm text-muted-foreground">{t('common.loading')}</div>
                                                            ) : shareResults.length > 0 ? (
                                                                <CommandGroup>
                                                                    {shareResults.map((user) => (
                                                                        <CommandItem
                                                                            key={user.id}
                                                                            value={user.email}
                                                                            onSelect={() => handleShareUserSelect(user)}
                                                                        >
                                                                            <div className="flex min-w-0 flex-col">
                                                                                <span className="truncate font-medium">{user.email}</span>
                                                                                {user.name ? (
                                                                                    <span className="truncate text-xs text-muted-foreground">{user.name}</span>
                                                                                ) : null}
                                                                            </div>
                                                                        </CommandItem>
                                                                    ))}
                                                                </CommandGroup>
                                                            ) : (
                                                                <CommandEmpty>{t('common.noResults')}</CommandEmpty>
                                                            )}
                                                        </CommandList>
                                                    </Command>
                                                </div>
                                            ) : null}
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {selectedShareUser
                                                ? selectedShareUser.name
                                                    ? `${selectedShareUser.name} · ${selectedShareUser.email}`
                                                    : selectedShareUser.email
                                                : t('account.searchUsersHelp')}
                                        </p>
                                    </div>
                                    <div>
                                        <Label htmlFor="share-permission">{t('account.permissionLevel')}</Label>
                                        <select
                                            id="share-permission"
                                            value={sharePermission}
                                            className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            onChange={(event) => setSharePermission(event.target.value)}
                                        >
                                            <option value="view">{t('common.view')}</option>
                                            <option value="edit">{t('common.edit')}</option>
                                        </select>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button onClick={handleInvite} disabled={shareLoading || !selectedShareUser}>
                                            {t('account.inviteUser')}
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {currentAccount.sharedUsers?.length ? currentAccount.sharedUsers.map((sharedUser) => (
                                        <div key={sharedUser.id} className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p className="font-medium">{sharedUser.name}</p>
                                                <p className="text-sm text-muted-foreground">{sharedUser.email}</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <select
                                                    value={sharedUser.permissionLevel}
                                                    className="flex h-9 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    disabled={updatingShareId === sharedUser.id}
                                                    onChange={(event) => handleSharePermissionUpdate(sharedUser.id, event.target.value)}
                                                >
                                                    <option value="view">{t('common.view')}</option>
                                                    <option value="edit">{t('common.edit')}</option>
                                                </select>
                                                <Button
                                                    variant="outline"
                                                    disabled={updatingShareId === sharedUser.id}
                                                    onClick={() => handleShareRevoke(sharedUser.id)}
                                                >
                                                    {t('account.revokeAccess')}
                                                </Button>
                                            </div>
                                        </div>
                                    )) : (
                                        <p className="text-sm text-muted-foreground">{t('account.noShares')}</p>
                                    )}
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}