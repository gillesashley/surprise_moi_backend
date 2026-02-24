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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Categories</CardTitle>
                            <CardDescription>
                                Manage product categories
                            </CardDescription>
                        </div>
                        {canCreate && (
                            <Button asChild>
                                <Link href={categoryCreate.url()}>
                                    <Plus className="mr-2 size-4" />
                                    Add Category
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
                                            Image
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Name
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Slug
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Products
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Status
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Order
                                        </th>
                                        <th className="p-2 text-right text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.data.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td className="p-2">
                                                {category.image ? (
                                                    <img
                                                        src={category.image}
                                                        alt={category.name}
                                                        className="h-10 w-10 rounded object-cover"
                                                    />
                                                ) : (
                                                    <div className="h-10 w-10 rounded bg-muted"></div>
                                                )}
                                            </td>
                                            <td className="p-2 text-sm font-medium">
                                                {category.icon && (
                                                    <span className="mr-2">
                                                        {category.icon}
                                                    </span>
                                                )}
                                                {category.name}
                                            </td>
                                            <td className="p-2 text-sm text-muted-foreground">
                                                {category.slug}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {category.products_count}
                                            </td>
                                            <td className="p-2 text-sm">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                        category.is_active
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                                    }`}
                                                >
                                                    {category.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="p-2 text-sm">
                                                {category.sort_order}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex justify-end gap-2">
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
                                                            <Pencil className="size-4" />
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
                        {categories.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {categories.data.length} of{' '}
                                    {categories.total} categories
                                </p>
                                <div className="flex gap-2">
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
                                    <span className="flex items-center px-2 text-sm">
                                        Page {categories.current_page} of{' '}
                                        {categories.last_page}
                                    </span>
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
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
