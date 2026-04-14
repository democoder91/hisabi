import { type DragEvent, type FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { getCsrfToken } from '@/Api/common';
import { cn } from '@/lib/utils';

interface BillingOption {
    id: number;
    slug: string;
    name_en: string;
    name_ar: string;
    currency: string;
    price: number;
    credits: number;
    renews_in_days?: number | null;
}

interface BillingManageProps {
    auth: {
        user: {
            is_super?: boolean;
        };
    };
    billingCurrency: string;
    creditPackages: BillingOption[];
    subscriptionPlans: BillingOption[];
}

type ProductType = 'credits' | 'subscription';

interface ProductFormData {
    slug: string;
    name_en: string;
    name_ar: string;
    currency: string;
    price: string;
    credits: string;
    renews_in_days: string;
}

function buildProductFormData(type: ProductType, currency: string, product?: BillingOption): ProductFormData {
    return {
        slug: product?.slug ?? '',
        name_en: product?.name_en ?? '',
        name_ar: product?.name_ar ?? '',
        currency: product?.currency ?? currency,
        price: product ? String(product.price) : '',
        credits: product ? String(product.credits) : '',
        renews_in_days: type === 'subscription' ? String(product?.renews_in_days ?? 30) : '',
    };
}

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(amount);
}

function reorderItems<T extends { id: number }>(items: T[], draggedId: number, targetId: number): T[] {
    const draggedIndex = items.findIndex((item) => item.id === draggedId);
    const targetIndex = items.findIndex((item) => item.id === targetId);

    if (draggedIndex === -1 || targetIndex === -1 || draggedIndex === targetIndex) {
        return items;
    }

    const nextItems = [...items];
    const [draggedItem] = nextItems.splice(draggedIndex, 1);

    nextItems.splice(targetIndex, 0, draggedItem);

    return nextItems;
}

async function reorderBillingProducts(routeName: string, productIds: number[]): Promise<void> {
    const response = await fetch(route(routeName), {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ product_ids: productIds }),
    });

    if (!response.ok) {
        throw new Error(`Failed to reorder billing products: ${response.status}`);
    }
}

