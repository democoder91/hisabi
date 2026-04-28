import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface ConversationCostRow {
    id: string;
    title: string;
    turns: number;
    cost: number;
}

interface BillingConversationCostPageProps {
    auth: {
        user: {
            is_super?: boolean;
        };
    };
    user: {
        id: number;
        name: string;
        email: string;
    };
    conversationCostCurrency: string;
    summary: {
        totalCost: number;
        totalTurns: number;
        conversationCount: number;
    };
    conversations: ConversationCostRow[];
}

function formatConversationCost(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: amount >= 1 ? 4 : 6,
    }).format(amount);
}

export default function BillingUserConversationCosts({
    auth,
    user,
    conversationCostCurrency,
    summary,
    conversations,
}: BillingConversationCostPageProps) {
    const { t } = useTranslation();

    const header = (
        <div className="space-y-1">
            <h2>{t('billing.conversationCostDetailsTitle')}</h2>
            <p className="text-sm text-muted-foreground">{t('billing.conversationCostDetailsDescription', { user: user.email })}</p>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('billing.conversationCostDetailsTitle')} />

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                            <h3 className="text-lg font-semibold">{user.name}</h3>
                            <p className="text-sm text-muted-foreground">{user.email}</p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={route('billing.manage.users')}>{t('billing.backToUserAccess')}</Link>
                        </Button>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>{t('billing.totalConversationCost')}</CardDescription>
                                <CardTitle>{formatConversationCost(summary.totalCost, conversationCostCurrency)}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>{t('billing.totalConversationTurns')}</CardDescription>
                                <CardTitle>{summary.totalTurns}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>{t('billing.totalConversations')}</CardDescription>
                                <CardTitle>{summary.conversationCount}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle>{t('billing.conversationCostTableTitle')}</CardTitle>
                            <CardDescription>{t('billing.conversationCostTableDescription')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {conversations.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('billing.noConversationCosts')}</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('billing.conversationId')}</TableHead>
                                            <TableHead>{t('billing.conversationTitle')}</TableHead>
                                            <TableHead className="text-right">{t('billing.conversationTurns')}</TableHead>
                                            <TableHead className="text-right">{t('billing.conversationCost')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {conversations.map((conversation) => (
                                            <TableRow key={conversation.id}>
                                                <TableCell className="font-mono text-xs">{conversation.id}</TableCell>
                                                <TableCell>{conversation.title || t('billing.untitledConversation')}</TableCell>
                                                <TableCell className="text-right">{conversation.turns}</TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {formatConversationCost(conversation.cost, conversationCostCurrency)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Authenticated>
    );
}