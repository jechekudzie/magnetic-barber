import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Scissors } from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { Container, Section } from '@/components/site/section';
import { StyleCard } from '@/components/site/style-card';
import type { SiteShared, Style } from '@/types/catalog';

type StyleProps = {
    site: SiteShared;
    style: Style;
    related: Style[];
};

export default function StyleDetail({ site, style, related }: StyleProps) {
    const price = style.service?.price;

    return (
        <>
            <Head title={`${style.code} ${style.name}`}>
                <meta
                    name="description"
                    content={
                        style.description ??
                        `${style.name} at Magnetic Barbershop.`
                    }
                />
            </Head>

            <Container className="pt-8">
                <Link
                    href="/styles"
                    className="text-smoke hover:text-gold inline-flex items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    All styles
                </Link>
            </Container>

            <Section className="pt-8">
                <div className="grid gap-10 lg:grid-cols-2">
                    <div className="site-panel bg-panel-alt overflow-hidden">
                        {style.image_url ? (
                            <img
                                src={style.image_url}
                                alt={style.name}
                                className="aspect-4/5 w-full object-cover"
                            />
                        ) : (
                            <div
                                className="site-glow flex aspect-4/5 w-full items-center justify-center"
                                aria-hidden="true"
                            >
                                <Scissors className="text-gold/25 size-20" />
                            </div>
                        )}
                    </div>

                    <div>
                        <span className="bg-gold text-ink site-display inline-flex rounded-full px-3 py-1 text-sm tabular-nums">
                            {style.code}
                        </span>

                        <h1 className="site-display mt-5 text-4xl sm:text-5xl">
                            {style.name}
                        </h1>

                        {style.description && (
                            <p className="text-smoke mt-5 text-lg leading-relaxed">
                                {style.description}
                            </p>
                        )}

                        <dl className="border-bone/8 mt-8 grid gap-5 border-y py-6 sm:grid-cols-2">
                            {style.typical_duration_minutes && (
                                <div>
                                    <dt className="text-smoke text-xs tracking-wide uppercase">
                                        Usually takes
                                    </dt>
                                    <dd className="site-display mt-1 flex items-center gap-2 text-xl">
                                        <Clock
                                            className="text-gold size-4"
                                            aria-hidden="true"
                                        />
                                        {style.typical_duration_minutes} min
                                    </dd>
                                </div>
                            )}
                            {price && (
                                <div>
                                    <dt className="text-smoke text-xs tracking-wide uppercase">
                                        {site.branch
                                            ? `From, at ${site.branch.name}`
                                            : 'From'}
                                    </dt>
                                    <dd className="site-display text-gold mt-1 text-xl">
                                        {price.formatted}
                                    </dd>
                                </div>
                            )}
                            {style.gender_label && (
                                <div>
                                    <dt className="text-smoke text-xs tracking-wide uppercase">
                                        Suits
                                    </dt>
                                    <dd className="mt-1 text-xl">
                                        {style.gender_label}
                                    </dd>
                                </div>
                            )}
                            {style.hair_type_tag.length > 0 && (
                                <div>
                                    <dt className="text-smoke text-xs tracking-wide uppercase">
                                        Hair type
                                    </dt>
                                    <dd className="mt-2 flex flex-wrap gap-1.5">
                                        {style.hair_type_tag.map((type) => (
                                            <span
                                                key={type}
                                                className="border-bone/12 text-bone/75 rounded-full border px-2.5 py-1 text-xs"
                                            >
                                                {type}
                                            </span>
                                        ))}
                                    </dd>
                                </div>
                            )}
                        </dl>

                        {style.service && (
                            <p className="text-smoke mt-6 text-sm">
                                Booked as{' '}
                                <span className="text-bone font-medium">
                                    {style.service.name}
                                </span>
                                .
                            </p>
                        )}

                        <div className="mt-8 flex flex-wrap gap-3">
                            <SiteLink
                                href={`/book?style=${style.id}`}
                                size="lg"
                            >
                                Book this cut
                            </SiteLink>
                            {site.whatsapp_link && (
                                <SiteLink
                                    href={`${site.whatsapp_link}?text=${encodeURIComponent(`Hi Magnetic, I would like number ${style.code}, the ${style.name}.`)}`}
                                    variant="outline"
                                    size="lg"
                                    external
                                >
                                    Ask on WhatsApp
                                </SiteLink>
                            )}
                        </div>
                    </div>
                </div>
            </Section>

            {related.length > 0 && (
                <Section
                    className="bg-panel/30"
                    eyebrow="More from the gallery"
                    title="Other cuts"
                >
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {related.map((item) => (
                            <StyleCard key={item.slug} style={item} />
                        ))}
                    </div>
                </Section>
            )}
        </>
    );
}
