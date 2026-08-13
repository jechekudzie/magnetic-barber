import { Head, router } from '@inertiajs/react';
import { Plus, Scissors, Star } from 'lucide-react';
import { AdminPage, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch, Style } from '@/types/catalog';

export default function Styles({ styles }: {
    branchContext: { current: Branch | null; available: Branch[] };
    styles: Style[];
}) {
    return (
        <>
            <Head title="Style gallery" />

            <AdminPage
                title="Style gallery"
                lede="Featured styles show on the homepage. The number is what a client says at the desk."
                action={
                    <Button onClick={() => router.get('/admin/styles/create')}>
                        <Plus className="size-4" aria-hidden="true" />
                        New style
                    </Button>
                }
            >
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {styles.map((style) => (
                        <article
                            key={style.slug}
                            className="bg-card overflow-hidden rounded-xl border"
                        >
                            <div className="bg-muted relative aspect-4/3">
                                {style.image_url ? (
                                    <img
                                        src={style.image_url}
                                        alt={style.name}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center">
                                        <Scissors
                                            className="text-muted-foreground/30 size-10"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                                <span className="bg-primary text-primary-foreground absolute top-2 left-2 rounded-full px-2 py-0.5 text-xs font-bold tabular-nums">
                                    {style.code}
                                </span>
                            </div>

                            <div className="p-4">
                                <h3 className="font-medium">{style.name}</h3>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {style.gender_label && (
                                        <Pill>{style.gender_label}</Pill>
                                    )}
                                    {style.is_featured && (
                                        <Pill tone="gold">Featured</Pill>
                                    )}
                                </div>
                                <div className="mt-4 flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() =>
                                            router.get(
                                                `/admin/styles/${style.slug}/edit`,
                                            )
                                        }
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant={
                                            style.is_featured
                                                ? 'outline'
                                                : 'default'
                                        }
                                        className="flex-1"
                                        onClick={() =>
                                            router.put(
                                                `/admin/styles/${style.slug}/feature`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Star className="size-4" aria-hidden="true" />
                                        {style.is_featured ? 'Unfeature' : 'Feature'}
                                    </Button>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </AdminPage>
        </>
    );
}

Styles.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Style gallery', href: '/admin/styles' },
    ],
};
