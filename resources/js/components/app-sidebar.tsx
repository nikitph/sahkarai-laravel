import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpenText,
    Bot,
    CreditCard,
    Gauge,
    LayoutGrid,
    UsersRound,
    Building2,
    Settings2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useT } from '@/lib/i18n';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { product, organization } = usePage().props;
    const t = useT();
    const items: NavItem[] = [
        { title: t('dashboard'), href: '/dashboard', icon: LayoutGrid },
        { title: t('archive'), href: '/archive', icon: BookOpenText },
        ...(product?.tier === 'tier_2' || product?.tier === 'tier_3'
            ? [{ title: t('chat'), href: '/chats', icon: Bot }]
            : []),
        ...(product?.tier !== 'free'
            ? [
                  {
                      title: t('notifications'),
                      href: '/notifications',
                      icon: Bell,
                      badge: product?.unread_notifications,
                  },
              ]
            : []),
        { title: t('billing'), href: '/billing', icon: CreditCard },
        ...(organization?.permissions.includes('members.manage')
            ? [{ title: 'People & access', href: '/members', icon: UsersRound }]
            : []),
        ...(organization?.team_billing_enabled &&
        organization.permissions.includes('billing.manage')
            ? [
                  {
                      title: 'Team billing',
                      href: '/billing/team',
                      icon: Building2,
                  },
              ]
            : []),
        ...(product?.role === 'saas_admin'
            ? [{ title: t('ops'), href: '/ops', icon: Gauge }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="mx-2 flex items-center justify-between rounded-xl border border-sidebar-border/70 bg-sidebar-accent/50 px-3 py-2 group-data-[collapsible=icon]:hidden">
                    <div>
                        <p className="text-[10px] font-semibold tracking-[.18em] text-muted-foreground uppercase">
                            Current plan
                        </p>
                        <p className="text-sm font-semibold capitalize">
                            {product?.tier.replace('_', ' ')}
                        </p>
                    </div>
                    {(product?.tier === 'tier_2' ||
                        product?.tier === 'tier_3') && (
                        <Badge variant="secondary">{product.credits} cr</Badge>
                    )}
                </div>
            </SidebarHeader>
            <SidebarContent>
                <OrganizationSwitcher />
                <NavMain items={items} />
            </SidebarContent>
            <SidebarFooter>
                <NavFooter
                    items={[
                        {
                            title: t('settings'),
                            href: '/settings/profile',
                            icon: Settings2,
                        },
                    ]}
                    className="mt-auto"
                />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
