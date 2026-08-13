import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function AdminPage({
    title,
    lede,
    action,
    children,
}: {
    title: string;
    lede?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {title}
                    </h1>
                    {lede && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {lede}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

export function StatCard({
    label,
    value,
    hint,
    accent = false,
}: {
    label: string;
    value: string | number;
    hint?: string;
    accent?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border p-5',
                accent
                    ? 'border-primary/40 bg-primary/5'
                    : 'bg-card border-border',
            )}
        >
            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-2 text-3xl font-bold tabular-nums">{value}</p>
            {hint && (
                <p className="text-muted-foreground mt-1 text-xs">{hint}</p>
            )}
        </div>
    );
}

export function Panel({
    title,
    description,
    children,
    className,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'bg-card overflow-hidden rounded-xl border',
                className,
            )}
        >
            {(title || description) && (
                <header className="border-b px-5 py-4">
                    {title && <h2 className="font-semibold">{title}</h2>}
                    {description && (
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            {description}
                        </p>
                    )}
                </header>
            )}
            {children}
        </section>
    );
}

export function Pill({
    tone = 'neutral',
    children,
}: {
    tone?: 'neutral' | 'good' | 'warn' | 'gold';
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tone === 'neutral' && 'bg-muted text-muted-foreground',
                tone === 'good' &&
                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                tone === 'warn' &&
                    'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                tone === 'gold' && 'bg-primary/15 text-primary-foreground/90',
            )}
        >
            {children}
        </span>
    );
}
