import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    ArrowRightIcon,
    ChartLineUpIcon,
    DeviceMobileCameraIcon,
    RobotIcon,
    ShieldCheckIcon,
} from '@phosphor-icons/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ApplicationLogo from '@/components/Global/ApplicationLogo';

export default function Landing() {
    const { t } = useTranslation();

    const featureCards = [
        {
            icon: DeviceMobileCameraIcon,
            title: t('landing.features.capture.title'),
            description: t('landing.features.capture.description'),
        },
        {
            icon: ChartLineUpIcon,
            title: t('landing.features.insights.title'),
            description: t('landing.features.insights.description'),
        },
        {
            icon: ShieldCheckIcon,
            title: t('landing.features.control.title'),
            description: t('landing.features.control.description'),
        },
        {
            icon: RobotIcon,
            title: t('landing.features.assistant.title'),
            description: t('landing.features.assistant.description'),
        },
    ];

    const workflowSteps = [
        {
            number: '01',
            title: t('landing.workflow.capture.title'),
            description: t('landing.workflow.capture.description'),
        },
        {
            number: '02',
            title: t('landing.workflow.review.title'),
            description: t('landing.workflow.review.description'),
        },
        {
            number: '03',
            title: t('landing.workflow.understand.title'),
            description: t('landing.workflow.understand.description'),
        },
    ];

    const highlights = [
        {
            value: t('landing.highlights.bilingual.value'),
            label: t('landing.highlights.bilingual.label'),
        },
        {
            value: t('landing.highlights.audit.value'),
            label: t('landing.highlights.audit.label'),
        },
        {
            value: t('landing.highlights.ai.value'),
            label: t('landing.highlights.ai.label'),
        },
    ];

    return (
        <>
            <Head title={t('landing.metaTitle')} />

            <div className="relative min-h-screen overflow-hidden bg-background">
                <div className="absolute inset-x-0 top-0 h-[34rem] bg-linear-to-b from-brand/15 via-highlight/20 to-transparent" />
                <div className="absolute -top-20 left-1/2 size-[30rem] -translate-x-1/2 rounded-full bg-brand/20 blur-3xl" />
                <div className="absolute right-0 top-48 size-72 rounded-full bg-highlight/30 blur-3xl" />

                <main className="relative">
                    <section className="px-4 py-6 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-7xl">
                            <nav className="flex flex-col gap-4 rounded-full border bg-background/80 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-3">
                                    <ApplicationLogo />
                                </div>

                                <div className="flex items-center gap-2 self-end sm:self-auto">
                                    <Button variant="ghost" asChild>
                                        <Link href={route('login')}>{t('auth.login')}</Link>
                                    </Button>
                                    <Button className="rounded-full" asChild>
                                        <Link href={route('register')}>{t('landing.primaryCta')}</Link>
                                    </Button>
                                </div>
                            </nav>
                        </div>
                    </section>

                    <section className="px-4 pb-14 pt-6 sm:px-6 lg:px-8 lg:pt-10">
                        <div className="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                            <div className="space-y-8">
                                <Badge variant="secondary" className="rounded-full px-4 py-1.5 text-xs uppercase tracking-[0.3em]">
                                    {t('landing.badge')}
                                </Badge>

                                <div className="space-y-5">
                                    <h1 className="max-w-3xl text-4xl font-black tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                                        {t('landing.headline')}
                                    </h1>
                                    <p className="max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                                        {t('landing.subheadline')}
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <Button size="lg" className="h-11 rounded-full px-6" asChild>
                                        <Link href={route('register')}>
                                            {t('landing.primaryCta')}
                                            <ArrowRightIcon size={16} />
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" className="h-11 rounded-full px-6" asChild>
                                        <Link href={route('login')}>{t('landing.secondaryCta')}</Link>
                                    </Button>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    {highlights.map((highlight) => (
                                        <Card key={highlight.label} className="border-white/40 bg-background/80 shadow-sm backdrop-blur">
                                            <CardContent className="p-4">
                                                <p className="text-2xl font-bold tracking-tight">{highlight.value}</p>
                                                <p className="mt-1 text-sm text-muted-foreground">{highlight.label}</p>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </div>

                            <div className="relative">
                                <div className="absolute -inset-4 rounded-[2rem] bg-linear-to-br from-brand/25 via-transparent to-highlight/35 blur-2xl" />
                                <Card className="relative overflow-hidden border-white/40 bg-background/85 shadow-2xl backdrop-blur">
                                    <CardContent className="p-3 sm:p-5">
                                        <div className="mb-4 flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold">{t('landing.previewTitle')}</p>
                                                <p className="text-xs text-muted-foreground">{t('landing.previewBody')}</p>
                                            </div>
                                            <Badge variant="secondary" className="rounded-full px-3 py-1">
                                                {t('landing.previewBadge')}
                                            </Badge>
                                        </div>

                                        <div className="overflow-hidden rounded-[1.5rem] border bg-muted/30">
                                            <img
                                                src="/images/showcase.png"
                                                alt={t('landing.previewAlt')}
                                                className="h-auto w-full object-cover"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </section>

                    <section className="px-4 py-12 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-2xl space-y-3">
                                <p className="text-sm font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                    {t('landing.featuresEyebrow')}
                                </p>
                                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">{t('landing.featuresTitle')}</h2>
                                <p className="text-muted-foreground">{t('landing.featuresSubtitle')}</p>
                            </div>

                            <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                {featureCards.map((feature) => {
                                    const Icon = feature.icon;

                                    return (
                                        <Card key={feature.title} className="border-white/30 bg-background/80 shadow-sm backdrop-blur transition-transform duration-300 hover:-translate-y-1">
                                            <CardContent className="space-y-4 p-5">
                                                <div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                                    <Icon size={24} weight="duotone" />
                                                </div>
                                                <div className="space-y-2">
                                                    <h3 className="text-lg font-semibold">{feature.title}</h3>
                                                    <p className="text-sm leading-6 text-muted-foreground">{feature.description}</p>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="px-4 py-12 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-7xl rounded-[2rem] border bg-muted/40 p-6 sm:p-8 lg:p-10">
                            <div className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
                                <div className="space-y-4">
                                    <p className="text-sm font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                        {t('landing.workflowEyebrow')}
                                    </p>
                                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">{t('landing.workflowTitle')}</h2>
                                    <p className="text-muted-foreground">{t('landing.workflowSubtitle')}</p>
                                </div>

                                <div className="grid gap-4">
                                    {workflowSteps.map((step) => (
                                        <Card key={step.number} className="bg-background/90 shadow-sm">
                                            <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start">
                                                <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground font-bold">
                                                    {step.number}
                                                </div>
                                                <div className="space-y-2">
                                                    <h3 className="text-lg font-semibold">{step.title}</h3>
                                                    <p className="text-sm leading-6 text-muted-foreground">{step.description}</p>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="px-4 pb-16 pt-12 sm:px-6 lg:px-8">
                        <div className="mx-auto flex max-w-7xl flex-col gap-5 rounded-[2rem] border bg-background/85 p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                            <div className="max-w-2xl space-y-2">
                                <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">{t('landing.finalTitle')}</h2>
                                <p className="text-muted-foreground">{t('landing.finalSubtitle')}</p>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button size="lg" className="rounded-full px-6" asChild>
                                    <Link href={route('register')}>{t('landing.primaryCta')}</Link>
                                </Button>
                                <Button size="lg" variant="outline" className="rounded-full px-6" asChild>
                                    <Link href={route('login')}>{t('auth.login')}</Link>
                                </Button>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}