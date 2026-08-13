import { Head } from '@inertiajs/react';
import { Camera, ClipboardList, Repeat, ShieldCheck } from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { PlanCard } from '@/components/site/plan-card';
import { PriceRow } from '@/components/site/price-row';
import { Container, Section } from '@/components/site/section';
import { StaffCard } from '@/components/site/staff-card';
import type { Plan, Service, SiteShared, StaffMember } from '@/types/catalog';

type SkinProps = {
    site: SiteShared;
    services: Service[];
    plans: Plan[];
    team: StaffMember[];
};

const promises = [
    {
        icon: ClipboardList,
        title: 'Your profile, saved once',
        body: 'Skin type, sensitivities, allergies and the products you already use. Read at every visit, never asked twice.',
    },
    {
        icon: Camera,
        title: 'Before and after, with consent',
        body: 'Photos are stored privately on your record and only used publicly if you tick that box separately.',
    },
    {
        icon: Repeat,
        title: 'A four week nudge',
        body: 'One reminder at the right moment, so a facial becomes a habit rather than a one off.',
    },
    {
        icon: ShieldCheck,
        title: 'Patch tests taken seriously',
        body: 'Anything that needs a patch test cannot be booked inside the lead time. The system will not let it happen.',
    },
];

export default function Skin({ site, services, plans, team }: SkinProps) {
    const aestheticians = team.filter((member) =>
        member.title?.toLowerCase().includes('aesthetician'),
    );

    return (
        <>
            <Head title="The Skin Room">
                <meta
                    name="description"
                    content="Facials, deep cleanses and razor bump treatment at Magnetic Barbershop. Clear skin, clear mind, clear goals."
                />
            </Head>

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-16 sm:py-24">
                    <p className="site-eyebrow mb-5">The Skin Room</p>
                    <h1 className="site-display text-5xl sm:text-6xl lg:text-7xl">
                        Clear skin.
                        <br />
                        Clear mind.
                        <br />
                        <span className="text-gold">Clear goals.</span>
                    </h1>
                    <p className="text-smoke mt-7 max-w-xl text-lg leading-relaxed">
                        Booked like any other service, on its own or added to a
                        cut. Invest in your skin. It is going to represent you
                        for a long time.
                    </p>
                    <SiteLink href="/book" size="lg" className="mt-9">
                        Book a facial
                    </SiteLink>
                </Container>
            </section>

            {services.length > 0 && (
                <Section
                    eyebrow="The treatments"
                    title="What we do in the skin room"
                    lede={
                        site.branch
                            ? `Prices shown for ${site.branch.name}.`
                            : undefined
                    }
                >
                    <ul className="site-panel px-5 sm:px-7">
                        {services.map((service) => (
                            <PriceRow key={service.slug} service={service} />
                        ))}
                    </ul>
                </Section>
            )}

            <Section
                className="bg-panel/30"
                eyebrow="How we handle it"
                title="Skin data is personal data."
                lede="We collect the minimum, say why, and let you take it back."
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    {promises.map((promise) => (
                        <article
                            key={promise.title}
                            className="site-panel flex gap-4 p-6"
                        >
                            <span className="bg-gold/12 text-gold flex size-11 shrink-0 items-center justify-center rounded-full">
                                <promise.icon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <div>
                                <h3 className="text-bone font-semibold">
                                    {promise.title}
                                </h3>
                                <p className="text-smoke mt-1.5 text-sm leading-relaxed">
                                    {promise.body}
                                </p>
                            </div>
                        </article>
                    ))}
                </div>
            </Section>

            {plans.length > 0 && (
                <Section
                    eyebrow="Packages"
                    title="Six sessions, one price."
                    lede="Skin work compounds. A package is how it stops being a one off."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard key={plan.slug} plan={plan} />
                        ))}
                    </div>
                </Section>
            )}

            {aestheticians.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="Who you will see"
                    title="The skin room team"
                >
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {aestheticians.map((member) => (
                            <StaffCard key={member.slug} member={member} />
                        ))}
                    </div>
                </Section>
            )}
        </>
    );
}
