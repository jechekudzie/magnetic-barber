import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    CalendarCheck,
    Car,
    Clock,
    DoorOpen,
    Gem,
    History,
    MapPin,
    Star,
    Tag,
    Users,
} from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { PlanCard } from '@/components/site/plan-card';
import { PriceRow } from '@/components/site/price-row';
import { Container, Section } from '@/components/site/section';
import { StaffCard } from '@/components/site/staff-card';
import { StyleCard } from '@/components/site/style-card';
import { isProbablyOpenNow, openingLine } from '@/lib/hours';
import type {
    Money,
    Plan,
    Review,
    Service,
    SiteShared,
    StaffMember,
    Style,
} from '@/types/catalog';

type HomeProps = {
    site: SiteShared;
    featuredServices: Service[];
    featuredStyles: Style[];
    plans: Plan[];
    team: StaffMember[];
    reviews: { data: Review[]; average_rating: number | null; total: number };
    fromPrice: Money | null;
};

const ways = [
    {
        icon: DoorOpen,
        title: 'Walk in',
        lede: 'Come as you are.',
        points: [
            'Reception logs you in seconds.',
            'The board shows the wait and who is next.',
            'New faces get an account on the spot.',
        ],
    },
    {
        icon: CalendarCheck,
        title: 'Book ahead',
        lede: 'Your chair, your time.',
        points: [
            'Pick the branch, barber, service and time.',
            'Reminders come to you automatically.',
            'Late and no show rules applied fairly.',
        ],
    },
    {
        icon: Car,
        title: 'House call',
        lede: 'The chair comes to you.',
        points: [
            'Set your address and preferred time.',
            'Travel fee shown before you confirm.',
            'Your barber gets the job card on their phone.',
        ],
    },
];

const retention = [
    {
        icon: BellRing,
        title: 'Your cut is due',
        body: 'We learn your cycle and message at the right moment, not on a random timer.',
    },
    {
        icon: Gem,
        title: 'Points on every dollar',
        body: 'Earn on cuts, skin and products. Redeem for a wash, a tint or a facial.',
    },
    {
        icon: Users,
        title: 'Referral code',
        body: 'Share your code. Both sides get credit once your friend sits in the chair.',
    },
    {
        icon: History,
        title: 'Style history',
        body: 'Last cut, guard numbers, colour used. Any barber can repeat it exactly.',
    },
    {
        icon: Tag,
        title: 'Monthly plans',
        body: 'Pay once, cut all month. Predictable for us, cheaper for you.',
    },
    {
        icon: Star,
        title: 'Rate the visit',
        body: 'A quick rating after each visit. Low scores reach the manager first.',
    },
];

