import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ClockCountdownIcon, CreditCardIcon, LightningIcon, SparkleIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

interface BillingOption {
    slug: string;
    name: string;
    price: number;
    credits?: number;
    renews_in_days?: number;
}

interface BillingIndexProps {
    auth: {
        user: {
            available_credits: number;
            trial_ends_at: string | null;
            is_super?: boolean;
        };
    };
    creditPackages: BillingOption[];
    subscriptionPlans: BillingOption[];
    billingCurrency: string;
    hasActiveSubscription: boolean;
    checkoutAvailable: boolean;
}

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(amount);
}

function describeTrial(trialEndsAt: string | null, t: (key: string, options?: Record<string, unknown>) => string): string {
    if (!trialEndsAt) {
        return t('billing.noActiveTrial');
    }

    const end = new Date(trialEndsAt);
    const diffInMilliseconds = end.getTime() - Date.now();

    if (diffInMilliseconds <= 0) {
        return t('billing.trialExpired');
    }

    const diffInDays = Math.ceil(diffInMilliseconds / (1000 * 60 * 60 * 24));

    return t('billing.trialRemaining', { count: diffInDays });
}

export default function BillingIndex({
    auth,
    creditPackages,
    subscriptionPlans,
    billingCurrency,
    hasActiveSubscription,
    checkoutAvailable,
}: BillingIndexProps) {
    const { t } = useTranslation();
    const [pendingPurchase, setPendingPurchase] = useState<string | null>(null);

    const trialLabel = useMemo(
        () => describeTrial(auth?.user?.trial_ends_at ?? null, t),
        [auth?.user?.trial_ends_at, t],
    );

    const startCheckout = (routeName: string, slug: string) => {
        if (!checkoutAvailable) {
            return;
        }

        const purchaseKey = `${routeName}:${slug}`;

        setPendingPurchase(purchaseKey);
        router.post(route(routeName, slug), {}, {
            onFinish: () => setPendingPurchase(null),
        });
    };

    return (
        <Authenticated auth={auth}>
            <Head title={t('billing.title')} />

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-6">
                    <Card className="overflow-hidden border-primary/15 bg-gradient-to-br from-primary/8 via-background to-background">
                        <CardHeader className="gap-4 md:flex md:flex-row md:items-start md:justify-between">
                            <div className="space-y-3">
                                <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                    <SparkleIcon size={14} weight="fill" />
                                    {t('billing.creditRule')}
                                </div>
                                <div className="space-y-2">
                                    <CardTitle className="text-3xl">{t('billing.title')}</CardTitle>
                                    <CardDescription className="max-w-2xl text-sm leading-6">
                                        {t('billing.pageDescription')}
                                    </CardDescription>
                                </div>
                            </div>
                            {auth?.user?.is_super && (
                                <Button asChild variant="outline">
                                    <Link href={route('billing.manage')}>{t('billing.manageTitle')}</Link>
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-2xl border bg-background/80 p-5 shadow-xs">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm text-muted-foreground">{t('billing.availableCredits')}</p>
                                        <p className="mt-2 text-4xl font-semibold tracking-tight">{auth?.user?.available_credits ?? 0}</p>
                                    </div>
                                    <div className="rounded-2xl bg-primary/10 p-3 text-primary">
                                        <LightningIcon size={28} weight="duotone" />
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-2xl border bg-background/80 p-5 shadow-xs">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm text-muted-foreground">{t('billing.accessStatus')}</p>
                                        <p className="mt-2 text-xl font-semibold tracking-tight">
                                            {hasActiveSubscription ? t('billing.activeSubscription') : trialLabel}
                                        </p>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {hasActiveSubscription
                                                ? t('billing.activeSubscriptionDescription')
                                                : t('billing.expiredAccessDescription')}
                                        </p>
                                    </div>
                                    <div className="rounded-2xl bg-primary/10 p-3 text-primary">
                                        <ClockCountdownIcon size={28} weight="duotone" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {!checkoutAvailable && (
                        <Card className="border-amber-300/70 bg-amber-50/80">
                            <CardContent className="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary">{t('billing.manualAccessOnly')}</Badge>
                                        <p className="font-medium text-amber-950">{t('billing.checkoutUnavailableTitle')}</p>
                                    </div>
                                    <p className="max-w-3xl text-sm text-amber-900/80">
                                        {t('billing.checkoutUnavailableDescription')}
                                    </p>
                                </div>
                                {auth?.user?.is_super && (
                                    <Button asChild variant="outline">
                                        <Link href={route('billing.manage.users')}>{t('navigation.billingUserAccess')}</Link>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <section className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-semibold">{t('billing.subscriptionTiers')}</h2>
                            <p className="text-sm text-muted-foreground">
                                {t('billing.subscriptionTiersDescription')}
                            </p>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            {subscriptionPlans.map((plan) => {
                                const purchaseKey = `billing.checkout.subscription:${plan.slug}`;

                                return (
                                    <Card key={plan.slug} className="h-full border-primary/10">
                                        <CardHeader>
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <CardTitle>{plan.name}</CardTitle>
                                                    <CardDescription>
                                                        {t('billing.subscriptionCardDescription', { credits: plan.credits, days: plan.renews_in_days })}
                                                    </CardDescription>
                                                </div>
                                                <div className="rounded-2xl bg-primary/10 p-3 text-primary">
                                                    <CreditCardIcon size={24} weight="duotone" />
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div>
                                                <p className="text-3xl font-semibold tracking-tight">
                                                    {formatMoney(plan.price, billingCurrency)}
                                                </p>
                                                <p className="text-sm text-muted-foreground">{t('billing.perBillingCycle')}</p>
                                            </div>
                                            <ul className="space-y-2 text-sm text-muted-foreground">
                                                <li>{t('billing.creditsAddedOnPayment', { credits: plan.credits })}</li>
                                                <li>{t('billing.subscriptionBenefitAccess')}</li>
                                                <li>{checkoutAvailable ? t('billing.subscriptionBenefitHosted') : t('billing.manualAccessOnly')}</li>
                                                <li>{t('billing.subscriptionBenefitPairs')}</li>
                                            </ul>
                                        </CardContent>
                                        <CardFooter>
                                            <Button
                                                className="w-full"
                                                disabled={!checkoutAvailable || pendingPurchase === purchaseKey}
                                                onClick={() => startCheckout('billing.checkout.subscription', plan.slug)}
                                            >
                                                {!checkoutAvailable
                                                    ? t('billing.checkoutDisabledButton')
                                                    : pendingPurchase === purchaseKey
                                                        ? t('billing.redirecting')
                                                        : t('billing.choosePlan')}
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                );
                            })}
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-semibold">{t('billing.creditTopUps')}</h2>
                            <p className="text-sm text-muted-foreground">
                                {t('billing.creditTopUpsDescription')}
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {creditPackages.map((pkg) => {
                                const purchaseKey = `billing.checkout.credits:${pkg.slug}`;

                                return (
                                    <Card key={pkg.slug} className="h-full">
                                        <CardHeader>
                                            <CardTitle>{pkg.name}</CardTitle>
                                            <CardDescription>{t('billing.aiPromptsCount', { credits: pkg.credits })}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div>
                                                <p className="text-3xl font-semibold tracking-tight">
                                                    {formatMoney(pkg.price, billingCurrency)}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('billing.perCredit', { amount: formatMoney(pkg.price / Math.max(pkg.credits ?? 1, 1), billingCurrency) })}
                                                </p>
                                            </div>
                                            <div className="rounded-2xl border bg-muted/30 p-4 text-sm text-muted-foreground">
                                                {t('billing.topUpCardDescription')}
                                            </div>
                                        </CardContent>
                                        <CardFooter>
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                disabled={!checkoutAvailable || pendingPurchase === purchaseKey}
                                                onClick={() => startCheckout('billing.checkout.credits', pkg.slug)}
                                            >
                                                {!checkoutAvailable
                                                    ? t('billing.checkoutDisabledButton')
                                                    : pendingPurchase === purchaseKey
                                                        ? t('billing.redirecting')
                                                        : t('billing.buyCredits')}
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                );
                            })}
                        </div>
                    </section>
                </div>
            </div>
        </Authenticated>
    );
}