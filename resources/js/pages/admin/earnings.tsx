import { Head, router } from '@inertiajs/react';
import { Clock, TrendingUp } from 'lucide-react';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Money } from '@/types/catalog';

type StaffRow = {
    staff_id: string;
    name: string;
    rate: number;
    services: number;
    clients: number;
    visits: number;
    earned: Money;
    took: Money;
};

type TimingRow = {
    name: string;
    cuts: number;
    booked: number;
    actual: number;
};

/** The quick ranges a shop actually asks for. */
const RANGES: Array<{ label: string; days: number }> = [
    { label: 'Today', days: 0 },
    { label: '7 days', days: 6 },
    { label: '30 days', days: 29 },
    { label: '90 days', days: 89 },
];

function isoDaysAgo(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() - days);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
        date.getDate(),
    ).padStart(2, '0')}`;
}

/**
 * What the floor earned, and how long it took.
 *
 * The owner can see today in the app. This is the desktop version, because
 * payroll is not done on a phone: it covers a range, and it puts the time a
 * cut actually took beside the money it brought in.
 */
export default function Earnings({
    range,
    totals,
    staff,
    timing,
}: {
    range: { from: string; to: string; days: number };
    totals: { earned: Money; took: Money; visits: number; services: number };
    staff: StaffRow[];
    timing: TimingRow[];
}) {
    const topEarned = staff[0]?.earned.cents ?? 0;

    return (
        <AdminPage
            title="Earnings"
            lede={`Commission owed and time spent, ${range.from} to ${range.to}.`}
            action={
                <div className="flex flex-wrap gap-2">
                    {RANGES.map((option) => (
                        <Button
                            key={option.label}
                            variant={range.days === option.days + 1 ? 'default' : 'outline'}
                            onClick={() =>
                                router.get(
                                    '/admin/earnings',
                                    { from: isoDaysAgo(option.days), to: isoDaysAgo(0) },
                                    { preserveState: true, preserveScroll: true },
                                )
                            }
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
            }
        >
            <Head title="Earnings" />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Commission owed"
                    value={totals.earned.formatted}
                    accent
                    hint="To the barbers, for this range"
                />
                <StatCard label="Service value" value={totals.took.formatted} />
                <StatCard label="Visits" value={String(totals.visits)} />
                <StatCard label="Services" value={String(totals.services)} />
            </div>

            <Panel
                title="By barber"
                description="Commission is a percentage of each service line the barber personally rendered, frozen at the rate in force when it was done."
            >
                {staff.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-sm">
                        Nothing completed in this range.
                    </p>
                ) : (
                    <ul className="divide-border/60 divide-y">
                        {staff.map((row, index) => (
                            <li key={row.staff_id} className="py-4">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <div className="flex items-center gap-3">
                                        <span className="text-muted-foreground bg-muted flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                                            {index + 1}
                                        </span>
                                        <span className="font-semibold">{row.name}</span>
                                        <Pill>{row.rate}%</Pill>
                                    </div>

                                    <span className="text-lg font-bold tabular-nums">
                                        {row.earned.formatted}
                                    </span>
                                </div>

                                {/* A bar beats a column of numbers for "who did
                                    the most", which is the only comparison
                                    anybody makes on this screen. */}
                                <div className="bg-muted mt-3 h-1.5 overflow-hidden rounded-full">
                                    <div
                                        className="bg-primary h-full rounded-full"
                                        style={{
                                            width: `${
                                                topEarned > 0
                                                    ? Math.round(
                                                          (row.earned.cents / topEarned) * 100,
                                                      )
                                                    : 0
                                            }%`,
                                        }}
                                    />
                                </div>

                                <p className="text-muted-foreground mt-2 text-sm">
                                    {row.visits} visit{row.visits === 1 ? '' : 's'} ·{' '}
                                    {row.services} service{row.services === 1 ? '' : 's'} ·{' '}
                                    {row.clients} client{row.clients === 1 ? '' : 's'} ·{' '}
                                    {row.took.formatted} of service value
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            <Panel
                title="How long cuts take"
                description="What the barber's own clock said against what the catalog allows. A service that reliably runs over is costing a slot a day, and no other screen says so."
            >
                {timing.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-sm">
                        Nothing timed yet. Minutes are recorded when a barber taps Start and
                        Finish.
                    </p>
                ) : (
                    <ul className="divide-border/60 divide-y">
                        {timing.map((row) => {
                            const over = row.actual - row.booked;
                            const drift = row.booked > 0 ? over / row.booked : 0;

                            return (
                                <li
                                    key={row.name}
                                    className="flex flex-wrap items-center justify-between gap-3 py-4"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">{row.name}</p>
                                        <p className="text-muted-foreground mt-0.5 text-sm">
                                            {row.cuts} cut{row.cuts === 1 ? '' : 's'} timed
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-4 tabular-nums">
                                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-sm">
                                            <Clock className="size-3.5" aria-hidden="true" />
                                            {row.booked} booked
                                        </span>

                                        <span className="text-sm font-semibold">
                                            {row.actual} actual
                                        </span>

                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                                Math.abs(drift) < 0.1
                                                    ? 'bg-muted text-muted-foreground'
                                                    : over > 0
                                                      ? 'bg-destructive/10 text-destructive'
                                                      : 'bg-primary/10 text-primary',
                                            )}
                                        >
                                            <TrendingUp className="size-3" aria-hidden="true" />
                                            {over > 0 ? '+' : ''}
                                            {over} min
                                        </span>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Panel>
        </AdminPage>
    );
}
