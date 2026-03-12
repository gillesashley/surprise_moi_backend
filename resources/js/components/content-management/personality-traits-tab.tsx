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
    create as personalityTraitCreate,
    destroy as personalityTraitDestroy,
    edit as personalityTraitEdit,
} from '@/routes/dashboard/personality-traits';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
    search?: string;
}

export function PersonalityTraitsTab({
    personalityTraits,
    canCreate,
    canDelete,
    search,
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
                    Personality Traits
                </Typography>
                {canCreate && (
                    <Button asChild>
                        <Link href={personalityTraitCreate.url()}>
                            <Plus style={{ width: 16, height: 16, marginRight: 8 }} />
                            Add Trait
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
                    {personalityTraits.data.map((trait) => (
                        <TableRow key={trait.id}>
                            <TableCell>
                                <Typography variant="body2" fontWeight={500}>
                                    {trait.name}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {trait.icon || '-'}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {trait.users_count}
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
                                            href={personalityTraitEdit.url(
                                                trait.id,
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
                                                    trait.id,
                                                    trait.name,
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
                    {personalityTraits.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4}>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ textAlign: 'center', p: 2 }}
                                >
                                    No personality traits found
                                </Typography>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <Pagination
                currentPage={personalityTraits.current_page}
                lastPage={personalityTraits.last_page}
                onPageChange={(page) => {
                    router.get('/dashboard/content-management', {
                        tab: 'traits',
                        traits_page: page,
                        ...(search ? { search } : {}),
                    });
                }}
            />
        </Box>
    );
}
