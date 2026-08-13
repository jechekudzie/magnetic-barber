import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { SiteLink } from '@/components/site/button';
import { PriceRow } from '@/components/site/price-row';
import { Container, Section } from '@/components/site/section';
import { cn } from '@/lib/utils';
import type { ServiceCategory, SiteShared } from '@/types/catalog';

type ServicesProps = {
    site: SiteShared;
    categories: ServiceCategory[];
};

export default function Services({ site, categories }: ServicesProps) {
    const [active, setActive] = useState<string>('all');
    const branch = site.branch;

    const visible =
        active === 'all'
            ? categories
            : categories.filter((category) => category.slug === active);

    return (
        <>
            <Head title="Services and prices">
                <meta
                    name="description"
                    content="The full Magnetic Barbershop price list: cuts, beards, wash and steam, colour, facials and house calls."
                />
            </Head>

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-16 sm:py-20">
                    <p className="site-eyebrow mb-4">
                        Everything we sell, in one menu
                    </p>
                    <h1 className="site-display max-w-2xl text-4xl sm:text-5xl lg:text-6xl">
                        Prices, in full, before you sit down.
                    </h1>
                    <p className="text-smoke mt-5 max-w-xl leading-relaxed">
                        {branch
                            ? `Showing ${branch.name}. Prices and times are set per branch and update here the moment they change.`
                            : 'Prices are set per branch and update here the moment they change.'}
                    </p>
                </Container>
            </section>

            {categories.length === 0 ? (
                <Section>
                    <p className="text-smoke">
                        The price list for this branch is not published yet.
                    </p>
                </Section>
            ) : (
                <Section>
                    {/* Category filter. Scrolls sideways on a phone rather
                        than wrapping into a wall of chips. */}
                    <div className="scrollbar-none -mx-5 mb-10 flex gap-2 overflow-x-auto px-5 sm:mx-0 sm:flex-wrap sm:px-0">
                        <FilterChip
                            label="Everything"
                            active={active === 'all'}
                            onClick={() => setActive('all')}
                        />
                        {categories.map((category) => (
                            <FilterChip
                                key={category.slug}
                                label={category.name}
                                active={active === category.slug}
                                onClick={() => setActive(category.slug)}
                            />
                        ))}
                    </div>

                    <div className="space-y-12">
                        {visible.map((category) => (
                            <div key={category.slug}>
                                <div className="mb-5">
                                    <h2 className="site-display text-2xl sm:text-3xl">
                                        {category.name}
                                    </h2>
                                    {category.tagline && (
                                        <p className="text-smoke mt-1.5 text-sm">
                                            {category.tagline}
                                        </p>
                                    )}
                                </div>
                                <ul className="site-panel px-5 sm:px-7">
                                    {(category.services ?? []).map((service) => (
                                        <PriceRow
                                            key={service.slug}
                                            service={service}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    <div className="site-panel mt-12 flex flex-col items-start gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                        <div>
                            <h2 className="site-display text-2xl">
                                Ready when you are.
                            </h2>
                            <p className="text-smoke mt-1.5 text-sm">
                                Book a chair, or message us and we will sort it.
                            </p>
                        </div>
                        <SiteLink href="/book" size="lg">
                            Book a chair
                        </SiteLink>
                    </div>
                </Section>
            )}
        </>
    );
}

function FilterChip({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                active
                    ? 'border-gold bg-gold text-ink'
                    : 'border-bone/15 text-bone/75 hover:border-gold/50 hover:text-gold',
            )}
        >
            {label}
        </button>
    );
}
