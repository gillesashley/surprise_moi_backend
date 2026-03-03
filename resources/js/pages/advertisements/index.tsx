import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Eye, MousePointer, Pencil, Plus, Trash2 } from 'lucide-react';

interface Advertisement {
    id: number;
    title: string;
    description: string | null;
    image_path: string | null;
    link_url: string | null;
    status: 'active' | 'inactive' | 'scheduled';
    placement: 'home_banner' | 'feed' | 'popup' | 'sidebar';
    display_order: number;
    clicks: number;
    impressions: number;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    deleted_at: string | null;
    creator?: {
        id: number;
        name: string;
    };
}

interface PaginatedAdvertisements {
    data: Advertisement[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    advertisements: PaginatedAdvertisements;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Advertisements',
        href: '/dashboard/advertisements',
    },
];

export default function AdvertisementsIndex({ advertisements }: Props) {
    const { auth } = usePage<SharedData>().props;

    const handleDelete = (id: number) => {
        if (
            confirm(
                'Are you sure you want to delete this advertisement? This action cannot be undone.',
            )
        ) {
            router.delete(`/dashboard/advertisements/${id}`);
        }
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<
            string,
            'default' | 'secondary' | 'destructive' | 'outline'
        > = {
            active: 'default',
            inactive: 'secondary',
            scheduled: 'outline',
        };

        return (
            <Badge variant={variants[status] || 'default'}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Advertisements" />

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="h4" sx={{ fontWeight: 700, letterSpacing: '-0.025em' }}>
                            Advertisements
                        </Typography>
                        <Typography sx={{ color: 'text.secondary' }}>
                            Manage advertisements displayed in the app
                        </Typography>
                    </Box>
                    <Link href="/dashboard/advertisements/create">
                        <Button>
                            <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                            Create Advertisement
                        </Button>
                    </Link>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>All Advertisements</CardTitle>
                        <CardDescription>
                            Total: {advertisements.total} advertisements
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            {advertisements.data.length === 0 ? (
                                <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', py: 6, textAlign: 'center' }}>
                                    <Typography sx={{ color: 'text.secondary' }}>
                                        No advertisements found
                                    </Typography>
                                    <Link href="/dashboard/advertisements/create">
                                        <Button
                                            style={{ marginTop: 16 }}
                                            variant="outline"
                                        >
                                            <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                            Create your first advertisement
                                        </Button>
                                    </Link>
                                </Box>
                            ) : (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                    {advertisements.data.map((ad) => (
                                        <Card
                                            key={ad.id}
                                            style={
                                                ad.deleted_at
                                                    ? { opacity: 0.5 }
                                                    : undefined
                                            }
                                        >
                                            <CardContent>
                                                <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2, p: 2 }}>
                                                {ad.image_path && (
                                                    <Box
                                                        component="img"
                                                        src={`/storage/${ad.image_path}`}
                                                        alt={ad.title}
                                                        sx={{ height: 96, width: 96, borderRadius: 1, objectFit: 'cover' }}
                                                    />
                                                )}
                                                <Box sx={{ flex: 1 }}>
                                                    <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                                                        <Box>
                                                            <Typography variant="h6" sx={{ fontWeight: 600 }}>
                                                                {ad.title}
                                                            </Typography>
                                                            {ad.description && (
                                                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                                    {
                                                                        ad.description
                                                                    }
                                                                </Typography>
                                                            )}
                                                        </Box>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            {getStatusBadge(
                                                                ad.status,
                                                            )}
                                                            {ad.deleted_at && (
                                                                <Badge variant="destructive">
                                                                    Deleted
                                                                </Badge>
                                                            )}
                                                        </Box>
                                                    </Box>

                                                    <Box sx={{ mt: 1, display: 'flex', flexWrap: 'wrap', gap: 2, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                            <Eye style={{ width: 16, height: 16 }} />
                                                            {ad.impressions.toLocaleString()}{' '}
                                                            impressions
                                                        </Box>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                            <MousePointer style={{ width: 16, height: 16 }} />
                                                            {ad.clicks.toLocaleString()}{' '}
                                                            clicks
                                                        </Box>
                                                        <Chip
                                                            variant="outlined"
                                                            size="small"
                                                            label={ad.placement
                                                                .split('_')
                                                                .map(
                                                                    (word) =>
                                                                        word
                                                                            .charAt(
                                                                                0,
                                                                            )
                                                                            .toUpperCase() +
                                                                        word.slice(
                                                                            1,
                                                                        ),
                                                                )
                                                                .join(' ')}
                                                        />
                                                        <Box component="span">
                                                            Order:{' '}
                                                            {ad.display_order}
                                                        </Box>
                                                    </Box>

                                                    {ad.link_url && (
                                                        <Box sx={{ mt: 1 }}>
                                                            <Box
                                                                component="a"
                                                                href={
                                                                    ad.link_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                sx={{ fontSize: '0.75rem', color: 'info.main', '&:hover': { textDecoration: 'underline' } }}
                                                            >
                                                                {ad.link_url}
                                                            </Box>
                                                        </Box>
                                                    )}

                                                    {!ad.deleted_at && (
                                                        <Box sx={{ mt: 2, display: 'flex', gap: 1 }}>
                                                            <Link
                                                                href={`/dashboard/advertisements/${ad.id}/edit`}
                                                            >
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    <Pencil style={{ marginRight: 8, width: 12, height: 12 }} />
                                                                    Edit
                                                                </Button>
                                                            </Link>
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        ad.id,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 style={{ marginRight: 8, width: 12, height: 12 }} />
                                                                Delete
                                                            </Button>
                                                        </Box>
                                                    )}
                                                </Box>
                                                </Box>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </Box>
                            )}

                            {advertisements.last_page > 1 && (
                                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 1, pt: 2 }}>
                                    {Array.from(
                                        { length: advertisements.last_page },
                                        (_, i) => i + 1,
                                    ).map((page) => (
                                        <Link
                                            key={page}
                                            href={`/dashboard/advertisements?page=${page}`}
                                            preserveState
                                        >
                                            <Button
                                                variant={
                                                    page ===
                                                    advertisements.current_page
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                            >
                                                {page}
                                            </Button>
                                        </Link>
                                    ))}
                                </Box>
                            )}
                        </Box>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
