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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardTitle>Personality Traits</CardTitle>
                            <CardDescription>
                                Manage user personality traits for
                                personalization
                            </CardDescription>
                        </Box>
                        {canCreate && (
                            <Button asChild>
                                <Link href={personalityTraitCreate.url()}>
                                    <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                    Add Trait
                                </Link>
                            </Button>
                        )}
                        </Box>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <Box component="thead">
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Name
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Icon
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Users
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </Box>
                                <Box component="tbody">
                                    {personalityTraits.data.map((trait) => (
                                        <Box
                                            component="tr"
                                            key={trait.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { borderBottom: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                                {trait.name}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {trait.icon || '-'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {trait.users_count}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
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
                                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--destructive)' }} />
                                                        </Button>
                                                    )}
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </Box>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {personalityTraits.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {personalityTraits.data.length} of{' '}
                                    {personalityTraits.total} traits
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
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
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {personalityTraits.current_page} of{' '}
                                        {personalityTraits.last_page}
                                    </Box>
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
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
