import { Head, router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type ReviewRow = {
    id: number;
    author_name: string | null;
    rating: number;
    comment: string | null;
    branch: string | null;
    is_public: boolean;
    is_flagged: boolean;
    published_at: string | null;
    created_at: string | null;
};

export default function Reviews({ reviews }: {
    branchContext: { current: Branch | null; available: Branch[] };
    reviews: ReviewRow[];
}) {
    return (
        <>
            <Head title="Reviews" />

            <AdminPage
                title="Reviews"
                lede="Nothing reaches the website until you publish it here."
            >
                <Panel>
                    {reviews.length === 0 ? (
                        <p className="text-muted-foreground p-5 text-sm">
                            No reviews yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {reviews.map((review) => (
                                <li
                                    key={review.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className="flex gap-0.5"
                                                aria-label={`${review.rating} out of 5`}
                                            >
                                                {Array.from(
                                                    { length: 5 },
                                                    (_, i) => (
                                                        <Star
                                                            key={i}
                                                            className={
                                                                i < review.rating
                                                                    ? 'fill-primary text-primary size-3.5'
                                                                    : 'text-muted-foreground/40 size-3.5'
                                                            }
                                                            aria-hidden="true"
                                                        />
                                                    ),
                                                )}
                                            </span>
                                            <span className="text-sm font-medium">
                                                {review.author_name ??
                                                    'Anonymous'}
                                            </span>
                                            {review.is_public ? (
                                                <Pill tone="good">Live</Pill>
                                            ) : (
                                                <Pill tone="warn">
                                                    Not published
                                                </Pill>
                                            )}
                                            {review.is_flagged && (
                                                <Pill tone="warn">Flagged</Pill>
                                            )}
                                        </div>
                                        {review.comment && (
                                            <p className="text-muted-foreground mt-1.5 text-sm">
                                                {review.comment}
                                            </p>
                                        )}
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {review.branch} · {review.created_at}
                                        </p>
                                    </div>

                                    <Button
                                        size="sm"
                                        variant={
                                            review.is_public
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={() =>
                                            router.put(
                                                `/admin/reviews/${review.id}/toggle`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {review.is_public ? 'Hide' : 'Publish'}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </AdminPage>
        </>
    );
}

Reviews.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reviews', href: '/admin/reviews' },
    ],
};
