/**
 * The shape of every public catalog payload.
 *
 * These mirror App\Http\Resources exactly, and the same payloads are served by
 * /api/v1 for the mobile app. When a resource changes, change it here too and
 * both clients stay honest. This file is the one to lift into packages/shared
 * when the React Native app starts.
 */

export type Money = {
    cents: number;
    currency: string;
    amount: number;
    formatted: string;
};

export type GenderTag = 'men' | 'women' | 'unisex' | 'kids';

export type ServiceCategory = {
    id: number;
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    icon: string | null;
    services?: Service[];
};

export type Service = {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    duration_minutes: number;
    /** Null when the service was not loaded through a branch. */
    price: Money | null;
    is_free: boolean;
    requires_patch_test: boolean;
    patch_test_lead_hours: number | null;
    is_skin_service: boolean;
    is_house_call_eligible: boolean;
    is_featured: boolean;
    category?: ServiceCategory;
};

export type Branch = {
    id: string;
    slug: string;
    code: string;
    name: string;
    tagline: string | null;
    phone: string | null;
    phone_display: string | null;
    whatsapp: string | null;
    whatsapp_link: string | null;
    email: string | null;
    address: {
        line: string | null;
        area: string | null;
        city: string;
        directions_note: string | null;
        latitude: number | null;
        longitude: number | null;
        map_url: string | null;
    };
    hours: {
        timezone: string;
        opens_at: string;
        closes_at: string;
        /** Carbon weekday numbers, 0 is Sunday. */
        days_open: number[];
    };
    chair_count: number;
    house_call_enabled: boolean;
    house_call_radius_km: number | null;
};

export type Style = {
    id: string;
    slug: string;
    code: string;
    name: string;
    description: string | null;
    gender_tag: GenderTag | null;
    gender_label: string | null;
    hair_type_tag: string[];
    typical_duration_minutes: number | null;
    image_url: string | null;
    is_featured: boolean;
    service?: Service;
};

export type Plan = {
    id: string;
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    type: 'unlimited' | 'session_pack';
    type_label: string;
    session_count: number | null;
    price: Money;
    validity_days: number;
    perks: string[];
    is_popular: boolean;
};

export type StaffMember = {
    id: string | null;
    slug: string;
    name: string;
    title: string | null;
    bio: string | null;
    specialities: string[];
    instagram_handle: string | null;
    photo_url: string | null;
    accepts_house_calls: boolean;
    is_bookable: boolean;
    rating: {
        average: number | null;
        count: number;
    };
};

export type Review = {
    id: number;
    author_name: string | null;
    rating: number;
    comment: string | null;
    published_at: string | null;
    branch?: {
        slug: string;
        name: string;
    };
};

export type StyleFilters = {
    genders: string[];
    hairTypes: string[];
};

/** Shared by every public page, so the header and footer always have context. */
export type SiteShared = {
    branches: Branch[];
    branch: Branch | null;
    whatsapp_link: string | null;
    instagram_url: string | null;
};
