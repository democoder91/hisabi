import { Link, router, usePage } from '@inertiajs/react';
import { SignOut, CaretUpDown, GearIcon, CheckIcon, GlobeIcon } from "@phosphor-icons/react";
import { useTranslation } from 'react-i18next';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuLabel,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { SidebarMenu, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { cn } from "@/lib/utils";
import { updateUserProfile } from '@/Api/user';

interface UserNavProps {
  user: {
    name: string;
    email: string;
  };
}

function getInitials(name: string): string {
  const names = name.trim().split(' ');
  if (names.length >= 2) {
    return (names[0][0] + names[names.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
}

export function UserNav({ user }: UserNavProps) {
  const { state } = useSidebar();
  const { t } = useTranslation();
  const initials = getInitials(user.name);
  const { locale } = usePage<{ locale: string }>().props as any;

  const handleLanguageChange = (newLocale: string) => {
    if (newLocale === locale) return;
    updateUserProfile({ locale: newLocale } as any)
      .then(() => {
        router.reload();
      })
      .catch(console.error);
  };

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              className={cn(
                "peer/menu-button flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-left text-sm outline-hidden ring-sidebar-ring transition-[width,height,padding]",
                "hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2",
                "active:bg-sidebar-accent active:text-sidebar-accent-foreground",
                "data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground",
                "h-12 group-data-[collapsible=icon]:p-0",
                "group-data-[collapsible=icon]:size-8"
              )}
            >
              <Avatar className="size-8 rounded-lg">
                <AvatarFallback className="rounded-lg">
                  {initials}
                </AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">{user.name}</span>
                <span className="truncate text-xs">{user.email}</span>
              </div>
              <CaretUpDown className="ml-auto size-4" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-56"
            align="end"
            side={state === "collapsed" ? "right" : "top"}
          >
            <DropdownMenuItem asChild>
              <Link
                href={route('settings')}
                className="cursor-pointer w-full"
              >
                <GearIcon className="mr-2 size-4" />
                <span>{t('userNav.settings')}</span>
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="flex items-center gap-2 text-xs font-normal text-muted-foreground">
              <GlobeIcon className="size-3" />
              {t('userNav.language')}
            </DropdownMenuLabel>
            <DropdownMenuItem
              className="cursor-pointer"
              onClick={() => handleLanguageChange('en')}
            >
              <span className="flex-1">{t('userNav.english')}</span>
              {locale === 'en' && <CheckIcon className="size-4 text-primary" />}
            </DropdownMenuItem>
            <DropdownMenuItem
              className="cursor-pointer"
              onClick={() => handleLanguageChange('ar')}
            >
              <span className="flex-1 font-arabic">{t('userNav.arabic')}</span>
              {locale === 'ar' && <CheckIcon className="size-4 text-primary" />}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link
                href={route('logout')}
                method="post"
                as="button"
                className="cursor-pointer w-full"
              >
                <SignOut className="mr-2 size-4" />
                <span>{t('userNav.logout')}</span>
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}
