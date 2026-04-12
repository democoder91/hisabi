import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { deleteAccount, inviteAccountShare, revokeAccountShare, updateAccount, updateAccountSharePermission } from '@/Api/accounts';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LongPressButton } from '@/components/ui/long-press-button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';

export default function Edit({ account, onClose, onDelete, onUpdate }) {
    const { t } = useTranslation();
    const [currentAccount, setCurrentAccount] = useState(null);
    const [name, setName] = useState('');
    const [balance, setBalance] = useState('0');
    const [loading, setLoading] = useState(false);
    const [shareEmail, setShareEmail] = useState('');
    const [sharePermission, setSharePermission] = useState('view');
    const [shareLoading, setShareLoading] = useState(false);
    const [updatingShareId, setUpdatingShareId] = useState<number | null>(null);

    useEffect(() => {
        if (!account) {
            return;
        }

        setCurrentAccount(account);
        setName(account.name);
        setBalance(String(account.balance ?? 0));
        setShareEmail('');
        setSharePermission('view');
        setLoading(false);
    }, [account]);

    const canManage = currentAccount?.canManage ?? false;

    const handleUpdate = () => {
        if (!currentAccount || loading || !name.trim()) {
            return;
        }

        setLoading(true);

        updateAccount({
            id: currentAccount.id,
            name: name.trim(),
            balance: Number(balance || 0),
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
        if (!currentAccount || !shareEmail.trim() || shareLoading) {
            return;
        }

        setShareLoading(true);

        inviteAccountShare({
            id: currentAccount.id,
            email: shareEmail.trim(),
            permissionLevel: sharePermission,
        })
            .then(({ data }) => {
                setCurrentAccount(data.account);
                onUpdate(data.account);
                setShareEmail('');
            })
            .catch(console.error)
            .finally(() => setShareLoading(false));
    };

    const handleSharePermissionUpdate = (shareUserId, permissionLevel) => {
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

    const handleShareRevoke = (shareUserId) => {
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
                                    <Label htmlFor="account-name">{t('account.name')}</Label>
                                    <Input
                                        id="account-name"
                                        value={name}
                                        className="mt-1"
                                        disabled={!canManage}
                                        onChange={(event) => setName(event.target.value)}
                                    />
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
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Badge variant="secondary" className="capitalize">
                                        {currentAccount.permissionLevel === 'owner' ? t('common.owner') : `${t('common.shared')} · ${t(`common.${currentAccount.permissionLevel}`)}`}
                                    </Badge>
                                    {!canManage && <span>{t('account.transactionsOnlyAccess')}</span>}
                                </div>

                                {canManage && (
                                    <div className="flex justify-end gap-2">
                                        <LongPressButton onLongPress={handleDelete}>{t('common.holdToDelete')}</LongPressButton>
                                        <Button onClick={handleUpdate} disabled={loading || !name.trim()}>
                                            {t('common.update')}
                                        </Button>
                                    </div>
                                )}
                            </TabsContent>

                            <TabsContent value="sharing" className="space-y-4 pt-2">
                                <div className="grid gap-3 rounded-lg border p-3">
                                    <div>
                                        <Label htmlFor="share-email">{t('account.inviteByEmail')}</Label>
                                        <Input
                                            id="share-email"
                                            type="email"
                                            value={shareEmail}
                                            className="mt-1"
                                            onChange={(event) => setShareEmail(event.target.value)}
                                            placeholder={t('account.invitePlaceholder')}
                                        />
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
                                        <Button onClick={handleInvite} disabled={shareLoading || !shareEmail.trim()}>
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