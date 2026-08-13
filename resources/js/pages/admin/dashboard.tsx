import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Gem, Star, TriangleAlert } from 'lucide-react';
import { BarChart, Gauge, RankChart } from '@/components/admin/charts';
import { AdminPage, Panel, StatCard } from '@/components/admin/page';
import { dashboard } from '@/routes';
import type { Branch, Money, Review } from '@/types/catalog';

type Window = { bookings: number; completed: number; value: Money };

type DashboardProps = {
    branchContext: { current: Branch | null; available: Branch[] };
    metrics: {
        catalog: {
            branches: number;
            services: number;
            styles: number;
            plans: number;
            team: number;
            unpriced_services: number;
        };
        today: Window;
        week: Window;
        upcoming: number;
        clients: { total: number; returning: number; repeat_rate: number };
        loyalty: { outstanding: number; issued: number };
        charts: {
            bookings_by_day: {
                label: string;
                date: string;
                count: number;
                value: number;
            }[];
            top_services: { name: string; count: number; value: number }[];
            by_status: { label: string; value: number }[];
            channel: { label: string; value: number }[];
        };
        reviews: { published: number; pending: number };
    };
    recentReviews: Review[];
};

export default function AdminDashboard({
    branchContext,
    metrics,
    recentReviews,
}: DashboardProps) {
    const branch = branchContext.current;

    return (
        <>
            <Head title="Dashboard" />

            <AdminPage
                title={branch ? branch.name : 'Magnetic Barbershop'}
                lede={
                    branch
                        ? `${branch.address.area ?? ''} · ${branch.chair_count} chairs`
                        : 'No branch assigned to this account yet.'
                }
                action={
                    <Link
                        href="/admin/bookings"
                        className="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                    >
                        All bookings
                        <ArrowRight className="size-3.5" aria-hidden="true" />
                    </Link>
                }
            >
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Booked today"
                        value={metrics.today.bookings}
                        hint={`${metrics.today.completed} completed`}
                        accent
                    />
                    <StatCard
                        label="Booked this week"
                        value={metrics.week.bookings}
                        hint={`${metrics.week.value.formatted} of work booked`}
                    />
                    <StatCard
                        label="Still to come"
                        value={metrics.upcoming}
                        hint="Confirmed, not yet in the chair"
                    />
                    <StatCard
                        label="Clients"
                        value={metrics.clients.total}
                        hint={`${metrics.clients.returning} have been back`}
                    />
                </div>

                {metrics.catalog.unpriced_services > 0 && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4">
                        <TriangleAlert
                            className="mt-0.5 size-5 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <div className="flex-1">
                            <p className="font-medium">
                                {metrics.catalog.unpriced_services} active{' '}
                                {metrics.catalog.unpriced_services === 1
                                    ? 'service has'
                                    : 'services have'}{' '}
                                no price at this branch
                            </p>
                            <p className="text-muted-foreground mt-0.5 text-sm">
                                They stay off the public price list until they
                                are priced.
                            </p>
                        </div>
                        <Link
                            href="/admin/pricing"
                            className="text-primary inline-flex shrink-0 items-center gap-1 text-sm font-medium hover:underline"
                        >
                            Fix
                            <ArrowRight className="size-3.5" aria-hidden="true" />
                        </Link>
                    </div>
                )}

                <Panel
                    title="Bookings, last week and next"
                    description="Counts by day. Cancellations and no shows are left out."
                >
                    <div className="p-5">
                        <BarChart
                            data={metrics.charts.bookings_by_day}
                            valueLabel={(row) => String(row.count)}
                        />
                    </div>
                </Panel>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Most booked services"
                        description="Across every booking on record"
                    >
                        <RankChart
                            data={metrics.charts.top_services}
                            formatValue={(row) => `${row.count}`}
                        />
                    </Panel>

                    <Panel title="Retention">
                        <div className="space-y-6 p-5">
                            <Gauge
                                percent={metrics.clients.repeat_rate}
                                label="Repeat visit rate"
                                caption="Share of clients who have been back more than once"
                            />

                            <div className="flex items-start gap-3 border-t pt-5">
                                <Gem
                                    className="text-primary mt-0.5 size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium tabular-nums">
                                        {metrics.loyalty.outstanding} points
                                        outstanding
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {metrics.loyalty.issued} issued in
                                        total.{' '}
                                        <Link
                                            href="/admin/loyalty"
                                            className="text-primary hover:underline"
                                        >
                                            Set the rules
                                        </Link>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Panel>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Where bookings come from">
                        <RankChart
                            data={metrics.charts.channel.map((row) => ({
                                name: row.label,
                                count: row.value,
                            }))}
                        />
                    </Panel>

                    <Panel title="Booking status">
                        <RankChart
                            data={metrics.charts.by_status.map((row) => ({
                                name: row.label,
                                count: row.value,
                            }))}
                        />
                    </Panel>

                    <Panel
                        title="Reviews"
                        description={`${metrics.reviews.published} published, ${metrics.reviews.pending} waiting`}
                    >
                        {recentReviews.length === 0 ? (
                            <p className="text-muted-foreground p-5 text-sm">
                                Nothing published yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recentReviews.slice(0, 3).map((review) => (
                                    <li key={review.id} className="p-4">
                                        <span
                                            className="flex gap-0.5"
                                            aria-label={`${review.rating} out of 5`}
                                        >
                                            {Array.from({ length: 5 }, (_, i) => (
                                                <Star
                                                    key={i}
                                                    className={
                                                        i < review.rating
                                                            ? 'fill-primary text-primary size-3.5'
                                                            : 'text-muted-foreground/40 size-3.5'
                                                    }
                                                    aria-hidden="true"
                                                />
                                            ))}
                                        </span>
                                        <p className="mt-1.5 text-sm">
                                            {review.comment}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <Panel
                    title="Not measured yet"
                    description="So the numbers above are not mistaken for a full picture"
                >
                    <ul className="text-muted-foreground divide-y text-sm">
                        {[
                            'Booked value is what was quoted, not what was taken. Real takings arrive with the till.',
                            'The live queue board arrives with the walk in slice.',
                            'Stock levels and product sales arrive with the products slice.',
                        ].map((item) => (
                            <li key={item} className="px-5 py-3">
                                {item}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </AdminPage>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
