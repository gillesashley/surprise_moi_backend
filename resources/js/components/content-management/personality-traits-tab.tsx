import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    create as personalityTraitCreate,
    destroy as personalityTraitDestroy,
    edit as personalityTraitEdit,
} from '@/routes/dashboard/personality-traits';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface PersonalityTrait {
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
    personalityTraits: PaginatedData<PersonalityTrait>;
    canCreate: boolean;
    canDelete: boolean;
}

export function PersonalityTraitsTab({
    personalityTraits,
    canCreate,
    canDelete,
}: Props) {
    const handleDelete = (traitId: number, traitName: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${traitName}"? This action cannot be undone.`,
            )
        ) {
            router.delete(personalityTraitDestroy.url(traitId), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="mt-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">Personality Traits</h3>
                {canCreate && (
                    <Button asChild>
                        <Link href={personalityTraitCreate.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Trait
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
                        {personalityTraits.data.map((trait) => (
                            <tr
                                key={trait.id}
                                className="border-b last:border-0 hover:bg-muted/50"
                            >
                                <td className="p-2 text-sm font-medium">
                                    {trait.name}
                                </td>
                                <td className="p-2 text-sm">
                                    {trait.icon || '-'}
                                </td>
                                <td className="p-2 text-sm">
                                    {trait.users_count}
                                </td>
                                <td className="p-2">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={personalityTraitEdit.url(
                                                    trait.id,
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
                                                        trait.id,
                                                        trait.name,
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
                        {personalityTraits.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    No personality traits found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                currentPage={personalityTraits.current_page}
                lastPage={personalityTraits.last_page}
                onPageChange={(page) => {
                    router.get('/content-management', {
                        tab: 'traits',
                        page,
                    });
                }}
            />
        </div>
    );
}
