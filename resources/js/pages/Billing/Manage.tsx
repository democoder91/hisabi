import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface BillingOption {
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

export default function BillingManage({ auth, billingCurrency, creditPackages, subscriptionPlans }: BillingManageProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        currency: billingCurrency,
        credit_packages: creditPackages,
        subscription_plans: subscriptionPlans,
    });

    const updateCreditPackage = (index: number, field: 'name_en' | 'name_ar' | 'price' | 'credits', value: string) => {
        setData('credit_packages', data.credit_packages.map((pkg, currentIndex) => {
            if (currentIndex !== index) {
                return pkg;
            }

            return {
                ...pkg,
                [field]: field === 'name_en' || field === 'name_ar' ? value : Number(value),
            };
        }));
    };

    const updateSubscriptionPlan = (index: number, field: 'name_en' | 'name_ar' | 'price' | 'credits' | 'renews_in_days', value: string) => {
        setData('subscription_plans', data.subscription_plans.map((plan, currentIndex) => {
            if (currentIndex !== index) {
                return plan;
            }

            return {
                ...plan,
                [field]: field === 'name_en' || field === 'name_ar' ? value : Number(value),
            };
        }));
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(route('billing.manage.update'));
    };

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

                    <form className="space-y-6" onSubmit={submit}>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('billing.currencyTitle')}</CardTitle>
                                <CardDescription>{t('billing.currencyDescription')}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Label htmlFor="billing-currency">{t('billing.currencyCode')}</Label>
                                <Input
                                    id="billing-currency"
                                    maxLength={3}
                                    value={data.currency}
                                    onChange={(event) => setData('currency', event.target.value.toUpperCase())}
                                />
                                {errors.currency && <p className="text-sm text-destructive">{errors.currency}</p>}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('billing.topUpsTitle')}</CardTitle>
                                <CardDescription>
                                    {t('billing.topUpsDescription')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {errors.credit_packages && <p className="text-sm text-destructive">{errors.credit_packages}</p>}

                                {data.credit_packages.map((pkg, index) => (
                                    <div key={pkg.slug} className="grid gap-4 rounded-2xl border p-4 md:grid-cols-4">
                                        <div className="space-y-2">
                                            <Label htmlFor={`credit-name-en-${pkg.slug}`}>{t('billing.nameEn')}</Label>
                                            <Input
                                                id={`credit-name-en-${pkg.slug}`}
                                                value={pkg.name_en}
                                                onChange={(event) => updateCreditPackage(index, 'name_en', event.target.value)}
                                            />
                                            {errors[`credit_packages.${index}.name_en`] && <p className="text-sm text-destructive">{errors[`credit_packages.${index}.name_en`]}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`credit-name-ar-${pkg.slug}`}>{t('billing.nameAr')}</Label>
                                            <Input
                                                id={`credit-name-ar-${pkg.slug}`}
                                                value={pkg.name_ar}
                                                onChange={(event) => updateCreditPackage(index, 'name_ar', event.target.value)}
                                            />
                                            {errors[`credit_packages.${index}.name_ar`] && <p className="text-sm text-destructive">{errors[`credit_packages.${index}.name_ar`]}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`credit-amount-${pkg.slug}`}>{t('billing.credits')}</Label>
                                            <Input
                                                id={`credit-amount-${pkg.slug}`}
                                                min={1}
                                                type="number"
                                                value={pkg.credits}
                                                onChange={(event) => updateCreditPackage(index, 'credits', event.target.value)}
                                            />
                                            {errors[`credit_packages.${index}.credits`] && <p className="text-sm text-destructive">{errors[`credit_packages.${index}.credits`]}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`credit-price-${pkg.slug}`}>{t('billing.price')}</Label>
                                            <Input
                                                id={`credit-price-${pkg.slug}`}
                                                min={1}
                                                type="number"
                                                value={pkg.price}
                                                onChange={(event) => updateCreditPackage(index, 'price', event.target.value)}
                                            />
                                            {errors[`credit_packages.${index}.price`] && <p className="text-sm text-destructive">{errors[`credit_packages.${index}.price`]}</p>}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('billing.subscriptionsTitle')}</CardTitle>
                                <CardDescription>
                                    {t('billing.subscriptionsDescription')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {errors.subscription_plans && <p className="text-sm text-destructive">{errors.subscription_plans}</p>}

                                {data.subscription_plans.map((plan, index) => {
                                    return (
                                        <div key={plan.slug} className="grid gap-4 rounded-2xl border p-4 md:grid-cols-5">
                                            <div className="space-y-2">
                                                <Label htmlFor={`subscription-name-en-${plan.slug}`}>{t('billing.nameEn')}</Label>
                                                <Input
                                                    id={`subscription-name-en-${plan.slug}`}
                                                    value={plan.name_en}
                                                    onChange={(event) => updateSubscriptionPlan(index, 'name_en', event.target.value)}
                                                />
                                                {errors[`subscription_plans.${index}.name_en`] && <p className="text-sm text-destructive">{errors[`subscription_plans.${index}.name_en`]}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`subscription-name-ar-${plan.slug}`}>{t('billing.nameAr')}</Label>
                                                <Input
                                                    id={`subscription-name-ar-${plan.slug}`}
                                                    value={plan.name_ar}
                                                    onChange={(event) => updateSubscriptionPlan(index, 'name_ar', event.target.value)}
                                                />
                                                {errors[`subscription_plans.${index}.name_ar`] && <p className="text-sm text-destructive">{errors[`subscription_plans.${index}.name_ar`]}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`subscription-price-${plan.slug}`}>{t('billing.price')}</Label>
                                                <Input
                                                    id={`subscription-price-${plan.slug}`}
                                                    min={1}
                                                    type="number"
                                                    value={plan.price}
                                                    onChange={(event) => updateSubscriptionPlan(index, 'price', event.target.value)}
                                                />
                                                {errors[`subscription_plans.${index}.price`] && <p className="text-sm text-destructive">{errors[`subscription_plans.${index}.price`]}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`subscription-credits-${plan.slug}`}>{t('billing.subscriptionCredits')}</Label>
                                                <Input
                                                    id={`subscription-credits-${plan.slug}`}
                                                    min={1}
                                                    type="number"
                                                    value={plan.credits}
                                                    onChange={(event) => updateSubscriptionPlan(index, 'credits', event.target.value)}
                                                />
                                                {errors[`subscription_plans.${index}.credits`] && <p className="text-sm text-destructive">{errors[`subscription_plans.${index}.credits`]}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`subscription-renews-${plan.slug}`}>{t('billing.renewsInDays')}</Label>
                                                <Input
                                                    id={`subscription-renews-${plan.slug}`}
                                                    min={1}
                                                    type="number"
                                                    value={plan.renews_in_days ?? 30}
                                                    onChange={(event) => updateSubscriptionPlan(index, 'renews_in_days', event.target.value)}
                                                />
                                                {errors[`subscription_plans.${index}.renews_in_days`] && <p className="text-sm text-destructive">{errors[`subscription_plans.${index}.renews_in_days`]}</p>}
                                            </div>
                                            <div className="md:col-span-5 rounded-2xl bg-muted/40 p-3 text-sm text-muted-foreground">
                                                {t('billing.subscriptionCreditsHelp')}
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-between gap-3">
                            <div className="text-sm text-muted-foreground">
                                {wasSuccessful ? t('billing.updated') : t('billing.changesApplyImmediately')}
                            </div>
                            <Button disabled={processing} type="submit">
                                {processing ? t('billing.saving') : t('billing.saveBillingSettings')}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Authenticated>
    );
}