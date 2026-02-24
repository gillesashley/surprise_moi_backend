import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    create as categoryCreate,
    destroy as categoryDestroy,
    edit as categoryEdit,
} from '@/routes/dashboard/categories';
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
        <div className="mt-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">Product Categories</h3>
                {canCreate && (
                    <Button asChild>
                        <Link href={categoryCreate.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Category
                        </Link>
                    </Button>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead>
                        <tr className="border-b">
                            <th className="p-2 text-left text-sm font-medium">
                                Name
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Slug
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Type
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
                                <td className="p-2 text-sm font-medium">
                                    {category.name}
                                </td>
                                <td className="w-fit p-2 text-sm">
                                    {category.slug}
                                </td>
                                <td className="p-2 text-sm text-muted-foreground">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                            category.type === 'service'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                        }`}
                                    >
                                        {category.type === 'service'
                                            ? 'Service'
                                            : 'Product'}
                                    </span>
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
                                            onClick={() =>
                                                onViewCategory(category)
                                            }
                                        >
                                            <Eye className="size-4" />
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
                        {categories.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    No categories found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                currentPage={categories.current_page}
                lastPage={categories.last_page}
                onPageChange={(page) => {
                    router.get('/content-management', {
                        tab: 'categories',
                        page,
                    });
                }}
            />
        </div>
    );
}
