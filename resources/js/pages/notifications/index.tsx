import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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

function toggleCategory(
    current: FeedCategory[],
    cat: FeedCategory,
): FeedCategory[] {
    return current.includes(cat)
        ? current.filter((c) => c !== cat)
        : [...current, cat];
}

export default function NotificationsIndex({
    feed,
    filters,
}: NotificationsPageProps) {
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

            <Box sx={{ p: { xs: 2, md: 3 }, display: 'flex', flexDirection: 'column', gap: 3 }}>
                <Typography variant="h5" fontWeight={700}>
                    Notifications
                </Typography>

                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                    <Chip
                        label="All"
                        clickable
                        color={filters.categories.length === 0 ? 'primary' : 'default'}
                        variant={filters.categories.length === 0 ? 'filled' : 'outlined'}
                        onClick={() => updateFilter([])}
                    />
                    {ALL_CATEGORIES.map((c) => {
                        const active = filters.categories.includes(c.id);
                        return (
                            <Chip
                                key={c.id}
                                label={c.label}
                                clickable
                                color={active ? 'primary' : 'default'}
                                variant={active ? 'filled' : 'outlined'}
                                onClick={() =>
                                    updateFilter(toggleCategory(filters.categories, c.id))
                                }
                            />
                        );
                    })}
                </Box>

                {feed.data.length === 0 && (
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ textAlign: 'center', py: 6 }}
                    >
                        No notifications yet.
                    </Typography>
                )}

                {(['today', 'yesterday', 'last7', 'older'] as const).map((bucket) => {
                    const rows = grouped[bucket];
                    if (rows.length === 0) {
                        return null;
                    }
                    return (
                        <Box key={bucket} sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                            <Typography
                                variant="overline"
                                color="text.secondary"
                                sx={{ fontWeight: 600, letterSpacing: '0.08em' }}
                            >
                                {BUCKET_LABEL[bucket]}
                            </Typography>
                            <Card>
                                <CardContent sx={{ p: 0, '&:last-child': { pb: 0 } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column' }}>
                                        {rows.map((row, idx) => {
                                            const Icon = ICON_FOR_TYPE[row.type];
                                            return (
                                                <Link
                                                    key={row.id}
                                                    href={row.action_url}
                                                    style={{ textDecoration: 'none', color: 'inherit' }}
                                                >
                                                    <Box
                                                        sx={{
                                                            display: 'flex',
                                                            alignItems: 'flex-start',
                                                            gap: 1.5,
                                                            px: 2,
                                                            py: 1.5,
                                                            borderTop: idx === 0 ? 0 : 1,
                                                            borderColor: 'divider',
                                                            transition: 'background-color 0.15s',
                                                            '&:hover': {
                                                                bgcolor: 'action.hover',
                                                            },
                                                        }}
                                                    >
                                                        <Icon
                                                            style={{
                                                                width: 20,
                                                                height: 20,
                                                                marginTop: 2,
                                                                flexShrink: 0,
                                                                color: 'rgb(107, 114, 128)',
                                                            }}
                                                        />
                                                        <Box sx={{ minWidth: 0, flex: 1 }}>
                                                            <Typography
                                                                variant="body2"
                                                                noWrap
                                                                sx={{ fontWeight: 500 }}
                                                            >
                                                                {row.actor?.name ?? 'Someone'}{' '}
                                                                <Typography
                                                                    component="span"
                                                                    variant="body2"
                                                                    sx={{ fontWeight: 400 }}
                                                                >
                                                                    {TITLE_FOR_TYPE[row.type]}
                                                                </Typography>
                                                            </Typography>
                                                            <Typography
                                                                variant="caption"
                                                                color="text.secondary"
                                                                noWrap
                                                                sx={{ display: 'block' }}
                                                            >
                                                                {row.subject.label}
                                                            </Typography>
                                                        </Box>
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                            sx={{ flexShrink: 0 }}
                                                        >
                                                            {formatRelative(row.occurred_at)}
                                                        </Typography>
                                                    </Box>
                                                </Link>
                                            );
                                        })}
                                    </Box>
                                </CardContent>
                            </Card>
                        </Box>
                    );
                })}

                {feed.current_page < feed.last_page && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', pt: 2 }}>
                        <Link
                            href={`/dashboard/notifications?page=${feed.current_page + 1}${categoriesQuery}`}
                            preserveScroll
                            style={{
                                textDecoration: 'none',
                                padding: '8px 16px',
                                border: '1px solid rgba(0,0,0,0.12)',
                                borderRadius: 6,
                                fontSize: 14,
                                color: 'inherit',
                            }}
                        >
                            Load more
                        </Link>
                    </Box>
                )}
            </Box>
        </AppLayout>
    );
}
