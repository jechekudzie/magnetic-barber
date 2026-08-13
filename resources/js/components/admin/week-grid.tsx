import { Car } from 'lucide-react';
import { STATUS_STYLES } from '@/components/admin/day-grid';
import { cn } from '@/lib/utils';
import type { Booking, WeekDay } from '@/types/bookings';

/**
 * Seven columns, one per day. Deliberately not a time grid: a week of chairs
 * at readable scale needs a screen nobody has. This answers "which days are
 * heavy", and clicking a day drops into the hour by hour view.
 */
export function WeekGrid({
    days,
    bookings,
    onSelect,
    onOpenDay,
}: {
    days: WeekDay[];
    bookings: Booking[];
    onSelect: (booking: Booking) => void;
    onOpenDay: (date: string) => void;
}) {
    const busiest = Math.max(
        ...days.map(
            (day) => bookings.filter((b) => b.date === day.date).length,
        ),
        1,
    );

    return (
        <div className="grid grid-cols-1 divide-y sm:grid-cols-7 sm:divide-x sm:divide-y-0">
            {days.map((day) => {
                const onDay = bookings
                    .filter((booking) => booking.date === day.date)
                    .sort((a, b) => a.start_minutes - b.start_minutes);

                return (
                    <div key={day.date} className="min-w-0">
                        <button
                            type="button"
                            onClick={() => onOpenDay(day.date)}
                            className={cn(
                                'hover:bg-muted/60 w-full border-b px-2 py-2 text-left transition-colors',
                                day.is_today && 'bg-primary/10',
                            )}
                        >
                            <span className="text-muted-foreground block text-[0.65rem] uppercase">
                                {day.weekday}
                            </span>
                            <span className="flex items-baseline justify-between gap-1">
                                <span className="text-sm font-semibold">
                                    {day.label}
                                </span>
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {onDay.length}
                                </span>
                            </span>
                            {/* A load bar, so a heavy Saturday is visible
                                without counting the blocks below it. */}
                            <span className="bg-muted mt-1.5 block h-1 overflow-hidden rounded-full">
                                <span
                                    className="bg-primary block h-full rounded-full"
                                    style={{
                                        width: `${(onDay.length / busiest) * 100}%`,
                                    }}
                                />
                            </span>
                        </button>

                        <div className="max-h-96 space-y-1 overflow-y-auto p-1.5">
                            {onDay.length === 0 ? (
                                <p className="text-muted-foreground/60 py-4 text-center text-xs">
                                    Nothing
                                </p>
                            ) : (
                                onDay.map((booking) => (
                                    <button
                                        key={booking.id}
                                        type="button"
                                        onClick={() => onSelect(booking)}
                                        className={cn(
                                            'block w-full truncate rounded border px-1.5 py-1 text-left text-[0.7rem]',
                                            STATUS_STYLES[booking.status] ??
                                                'bg-muted border-border',
                                        )}
                                    >
                                        <span className="font-semibold tabular-nums">
                                            {booking.time_label}
                                        </span>{' '}
                                        {booking.client.name}
                                        {booking.is_house_call && (
                                            <Car
                                                className="ml-1 inline size-3"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
