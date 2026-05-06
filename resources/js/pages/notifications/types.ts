export type FeedCategory = 'vendor_onboarding' | 'tier_upgrade' | 'field_agent';

export type FeedRowType =
    | 'submitted'
    | 'paid'
    | 'approved'
    | 'rejected'
    | 'flagged'
    | 'flag_reminded'
    | 'flag_expired';

export interface FeedRow {
    id: string;
    category: FeedCategory;
    type: FeedRowType;
    occurred_at: string;
    actor: { id: number; name: string } | null;
    subject: { id: number; type: string; label: string };
    action_url: string;
}

export interface FeedPaginator {
    data: FeedRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface NotificationsPageProps {
    feed: FeedPaginator;
    filters: { categories: FeedCategory[] };
}
