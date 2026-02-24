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
    create as interestCreate,
    destroy as interestDestroy,
    edit as interestEdit,
    index as interestsIndex,
} from '@/routes/dashboard/interests';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Interest {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface PaginatedInterests {
    data: Interest[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    interests: PaginatedInterests;
    canCreate: boolean;
    canDelete: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Interests',
        href: interestsIndex().url,
    },
];

export default function InterestsIndex({
    interests,
    canCreate,
    canDelete,
}: Props) {
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

    const handlePageChange = (page: number) => {
        router.get(
            interestsIndex.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Interests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Interests</CardTitle>
                            <CardDescription>
                                Manage user interests for personalization
                            </CardDescription>
                        </div>
                        {canCreate && (
                            <Button asChild>
                                <Link href={interestCreate.url()}>
                                    <Plus className="mr-2 size-4" />
                                    Add Interest
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
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {interests.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {interests.data.length} of{' '}
                                    {interests.total} interests
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                interests.current_page - 1,
                                            )
                                        }
                                        disabled={interests.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <span className="flex items-center px-2 text-sm">
                                        Page {interests.current_page} of{' '}
                                        {interests.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                interests.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            interests.current_page ===
                                            interests.last_page
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
