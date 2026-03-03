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
    index as categoriesIndex,
    create as categoryCreate,
    destroy as categoryDestroy,
    edit as categoryEdit,
} from '@/routes/dashboard/categories';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    created_at: string;
}

interface PaginatedCategories {
    data: Category[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    categories: PaginatedCategories;
    canCreate: boolean;
    canDelete: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categories',
        href: categoriesIndex().url,
    },
];

export default function CategoriesIndex({
    categories,
    canCreate,
    canDelete,
}: Props) {
    const { auth } = usePage<SharedData>().props;

    const handleDelete = (categoryId: number, categoryName: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${categoryName}"? This action cannot be undone.`,
            )
        ) {
            router.delete(categoryDestroy.url(categoryId), {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            categoriesIndex.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardTitle>Categories</CardTitle>
                            <CardDescription>
                                Manage product categories
                            </CardDescription>
                        </Box>
                        {canCreate && (
                            <Button asChild>
                                <Link href={categoryCreate.url()}>
                                    <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                    Add Category
                                </Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Image
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Name
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Slug
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Products
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Status
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Order
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {categories.data.map((category) => (
                                        <Box
                                            component="tr"
                                            key={category.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { border: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1 }}>
                                                {category.image ? (
                                                    <Box
                                                        component="img"
                                                        src={category.image}
                                                        alt={category.name}
                                                        sx={{ height: 40, width: 40, borderRadius: 1, objectFit: 'cover' }}
                                                    />
                                                ) : (
                                                    <Box sx={{ height: 40, width: 40, borderRadius: 1, bgcolor: 'action.hover' }} />
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                                {category.icon && (
                                                    <Box component="span" sx={{ mr: 1 }}>
                                                        {category.icon}
                                                    </Box>
                                                )}
                                                {category.name}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {category.slug}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {category.products_count}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                <Chip
                                                    label={category.is_active ? 'Active' : 'Inactive'}
                                                    color={category.is_active ? 'success' : 'default'}
                                                    size="small"
                                                    variant="outlined"
                                                />
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {category.sort_order}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={categoryEdit.url(
                                                                category.id,
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
                                                                    category.id,
                                                                    category.name,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 style={{ width: 16, height: 16, color: '#ef4444' }} />
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
                        {categories.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {categories.data.length} of{' '}
                                    {categories.total} categories
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                categories.current_page - 1,
                                            )
                                        }
                                        disabled={categories.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {categories.current_page} of{' '}
                                        {categories.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                categories.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            categories.current_page ===
                                            categories.last_page
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
