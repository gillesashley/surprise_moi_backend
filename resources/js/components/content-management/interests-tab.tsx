import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    create as interestCreate,
    destroy as interestDestroy,
    edit as interestEdit,
} from '@/routes/dashboard/interests';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Interest {
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
    interests: PaginatedData<Interest>;
    canCreate: boolean;
    canDelete: boolean;
}

export function InterestsTab({ interests, canCreate, canDelete }: Props) {
    const handleDelete = (interestId: number, interestName: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${interestName}"? This action cannot be undone.`,
            )
        ) {
            router.delete(interestDestroy.url(interestId), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="mt-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">User Interests</h3>
                {canCreate && (
                    <Button asChild>
                        <Link href={interestCreate.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Interest
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
                        {interests.data.map((interest) => (
                            <tr
                                key={interest.id}
                                className="border-b last:border-0 hover:bg-muted/50"
                            >
                                <td className="p-2 text-sm font-medium">
                                    {interest.name}
                                </td>
                                <td className="p-2 text-sm">
                                    {interest.icon || '-'}
                                </td>
                                <td className="p-2 text-sm">
                                    {interest.users_count}
                                </td>
                                <td className="p-2">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={interestEdit.url(
                                                    interest.id,
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
                                                        interest.id,
                                                        interest.name,
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
                        {interests.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    No interests found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                currentPage={interests.current_page}
                lastPage={interests.last_page}
                onPageChange={(page) => {
                    router.get('/content-management', {
                        tab: 'interests',
                        page,
                    });
                }}
            />
        </div>
    );
}
