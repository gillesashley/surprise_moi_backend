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

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Advertisements
                        </h1>
                        <p className="text-muted-foreground">
                            Manage advertisements displayed in the app
                        </p>
                    </div>
                    <Link href="/dashboard/advertisements/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Advertisement
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Advertisements</CardTitle>
                        <CardDescription>
                            Total: {advertisements.total} advertisements
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {advertisements.data.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <p className="text-muted-foreground">
                                        No advertisements found
                                    </p>
                                    <Link href="/dashboard/advertisements/create">
                                        <Button
                                            className="mt-4"
                                            variant="outline"
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create your first advertisement
                                        </Button>
                                    </Link>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {advertisements.data.map((ad) => (
                                        <Card
                                            key={ad.id}
                                            className={
                                                ad.deleted_at
                                                    ? 'opacity-50'
                                                    : ''
                                            }
                                        >
                                            <CardContent className="flex items-start gap-4 p-4">
                                                {ad.image_path && (
                                                    <img
                                                        src={`/storage/${ad.image_path}`}
                                                        alt={ad.title}
                                                        className="h-24 w-24 rounded-md object-cover"
                                                    />
                                                )}
                                                <div className="flex-1">
                                                    <div className="flex items-start justify-between">
                                                        <div>
                                                            <h3 className="text-lg font-semibold">
                                                                {ad.title}
                                                            </h3>
                                                            {ad.description && (
                                                                <p className="text-sm text-muted-foreground">
                                                                    {
                                                                        ad.description
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            {getStatusBadge(
                                                                ad.status,
                                                            )}
                                                            {ad.deleted_at && (
                                                                <Badge variant="destructive">
                                                                    Deleted
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <div className="mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground">
                                                        <div className="flex items-center gap-1">
                                                            <Eye className="h-4 w-4" />
                                                            {ad.impressions.toLocaleString()}{' '}
                                                            impressions
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <MousePointer className="h-4 w-4" />
                                                            {ad.clicks.toLocaleString()}{' '}
                                                            clicks
                                                        </div>
                                                        <Badge variant="outline">
                                                            {ad.placement
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
                                                        </Badge>
                                                        <span>
                                                            Order:{' '}
                                                            {ad.display_order}
                                                        </span>
                                                    </div>

                                                    {ad.link_url && (
                                                        <div className="mt-2">
                                                            <a
                                                                href={
                                                                    ad.link_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-xs text-blue-600 hover:underline"
                                                            >
                                                                {ad.link_url}
                                                            </a>
                                                        </div>
                                                    )}

                                                    {!ad.deleted_at && (
                                                        <div className="mt-4 flex gap-2">
                                                            <Link
                                                                href={`/dashboard/advertisements/${ad.id}/edit`}
                                                            >
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    <Pencil className="mr-2 h-3 w-3" />
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
                                                                <Trash2 className="mr-2 h-3 w-3" />
                                                                Delete
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}

                            {advertisements.last_page > 1 && (
                                <div className="flex items-center justify-center gap-2 pt-4">
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
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
