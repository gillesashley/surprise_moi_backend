import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    create as musicGenreCreate,
    destroy as musicGenreDestroy,
    edit as musicGenreEdit,
} from '@/routes/dashboard/music-genres';
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
}

export function MusicGenresTab({ musicGenres, canCreate, canDelete }: Props) {
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
        <div className="mt-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">Music Genres</h3>
                {canCreate && (
                    <Button asChild>
                        <Link href={musicGenreCreate.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Genre
                        </Link>
                    </Button>
                )}
            </div>
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
                        {musicGenres.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    No music genres found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                currentPage={musicGenres.current_page}
                lastPage={musicGenres.last_page}
                onPageChange={(page) => {
                    router.get('/content-management', {
                        tab: 'music',
                        page,
                    });
                }}
            />
        </div>
    );
}