export default function Home({
    site,
    featuredServices,
    featuredStyles,
    plans,
    team,
    reviews,
    fromPrice,
}: HomeProps) {
    const branch = site.branch;
    const openNow = branch ? isProbablyOpenNow(branch) : false;

    return (
        <>
            <Head title="Book. Cut. Glow. Come back.">
                <meta
                    name="description"
                    content="Magnetic Barbershop in the Harare Avenues. Fades, beards, colour and the Skin Room. Walk in, book ahead or have the chair come to you."
                />
            </Head>

            {/*
              Hero. The band is held close to the banner's own 3:2 so the photo
              is barely cropped, rather than being scaled up to fill a short
              strip.
            */}
            <section className="relative flex min-h-[92svh] items-center overflow-hidden sm:min-h-[85vh] lg:min-h-[88vh]">
                {/*
                  Full bleed shop banner. The image is 3:2, so it fills the band
                  without the hard crop a square photo would take. The washes
                  are kept light: enough to hold type contrast on the left,
                  little enough that the room still reads.
                */}
                <div className="absolute inset-0" aria-hidden="true">
                    {/* JPEG rather than the 2.2MB source PNG: Harare mobile
                        data is a design constraint, not an edge case. */}
                    <img
                        src="/images/hero1.jpg"
                        alt=""
                        fetchPriority="high"
                        className="size-full object-cover object-center"
                    />
                    {/* Vertical fade on phones, horizontal on desktop, so the
                        headline always lands on ink rather than on a face. */}
                    <div className="from-ink via-ink/65 sm:via-ink/35 absolute inset-0 bg-gradient-to-b to-transparent sm:bg-gradient-to-r" />
                    <div className="from-ink/85 absolute inset-0 bg-gradient-to-t via-transparent to-transparent" />
                    <div className="site-glow absolute inset-0 opacity-50" />
                </div>

                <Container className="relative w-full py-16 sm:py-20">
                    <div className="grid items-center gap-14 lg:grid-cols-[1.15fr_1fr]">
                        <div>
                            <p className="site-eyebrow mb-5">
                                {branch
                                    ? `${branch.address.area?.split(',').pop()?.trim()} · ${branch.address.city}`
                                    : 'Harare'}
                            </p>

                            <h1 className="site-display text-5xl sm:text-6xl lg:text-7xl">
                                Book. Cut.
                                <br />
                                <span className="text-gold">Glow.</span> Come
                                back.
                            </h1>

                            {branch?.tagline && (
                                <p className="text-gold mt-6 text-xl font-semibold sm:text-2xl">
                                    {branch.tagline}
                                </p>
                            )}

                            <p className="text-smoke mt-4 max-w-lg text-lg leading-relaxed">
                                One shop for walk ins, booked chairs, house
                                calls and skin. Same profile, same price list,
                                same history, whichever way you reach us.
                            </p>

                            <div className="mt-9 flex flex-wrap gap-3">
                                <SiteLink href="/book" size="lg">
                                    Book a chair
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </SiteLink>
                                <SiteLink
                                    href="/services"
                                    variant="outline"
                                    size="lg"
                                >
                                    See the price list
                                </SiteLink>
                            </div>

                            <dl className="mt-12 flex flex-wrap gap-x-10 gap-y-6">
                                {fromPrice && (
                                    <div>
                                        <dt className="text-smoke text-xs tracking-wide uppercase">
                                            Cuts from
                                        </dt>
                                        <dd className="site-display text-gold mt-1 text-2xl">
                                            {fromPrice.formatted}
                                        </dd>
                                    </div>
                                )}
                                {branch && branch.chair_count > 0 && (
                                    <div>
                                        <dt className="text-smoke text-xs tracking-wide uppercase">
                                            Chairs
                                        </dt>
                                        <dd className="site-display text-bone mt-1 text-2xl">
                                            {branch.chair_count}
                                        </dd>
                                    </div>
                                )}
                                {reviews.average_rating !== null && (
                                    <div>
                                        <dt className="text-smoke text-xs tracking-wide uppercase">
                                            Rated
                                        </dt>
                                        <dd className="site-display text-bone mt-1 flex items-center gap-1.5 text-2xl">
                                            {reviews.average_rating}
                                            <Star
                                                className="fill-gold text-gold size-4"
                                                aria-hidden="true"
                                            />
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                        {branch && (
                            <div className="site-panel bg-panel/70 ml-auto w-full max-w-sm p-6 backdrop-blur-md">
                                <div className="flex items-center gap-2.5">
                                    <span
                                        className={
                                            openNow
                                                ? 'bg-go size-2 animate-pulse rounded-full'
                                                : 'bg-smoke size-2 rounded-full'
                                        }
                                        aria-hidden="true"
                                    />
                                    <span className="text-sm font-semibold">
                                        {openNow ? 'Open now' : 'Closed now'}
                                    </span>
                                </div>

                                <p className="text-smoke mt-4 flex items-start gap-2.5 text-sm leading-relaxed">
                                    <Clock
                                        className="text-gold mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {openingLine(branch)}
                                </p>

                                <p className="text-smoke mt-3 flex items-start gap-2.5 text-sm leading-relaxed">
                                    <MapPin
                                        className="text-gold mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {branch.address.line}
                                        <br />
                                        {branch.address.area}
                                    </span>
                                </p>

                                {branch.address.map_url && (
                                    <SiteLink
                                        href={branch.address.map_url}
                                        variant="outline"
                                        size="sm"
                                        external
                                        className="mt-5 w-full"
                                    >
                                        Get directions
                                    </SiteLink>
                                )}
                            </div>
                        )}
                    </div>
                </Container>
            </section>

            {/* Three ways in */}
            <Section
                eyebrow="Three ways a client reaches us"
                title="Same profile. Same prices. Only the setting changes."
                lede="Every visit, whatever the channel, lands on one client record and one daily report."
            >
                <div className="grid gap-5 md:grid-cols-3">
                    {ways.map((way) => (
                        <article
                            key={way.title}
                            className="site-panel site-panel-hover p-6 sm:p-7"
                        >
                            <span className="bg-gold text-ink mb-5 inline-flex size-12 items-center justify-center rounded-full">
                                <way.icon className="size-6" aria-hidden="true" />
                            </span>
                            <h3 className="site-display text-gold text-2xl">
                                {way.title}
                            </h3>
                            <p className="text-bone/85 mt-1 text-sm">
                                {way.lede}
                            </p>
                            <ul className="mt-5 space-y-2.5">
                                {way.points.map((point) => (
                                    <li
                                        key={point}
                                        className="text-smoke flex gap-2.5 text-sm leading-relaxed"
                                    >
                                        <span
                                            className="bg-gold mt-2 size-1 shrink-0 rounded-full"
                                            aria-hidden="true"
                                        />
                                        {point}
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </Section>

            {/* Style gallery */}
            {featuredStyles.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="Style gallery"
                    title="Pick your cut by number"
                    lede="Our own photos, named and numbered, so the order is never lost in translation."
                    action={
                        <SiteLink href="/styles" variant="outline">
                            All styles
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </SiteLink>
                    }
                >
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                        {featuredStyles.map((style) => (
                            <StyleCard key={style.slug} style={style} />
                        ))}
                    </div>
                </Section>
            )}

            {/* Featured services */}
            {featuredServices.length > 0 && (
                <Section
                    eyebrow="Everything we sell, in one menu"
                    title="The chair, the beard, the skin."
                    lede={
                        branch
                            ? `Prices shown for ${branch.name}. Every branch sets its own.`
                            : undefined
                    }
                    action={
                        <SiteLink href="/services" variant="outline">
                            Full price list
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </SiteLink>
                    }
                >
                    <ul className="site-panel px-5 sm:px-7">
                        {featuredServices.map((service) => (
                            <PriceRow key={service.slug} service={service} />
                        ))}
                    </ul>
                </Section>
            )}

            {/* The Skin Room */}
            <section className="site-glow border-bone/8 border-y">
                <Container className="py-16 sm:py-24">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <p className="site-eyebrow mb-4">The Skin Room</p>
                            <h2 className="site-display text-4xl sm:text-5xl">
                                Clear skin.
                                <br />
                                Clear mind.
                                <br />
                                <span className="text-gold">Clear goals.</span>
                            </h2>
                            <p className="text-smoke mt-6 max-w-md leading-relaxed">
                                Facials booked like any other service, on their
                                own or added to a cut. Your skin profile is
                                saved once and read at every visit.
                            </p>
                            <SiteLink href="/skin" className="mt-8">
                                Inside the Skin Room
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </SiteLink>
                        </div>

                        <ul className="space-y-3">
                            {[
                                'Skin profile saved once: type, sensitivities, products used.',
                                'Before and after photos on your record, only with your consent.',
                                'A four week reminder, so a facial becomes a habit not a one off.',
                                'Bundle deals: cut plus facial, or a six session package.',
                            ].map((item) => (
                                <li
                                    key={item}
                                    className="site-panel text-bone/85 p-4 text-sm leading-relaxed"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                </Container>
            </section>

            {/* Retention */}
            <Section
                eyebrow="Why they come back"
                title="Retention is a feature, not a hope."
            >
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {retention.map((item) => (
                        <article key={item.title} className="flex gap-4">
                            <span className="bg-gold/12 text-gold flex size-11 shrink-0 items-center justify-center rounded-full">
                                <item.icon className="size-5" aria-hidden="true" />
                            </span>
                            <div>
                                <h3 className="text-bone font-semibold">
                                    {item.title}
                                </h3>
                                <p className="text-smoke mt-1.5 text-sm leading-relaxed">
                                    {item.body}
                                </p>
                            </div>
                        </article>
                    ))}
                </div>
            </Section>

            {/* Plans */}
            {plans.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="Monthly plans"
                    title="Pay once, cut all month."
                    lede="Predictable income for us, real savings for you."
                    action={
                        <SiteLink href="/plans" variant="outline">
                            Compare plans
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </SiteLink>
                    }
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        {plans.slice(0, 3).map((plan) => (
                            <PlanCard key={plan.slug} plan={plan} />
                        ))}
                    </div>
                </Section>
            )}

            {/* Team */}
            {team.length > 0 && (
                <Section
                    eyebrow="The chairs"
                    title="Who is cutting"
                    lede="Pick a barber, or leave it to us and take the next one free."
                >
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {team.slice(0, 4).map((member) => (
                            <StaffCard key={member.slug} member={member} />
                        ))}
                    </div>
                </Section>
            )}

            {/* Reviews */}
            {reviews.data.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="From the chair"
                    title="What clients say"
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        {reviews.data.slice(0, 3).map((review) => (
                            <figure
                                key={review.id}
                                className="site-panel flex flex-col p-6"
                            >
                                <div
                                    className="mb-4 flex gap-0.5"
                                    aria-label={`${review.rating} out of 5`}
                                >
                                    {Array.from({ length: 5 }, (_, index) => (
                                        <Star
                                            key={index}
                                            className={
                                                index < review.rating
                                                    ? 'fill-gold text-gold size-4'
                                                    : 'text-smoke/40 size-4'
                                            }
                                            aria-hidden="true"
                                        />
                                    ))}
                                </div>
                                <blockquote className="text-bone/90 flex-1 text-sm leading-relaxed">
                                    {review.comment}
                                </blockquote>
                                <figcaption className="text-smoke mt-4 text-xs">
                                    {review.author_name}
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                </Section>
            )}

            {/* Closing CTA */}
            <section className="site-glow border-bone/8 border-t">
                <Container className="py-20 text-center sm:py-28">
                    <h2 className="site-display mx-auto max-w-2xl text-4xl sm:text-5xl">
                        Always one tap away.
                    </h2>
                    <p className="text-smoke mx-auto mt-5 max-w-lg leading-relaxed">
                        Book on the site, message us on WhatsApp, or walk
                        through the door. It all ends up in the same chair.
                    </p>
                    <div className="mt-9 flex flex-wrap justify-center gap-3">
                        <SiteLink href="/book" size="lg">
                            Book a chair
                        </SiteLink>
                        {site.whatsapp_link && (
                            <SiteLink
                                href={site.whatsapp_link}
                                variant="outline"
                                size="lg"
                                external
                            >
                                Message on WhatsApp
                            </SiteLink>
                        )}
                    </div>
                    {branch?.address.map_url && (
                        <p className="mt-8 text-sm">
                            <Link
                                href="/visit"
                                className="text-gold hover:text-gold-lite underline underline-offset-4 transition-colors"
                            >
                                {branch.address.line}, {branch.address.city}
                            </Link>
                        </p>
                    )}
                </Container>
            </section>
        </>
    );
}