export default function BillingManage({ auth, billingCurrency, creditPackages, subscriptionPlans }: BillingManageProps) {
    const { t } = useTranslation();
    const [editorOpen, setEditorOpen] = useState(false);
    const [editorType, setEditorType] = useState<ProductType>('credits');
    const [editingProductId, setEditingProductId] = useState<number | null>(null);
    const [deletingKey, setDeletingKey] = useState<string | null>(null);
    const [orderedCreditPackages, setOrderedCreditPackages] = useState(creditPackages);
    const [orderedSubscriptionPlans, setOrderedSubscriptionPlans] = useState(subscriptionPlans);
    const [reorderingType, setReorderingType] = useState<ProductType | null>(null);
    const [dragState, setDragState] = useState<{ type: ProductType; productId: number } | null>(null);
    const [dragOverProductId, setDragOverProductId] = useState<number | null>(null);

    const {
        data: currencyData,
        setData: setCurrencyData,
        put: putCurrency,
        processing: currencyProcessing,
        errors: currencyErrors,
        wasSuccessful: currencyWasSuccessful,
    } = useForm({
        currency: billingCurrency,
    });

    const {
        data: productData,
        setData: setProductData,
        post,
        put,
        processing: productProcessing,
        errors: productErrors,
        wasSuccessful: productWasSuccessful,
        clearErrors: clearProductErrors,
    } = useForm<ProductFormData>(buildProductFormData('credits', billingCurrency));

    useEffect(() => {
        setOrderedCreditPackages(creditPackages);
    }, [creditPackages]);

    useEffect(() => {
        setOrderedSubscriptionPlans(subscriptionPlans);
    }, [subscriptionPlans]);

    const closeEditor = () => {
        setEditorOpen(false);
        setEditingProductId(null);
        clearProductErrors();
        setProductData(buildProductFormData('credits', currencyData.currency));
        setEditorType('credits');
    };

    const openCreateDialog = (type: ProductType) => {
        setEditorType(type);
        setEditingProductId(null);
        clearProductErrors();
        setProductData(buildProductFormData(type, currencyData.currency));
        setEditorOpen(true);
    };

    const openEditDialog = (type: ProductType, product: BillingOption) => {
        setEditorType(type);
        setEditingProductId(product.id);
        clearProductErrors();
        setProductData(buildProductFormData(type, currencyData.currency, product));
        setEditorOpen(true);
    };

    const submitCurrency = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        putCurrency(route('billing.manage.currency.update'), {
            preserveScroll: true,
        });
    };

    const submitProduct = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const routePrefix = editorType === 'credits'
            ? 'billing.manage.credit-packages'
            : 'billing.manage.subscription-plans';

        if (editingProductId === null) {
            post(route(`${routePrefix}.store`), {
                preserveScroll: true,
                onSuccess: () => closeEditor(),
            });

            return;
        }

        put(route(`${routePrefix}.update`, editingProductId), {
            preserveScroll: true,
            onSuccess: () => closeEditor(),
        });
    };

    const deleteProduct = (type: ProductType, product: BillingOption) => {
        if (!window.confirm(t('billing.deleteConfirmation', { name: product.name_en }))) {
            return;
        }

        const routeName = type === 'credits'
            ? 'billing.manage.credit-packages.destroy'
            : 'billing.manage.subscription-plans.destroy';
        const nextDeletingKey = `${type}:${product.id}`;

        setDeletingKey(nextDeletingKey);

        router.delete(route(routeName, product.id), {
            preserveScroll: true,
            onFinish: () => setDeletingKey(null),
        });
    };

    const persistOrder = async (type: ProductType, previousProducts: BillingOption[], nextProducts: BillingOption[]) => {
        const setProducts = type === 'credits' ? setOrderedCreditPackages : setOrderedSubscriptionPlans;
        const routeName = type === 'credits'
            ? 'billing.manage.credit-packages.reorder'
            : 'billing.manage.subscription-plans.reorder';

        setProducts(nextProducts);
        setReorderingType(type);

        try {
            await reorderBillingProducts(routeName, nextProducts.map((product) => product.id));
        } catch {
            setProducts(previousProducts);
        } finally {
            setReorderingType(null);
            setDragState(null);
            setDragOverProductId(null);
        }
    };

    const handleDragStart = (type: ProductType, productId: number) => (event: DragEvent<HTMLTableRowElement>) => {
        if (reorderingType !== null) {
            event.preventDefault();

            return;
        }

        setDragState({ type, productId });
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(productId));
    };

    const handleDragOver = (type: ProductType, targetId: number) => (event: DragEvent<HTMLTableRowElement>) => {
        if (!dragState || dragState.type !== type || dragState.productId === targetId) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        setDragOverProductId(targetId);
    };

    const handleDrop = (type: ProductType, targetId: number) => (event: DragEvent<HTMLTableRowElement>) => {
        event.preventDefault();

        if (!dragState || dragState.type !== type) {
            setDragOverProductId(null);

            return;
        }

        const currentProducts = type === 'credits' ? orderedCreditPackages : orderedSubscriptionPlans;
        const nextProducts = reorderItems(currentProducts, dragState.productId, targetId);

        if (nextProducts === currentProducts) {
            setDragState(null);
            setDragOverProductId(null);

            return;
        }

        persistOrder(type, currentProducts, nextProducts);
    };

    const handleDragEnd = () => {
        if (reorderingType === null) {
            setDragState(null);
            setDragOverProductId(null);
        }
    };

    const editorTitle = editingProductId === null
        ? (editorType === 'credits' ? t('billing.createTopUpTitle') : t('billing.createSubscriptionTitle'))
        : (editorType === 'credits' ? t('billing.editTopUpTitle') : t('billing.editSubscriptionTitle'));
    const editorSubmitLabel = editingProductId === null
        ? (editorType === 'credits' ? t('billing.createTopUp') : t('billing.createSubscription'))
        : t('billing.saveChanges');
    const successMessage = currencyWasSuccessful || productWasSuccessful
        ? t('billing.updated')
        : t('billing.changesApplyImmediately');

    return (
        <Authenticated auth={auth}>
            <Head title={t('billing.manageTitle')} />

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">{t('billing.manageTitle')}</h1>
                            <p className="text-sm text-muted-foreground">
                                {t('billing.manageDescription')}
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={route('billing.index')}>{t('billing.backToBilling')}</Link>
                        </Button>
                    </div>

                    <form className="space-y-4" onSubmit={submitCurrency}>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('billing.currencyTitle')}</CardTitle>
                                <CardDescription>{t('billing.currencyDescription')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                    <div className="space-y-2">
                                        <Label htmlFor="billing-currency">{t('billing.currencyCode')}</Label>
                                        <Input
                                            id="billing-currency"
                                            maxLength={3}
                                            value={currencyData.currency}
                                            onChange={(event) => setCurrencyData('currency', event.target.value.toUpperCase())}
                                        />
                                        {currencyErrors.currency && <p className="text-sm text-destructive">{currencyErrors.currency}</p>}
                                        <p className="text-sm text-muted-foreground">{t('billing.currencySyncHint')}</p>
                                    </div>
                                    <Button disabled={currencyProcessing} type="submit">
                                        {currencyProcessing ? t('billing.saving') : t('billing.saveCurrency')}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>

                    <Card>
                        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div className="space-y-1">
                                <CardTitle>{t('billing.topUpsTitle')}</CardTitle>
                                <CardDescription>{t('billing.topUpsDescription')}</CardDescription>
                            </div>
                            <Button onClick={() => openCreateDialog('credits')} type="button">
                                {t('billing.createTopUp')}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-sm text-muted-foreground">{t('billing.dragToReorder')}</p>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-28">{t('billing.order')}</TableHead>
                                        <TableHead>{t('billing.slug')}</TableHead>
                                        <TableHead>{t('billing.nameEn')}</TableHead>
                                        <TableHead>{t('billing.nameAr')}</TableHead>
                                        <TableHead>{t('billing.credits')}</TableHead>
                                        <TableHead>{t('billing.price')}</TableHead>
                                        <TableHead className="text-right">{t('billing.actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orderedCreditPackages.length === 0 && (
                                        <TableRow>
                                            <TableCell className="text-center text-muted-foreground" colSpan={7}>
                                                {t('billing.noTopUps')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {orderedCreditPackages.map((pkg, index) => (
                                        <TableRow
                                            key={pkg.id}
                                            className={cn(
                                                'cursor-grab',
                                                dragState?.type === 'credits' && dragState.productId === pkg.id && 'bg-muted/60 opacity-70',
                                                dragOverProductId === pkg.id && dragState?.productId !== pkg.id && 'bg-primary/5 ring-1 ring-primary/30',
                                                reorderingType === 'credits' && 'cursor-progress',
                                            )}
                                            draggable={reorderingType === null}
                                            onDragEnd={handleDragEnd}
                                            onDragOver={handleDragOver('credits', pkg.id)}
                                            onDragStart={handleDragStart('credits', pkg.id)}
                                            onDrop={handleDrop('credits', pkg.id)}
                                        >
                                            <TableCell className="w-28 text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">{index + 1}</span>
                                                    <span className="rounded border px-2 py-1 text-xs uppercase tracking-wide">{t('billing.dragHandle')}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium">{pkg.slug}</TableCell>
                                            <TableCell>{pkg.name_en}</TableCell>
                                            <TableCell>{pkg.name_ar}</TableCell>
                                            <TableCell>{pkg.credits}</TableCell>
                                            <TableCell>{formatMoney(pkg.price, pkg.currency)}</TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-2">
                                                    <Button onClick={() => openEditDialog('credits', pkg)} size="sm" type="button" variant="outline">
                                                        {t('billing.edit')}
                                                    </Button>
                                                    <Button
                                                        disabled={deletingKey === `credits:${pkg.id}`}
                                                        onClick={() => deleteProduct('credits', pkg)}
                                                        size="sm"
                                                        type="button"
                                                        variant="destructiveGhost"
                                                    >
                                                        {t('billing.delete')}
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div className="space-y-1">
                                <CardTitle>{t('billing.subscriptionsTitle')}</CardTitle>
                                <CardDescription>{t('billing.subscriptionsDescription')}</CardDescription>
                            </div>
                            <Button onClick={() => openCreateDialog('subscription')} type="button">
                                {t('billing.createSubscription')}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-sm text-muted-foreground">{t('billing.dragToReorder')}</p>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-28">{t('billing.order')}</TableHead>
                                        <TableHead>{t('billing.slug')}</TableHead>
                                        <TableHead>{t('billing.nameEn')}</TableHead>
                                        <TableHead>{t('billing.nameAr')}</TableHead>
                                        <TableHead>{t('billing.subscriptionCredits')}</TableHead>
                                        <TableHead>{t('billing.renewsInDays')}</TableHead>
                                        <TableHead>{t('billing.price')}</TableHead>
                                        <TableHead className="text-right">{t('billing.actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orderedSubscriptionPlans.length === 0 && (
                                        <TableRow>
                                            <TableCell className="text-center text-muted-foreground" colSpan={8}>
                                                {t('billing.noSubscriptions')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {orderedSubscriptionPlans.map((plan, index) => (
                                        <TableRow
                                            key={plan.id}
                                            className={cn(
                                                'cursor-grab',
                                                dragState?.type === 'subscription' && dragState.productId === plan.id && 'bg-muted/60 opacity-70',
                                                dragOverProductId === plan.id && dragState?.productId !== plan.id && 'bg-primary/5 ring-1 ring-primary/30',
                                                reorderingType === 'subscription' && 'cursor-progress',
                                            )}
                                            draggable={reorderingType === null}
                                            onDragEnd={handleDragEnd}
                                            onDragOver={handleDragOver('subscription', plan.id)}
                                            onDragStart={handleDragStart('subscription', plan.id)}
                                            onDrop={handleDrop('subscription', plan.id)}
                                        >
                                            <TableCell className="w-28 text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">{index + 1}</span>
                                                    <span className="rounded border px-2 py-1 text-xs uppercase tracking-wide">{t('billing.dragHandle')}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium">{plan.slug}</TableCell>
                                            <TableCell>{plan.name_en}</TableCell>
                                            <TableCell>{plan.name_ar}</TableCell>
                                            <TableCell>{plan.credits}</TableCell>
                                            <TableCell>{plan.renews_in_days ?? t('billing.noRenewal')}</TableCell>
                                            <TableCell>{formatMoney(plan.price, plan.currency)}</TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-2">
                                                    <Button onClick={() => openEditDialog('subscription', plan)} size="sm" type="button" variant="outline">
                                                        {t('billing.edit')}
                                                    </Button>
                                                    <Button
                                                        disabled={deletingKey === `subscription:${plan.id}`}
                                                        onClick={() => deleteProduct('subscription', plan)}
                                                        size="sm"
                                                        type="button"
                                                        variant="destructiveGhost"
                                                    >
                                                        {t('billing.delete')}
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <div className="text-sm text-muted-foreground">
                        {successMessage}
                    </div>
                </div>
            </div>

            <Dialog open={editorOpen} onOpenChange={(open) => {
                if (!open) {
                    closeEditor();
                }
            }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editorTitle}</DialogTitle>
                        <DialogDescription>{t('billing.productDialogDescription')}</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitProduct}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="product-slug">{t('billing.slug')}</Label>
                                <Input
                                    id="product-slug"
                                    value={productData.slug}
                                    onChange={(event) => setProductData('slug', event.target.value)}
                                />
                                {productErrors.slug && <p className="text-sm text-destructive">{productErrors.slug}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-currency">{t('billing.currencyCode')}</Label>
                                <Input
                                    id="product-currency"
                                    maxLength={3}
                                    value={productData.currency}
                                    onChange={(event) => setProductData('currency', event.target.value.toUpperCase())}
                                />
                                {productErrors.currency && <p className="text-sm text-destructive">{productErrors.currency}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-name-en">{t('billing.nameEn')}</Label>
                                <Input
                                    id="product-name-en"
                                    value={productData.name_en}
                                    onChange={(event) => setProductData('name_en', event.target.value)}
                                />
                                {productErrors.name_en && <p className="text-sm text-destructive">{productErrors.name_en}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-name-ar">{t('billing.nameAr')}</Label>
                                <Input
                                    id="product-name-ar"
                                    value={productData.name_ar}
                                    onChange={(event) => setProductData('name_ar', event.target.value)}
                                />
                                {productErrors.name_ar && <p className="text-sm text-destructive">{productErrors.name_ar}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-price">{t('billing.price')}</Label>
                                <Input
                                    id="product-price"
                                    min={1}
                                    type="number"
                                    value={productData.price}
                                    onChange={(event) => setProductData('price', event.target.value)}
                                />
                                {productErrors.price && <p className="text-sm text-destructive">{productErrors.price}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-credits">
                                    {editorType === 'subscription' ? t('billing.subscriptionCredits') : t('billing.credits')}
                                </Label>
                                <Input
                                    id="product-credits"
                                    min={1}
                                    type="number"
                                    value={productData.credits}
                                    onChange={(event) => setProductData('credits', event.target.value)}
                                />
                                {productErrors.credits && <p className="text-sm text-destructive">{productErrors.credits}</p>}
                            </div>
                            {editorType === 'subscription' && (
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="product-renews-in-days">{t('billing.renewsInDays')}</Label>
                                    <Input
                                        id="product-renews-in-days"
                                        min={1}
                                        type="number"
                                        value={productData.renews_in_days}
                                        onChange={(event) => setProductData('renews_in_days', event.target.value)}
                                    />
                                    {productErrors.renews_in_days && <p className="text-sm text-destructive">{productErrors.renews_in_days}</p>}
                                    <p className="text-sm text-muted-foreground">{t('billing.subscriptionCreditsHelp')}</p>
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button onClick={closeEditor} type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                            <Button disabled={productProcessing} type="submit">
                                {productProcessing ? t('billing.saving') : editorSubmitLabel}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Authenticated>
    );
}