import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create as musicGenreCreate,
    destroy as musicGenreDestroy,
    edit as musicGenreEdit,
} from '@/routes/dashboard/music-genres';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface MusicGenre {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    musicGenres: PaginatedData<MusicGenre>;
    canCreate: boolean;
    canDelete: boolean;
    search?: string;
}

export function MusicGenresTab({ musicGenres, canCreate, canDelete, search }: Props) {
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

    return (
        <Box sx={{ mt: 3 }}>
            <Box
                sx={{
                    mb: 2,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <Typography variant="h6" fontWeight={600}>
                    Music Genres
                </Typography>
                {canCreate && (
                    <Button asChild>
                        <Link href={musicGenreCreate.url()}>
                            <Plus style={{ width: 16, height: 16, marginRight: 8 }} />
                            Add Genre
                        </Link>
                    </Button>
                )}
            </Box>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Icon</TableHead>
                        <TableHead>Users</TableHead>
                        <TableHead style={{ textAlign: 'right' }}>
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {musicGenres.data.map((genre) => (
                        <TableRow key={genre.id}>
                            <TableCell>
                                <Typography variant="body2" fontWeight={500}>
                                    {genre.name}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {genre.icon || '-'}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {genre.users_count}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        justifyContent: 'flex-end',
                                        gap: 1,
                                    }}
                                >
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
                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--mui-palette-error-main, #d32f2f)' }} />
                                        </Button>
                                    )}
                                </Box>
                            </TableCell>
                        </TableRow>
                    ))}
                    {musicGenres.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4}>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ textAlign: 'center', p: 2 }}
                                >
                                    No music genres found
                                </Typography>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <Pagination
                currentPage={musicGenres.current_page}
                lastPage={musicGenres.last_page}
                onPageChange={(page) => {
                    router.get('/dashboard/content-management', {
                        tab: 'music',
                        music_page: page,
                        ...(search ? { search } : {}),
                    });
                }}
            />
        </Box>
    );
}
