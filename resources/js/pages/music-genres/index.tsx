import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    create as musicGenreCreate,
    destroy as musicGenreDestroy,
    edit as musicGenreEdit,
    index as musicGenresIndex,
} from '@/routes/dashboard/music-genres';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface MusicGenre {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface PaginatedMusicGenres {
    data: MusicGenre[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    musicGenres: PaginatedMusicGenres;
    canCreate: boolean;
    canDelete: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Music Genres',
        href: musicGenresIndex().url,
    },
];

export default function MusicGenresIndex({
    musicGenres,
    canCreate,
    canDelete,
}: Props) {
    const handleDelete = (genreId: number, genreName: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${genreName}"? This action cannot be undone.`,
            )
        ) {
            router.delete(musicGenreDestroy.url(genreId), {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            musicGenresIndex.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Music Genres" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardTitle>Music Genres</CardTitle>
                            <CardDescription>
                                Manage music genres for user personalization
                            </CardDescription>
                        </Box>
                        {canCreate && (
                            <Button asChild>
                                <Link href={musicGenreCreate.url()}>
                                    <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                    Add Genre
                                </Link>
                            </Button>
                        )}
                        </Box>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <Box component="thead">
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Name
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Icon
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Users
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </Box>
                                <Box component="tbody">
                                    {musicGenres.data.map((genre) => (
                                        <Box
                                            component="tr"
                                            key={genre.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { borderBottom: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                                {genre.name}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {genre.icon || '-'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {genre.users_count}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={musicGenreEdit.url(
                                                                genre.id,
                                                            )}
                                                        >
                                                            <Pencil style={{ width: 16, height: 16 }} />
                                                        </Link>
                                                    </Button>
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    genre.id,
                                                                    genre.name,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--destructive)' }} />
                                                        </Button>
                                                    )}
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </Box>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {musicGenres.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {musicGenres.data.length} of{' '}
                                    {musicGenres.total} genres
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                musicGenres.current_page - 1,
                                            )
                                        }
                                        disabled={
                                            musicGenres.current_page === 1
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {musicGenres.current_page} of{' '}
                                        {musicGenres.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                musicGenres.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            musicGenres.current_page ===
                                            musicGenres.last_page
                                        }
                                    >
                                        Next
                                    </Button>
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
