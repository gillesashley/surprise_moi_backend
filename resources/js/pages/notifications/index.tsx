import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ICON_FOR_TYPE, TITLE_FOR_TYPE } from './icons';
import type { FeedCategory, FeedRow, NotificationsPageProps } from './types';

const ALL_CATEGORIES: { id: FeedCategory; label: string }[] = [
    { id: 'vendor_onboarding', label: 'Vendor Onboarding' },
    { id: 'tier_upgrade', label: 'Tier Upgrades' },
    { id: 'field_agent', label: 'Field Agents' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifications', href: '/dashboard/notifications' },
];

type Bucket = 'today' | 'yesterday' | 'last7' | 'older';

function bucketOf(iso: string): Bucket {
    const now = new Date();
    const at = new Date(iso);
    const days = Math.floor((now.getTime() - at.getTime()) / 86_400_000);
    if (days <= 0 && now.getDate() === at.getDate()) {
        return 'today';
    }
    if (days <= 1) {
        return 'yesterday';
    }
    if (days <= 7) {
        return 'last7';
    }
    return 'older';
}

const BUCKET_LABEL: Record<Bucket, string> = {
    today: 'Today',
    yesterday: 'Yesterday',
    last7: 'Last 7 days',
    older: 'Older',
};

function formatRelative(iso: string): string {
    const at = new Date(iso);
    const diffSec = Math.floor((Date.now() - at.getTime()) / 1000);
    if (diffSec < 60) return `${diffSec}s ago`;
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 86_400) return `${Math.floor(diffSec / 3600)}h ago`;
    return `${Math.floor(diffSec / 86_400)}d ago`;
}

function toggleCategory(current: FeedCategory[], cat: FeedCategory): FeedCategory[] {
    return current.includes(cat) ? current.filter((c) => c !== cat) : [...current, cat];
}

export default function NotificationsIndex({ feed, filters }: NotificationsPageProps) {
    const updateFilter = (next: FeedCategory[]) => {
        router.get(
            '/dashboard/notifications',
            { categories: next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const grouped = feed.data.reduce<Record<Bucket, FeedRow[]>>(
        (acc, row) => {
            const key = bucketOf(row.occurred_at);
            acc[key].push(row);
            return acc;
        },
        { today: [], yesterday: [], last7: [], older: [] },
    );

    const categoriesQuery = filters.categories.length
        ? `&categories=${filters.categories.join(',')}`
        : '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />

            <div className="space-y-4 px-6 py-4">
                <h1 className="text-2xl font-semibold">Notifications</h1>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => updateFilter([])}
                        className={`rounded-full border px-3 py-1 text-sm ${
                            filters.categories.length === 0
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        }`}
                    >
                        All
                    </button>
                    {ALL_CATEGORIES.map((c) => {
                        const active = filters.categories.includes(c.id);
                        return (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() =>
                                    updateFilter(toggleCategory(filters.categories, c.id))
                                }
                                className={`rounded-full border px-3 py-1 text-sm ${
                                    active ? 'bg-primary text-primary-foreground' : ''
                                }`}
                            >
                                {c.label}
                            </button>
                        );
                    })}
                </div>

                {feed.data.length === 0 && (
                    <p className="text-muted-foreground py-12 text-center">
                        No notifications yet.
                    </p>
                )}

                {(['today', 'yesterday', 'last7', 'older'] as const).map((bucket) => {
                    const rows = grouped[bucket];
                    if (rows.length === 0) {
                        return null;
                    }
                    return (
                        <section key={bucket} className="space-y-2">
                            <h2 className="text-muted-foreground text-xs font-medium uppercase tracking-wider">
                                {BUCKET_LABEL[bucket]}
                            </h2>
                            <ul className="divide-y rounded-lg border">
                                {rows.map((row) => {
                                    const Icon = ICON_FOR_TYPE[row.type];
                                    return (
                                        <li key={row.id}>
                                            <Link
                                                href={row.action_url}
                                                className="hover:bg-muted/40 flex items-start gap-3 px-4 py-3"
                                            >
                                                <Icon className="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm">
                                                        <span className="font-medium">
                                                            {row.actor?.name ?? 'Someone'}
                                                        </span>{' '}
                                                        {TITLE_FOR_TYPE[row.type]}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {row.subject.label}
                                                    </p>
                                                </div>
                                                <span className="text-muted-foreground shrink-0 text-xs">
                                                    {formatRelative(row.occurred_at)}
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    );
                })}

                {feed.current_page < feed.last_page && (
                    <div className="flex justify-center pt-4">
                        <Link
                            href={`/dashboard/notifications?page=${feed.current_page + 1}${categoriesQuery}`}
                            preserveScroll
                            className="rounded-md border px-4 py-2 text-sm"
                        >
                            Load more
                        </Link>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
