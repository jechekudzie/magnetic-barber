import { Link } from '@inertiajs/react';
import { Clock, Instagram, MapPin, MessageCircle, Phone } from 'lucide-react';
import { Logo } from '@/components/site/logo';
import { Container } from '@/components/site/section';
import { openingLine } from '@/lib/hours';
import type { SiteShared } from '@/types/catalog';

const columns = [
    {
        heading: 'The shop',
        links: [
            { label: 'Services and prices', href: '/services' },
            { label: 'Style gallery', href: '/styles' },
            { label: 'The Skin Room', href: '/skin' },
            { label: 'Monthly plans', href: '/plans' },
        ],
    },
    {
        heading: 'Visit',
        links: [
            { label: 'Find us', href: '/visit' },
            { label: 'Book a chair', href: '/book' },
            { label: 'Staff login', href: '/login' },
        ],
    },
];

export function SiteFooter({ site }: { site: SiteShared }) {
    const branch = site.branch;

    return (
        <footer className="border-bone/8 bg-panel/40 border-t">
            <Container className="py-14 sm:py-16">
                <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="lg:col-span-2">
                        <Logo />
                        <p className="text-smoke mt-5 max-w-sm text-sm leading-relaxed">
                            Book. Cut. Glow. Come back. One shop for walk ins,
                            booked chairs, house calls and skin.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            {site.whatsapp_link && (
                                <a
                                    href={site.whatsapp_link}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="border-bone/15 text-bone/85 hover:border-gold hover:text-gold inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors"
                                >
                                    <MessageCircle
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    WhatsApp
                                </a>
                            )}
                            {site.instagram_url && (
                                <a
                                    href={site.instagram_url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="border-bone/15 text-bone/85 hover:border-gold hover:text-gold inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors"
                                >
                                    <Instagram
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Instagram
                                </a>
                            )}
                        </div>
                    </div>

                    {columns.map((column) => (
                        <nav key={column.heading} aria-label={column.heading}>
                            <h2 className="site-eyebrow mb-4">
                                {column.heading}
                            </h2>
                            <ul className="space-y-2.5">
                                {column.links.map((link) => (
                                    <li key={link.href}>
                                        <Link
                                            href={link.href}
                                            className="text-bone/75 hover:text-gold text-sm transition-colors"
                                        >
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    ))}
                </div>

                {branch && (
                    <div className="border-bone/8 mt-12 grid gap-6 border-t pt-8 sm:grid-cols-3">
                        <p className="text-smoke flex gap-3 text-sm">
                            <MapPin
                                className="text-gold mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                {branch.address.line}
                                <br />
                                {branch.address.area}, {branch.address.city}
                            </span>
                        </p>
                        <p className="text-smoke flex gap-3 text-sm">
                            <Clock
                                className="text-gold mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{openingLine(branch)}</span>
                        </p>
                        {branch.phone && (
                            <p className="text-smoke flex gap-3 text-sm">
                                <Phone
                                    className="text-gold mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <a
                                    href={`tel:${branch.phone}`}
                                    className="hover:text-gold transition-colors"
                                >
                                    {branch.phone_display ?? branch.phone}
                                </a>
                            </p>
                        )}
                    </div>
                )}

                <p className="text-smoke/70 border-bone/8 mt-8 border-t pt-8 text-xs">
                    &copy; {new Date().getFullYear()} Magnetic Barbershop. All
                    rights reserved.
                </p>
            </Container>
        </footer>
    );
}
