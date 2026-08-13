import type { CalendarDays} from 'lucide-react';
import { Clock, X } from 'lucide-react';
import { useEffect } from 'react';
import type { BookableDay } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { Slot } from '@/types/booking';

/**
 * A plain modal rather than the shadcn Dialog: the public site owns its own
 * dark palette, and the admin Dialog is themed for the white admin.
 */
function Modal({
    open,
    title,
    onClose,
    children,
}: {
    open: boolean;
    title: string;
    onClose: () => void;
    children: React.ReactNode;
}) {
    useEffect(() => {
        if (!open) {
            return;
        }

        function onKey(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-60 flex items-end justify-center sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-label={title}
        >
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="bg-ink/80 absolute inset-0 backdrop-blur-sm"
            />

            <div className="site-panel bg-panel animate-in slide-in-from-bottom-4 sm:zoom-in-95 relative flex max-h-[85svh] w-full max-w-lg flex-col rounded-b-none duration-200 sm:rounded-b-2xl">
                <header className="border-bone/8 flex items-center justify-between border-b px-5 py-4">
                    <h2 className="site-display text-xl">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className="text-smoke hover:text-gold -mr-2 p-2 transition-colors"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </header>

                <div className="overflow-y-auto p-5">{children}</div>
            </div>
        </div>
    );
}

/** The field the visitor taps to open a picker. */
export function PickerField({
    label,
    value,
    placeholder,
    icon: Icon,
    onClick,
    disabled = false,
}: {
    label: string;
    value: string | null;
    placeholder: string;
    icon: typeof CalendarDays;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={cn(
                'site-panel flex w-full items-center gap-4 p-4 text-left transition-colors',
                disabled
                    ? 'cursor-not-allowed opacity-50'
                    : 'hover:border-gold/50',
                value && 'border-gold/60',
            )}
        >
            <span className="bg-gold/12 text-gold flex size-11 shrink-0 items-center justify-center rounded-full">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="site-eyebrow block">{label}</span>
                <span
                    className={cn(
                        'mt-1 block truncate text-lg font-semibold',
                        value ? 'text-bone' : 'text-smoke',
                    )}
                >
                    {value ?? placeholder}
                </span>
            </span>
        </button>
    );
}

export function DateModal({
    open,
    days,
    selected,
    onSelect,
    onClose,
}: {
    open: boolean;
    days: BookableDay[];
    selected: string;
    onSelect: (date: string) => void;
    onClose: () => void;
}) {
    return (
        <Modal open={open} title="Pick a day" onClose={onClose}>
            <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                {days.map((day) => (
                    <button
                        key={day.date}
                        type="button"
                        disabled={!day.isOpen}
                        onClick={() => {
                            onSelect(day.date);
                            onClose();
                        }}
                        aria-pressed={selected === day.date}
                        className={cn(
                            'flex flex-col items-center gap-0.5 rounded-xl border py-3 transition-colors',
                            !day.isOpen &&
                                'border-bone/8 text-smoke/40 cursor-not-allowed line-through',
                            day.isOpen &&
                                selected === day.date &&
                                'border-gold bg-gold text-ink',
                            day.isOpen &&
                                selected !== day.date &&
                                'border-bone/15 text-bone hover:border-gold/50',
                        )}
                    >
                        <span className="text-[0.65rem] uppercase">
                            {day.isToday ? 'Today' : day.weekdayLabel}
                        </span>
                        <span className="text-lg font-bold tabular-nums">
                            {day.dayNumber}
                        </span>
                        <span className="text-[0.65rem] uppercase">
                            {day.monthLabel}
                        </span>
                    </button>
                ))}
            </div>

            <p className="text-smoke mt-5 text-xs">
                Crossed out days are when the shop is closed.
            </p>
        </Modal>
    );
}

export function TimeModal({
    open,
    slots,
    selected,
    loading,
    reason,
    onSelect,
    onClose,
}: {
    open: boolean;
    slots: Slot[];
    selected: Slot | null;
    loading: boolean;
    reason: string | null;
    onSelect: (slot: Slot) => void;
    onClose: () => void;
}) {
    // Grouping beats one long wall of times on a phone.
    const groups = [
        { label: 'Morning', slots: slots.filter((s) => s.label.endsWith('am')) },
        {
            label: 'Afternoon and evening',
            slots: slots.filter((s) => s.label.endsWith('pm')),
        },
    ].filter((group) => group.slots.length > 0);

    return (
        <Modal open={open} title="Pick a time" onClose={onClose}>
            {loading ? (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {Array.from({ length: 12 }, (_, i) => (
                        <div
                            key={i}
                            className="bg-panel-alt h-11 animate-pulse rounded-lg"
                        />
                    ))}
                </div>
            ) : slots.length === 0 ? (
                <p className="text-smoke py-8 text-center text-sm">
                    {reason ?? 'Nothing free that day. Try another date.'}
                </p>
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => (
                        <div key={group.label}>
                            <p className="site-eyebrow mb-3">{group.label}</p>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                {group.slots.map((slot) => (
                                    <button
                                        key={slot.start}
                                        type="button"
                                        onClick={() => {
                                            onSelect(slot);
                                            onClose();
                                        }}
                                        aria-pressed={
                                            selected?.start === slot.start
                                        }
                                        className={cn(
                                            'h-11 rounded-lg border text-sm font-medium tabular-nums transition-colors',
                                            selected?.start === slot.start
                                                ? 'border-gold bg-gold text-ink'
                                                : 'border-bone/15 text-bone hover:border-gold/50',
                                        )}
                                    >
                                        {slot.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

export { Clock };
