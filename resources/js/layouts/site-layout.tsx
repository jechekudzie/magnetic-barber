import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { WhatsAppFloat } from '@/components/site/whatsapp-float';
import type { SiteShared } from '@/types/catalog';

/**
 * The public site is always the dark gold room. It does not follow the
 * appearance toggle, which stays a staff preference for the admin.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const { site } = usePage<{ site: SiteShared }>().props;

    return (
        <div className="site flex min-h-svh flex-col">
            <SiteHeader site={site} />

            <main id="content" className="flex-1">
                {children}
            </main>

            <SiteFooter site={site} />

            <WhatsAppFloat href={site.whatsapp_link} />

            {/*
              Phones are the main way clients reach this site, so booking lives
              under the thumb. WhatsApp is one tap away on the float just above,
              so it is not repeated here.
            */}
            <div className="border-bone/10 bg-ink/95 fixed inset-x-0 bottom-0 z-40 border-t p-3 backdrop-blur-xl sm:hidden">
                <Link
                    href="/book"
                    className="bg-gold text-ink flex h-12 items-center justify-center gap-2 rounded-full text-sm font-semibold"
                >
                    <CalendarCheck className="size-4" aria-hidden="true" />
                    Book a chair
                </Link>
            </div>

            {/* Clears the fixed bar so the footer is never trapped beneath it. */}
            <div className="h-20 sm:hidden" aria-hidden="true" />
        </div>
    );
}
