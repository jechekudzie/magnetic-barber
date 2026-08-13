import { Head } from '@inertiajs/react';
import { Car, Clock, Instagram, MapPin, MessageCircle, Phone } from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { Container, Section } from '@/components/site/section';
import { StaffCard } from '@/components/site/staff-card';
import { dayName, friendlyTime, isProbablyOpenNow } from '@/lib/hours';
import type { Money, SiteShared, StaffMember } from '@/types/catalog';

type VisitProps = {
    site: SiteShared;
    team: StaffMember[];
    fromPrice: Money | null;
};

const WEEK = [1, 2, 3, 4, 5, 6, 0];

export default function Visit({ site, team, fromPrice }: VisitProps) {
    const branch = site.branch;

    if (!branch) {
        return (
            <Section>
                <p className="text-smoke">No branch is published yet.</p>
            </Section>
        );
    }

    const openNow = isProbablyOpenNow(branch);

    return (
        <>
            <Head title="Visit us">
                <meta
                    name="description"
                    content="Magnetic Barbershop, Devonshire House Room 6, corner Josiah Chinamano and Blackstone Avenue, Harare Avenues."
                />
            </Head>

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-16 sm:py-20">
                    <p className="site-eyebrow mb-4">Find us</p>
                    <h1 className="site-display max-w-2xl text-4xl sm:text-5xl lg:text-6xl">
                        {branch.name}
                    </h1>
                    {branch.tagline && (
                        <p className="text-gold mt-3 text-lg">
                            {branch.tagline}
                        </p>
                    )}

                    <div className="mt-8 flex flex-wrap items-center gap-4">
                        <span className="site-panel inline-flex items-center gap-2.5 px-4 py-2 text-sm">
                            <span
                                className={
                                    openNow
                                        ? 'bg-go size-2 rounded-full'
                                        : 'bg-smoke size-2 rounded-full'
                                }
                                aria-hidden="true"
                            />
                            {openNow ? 'Open now' : 'Closed now'}
                        </span>
                        {fromPrice && (
                            <span className="text-smoke text-sm">
                                Cuts from{' '}
                                <span className="text-gold font-semibold">
                                    {fromPrice.formatted}
                                </span>
                            </span>
                        )}
                        {branch.chair_count > 0 && (
                            <span className="text-smoke text-sm">
                                {branch.chair_count} chairs
                            </span>
                        )}
                    </div>
                </Container>
            </section>

            <Section>
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Address */}
                    <article className="site-panel p-6 sm:p-7">
                        <span className="bg-gold/12 text-gold mb-5 inline-flex size-11 items-center justify-center rounded-full">
                            <MapPin className="size-5" aria-hidden="true" />
                        </span>
                        <h2 className="site-display text-2xl">The shop</h2>
                        <address className="text-smoke mt-3 text-sm leading-relaxed not-italic">
                            {branch.address.line}
                            <br />
                            {branch.address.area}
                            <br />
                            {branch.address.city}
                        </address>
                        {branch.address.directions_note && (
                            <p className="text-bone/75 border-bone/8 mt-4 border-t pt-4 text-sm leading-relaxed">
                                {branch.address.directions_note}
                            </p>
                        )}
                        {branch.address.map_url && (
                            <SiteLink
                                href={branch.address.map_url}
                                variant="outline"
                                size="sm"
                                external
                                className="mt-5"
                            >
                                Open in Maps
                            </SiteLink>
                        )}
                    </article>

                    {/* Hours */}
                    <article className="site-panel p-6 sm:p-7">
                        <span className="bg-gold/12 text-gold mb-5 inline-flex size-11 items-center justify-center rounded-full">
                            <Clock className="size-5" aria-hidden="true" />
                        </span>
                        <h2 className="site-display text-2xl">Hours</h2>
                        <dl className="mt-4 space-y-2">
                            {WEEK.map((day) => {
                                const open =
                                    branch.hours.days_open.includes(day);

                                return (
                                    <div
                                        key={day}
                                        className="flex justify-between gap-4 text-sm"
                                    >
                                        <dt className="text-bone/85">
                                            {dayName(day)}
                                        </dt>
                                        <dd
                                            className={
                                                open
                                                    ? 'text-smoke tabular-nums'
                                                    : 'text-smoke/50'
                                            }
                                        >
                                            {open
                                                ? `${friendlyTime(branch.hours.opens_at)} – ${friendlyTime(branch.hours.closes_at)}`
                                                : 'Closed'}
                                        </dd>
                                    </div>
                                );
                            })}
                        </dl>
                    </article>

                    {/* Reach us */}
                    <article className="site-panel p-6 sm:p-7">
                        <span className="bg-gold/12 text-gold mb-5 inline-flex size-11 items-center justify-center rounded-full">
                            <MessageCircle className="size-5" aria-hidden="true" />
                        </span>
                        <h2 className="site-display text-2xl">Reach us</h2>
                        <ul className="mt-4 space-y-3 text-sm">
                            {branch.phone && (
                                <li>
                                    <a
                                        href={`tel:${branch.phone}`}
                                        className="text-bone/85 hover:text-gold inline-flex items-center gap-2.5 transition-colors"
                                    >
                                        <Phone
                                            className="text-gold size-4"
                                            aria-hidden="true"
                                        />
                                        {branch.phone_display ?? branch.phone}
                                    </a>
                                </li>
                            )}
                            {branch.whatsapp_link && (
                                <li>
                                    <a
                                        href={branch.whatsapp_link}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="text-bone/85 hover:text-gold inline-flex items-center gap-2.5 transition-colors"
                                    >
                                        <MessageCircle
                                            className="text-gold size-4"
                                            aria-hidden="true"
                                        />
                                        WhatsApp us
                                    </a>
                                </li>
                            )}
                            {site.instagram_url && (
                                <li>
                                    <a
                                        href={site.instagram_url}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="text-bone/85 hover:text-gold inline-flex items-center gap-2.5 transition-colors"
                                    >
                                        <Instagram
                                            className="text-gold size-4"
                                            aria-hidden="true"
                                        />
                                        Instagram
                                    </a>
                                </li>
                            )}
                        </ul>

                        {branch.house_call_enabled && (
                            <p className="text-smoke border-bone/8 mt-5 flex gap-2.5 border-t pt-5 text-sm leading-relaxed">
                                <Car
                                    className="text-gold mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                House calls within{' '}
                                {branch.house_call_radius_km} km. Travel fee is
                                shown before you confirm.
                            </p>
                        )}
                    </article>
                </div>
            </Section>

            {team.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="The chairs"
                    title="Who is cutting here"
                >
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {team.map((member) => (
                            <StaffCard key={member.slug} member={member} />
                        ))}
                    </div>
                </Section>
            )}
        </>
    );
}
