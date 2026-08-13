import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BranchSwitcher } from '@/components/site/branch-switcher';
import { SiteLink } from '@/components/site/button';
import { Logo } from '@/components/site/logo';
import { cn } from '@/lib/utils';
import type { SiteShared } from '@/types/catalog';

const links = [
    { label: 'Home', href: '/' },
    { label: 'Services', href: '/services' },
    { label: 'Styles', href: '/styles' },
    { label: 'The Skin Room', href: '/skin' },
    { label: 'Plans', href: '/plans' },
    { label: 'Visit', href: '/visit' },
];

export function SiteHeader({ site }: { site: SiteShared }) {
    const { url } = usePage();

    /**
     * The sheet is scoped to the page it was opened on, so any navigation
     * closes it, including a back gesture. Deriving it during render avoids
     * the cascading re-render an effect would cause.
     */
    const [menu, setMenu] = useState({ open: false, url });
    const menuOpen = menu.open && menu.url === url;

    function setMenuOpen(open: boolean) {
        setMenu({ open, url });
    }

    // A full screen sheet over a scrolling page is disorienting on phones.
    useEffect(() => {
        document.body.style.overflow = menuOpen ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    }, [menuOpen]);

    const path = url.split('?')[0];

    return (
        <header className="border-bone/8 bg-ink/85 sticky top-0 z-50 border-b backdrop-blur-xl">
            <div className="mx-auto flex h-18 w-full max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
                <Link href="/" aria-label="Magnetic Barbershop, home">
                    <Logo />
                </Link>

                <nav
                    aria-label="Main"
                    className="hidden items-center gap-1 lg:flex"
                >
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className={cn(
                                'rounded-full px-3.5 py-2 text-sm font-medium transition-colors',
                                path === link.href
                                    ? 'text-gold'
                                    : 'text-bone/75 hover:text-gold',
                            )}
                            aria-current={path === link.href ? 'page' : undefined}
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>

                <div className="flex items-center gap-3">
                    <BranchSwitcher
                        branches={site.branches}
                        current={site.branch}
                        className="hidden sm:block"
                    />
                    <SiteLink href="/book" size="sm" className="hidden sm:inline-flex">
                        Book a chair
                    </SiteLink>

                    <button
                        type="button"
                        onClick={() => setMenuOpen(true)}
                        aria-controls="mobile-menu"
                        className="text-bone hover:text-gold -mr-2 p-2 transition-colors lg:hidden"
                        aria-label="Open menu"
                        aria-expanded={menuOpen}
                    >
                        <Menu className="size-6" aria-hidden="true" />
                    </button>
                </div>
            </div>

            {menuOpen && (
                <div
                    id="mobile-menu"
                    className="bg-ink fixed inset-0 z-50 flex flex-col lg:hidden"
                >
                    <div className="border-bone/8 flex h-18 items-center justify-between border-b px-5">
                        <Logo />
                        <button
                            type="button"
                            onClick={() => setMenuOpen(false)}
                            className="text-bone hover:text-gold -mr-2 p-2 transition-colors"
                            aria-label="Close menu"
                        >
                            <X className="size-6" aria-hidden="true" />
                        </button>
                    </div>

                    <nav
                        aria-label="Mobile"
                        className="flex flex-1 flex-col gap-1 overflow-y-auto px-5 py-8"
                    >
                        {links.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={cn(
                                    'site-display border-bone/8 border-b py-4 text-3xl transition-colors',
                                    path === link.href
                                        ? 'text-gold'
                                        : 'text-bone hover:text-gold',
                                )}
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="border-bone/8 space-y-4 border-t px-5 py-6">
                        <BranchSwitcher
                            branches={site.branches}
                            current={site.branch}
                        />
                        <SiteLink href="/book" size="lg" className="w-full">
                            Book a chair
                        </SiteLink>
                    </div>
                </div>
            )}
        </header>
    );
}
