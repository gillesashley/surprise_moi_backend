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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardTitle>Interests</CardTitle>
                            <CardDescription>
                                Manage user interests for personalization
                            </CardDescription>
                        </Box>
                        {canCreate && (
                            <Button asChild>
                                <Link href={interestCreate.url()}>
                                    <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                    Add Interest
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
                                    {interests.data.map((interest) => (
                                        <Box
                                            component="tr"
                                            key={interest.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { borderBottom: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                                {interest.name}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {interest.icon || '-'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {interest.users_count}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
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
                        {interests.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {interests.data.length} of{' '}
                                    {interests.total} interests
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
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
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {interests.current_page} of{' '}
                                        {interests.last_page}
                                    </Box>
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
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
