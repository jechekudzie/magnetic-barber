import type { Branch, Service, ServiceCategory } from '@/types/catalog';

/** A day the visitor can pick from the date strip. */
export type BookableDay = {
    /** YYYY-MM-DD in the branch's local reckoning. */
    date: string;
    weekdayLabel: string;
    dayNumber: string;
    monthLabel: string;
    isToday: boolean;
    isOpen: boolean;
};

function toDateKey(date: Date): string {
    // Local parts, not toISOString, which would shift the day across midnight.
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * The next `count` days, flagged with whether the shop trades that weekday.
 * Closed days still render so the client can see why they cannot pick them.
 */
export function bookableDays(branch: Branch | null, count = 21): BookableDay[] {
    const open = branch?.hours.days_open ?? [];
    const today = new Date();
    const days: BookableDay[] = [];

    for (let offset = 0; offset < count; offset++) {
        const date = new Date(today);
        date.setDate(today.getDate() + offset);

        days.push({
            date: toDateKey(date),
            weekdayLabel: date.toLocaleDateString(undefined, { weekday: 'short' }),
            dayNumber: String(date.getDate()),
            monthLabel: date.toLocaleDateString(undefined, { month: 'short' }),
            isToday: offset === 0,
            isOpen: open.includes(date.getDay()),
        });
    }

    return days;
}

/** The first day the shop is actually open, used as the default selection. */
export function firstOpenDay(days: BookableDay[]): string | null {
    return days.find((day) => day.isOpen)?.date ?? null;
}

/**
 * Flattens the category-grouped price list into one lookup, so the summary can
 * resolve a chosen id without walking the tree every render.
 */
export function serviceIndex(
    categories: ServiceCategory[],
): Map<string, Service> {
    const index = new Map<string, Service>();

    categories.forEach((category) => {
        (category.services ?? []).forEach((service) => {
            index.set(service.id, service);
        });
    });

    return index;
}

export type Selection = {
    services: Service[];
    totalCents: number;
    totalMinutes: number;
    currency: string;
};

export function summarise(
    ids: string[],
    index: Map<string, Service>,
): Selection {
    const services = ids
        .map((id) => index.get(id))
        .filter((service): service is Service => service !== undefined);

    return {
        services,
        totalCents: services.reduce(
            (total, service) => total + (service.price?.cents ?? 0),
            0,
        ),
        totalMinutes: services.reduce(
            (total, service) => total + service.duration_minutes,
            0,
        ),
        currency: services[0]?.price?.currency ?? 'USD',
    };
}

/** Matches the server's Money formatting so the two never disagree. */
export function formatCents(cents: number, currency = 'USD'): string {
    const symbol = currency === 'USD' ? '$' : `${currency} `;
    const amount = cents / 100;

    return `${symbol}${amount % 1 === 0 ? amount.toFixed(0) : amount.toFixed(2)}`;
}

export function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`;
}
