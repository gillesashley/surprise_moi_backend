import ContentManagementController from '@/actions/App/Http/Controllers/ContentManagementController';
import ImageUpload from '@/components/image-upload';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { update as categoryUpdate } from '@/routes/dashboard/categories';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

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
}

interface Props {
    category: Category;
}

const breadcrumbs = (category: Category): BreadcrumbItem[] => [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: category.name,
        href: categoryUpdate.url(category.id),
    },
];

export default function CategoryEdit({ category }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(category)}>
            <Head title={`Edit: ${category.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'categories' },
                            })}
                        >
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Content Management
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Edit Category</CardTitle>
                        <CardDescription>
                            Update category information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={categoryUpdate.url(category.id)}
                            method="post"
                            encType="multipart/form-data"
                        >
                            {({ errors, processing, wasSuccessful }) => (
                                <div className="space-y-4">
                                    <input
                                        type="hidden"
                                        name="_method"
                                        value="PUT"
                                    />
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={category.name}
                                            required
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="type">Type</Label>
                                        <Select
                                            name="type"
                                            defaultValue={category.type}
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="product">
                                                    Product
                                                </SelectItem>
                                                <SelectItem value="service">
                                                    Service
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.type && (
                                            <p className="text-sm text-destructive">
                                                {errors.type}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            defaultValue={
                                                category.description || ''
                                            }
                                            rows={3}
                                            placeholder="Detailed description for this category"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-destructive">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>

                                    <ImageUpload
                                        name="image"
                                        label="Category Image"
                                        helperText="Upload a representative image for this category"
                                        existingImage={category.image}
                                        error={errors.image as string}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="icon">
                                            Icon (emoji)
                                        </Label>
                                        <Input
                                            id="icon"
                                            name="icon"
                                            defaultValue={category.icon || ''}
                                        />
                                        {errors.icon && (
                                            <p className="text-sm text-destructive">
                                                {errors.icon}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="sort_order">
                                            Sort Order
                                        </Label>
                                        <Input
                                            id="sort_order"
                                            name="sort_order"
                                            type="number"
                                            defaultValue={category.sort_order}
                                        />
                                        {errors.sort_order && (
                                            <p className="text-sm text-destructive">
                                                {errors.sort_order}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={category.is_active}
                                            className="size-4"
                                        />
                                        <Label htmlFor="is_active">
                                            Active
                                        </Label>
                                    </div>

                                    {wasSuccessful && (
                                        <p className="text-sm text-green-600">
                                            Category updated successfully!
                                        </p>
                                    )}

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Save Changes'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={ContentManagementController.index.url(
                                                    {
                                                        query: {
                                                            tab: 'categories',
                                                        },
                                                    },
                                                )}
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
