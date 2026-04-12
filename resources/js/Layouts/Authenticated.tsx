import React, { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';
import { AppSidebar } from '@/components/app-sidebar';
import RightSidebar from '@/components/RightSidebar';
import { initI18n } from '@/i18n';

export default function Authenticated({ auth, header, children }: { auth?: any; header?: React.ReactNode; children: React.ReactNode }) {
    const { locale, direction } = usePage<{ locale: string; direction: string }>().props as any;

    useEffect(() => {
        if (locale) {
            initI18n(locale);
            document.documentElement.lang = locale;
            document.documentElement.dir = direction ?? 'ltr';
        }
    }, [locale, direction]);

    return (
        <SidebarProvider dir={direction}>
            <AppSidebar auth={auth} />
            <SidebarInset dir={direction}>
                <header className="flex h-16 shrink-0 items-center justify-center gap-2 border-b px-4 sticky top-0 bg-background z-10 md:rounded-t-xl">
                    <div className="flex items-center gap-2 w-full max-w-7xl">
                        <SidebarTrigger className="-ml-1" />
                        {header && (
                            <div className="flex flex-1 items-center gap-2">
                                {header}
                            </div>
                        )}
                    </div>
                </header>
                <main className="flex-1">
                    {children}
                </main>
            </SidebarInset>
            <RightSidebar />
        </SidebarProvider>
    );
}
