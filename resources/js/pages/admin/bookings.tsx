import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    LayoutList,
    Plus,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { BookingDrawer } from '@/components/admin/booking-drawer';
import { DayGrid, STATUS_STYLES } from '@/components/admin/day-grid';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { WeekGrid } from '@/components/admin/week-grid';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Booking, BookingsPageProps } from '@/types/bookings';

const VIEWS = [
    { value: 'day', label: 'Day', icon: CalendarDays },
    { value: 'week', label: 'Week', icon: CalendarDays },
    { value: 'list', label: 'List', icon: LayoutList },
] as const;

export default function Bookings({
    view,
    date,
    scope,
    scopes,
    range,
    filters,
    statuses,
    grids,
    days,
    bookings,
    summary,
}: BookingsPageProps) {
    const [open, setOpen] = useState<Booking | null>(null);
    const [search, setSearch] = useState(filters.search);

    function go(params: Record<string, string>) {
        router.get(
            '/admin/bookings',
            {
                view,
                date,
                scope,
                status: filters.status,
                search: filters.search,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Bookings" />

            <AdminPage
                title="Bookings"
                lede={range.label}
                action={
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Which branch. An owner also gets "All branches". */}
                        {scopes.length > 2 && (
                            <select
                                value={scope}
                                onChange={(event) =>
                                    go({ scope: event.target.value })
                                }
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs"
                            >
                                {scopes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        )}

                        <Button
                            size="sm"
                            onClick={() => router.get('/admin/bookings/create', { date })}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            New booking
                        </Button>

                        <div className="bg-muted flex rounded-md p-0.5">
                            {VIEWS.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => go({ view: option.value })}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                        view === option.value
                                            ? 'bg-card shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>
                }
            >
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="In view" value={summary.total} accent />
                    <StatCard label="Still to come" value={summary.confirmed} />
                    <StatCard label="Completed" value={summary.completed} />
                    <StatCard
                        label="Booked value"
                        value={summary.value.formatted}
                        hint="Quoted, not yet taken"
                    />
                </div>

                {/* Move through time, and jump back to today. */}
                {view !== 'list' && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: range.previous })}
                            aria-label="Previous"
                        >
                            <ChevronLeft className="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: range.today })}
                        >
                            Today
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: range.next })}
                            aria-label="Next"
                        >
                            <ChevronRight className="size-4" aria-hidden="true" />
                        </Button>

                        <Input
                            type="date"
                            value={date}
                            onChange={(event) =>
                                go({ date: event.target.value })
                            }
                            className="ml-auto w-40"
                        />

                        <select
                            value={filters.status}
                            onChange={(event) =>
                                go({ status: event.target.value })
                            }
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option value="all">Every status</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* ------------------------------------------------- day */}
                {view === 'day' &&
                    grids.map((grid) => (
                        <Panel
                            key={grid.branch.slug}
                            title={grids.length > 1 ? grid.branch.name : undefined}
                        >
                            <DayGrid
                                grid={grid}
                                bookings={bookings.filter(
                                    (booking) =>
                                        booking.branch === grid.branch.slug,
                                )}
                                onSelect={setOpen}
                            />
                        </Panel>
                    ))}

                {/* ------------------------------------------------ week */}
                {view === 'week' && (
                    <Panel>
                        <WeekGrid
                            days={days}
                            bookings={bookings}
                            onSelect={setOpen}
                            onOpenDay={(day) => go({ view: 'day', date: day })}
                        />
                    </Panel>
                )}

                {/* ------------------------------------------------ list */}
                {view === 'list' && (
                    <>
                        <Panel>
                            <div className="flex flex-wrap items-end gap-3 p-4">
                                <label className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        From
                                    </span>
                                    <Input
                                        type="date"
                                        value={filters.from}
                                        onChange={(event) =>
                                            go({ from: event.target.value })
                                        }
                                        className="w-40"
                                    />
                                </label>
                                <label className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        To
                                    </span>
                                    <Input
                                        type="date"
                                        value={filters.to}
                                        onChange={(event) =>
                                            go({ to: event.target.value })
                                        }
                                        className="w-40"
                                    />
                                </label>
                                <label className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        Status
                                    </span>
                                    <select
                                        value={filters.status}
                                        onChange={(event) =>
                                            go({ status: event.target.value })
                                        }
                                        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="all">All</option>
                                        {statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="flex min-w-48 flex-1 flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        Name, number or reference
                                    </span>
                                    <span className="relative">
                                        <Search
                                            className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            value={search}
                                            placeholder="MB-A7K2Q or 078 187 9820"
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    go({ search });
                                                }
                                            }}
                                            className="pl-9"
                                        />
                                    </span>
                                </label>
                                <Button onClick={() => go({ search })}>
                                    Search
                                </Button>
                            </div>
                        </Panel>

                        {summary.truncated && (
                            <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
                                <TriangleAlert
                                    className="mt-0.5 size-5 shrink-0 text-amber-600"
                                    aria-hidden="true"
                                />
                                <p>
                                    Showing the first{' '}
                                    <span className="font-medium">
                                        {summary.shown}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium">
                                        {summary.total}
                                    </span>
                                    . Narrow the dates to see the rest.
                                </p>
                            </div>
                        )}

                        <Panel>
                            {bookings.length === 0 ? (
                                <p className="text-muted-foreground p-8 text-center text-sm">
                                    Nothing booked in that window.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {bookings.map((booking) => (
                                        <li key={booking.id}>
                                            <button
                                                type="button"
                                                onClick={() => setOpen(booking)}
                                                className="hover:bg-muted/50 flex w-full items-center gap-4 p-4 text-left transition-colors"
                                            >
                                                <span className="w-24 shrink-0">
                                                    <span className="block text-sm font-semibold">
                                                        {booking.day_label}
                                                    </span>
                                                    <span className="text-muted-foreground block text-xs tabular-nums">
                                                        {booking.time_label}
                                                    </span>
                                                </span>

                                                <span className="min-w-0 flex-1">
                                                    <span className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {booking.client.name}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'rounded border px-1.5 py-0.5 text-[0.65rem]',
                                                                STATUS_STYLES[
                                                                    booking
                                                                        .status
                                                                ],
                                                            )}
                                                        >
                                                            {
                                                                booking.status_label
                                                            }
                                                        </span>
                                                        {booking.is_house_call && (
                                                            <Pill tone="gold">
                                                                House call
                                                            </Pill>
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground mt-0.5 block truncate text-sm">
                                                        {booking.services.join(
                                                            ', ',
                                                        )}
                                                        {booking.staff &&
                                                            ` · ${booking.staff}`}
                                                        {booking.branch_name &&
                                                            ` · ${booking.branch_name}`}
                                                    </span>
                                                </span>

                                                <span className="shrink-0 font-semibold tabular-nums">
                                                    {booking.total.formatted}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </>
                )}
            </AdminPage>

            <BookingDrawer booking={open} onClose={() => setOpen(null)} />
        </>
    );
}

Bookings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bookings', href: '/admin/bookings' },
    ],
};
