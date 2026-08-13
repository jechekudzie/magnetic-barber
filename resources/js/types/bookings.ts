import type { Branch, Money } from '@/types/catalog';

export type Booking = {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    type: string;
    is_house_call: boolean;
    branch: string | null;
    branch_name: string | null;
    date: string | null;
    day_label: string | null;
    time_label: string | null;
    /** Minutes from midnight in the branch's own timezone. */
    start_minutes: number;
    end_minutes: number;
    duration_minutes: number;
    staff_id: string | null;
    staff: string | null;
    client: {
        name: string | null;
        phone: string | null;
        account_number: string | null;
        visit_count: number;
    };
    services: string[];
    total: Money;
    address: string | null;
    note: string | null;
};

export type Grid = {
    branch: { slug: string; name: string };
    opens_minutes: number;
    closes_minutes: number;
    step: number;
    open_today: boolean;
    columns: { id: string; name: string; title: string | null }[];
};

export type WeekDay = {
    date: string;
    label: string;
    weekday: string;
    is_today: boolean;
};

export type BookingsPageProps = {
    branchContext: { current: Branch | null; available: Branch[] };
    view: 'day' | 'week' | 'list';
    date: string;
    scope: string;
    scopes: { value: string; label: string }[];
    range: {
        from: string;
        to: string;
        label: string;
        previous: string;
        next: string;
        today: string;
    };
    filters: { status: string; search: string; from: string; to: string };
    statuses: { value: string; label: string }[];
    grids: Grid[];
    days: WeekDay[];
    bookings: Booking[];
    summary: {
        total: number;
        shown: number;
        truncated: boolean;
        confirmed: number;
        completed: number;
        cancelled: number;
        value: Money;
    };
};
