import { Car, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Booking, Grid } from '@/types/bookings';

/** Pixels per minute. 1.4 gives a 30 minute slot a comfortable 42px. */
const SCALE = 1.4;

export const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-500/15 border-amber-500/50',
    confirmed: 'bg-primary/20 border-primary/60',
    checked_in: 'bg-primary/30 border-primary',
    in_progress: 'bg-primary/40 border-primary',
    completed: 'bg-emerald-500/15 border-emerald-500/50',
    cancelled: 'bg-muted border-border text-muted-foreground line-through',
    no_show: 'bg-destructive/10 border-destructive/40',
};

function label(minutes: number): string {
    const hour = Math.floor(minutes / 60);
    const minute = minutes % 60;
    const suffix = hour < 12 ? 'am' : 'pm';
    const twelve = hour % 12 === 0 ? 12 : hour % 12;

    return minute === 0
        ? `${twelve}${suffix}`
        : `${twelve}:${String(minute).padStart(2, '0')}`;
}

/**
 * The shop floor for one day: a column per chair, time running down.
 *
 * This is the view a barbershop actually needs. A list tells you what is
 * booked; this tells you who is free at 2pm, which is the question being
 * asked when somebody walks through the door.
 */
export function DayGrid({
    grid,
    bookings,
    onSelect,
}: {
    grid: Grid;
    bookings: Booking[];
    onSelect: (booking: Booking) => void;
}) {
    const height = (grid.closes_minutes - grid.opens_minutes) * SCALE;

    const lines: number[] = [];

    for (let m = grid.opens_minutes; m <= grid.closes_minutes; m += grid.step) {
        lines.push(m);
    }

    if (!grid.open_today) {
        return (
            <p className="text-muted-foreground p-8 text-center text-sm">
                {grid.branch.name} is closed on this day.
            </p>
        );
    }

    if (grid.columns.length === 0) {
        return (
            <p className="text-muted-foreground p-8 text-center text-sm">
                No bookable barbers at {grid.branch.name} yet.
            </p>
        );
    }

    // Anything without a barber would otherwise vanish from a per chair grid.
    const unassigned = bookings.filter((booking) => booking.staff_id === null);

    return (
        <div className="overflow-x-auto">
            <div className="flex min-w-max">
                {/* Time gutter, sticky so it survives sideways scrolling. */}
                <div className="bg-card sticky left-0 z-10 w-14 shrink-0 border-r">
                    <div className="h-11 border-b" />
                    <div className="relative" style={{ height }}>
                        {lines.slice(0, -1).map((minute) => (
                            <span
                                key={minute}
                                className="text-muted-foreground absolute right-2 -translate-y-1/2 text-[0.65rem] tabular-nums"
                                style={{
                                    top: (minute - grid.opens_minutes) * SCALE,
                                }}
                            >
                                {label(minute)}
                            </span>
                        ))}
                    </div>
                </div>

                {grid.columns.map((column) => {
                    const mine = bookings.filter(
                        (booking) => booking.staff_id === column.id,
                    );

                    return (
                        <div
                            key={column.id}
                            className="w-44 shrink-0 border-r last:border-r-0"
                        >
                            <div className="bg-muted/40 flex h-11 flex-col justify-center border-b px-2">
                                <span className="truncate text-sm font-medium">
                                    {column.name}
                                </span>
                                {column.title && (
                                    <span className="text-muted-foreground truncate text-[0.65rem]">
                                        {column.title}
                                    </span>
                                )}
                            </div>

                            <div className="relative" style={{ height }}>
                                {lines.map((minute) => (
                                    <span
                                        key={minute}
                                        className={cn(
                                            'absolute inset-x-0 border-t',
                                            minute % 60 === 0
                                                ? 'border-border'
                                                : 'border-border/40',
                                        )}
                                        style={{
                                            top:
                                                (minute - grid.opens_minutes) *
                                                SCALE,
                                        }}
                                    />
                                ))}

                                {mine.map((booking) => {
                                    const top =
                                        (booking.start_minutes -
                                            grid.opens_minutes) *
                                        SCALE;
                                    const raw =
                                        (booking.end_minutes -
                                            booking.start_minutes) *
                                        SCALE;

                                    return (
                                        <button
                                            key={booking.id}
                                            type="button"
                                            onClick={() => onSelect(booking)}
                                            title={`${booking.time_label} · ${booking.client.name}`}
                                            className={cn(
                                                'absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left transition-shadow hover:z-10 hover:shadow-md',
                                                STATUS_STYLES[booking.status] ??
                                                    'bg-muted border-border',
                                            )}
                                            style={{
                                                top,
                                                // Never shorter than a legible
                                                // block, even for a 15 minute cut.
                                                height: Math.max(raw - 2, 26),
                                            }}
                                        >
                                            <span className="block truncate text-[0.7rem] font-semibold tabular-nums">
                                                {booking.time_label}
                                            </span>
                                            <span className="block truncate text-[0.7rem]">
                                                {booking.client.name}
                                            </span>
                                            {raw > 52 && (
                                                <span className="block truncate text-[0.65rem] opacity-75">
                                                    {booking.services.join(', ')}
                                                </span>
                                            )}
                                            {booking.is_house_call && (
                                                <Car
                                                    className="absolute top-1 right-1 size-3"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>

            {unassigned.length > 0 && (
                <div className="border-t p-3">
                    <p className="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs font-medium">
                        <Clock className="size-3.5" aria-hidden="true" />
                        No barber assigned yet
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {unassigned.map((booking) => (
                            <button
                                key={booking.id}
                                type="button"
                                onClick={() => onSelect(booking)}
                                className={cn(
                                    'rounded-md border px-2 py-1 text-xs',
                                    STATUS_STYLES[booking.status],
                                )}
                            >
                                {booking.time_label} · {booking.client.name}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
