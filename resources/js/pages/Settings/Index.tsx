import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    UserCircleIcon,
    CaretLeftIcon,
    CaretDownIcon,
    SlidersHorizontalIcon,
    KeyIcon,
    DownloadIcon,
    UploadIcon,
    TagIcon,
    FunnelIcon,
    BellRingingIcon,
    ChatCircleDotsIcon,
    SignOutIcon
} from "@phosphor-icons/react";

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from "@/components/ui/sidebar";
import ApplicationLogo from "@/components/Global/ApplicationLogo";
import { updateUserProfile } from '@/Api/user';

// Helper function for route generation
const route = (name: string) => {
    const routes: Record<string, string> = {
        'dashboard': '/dashboard',
        'logout': '/logout'
    };
    return routes[name] || '/';
};

interface User {
    id: number;
    name: string;
    email: string;
}

export default function Index({ auth }: { auth: { user: User } }) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('account');
    const [name, setName] = useState(auth.user.name);
    const [email, setEmail] = useState(auth.user.email);
    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loadingProfile, setLoadingProfile] = useState(false);
    const [loadingPassword, setLoadingPassword] = useState(false);
    const [profileMessage, setProfileMessage] = useState('');
    const [passwordMessage, setPasswordMessage] = useState('');
    const [profileError, setProfileError] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [isProfileOpen, setIsProfileOpen] = useState(true);
    const [isPasswordOpen, setIsPasswordOpen] = useState(false);

    const settingsNavItems = [
        {
            section: t('settings.sections.general'),
            items: [
                { title: t('settings.nav.account'), value: "account", icon: UserCircleIcon },
                { title: t('settings.nav.preferences'), value: "preferences", icon: SlidersHorizontalIcon },
                { title: t('settings.nav.apiKey'), value: "api-key", icon: KeyIcon },
                { title: t('settings.nav.import'), value: "import", icon: DownloadIcon },
                { title: t('settings.nav.export'), value: "export", icon: UploadIcon },
            ]
        },
        {
            section: t('settings.sections.transactions'),
            items: [
                { title: t('settings.nav.tags'), value: "tags", icon: TagIcon },
                { title: t('settings.nav.smsParserRules'), value: "sms-parser-rules", icon: FunnelIcon },
            ]
        },
        {
            section: t('settings.sections.more'),
            items: [
                { title: t('settings.nav.productUpdates'), value: "product-updates", icon: BellRingingIcon },
                { title: t('settings.nav.feedback'), value: "feedback", icon: ChatCircleDotsIcon },
            ]
        },
    ];

    // Auto-dismiss profile messages after 5 seconds
    useEffect(() => {
        if (profileMessage || profileError) {
            const timer = setTimeout(() => {
                setProfileMessage('');
                setProfileError('');
            }, 5000);
            return () => clearTimeout(timer);
        }
    }, [profileMessage, profileError]);

    // Auto-dismiss password messages after 5 seconds
    useEffect(() => {
        if (passwordMessage || passwordError) {
            const timer = setTimeout(() => {
                setPasswordMessage('');
                setPasswordError('');
            }, 5000);
            return () => clearTimeout(timer);
        }
    }, [passwordMessage, passwordError]);

    const handleSaveProfile = () => {
        setProfileError('');
        setProfileMessage('');

        if (loadingProfile) return;
        setLoadingProfile(true);

        updateUserProfile({ name, email, currentPassword: undefined, password: undefined })
            .then(({ data }) => {
                setProfileMessage(t('settings.account.profileUpdated'));
                setLoadingProfile(false);
            })
            .catch((err) => {
                setProfileError(err.message || t('settings.account.profileError'));
                setLoadingProfile(false);
            });
    };

    const handleChangePassword = () => {
        setPasswordError('');
        setPasswordMessage('');

        if (password !== confirmPassword) {
            setPasswordError(t('settings.account.passwordsDoNotMatch'));
            return;
        }

        if (!currentPassword) {
            setPasswordError(t('settings.account.currentPasswordRequired'));
            return;
        }

        if (loadingPassword) return;
        setLoadingPassword(true);

        updateUserProfile({ name, email, currentPassword, password })
            .then(({ data }) => {
                setPasswordMessage(t('settings.account.passwordChanged'));
                setCurrentPassword('');
                setPassword('');
                setConfirmPassword('');
                setLoadingPassword(false);
            })
            .catch((err) => {
                setPasswordError(err.message || t('settings.account.passwordError'));
                setLoadingPassword(false);
            });
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    const isProfileValid = name.trim() !== '' && email.trim() !== '';
    const isPasswordValid = currentPassword && password && confirmPassword && password === confirmPassword && password.length >= 8;

    return (
        <>
            <Head title={t('settings.title')} />
            <SidebarProvider>
                <Sidebar variant="inset">
                    <SidebarHeader>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" asChild>
                                    <Link href={route('dashboard')} className="flex items-center gap-2">
                                        <CaretLeftIcon size={20} />
                                        <ApplicationLogo />
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>
                    <SidebarContent>
                        {settingsNavItems.map((section) => (
                            <SidebarGroup key={section.section}>
                                <SidebarGroupLabel>{section.section}</SidebarGroupLabel>
                                <SidebarGroupContent>
                                    <SidebarMenu>
                                        {section.items.map((item) => (
                                            <SidebarMenuItem key={item.value}>
                                                <SidebarMenuButton
                                                    onClick={() => setActiveTab(item.value)}
                                                    isActive={activeTab === item.value}
                                                >
                                                    <item.icon />
                                                    <span>{item.title}</span>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        ))}

                        {/* Logout Button */}
                        <SidebarGroup>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    <SidebarMenuItem>
                                        <SidebarMenuButton
                                            onClick={handleLogout}
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        >
                                            <SignOutIcon />
                                            <span>{t('userNav.logout')}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>
                </Sidebar>
                <SidebarInset>
                    <header className="flex h-16 shrink-0 items-center justify-center gap-2 border-b px-4 sticky top-0 bg-background z-10">
                        <div className="flex items-center gap-2 w-full max-w-7xl">
                            <SidebarTrigger className="-ml-1" />
                            <h2 className="text-lg">{t('settings.title')}</h2>
                        </div>
                    </header>
                    <main className="flex flex-1 flex-col gap-4 p-4 items-center">
                        <div className="w-full max-w-7xl">
                            {activeTab === 'account' && (
                                <div className="space-y-4">
                                    {/* Profile Information Section */}
                                    <Collapsible open={isProfileOpen} onOpenChange={setIsProfileOpen}>
                                        <Card>
                                            <CollapsibleTrigger className="w-full">
                                                <CardHeader className="cursor-pointer hover:bg-accent/50 transition-colors">
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-left">
                                                            <CardTitle>{t('settings.account.profileInformation')}</CardTitle>
                                                            <CardDescription>
                                                                {t('settings.account.profileDescription')}
                                                            </CardDescription>
                                                        </div>
                                                        <CaretDownIcon
                                                            size={20}
                                                            className={`transition-transform ${isProfileOpen ? 'rotate-180' : ''}`}
                                                        />
                                                    </div>
                                                </CardHeader>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <CardContent className="space-y-4">
                                                    {profileMessage && (
                                                        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                                                            {profileMessage}
                                                        </div>
                                                    )}

                                                    {profileError && (
                                                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                                            {profileError}
                                                        </div>
                                                    )}

                                                    <div className="space-y-2">
                                                        <Label htmlFor="name">{t('settings.account.name')}</Label>
                                                        <Input
                                                            id="name"
                                                            type="text"
                                                            value={name}
                                                            onChange={(e) => setName(e.target.value)}
                                                            placeholder={t('settings.account.namePlaceholder')}
                                                        />
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="email">{t('settings.account.email')}</Label>
                                                        <Input
                                                            id="email"
                                                            type="email"
                                                            value={email}
                                                            onChange={(e) => setEmail(e.target.value)}
                                                            placeholder={t('settings.account.emailPlaceholder')}
                                                        />
                                                    </div>

                                                    <div className="pt-2">
                                                        <Button
                                                            onClick={handleSaveProfile}
                                                            disabled={!isProfileValid || loadingProfile}
                                                            className="w-full sm:w-auto"
                                                        >
                                                            {loadingProfile ? t('settings.account.saving') : t('settings.account.saveProfile')}
                                                        </Button>
                                                    </div>
                                                </CardContent>
                                            </CollapsibleContent>
                                        </Card>
                                    </Collapsible>

                                    {/* Change Password Section */}
                                    <Collapsible open={isPasswordOpen} onOpenChange={setIsPasswordOpen}>
                                        <Card>
                                            <CollapsibleTrigger className="w-full">
                                                <CardHeader className="cursor-pointer hover:bg-accent/50 transition-colors">
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-left">
                                                            <CardTitle>{t('settings.account.changePassword')}</CardTitle>
                                                            <CardDescription>
                                                                {t('settings.account.changePasswordDescription')}
                                                            </CardDescription>
                                                        </div>
                                                        <CaretDownIcon
                                                            size={20}
                                                            className={`transition-transform ${isPasswordOpen ? 'rotate-180' : ''}`}
                                                        />
                                                    </div>
                                                </CardHeader>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <CardContent className="space-y-4">
                                                    {passwordMessage && (
                                                        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                                                            {passwordMessage}
                                                        </div>
                                                    )}

                                                    {passwordError && (
                                                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                                            {passwordError}
                                                        </div>
                                                    )}

                                                    <div className="space-y-2">
                                                        <Label htmlFor="currentPassword">{t('settings.account.currentPassword')}</Label>
                                                        <Input
                                                            id="currentPassword"
                                                            type="password"
                                                            value={currentPassword}
                                                            onChange={(e) => setCurrentPassword(e.target.value)}
                                                            placeholder={t('settings.account.currentPasswordPlaceholder')}
                                                        />
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="password">{t('settings.account.newPassword')}</Label>
                                                        <Input
                                                            id="password"
                                                            type="password"
                                                            value={password}
                                                            onChange={(e) => setPassword(e.target.value)}
                                                            placeholder={t('settings.account.newPasswordPlaceholder')}
                                                        />
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="confirmPassword">{t('settings.account.confirmPassword')}</Label>
                                                        <Input
                                                            id="confirmPassword"
                                                            type="password"
                                                            value={confirmPassword}
                                                            onChange={(e) => setConfirmPassword(e.target.value)}
                                                            placeholder={t('settings.account.confirmPasswordPlaceholder')}
                                                        />
                                                    </div>

                                                    <div className="pt-2">
                                                        <Button
                                                            onClick={handleChangePassword}
                                                            disabled={!isPasswordValid || loadingPassword}
                                                            className="w-full sm:w-auto"
                                                        >
                                                            {loadingPassword ? t('settings.account.changingPassword') : t('settings.account.changePasswordButton')}
                                                        </Button>
                                                    </div>
                                                </CardContent>
                                            </CollapsibleContent>
                                        </Card>
                                    </Collapsible>
                                </div>
                            )}

                            {activeTab === 'preferences' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.preferences.title')}</CardTitle>
                                        <CardDescription>{t('settings.preferences.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'api-key' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.apiKey.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.apiKey.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'import' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.import.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.import.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'export' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.export.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.export.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'tags' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.tags.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.tags.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'sms-parser-rules' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.smsParserRules.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.smsParserRules.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'product-updates' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.productUpdates.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.productUpdates.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}

                            {activeTab === 'feedback' && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('settings.placeholder.feedback.title')}</CardTitle>
                                        <CardDescription>{t('settings.placeholder.feedback.description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{t('common.comingSoon')}</p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </main>
                </SidebarInset>
            </SidebarProvider>
        </>
    );
}
