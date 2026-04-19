import { Head } from '@inertiajs/react';
import { BookOpenTextIcon, ChatCircleTextIcon, FlowArrowIcon, WalletIcon } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

import HisabiAIChat from '@/components/Global/HisabiAIChat';
import Authenticated from '@/Layouts/Authenticated';

interface GuidePageProps {
    auth: unknown;
}

export default function GuideIndex({ auth }: GuidePageProps) {
    const { t } = useTranslation();

    const useCases = [
        {
            icon: WalletIcon,
            title: t('guide.useCases.accounts.title'),
            description: t('guide.useCases.accounts.description'),
        },
        {
            icon: FlowArrowIcon,
            title: t('guide.useCases.transactions.title'),
            description: t('guide.useCases.transactions.description'),
        },
        {
            icon: BookOpenTextIcon,
            title: t('guide.useCases.budgets.title'),
            description: t('guide.useCases.budgets.description'),
        },
        {
            icon: ChatCircleTextIcon,
            title: t('guide.useCases.ai.title'),
            description: t('guide.useCases.ai.description'),
        },
    ];

    return (
        <Authenticated
            auth={auth}
            header={
                <div className="flex flex-col">
                    <h1 className="text-lg font-semibold">{t('guide.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('guide.description')}</p>
                </div>
            }
        >
            <Head title={t('guide.title')} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:px-8">
                <section className="overflow-hidden rounded-3xl border border-border/60 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.16),_transparent_35%),linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-6 shadow-sm dark:bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.14),_transparent_35%),linear-gradient(135deg,rgba(15,23,42,0.96),rgba(15,23,42,0.88))]">
                    <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-start">
                        <div className="space-y-4">
                            <span className="inline-flex rounded-full border border-amber-400/30 bg-amber-500/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] text-amber-700 dark:text-amber-200">
                                {t('guide.eyebrow')}
                            </span>
                            <div className="space-y-3">
                                <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                                    {t('guide.heroTitle')}
                                </h2>
                                <p className="max-w-2xl text-base leading-7 text-muted-foreground">
                                    {t('guide.heroDescription')}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-border/60 bg-background/80 p-5 backdrop-blur">
                            <h3 className="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                {t('guide.quickStartTitle')}
                            </h3>
                            <div className="mt-4 grid gap-3">
                                {useCases.map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <div key={item.title} className="rounded-2xl border border-border/50 bg-card/80 p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-700 dark:text-amber-200">
                                                    <Icon size={22} weight="duotone" />
                                                </div>
                                                <div className="space-y-1">
                                                    <h4 className="font-medium text-foreground">{item.title}</h4>
                                                    <p className="text-sm leading-6 text-muted-foreground">{item.description}</p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                    <div className="rounded-3xl border border-border/60 bg-card p-6 shadow-sm">
                        <div className="space-y-3">
                            <h2 className="text-xl font-semibold text-foreground">{t('guide.commonTasksTitle')}</h2>
                            <p className="text-sm leading-6 text-muted-foreground">{t('guide.commonTasksDescription')}</p>
                        </div>

                        <div className="mt-6 grid gap-4">
                            <div className="rounded-2xl border border-border/60 p-4">
                                <h3 className="font-medium text-foreground">{t('guide.commonTasks.items.first.title')}</h3>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('guide.commonTasks.items.first.description')}</p>
                            </div>
                            <div className="rounded-2xl border border-border/60 p-4">
                                <h3 className="font-medium text-foreground">{t('guide.commonTasks.items.second.title')}</h3>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('guide.commonTasks.items.second.description')}</p>
                            </div>
                            <div className="rounded-2xl border border-border/60 p-4">
                                <h3 className="font-medium text-foreground">{t('guide.commonTasks.items.third.title')}</h3>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('guide.commonTasks.items.third.description')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="min-h-[44rem] overflow-hidden rounded-3xl border border-border/60 bg-card shadow-sm">
                        <HisabiAIChat
                            title={t('guide.aiTitle')}
                            subtitle={t('guide.aiDescription')}
                            emptyTitle={t('guide.aiEmptyTitle')}
                            emptyDescription={t('guide.aiEmptyDescription')}
                            placeholder={t('guide.aiPlaceholder')}
                            loadingText={t('guide.aiLoading')}
                            defaultSuggestions={[
                                t('guide.aiSuggestions.first'),
                                t('guide.aiSuggestions.second'),
                                t('guide.aiSuggestions.third'),
                            ]}
                        />
                    </div>
                </section>
            </div>
        </Authenticated>
    );
}