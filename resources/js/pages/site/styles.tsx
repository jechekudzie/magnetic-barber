import { Head, router } from '@inertiajs/react';
import { Container, Section } from '@/components/site/section';
import { StyleCard } from '@/components/site/style-card';
import { cn } from '@/lib/utils';
import type { SiteShared, Style, StyleFilters } from '@/types/catalog';

type StylesProps = {
    site: SiteShared;
    styles: Style[];
    filters: StyleFilters;
    activeFilters: { gender: string; hair_type: string };
};

const GENDER_LABELS: Record<string, string> = {
    men: 'Men',
    women: 'Women',
    unisex: 'Unisex',
    kids: 'Kids',
};

export default function Styles({ styles, filters, activeFilters }: StylesProps) {
    /**
     * Filters live in the query string so a client can send a friend the exact
     * grid they are looking at.
     */
    function applyFilter(key: 'gender' | 'hair_type', value: string) {
        router.get(
            '/styles',
            { ...activeFilters, [key]: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Style gallery">
                <meta
                    name="description"
                    content="Every cut we do, named and numbered. Fades, tapers, line ups, afro shapes, locs and more."
                />
            </Head>

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-16 sm:py-20">
                    <p className="site-eyebrow mb-4">Style gallery</p>
                    <h1 className="site-display max-w-2xl text-4xl sm:text-5xl lg:text-6xl">
                        Pick your cut by number.
                    </h1>
                    <p className="text-smoke mt-5 max-w-xl leading-relaxed">
                        Every style carries a number. Ask for number 03 at the
                        desk or over WhatsApp and any barber in the shop cuts
                        the same thing.
                    </p>
                </Container>
            </section>

            <Section>
                <div className="mb-10 space-y-4">
                    <FilterRow
                        legend="Who it is for"
                        options={[
                            { value: 'all', label: 'Everyone' },
                            ...filters.genders.map((gender) => ({
                                value: gender,
                                label: GENDER_LABELS[gender] ?? gender,
                            })),
                        ]}
                        active={activeFilters.gender}
                        onChange={(value) => applyFilter('gender', value)}
                    />

                    {filters.hairTypes.length > 0 && (
                        <FilterRow
                            legend="Hair type"
                            options={[
                                { value: 'all', label: 'Any hair' },
                                ...filters.hairTypes.map((type) => ({
                                    value: type,
                                    label:
                                        type.charAt(0).toUpperCase() +
                                        type.slice(1),
                                })),
                            ]}
                            active={activeFilters.hair_type}
                            onChange={(value) => applyFilter('hair_type', value)}
                        />
                    )}
                </div>

                {styles.length === 0 ? (
                    <p className="text-smoke py-12 text-center">
                        Nothing matches that combination yet. Try widening the
                        filters.
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {styles.map((style) => (
                            <StyleCard key={style.slug} style={style} />
                        ))}
                    </div>
                )}
            </Section>
        </>
    );
}

function FilterRow({
    legend,
    options,
    active,
    onChange,
}: {
    legend: string;
    options: { value: string; label: string }[];
    active: string;
    onChange: (value: string) => void;
}) {
    return (
        <fieldset>
            <legend className="site-eyebrow mb-3">{legend}</legend>
            <div className="scrollbar-none -mx-5 flex gap-2 overflow-x-auto px-5 sm:mx-0 sm:flex-wrap sm:px-0">
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        aria-pressed={active === option.value}
                        className={cn(
                            'shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                            active === option.value
                                ? 'border-gold bg-gold text-ink'
                                : 'border-bone/15 text-bone/75 hover:border-gold/50 hover:text-gold',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
            </div>
        </fieldset>
    );
}
