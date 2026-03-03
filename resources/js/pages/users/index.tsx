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
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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

const getRoleBadgeColor = (role: string): 'error' | 'secondary' | 'info' | 'default' => {
    switch (role) {
        case 'super_admin':
            return 'error';
        case 'admin':
            return 'secondary';
        case 'vendor':
            return 'info';
        default:
            return 'default';
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
            <ArrowUp style={{ marginLeft: 4, display: 'inline', width: 16, height: 16 }} />
        ) : (
            <ArrowDown style={{ marginLeft: 4, display: 'inline', width: 16, height: 16 }} />
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <CardTitle>User Management</CardTitle>
                        <CardDescription>
                            Manage all registered users in the system
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Search Bar */}
                        <Box sx={{ mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ position: 'relative', flex: 1 }}>
                                <Search style={{ position: 'absolute', top: 10, left: 10, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Input
                                    type="search"
                                    placeholder="Search by name, email, or phone..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    style={{ paddingLeft: 36 }}
                                />
                            </Box>
                        </Box>

                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box
                                            component="th"
                                            sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                            onClick={() => handleSort('name')}
                                        >
                                            Name{getSortIcon('name')}
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                            onClick={() => handleSort('email')}
                                        >
                                            Email{getSortIcon('email')}
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                            onClick={() => handleSort('phone')}
                                        >
                                            Phone{getSortIcon('phone')}
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                            onClick={() => handleSort('role')}
                                        >
                                            Role{getSortIcon('role')}
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                            onClick={() =>
                                                handleSort('created_at')
                                            }
                                        >
                                            Joined{getSortIcon('created_at')}
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <Box
                                            component="tr"
                                            key={user.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { border: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {user.name}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {user.email}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {user.phone || '-'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                <Chip
                                                    label={formatRole(user.role || 'customer')}
                                                    color={getRoleBadgeColor(user.role || 'customer')}
                                                    size="small"
                                                    variant="outlined"
                                                />
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
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
                                                            <Eye style={{ width: 16, height: 16 }} />
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
                                                            <Pencil style={{ width: 16, height: 16 }} />
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
                                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--destructive)' }} />
                                                        </Button>
                                                    )}
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </tbody>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {users.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {users.data.length} of {users.total}{' '}
                                    users
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
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
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {users.current_page} of{' '}
                                        {users.last_page}
                                    </Box>
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
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
