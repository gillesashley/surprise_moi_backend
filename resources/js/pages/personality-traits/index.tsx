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
    create as personalityTraitCreate,
    destroy as personalityTraitDestroy,
    edit as personalityTraitEdit,
    index as personalityTraitsIndex,
} from '@/routes/dashboard/personality-traits';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface PersonalityTrait {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface PaginatedPersonalityTraits {
    data: PersonalityTrait[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    personalityTraits: PaginatedPersonalityTraits;
    canCreate: boolean;
    canDelete: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Personality Traits',
        href: personalityTraitsIndex().url,
    },
];

export default function PersonalityTraitsIndex({
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

    const handlePageChange = (page: number) => {
        router.get(
            personalityTraitsIndex.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Personality Traits" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Personality Traits</CardTitle>
                            <CardDescription>
                                Manage user personality traits for
                                personalization
                            </CardDescription>
                        </div>
                        {canCreate && (
                            <Button asChild>
                                <Link href={personalityTraitCreate.url()}>
                                    <Plus className="mr-2 size-4" />
                                    Add Trait
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
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {personalityTraits.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {personalityTraits.data.length} of{' '}
                                    {personalityTraits.total} traits
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                personalityTraits.current_page -
                                                    1,
                                            )
                                        }
                                        disabled={
                                            personalityTraits.current_page === 1
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <span className="flex items-center px-2 text-sm">
                                        Page {personalityTraits.current_page} of{' '}
                                        {personalityTraits.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                personalityTraits.current_page +
                                                    1,
                                            )
                                        }
                                        disabled={
                                            personalityTraits.current_page ===
                                            personalityTraits.last_page
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
