import type { Money } from '@/types/catalog';

export type Slot = {
    /** UTC ISO string. The value posted back when booking. */
    start: string;
    /** Already formatted in the branch's timezone, e.g. "10:30am". */
    label: string;
};

export type StaffAvailability = {
    id: string | null;
    name: string;
    slots: Slot[];
};

export type Availability = {
    date: string;
    duration_minutes: number;
    staff: StaffAvailability[];
    any_staff: Slot[];
    closed: boolean;
    /** True while the grid is drawn against the shortest service, before
     *  the visitor has chosen what they actually want. */
    provisional: boolean;
    reason: string | null;
};

/** What the wizard learns when someone types a number it already knows. */
export type ClientLookup = {
    found: boolean;
    first_name: string | null;
    account_number: string | null;
    visit_count: number;
    last_visit: string | null;
    preferred_staff_id: string | null;
    points: number;
};

export type Appointment = {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    type: string;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    when_label: string;
    duration_minutes: number;
    total: Money;
    client_note: string | null;
    branch?: {
        slug: string;
        name: string;
        address_line: string | null;
        area: string | null;
        map_url: string | null;
    };
    staff?: { name: string; title: string | null } | null;
    travel_fee?: Money;
    house_call?: {
        address: string;
        directions_note: string | null;
        travel_fee: Money;
    } | null;
    services?: {
        name: string;
        duration_minutes: number;
        price: Money;
    }[];
};
