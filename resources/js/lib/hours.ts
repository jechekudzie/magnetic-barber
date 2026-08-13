import type { Branch } from '@/types/catalog';

const DAY_NAMES = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

const SHORT_DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** 08:00 reads better as 8am on a marketing page. */
export function friendlyTime(time: string): string {
    const [rawHour, rawMinute] = time.split(':');
    const hour = Number(rawHour);
    const minute = Number(rawMinute ?? 0);
    const suffix = hour < 12 ? 'am' : 'pm';
    const twelveHour = hour % 12 === 0 ? 12 : hour % 12;

    return minute === 0
        ? `${twelveHour}${suffix}`
        : `${twelveHour}.${String(minute).padStart(2, '0')}${suffix}`;
}

/**
 * Collapses [1,2,3,4,5,6] into "Mon to Sat" rather than listing six days.
 * Falls back to a comma list when the open days are not consecutive.
 */
export function openDaysLabel(days: number[]): string {
    if (days.length === 0) {
        return 'Closed';
    }

    if (days.length === 7) {
        return 'Every day';
    }

    const sorted = [...days].sort((a, b) => a - b);
    const isConsecutive = sorted.every(
        (day, index) => index === 0 || day === sorted[index - 1] + 1,
    );

    if (isConsecutive && sorted.length > 2) {
        return `${SHORT_DAY_NAMES[sorted[0]]} to ${SHORT_DAY_NAMES[sorted[sorted.length - 1]]}`;
    }

    return sorted.map((day) => SHORT_DAY_NAMES[day]).join(', ');
}

/** "Mon to Sat, 8am to 7pm" */
export function openingLine(branch: Branch): string {
    const days = openDaysLabel(branch.hours.days_open);

    if (days === 'Closed') {
        return 'Closed';
    }

    return `${days}, ${friendlyTime(branch.hours.opens_at)} to ${friendlyTime(branch.hours.closes_at)}`;
}

/**
 * A best effort "open now" for the header chip. It reads the visitor's own
 * clock, so it is a hint rather than a promise: the authoritative answer will
 * come from the live queue endpoint once the shop floor slice lands.
 */
export function isProbablyOpenNow(branch: Branch): boolean {
    const now = new Date();

    if (!branch.hours.days_open.includes(now.getDay())) {
        return false;
    }

    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const toMinutes = (time: string) => {
        const [hour, minute] = time.split(':').map(Number);

        return hour * 60 + (minute || 0);
    };

    return (
        minutesNow >= toMinutes(branch.hours.opens_at) &&
        minutesNow < toMinutes(branch.hours.closes_at)
    );
}

export function dayName(day: number): string {
    return DAY_NAMES[day] ?? '';
}
