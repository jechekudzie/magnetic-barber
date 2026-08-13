import { Head, router } from '@inertiajs/react';
import { Check, Plus } from 'lucide-react';
import { AdminPage, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch, Plan } from '@/types/catalog';

export default function Plans({ plans }: {
    branchContext: { current: Branch | null; available: Branch[] };
    plans: Plan[];
}) {
    return (
        <>
            <Head title="Plans" />

            <AdminPage
                title="Plans"
                lede="Renewable cut plans and skin packages. Subscriptions against these arrive with the retention slice."
                action={
                    <Button onClick={() => router.get('/admin/plans/create')}>
                        <Plus className="size-4" aria-hidden="true" />
                        New plan
                    </Button>
                }
            >
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <article
                            key={plan.slug}
                            className="bg-card flex flex-col rounded-xl border p-5"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="font-semibold">{plan.name}</h3>
                                {plan.is_popular && (
                                    <Pill tone="gold">Most taken</Pill>
                                )}
                            </div>
                            {plan.tagline && (
                                <p className="text-muted-foreground mt-0.5 text-sm">
                                    {plan.tagline}
                                </p>
                            )}

                            <p className="mt-4 text-2xl font-bold tabular-nums">
                                {plan.price.formatted}
                                <span className="text-muted-foreground ml-1 text-sm font-normal">
                                    / {plan.validity_days} days
                                </span>
                            </p>

                            <p className="text-muted-foreground mt-1 text-sm">
                                {plan.type === 'unlimited'
                                    ? 'Unlimited visits'
                                    : `${plan.session_count} sessions`}
                            </p>

                            <Button
                                size="sm"
                                variant="outline"
                                className="mt-4 w-full"
                                onClick={() =>
                                    router.get(`/admin/plans/${plan.slug}/edit`)
                                }
                            >
                                Edit
                            </Button>

                            {plan.perks.length > 0 && (
                                <ul className="mt-4 space-y-1.5">
                                    {plan.perks.map((perk) => (
                                        <li
                                            key={perk}
                                            className="text-muted-foreground flex gap-2 text-sm"
                                        >
                                            <Check
                                                className="text-primary mt-0.5 size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {perk}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </article>
                    ))}
                </div>
            </AdminPage>
        </>
    );
}

Plans.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Plans', href: '/admin/plans' },
    ],
};
