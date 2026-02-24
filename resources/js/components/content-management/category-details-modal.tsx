import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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

interface Props {
    category: Category | null;
    onClose: () => void;
}

export function CategoryDetailsModal({ category, onClose }: Props) {
    return (
        <Dialog open={!!category} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-2xl" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Category Details</DialogTitle>
                </DialogHeader>
                {category && (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Name
                                </h4>
                                <p className="text-base font-medium">
                                    {category.name}
                                </p>
                            </div>
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Type
                                </h4>
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
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Slug
                                </h4>
                                <p className="text-base text-muted-foreground">
                                    {category.slug}
                                </p>
                            </div>
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Products
                                </h4>
                                <p className="text-base">
                                    {category.products_count}
                                </p>
                            </div>
                        </div>

                        {category.description && (
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Description
                                </h4>
                                <p className="text-base">
                                    {category.description}
                                </p>
                            </div>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            {category.icon && (
                                <div>
                                    <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                        Icon
                                    </h4>
                                    <div className="flex items-center gap-2">
                                        <img
                                            src={
                                                category.icon.startsWith('http')
                                                    ? category.icon
                                                    : category.icon.startsWith(
                                                            '/',
                                                        )
                                                      ? category.icon
                                                      : `/${category.icon}`
                                            }
                                            alt="Category icon"
                                            className="h-12 w-12 object-contain"
                                            onError={(e) => {
                                                e.currentTarget.style.display =
                                                    'none';
                                            }}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        {category.image && (
                            <div>
                                <h4 className="mb-2 text-sm font-medium text-muted-foreground">
                                    Image
                                </h4>
                                <img
                                    src={
                                        category.image.startsWith('http')
                                            ? category.image
                                            : category.image.startsWith('/')
                                              ? category.image
                                              : `/storage/${category.image}`
                                    }
                                    alt={category.name}
                                    className="w-full max-w-md rounded-lg border border-input object-cover"
                                    onError={(e) => {
                                        e.currentTarget.src =
                                            '/placeholder-image.png';
                                    }}
                                />
                            </div>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Sort Order
                                </h4>
                                <p className="text-base">
                                    {category.sort_order}
                                </p>
                            </div>
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-muted-foreground">
                                    Products
                                </h4>
                                <p className="text-base">
                                    {category.products_count}
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
