import { cn } from '@/lib/utils';

/**
 * Charts are hand drawn rather than pulled from a library.
 *
 * These are three small, fixed shapes reading a handful of points each. A
 * charting dependency would add well over 100KB to every admin page load to
 * draw bars that are a div wide, and would need theming to match anyway.
 */

export function BarChart({
    data,
    valueLabel,
    className,
}: {
    data: { label: string; count: number; value?: number }[];
    valueLabel?: (row: { count: number; value?: number }) => string;
    className?: string;
}) {
    const peak = Math.max(...data.map((row) => row.count), 1);

    return (
        <div className={cn('flex items-end gap-1.5', className)}>
            {data.map((row) => {
                const height = (row.count / peak) * 100;

                return (
                    <div
                        key={row.label}
                        className="group flex min-w-0 flex-1 flex-col items-center gap-1.5"
                    >
                        <span className="text-muted-foreground text-[0.65rem] tabular-nums opacity-0 transition-opacity group-hover:opacity-100">
                            {valueLabel
                                ? valueLabel(row)
                                : String(row.count)}
                        </span>

                        <div className="bg-muted flex h-28 w-full items-end overflow-hidden rounded-sm">
                            <div
                                className={cn(
                                    'w-full rounded-sm transition-all duration-500',
                                    row.count > 0
                                        ? 'bg-primary'
                                        : 'bg-transparent',
                                )}
                                style={{
                                    height: `${row.count > 0 ? Math.max(height, 6) : 0}%`,
                                }}
                                title={`${row.label}: ${row.count}`}
                            />
                        </div>

                        <span className="text-muted-foreground w-full truncate text-center text-[0.6rem]">
                            {row.label}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

/** A horizontal ranking, which reads better than a pie for "top N". */
export function RankChart({
    data,
    formatValue,
}: {
    data: { name: string; count: number; value?: number }[];
    formatValue?: (row: { count: number; value?: number }) => string;
}) {
    if (data.length === 0) {
        return (
            <p className="text-muted-foreground p-5 text-sm">
                Nothing booked yet.
            </p>
        );
    }

    const peak = Math.max(...data.map((row) => row.count), 1);

    return (
        <ul className="space-y-3 p-5">
            {data.map((row) => (
                <li key={row.name}>
                    <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                        <span className="truncate">{row.name}</span>
                        <span className="text-muted-foreground shrink-0 tabular-nums">
                            {formatValue ? formatValue(row) : row.count}
                        </span>
                    </div>
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div
                            className="bg-primary h-full rounded-full transition-all duration-500"
                            style={{ width: `${(row.count / peak) * 100}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** A single proportion, used for the repeat visit rate. */
export function Gauge({
    percent,
    label,
    caption,
}: {
    percent: number;
    label: string;
    caption?: string;
}) {
    const clamped = Math.max(0, Math.min(100, percent));
    const circumference = 2 * Math.PI * 40;

    return (
        <div className="flex items-center gap-4">
            <svg viewBox="0 0 100 100" className="size-24 -rotate-90" aria-hidden="true">
                <circle
                    cx="50"
                    cy="50"
                    r="40"
                    fill="none"
                    strokeWidth="10"
                    className="stroke-muted"
                />
                <circle
                    cx="50"
                    cy="50"
                    r="40"
                    fill="none"
                    strokeWidth="10"
                    strokeLinecap="round"
                    className="stroke-primary transition-all duration-700"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - clamped / 100)}
                />
            </svg>

            <div>
                <p className="text-3xl font-bold tabular-nums">{clamped}%</p>
                <p className="text-sm font-medium">{label}</p>
                {caption && (
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        {caption}
                    </p>
                )}
            </div>
        </div>
    );
}
