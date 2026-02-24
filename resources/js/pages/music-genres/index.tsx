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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Music Genres</CardTitle>
                            <CardDescription>
                                Manage music genres for user personalization
                            </CardDescription>
                        </div>
                        {canCreate && (
                            <Button asChild>
                                <Link href={musicGenreCreate.url()}>
                                    <Plus className="mr-2 size-4" />
                                    Add Genre
                                </Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2 text-left text-sm font-medium">
                                            Name
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Icon
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Users
                                        </th>
                                        <th className="p-2 text-right text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {musicGenres.data.map((genre) => (
                                        <tr
                                            key={genre.id}
                                            className="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td className="p-2 text-sm font-medium">
                                                {genre.name}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {genre.icon || '-'}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {genre.users_count}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex justify-end gap-2">
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
                                                            <Pencil className="size-4" />
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
                                                            <Trash2 className="size-4 text-destructive" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {musicGenres.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {musicGenres.data.length} of{' '}
                                    {musicGenres.total} genres
                                </p>
                                <div className="flex gap-2">
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
                                    <span className="flex items-center px-2 text-sm">
                                        Page {musicGenres.current_page} of{' '}
                                        {musicGenres.last_page}
                                    </span>
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
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
