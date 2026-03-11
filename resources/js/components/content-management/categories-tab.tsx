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
    create as categoryCreate,
    destroy as categoryDestroy,
    edit as categoryEdit,
} from '@/routes/dashboard/categories';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

interface Category {
    id: number;
    name: string;
    slug: string;
    type: 'product' | 'service';
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
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
    categories: PaginatedData<Category>;
    canCreate: boolean;
    canDelete: boolean;
    onViewCategory: (category: Category) => void;
}

export function CategoriesTab({
    categories,
    canCreate,
    canDelete,
    onViewCategory,
}: Props) {
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
                    Product Categories
                </Typography>
                {canCreate && (
                    <Button asChild>
                        <Link href={categoryCreate.url()}>
                            <Plus style={{ width: 16, height: 16, marginRight: 8 }} />
                            Add Category
                        </Link>
                    </Button>
                )}
            </Box>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Products</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Order</TableHead>
                        <TableHead style={{ textAlign: 'right' }}>
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {categories.data.map((category) => (
                        <TableRow key={category.id}>
                            <TableCell>
                                <Typography variant="body2" fontWeight={500}>
                                    {category.name}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {category.slug}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Chip
                                    label={
                                        category.type === 'service'
                                            ? 'Service'
                                            : 'Product'
                                    }
                                    size="small"
                                    color={
                                        category.type === 'service'
                                            ? 'info'
                                            : 'secondary'
                                    }
                                    variant="outlined"
                                />
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {category.products_count}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Chip
                                    label={
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }
                                    size="small"
                                    color={
                                        category.is_active
                                            ? 'success'
                                            : 'default'
                                    }
                                    variant="outlined"
                                />
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {category.sort_order}
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
                                        onClick={() =>
                                            onViewCategory(category)
                                        }
                                    >
                                        <Eye style={{ width: 16, height: 16 }} />
                                    </Button>
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
                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--mui-palette-error-main, #d32f2f)' }} />
                                        </Button>
                                    )}
                                </Box>
                            </TableCell>
                        </TableRow>
                    ))}
                    {categories.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={7}>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ textAlign: 'center', p: 2 }}
                                >
                                    No categories found
                                </Typography>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <Pagination
                currentPage={categories.current_page}
                lastPage={categories.last_page}
                onPageChange={(page) => {
                    router.get('/dashboard/content-management', {
                        tab: 'categories',
                        categories_page: page,
                    });
                }}
            />
        </Box>
    );
}
