import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import {
    edit as userEdit,
    show as userShow,
    index as usersIndex,
} from '@/routes/users';
import { type BreadcrumbItem, type PaginatedUsers } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    users: PaginatedUsers;
    roles: string[];
    canDelete: boolean;
    filters: {
        search?: string;
        sort_by?: string;
        sort_order?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: usersIndex().url,
    },
];

const formatRole = (role: string) => {
    return role
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case 'super_admin':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'admin':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'vendor':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

export default function UsersIndex({ users, canDelete, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get(
                    usersIndex.url(),
                    { search: searchTerm, page: 1 },
                    {
                        preserveState: true,
                        preserveScroll: true,
                    },
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    const handleDelete = (userId: number, userName: string) => {
        if (
            confirm(
                `Are you sure you want to delete ${userName}? This action cannot be undone.`,
            )
        ) {
            router.delete(userShow.url(userId), {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            usersIndex.url(),
            {
                page,
                search: filters.search,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleSort = (column: string) => {
        let newSortOrder = 'asc';
        if (filters.sort_by === column && filters.sort_order === 'asc') {
            newSortOrder = 'desc';
        }

        router.get(
            usersIndex.url(),
            {
                sort_by: column,
                sort_order: newSortOrder,
                search: filters.search,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const getSortIcon = (column: string) => {
        if (filters.sort_by !== column) return null;
        return filters.sort_order === 'asc' ? (
            <ArrowUp className="ml-1 inline size-4" />
        ) : (
            <ArrowDown className="ml-1 inline size-4" />
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>User Management</CardTitle>
                        <CardDescription>
                            Manage all registered users in the system
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Search Bar */}
                        <div className="mb-4 flex items-center gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder="Search by name, email, or phone..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th
                                            className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                            onClick={() => handleSort('name')}
                                        >
                                            Name{getSortIcon('name')}
                                        </th>
                                        <th
                                            className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                            onClick={() => handleSort('email')}
                                        >
                                            Email{getSortIcon('email')}
                                        </th>
                                        <th
                                            className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                            onClick={() => handleSort('phone')}
                                        >
                                            Phone{getSortIcon('phone')}
                                        </th>
                                        <th
                                            className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                            onClick={() => handleSort('role')}
                                        >
                                            Role{getSortIcon('role')}
                                        </th>
                                        <th
                                            className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                            onClick={() =>
                                                handleSort('created_at')
                                            }
                                        >
                                            Joined{getSortIcon('created_at')}
                                        </th>
                                        <th className="p-2 text-right text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td className="p-2 text-sm">
                                                {user.name}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {user.email}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {user.phone || '-'}
                                            </td>
                                            <td className="p-2 text-sm">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${getRoleBadgeColor(user.role || 'customer')}`}
                                                >
                                                    {formatRole(
                                                        user.role || 'customer',
                                                    )}
                                                </span>
                                            </td>
                                            <td className="p-2 text-sm">
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={userShow.url(
                                                                user.id,
                                                            )}
                                                        >
                                                            <Eye className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={userEdit.url(
                                                                user.id,
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
                                                                    user.id,
                                                                    user.name,
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
                        {users.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {users.data.length} of {users.total}{' '}
                                    users
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                users.current_page - 1,
                                            )
                                        }
                                        disabled={users.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <span className="flex items-center px-2 text-sm">
                                        Page {users.current_page} of{' '}
                                        {users.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                users.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            users.current_page ===
                                            users.last_page
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
