import { Link } from '@inertiajs/react';
import {
    BellRing,
    CalendarCheck,
    CalendarRange,
    FolderTree,
    Gem,
    Globe,
    LayoutGrid,
    MapPin,
    Scissors,
    Star,
    Tags,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Grouped by what a manager does, not by table name: the shop floor first,
 * then what it sells, then who sells it.
 */
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Bookings', href: '/admin/bookings', icon: CalendarCheck },
    { title: 'Pricing', href: '/admin/pricing', icon: Wallet },
    { title: 'Services', href: '/admin/services', icon: Tags },
    { title: 'Categories', href: '/admin/categories', icon: FolderTree },
    { title: 'Style gallery', href: '/admin/styles', icon: Scissors },
    { title: 'Plans', href: '/admin/plans', icon: CalendarRange },
    { title: 'Loyalty', href: '/admin/loyalty', icon: Gem },
    { title: 'Reminders', href: '/admin/reminders', icon: BellRing },
    { title: 'Team', href: '/admin/team', icon: Users },
    { title: 'Reviews', href: '/admin/reviews', icon: Star },
    { title: 'Branches', href: '/admin/branches', icon: MapPin },
];

const footerNavItems: NavItem[] = [
    { title: 'View the site', href: '/', icon: Globe },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
