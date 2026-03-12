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
    create as interestCreate,
    destroy as interestDestroy,
    edit as interestEdit,
} from '@/routes/dashboard/interests';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
    search?: string;
}

export function InterestsTab({ interests, canCreate, canDelete, search }: Props) {
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
                    User Interests
                </Typography>
                {canCreate && (
                    <Button asChild>
                        <Link href={interestCreate.url()}>
                            <Plus style={{ width: 16, height: 16, marginRight: 8 }} />
                            Add Interest
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
                        <TableHead sx={{ textAlign: 'right' }}>
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {interests.data.map((interest) => (
                        <TableRow key={interest.id}>
                            <TableCell>
                                <Typography variant="body2" fontWeight={500}>
                                    {interest.name}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {interest.icon || '-'}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {interest.users_count}
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
                                            href={interestEdit.url(
                                                interest.id,
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
                                                    interest.id,
                                                    interest.name,
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
                    {interests.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4}>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ textAlign: 'center', p: 2 }}
                                >
                                    No interests found
                                </Typography>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <Pagination
                currentPage={interests.current_page}
                lastPage={interests.last_page}
                onPageChange={(page) => {
                    router.get('/dashboard/content-management', {
                        tab: 'interests',
                        interests_page: page,
                        ...(search ? { search } : {}),
                    });
                }}
            />
        </Box>
    );
}
