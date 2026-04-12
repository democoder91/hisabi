import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  BankIcon,
  Receipt,
  StorefrontIcon,
  CirclesThreeIcon,
  ChartDonutIcon,
  ChartLineIcon,
} from "@phosphor-icons/react"

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import ApplicationLogo from "@/components/Global/ApplicationLogo"
import { UserNav } from "@/components/user-nav"

interface AppSidebarProps {
  auth?: {
    user: {
      name: string;
      email: string;
    };
  };
}

export function AppSidebar({ auth }: AppSidebarProps) {
  const { t } = useTranslation();
  const { direction } = usePage<any>().props as any;

  const items = [
    {
      title: t('navigation.dashboard'),
      url: "dashboard",
      icon: ChartDonutIcon,
    },
    {
      title: t('navigation.accounts'),
      url: "accounts",
      icon: BankIcon,
    },
    {
      title: t('navigation.transactions'),
      url: "transactions",
      icon: Receipt,
    },
    {
      title: t('navigation.budgets'),
      url: "budgets",
      icon: ChartLineIcon,
    },
    {
      title: t('navigation.brands'),
      url: "brands",
      icon: StorefrontIcon,
    },
    {
      title: t('navigation.categories'),
      url: "categories",
      icon: CirclesThreeIcon,
    },
  ];

  return (
    <Sidebar collapsible="offcanvas" variant="inset" side={direction === 'rtl' ? 'right' : 'left'}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/">
                <ApplicationLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              {items.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton asChild isActive={route().current(item.url)}>
                    <Link href={route(item.url)}>
                      <item.icon />
                      <span>{item.title}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        {auth?.user && <UserNav user={auth.user} />}
      </SidebarFooter>
    </Sidebar>
  )
}
