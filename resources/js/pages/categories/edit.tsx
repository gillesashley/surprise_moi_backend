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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'categories' },
                            })}
                        >
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Content Management
                        </Link>
                    </Button>
                </Box>

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
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <input
                                        type="hidden"
                                        name="_method"
                                        value="PUT"
                                    />
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={category.name}
                                            required
                                        />
                                        {errors.name && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.name}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.type}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Box
                                            component="textarea"
                                            id="description"
                                            name="description"
                                            defaultValue={
                                                category.description || ''
                                            }
                                            rows={3}
                                            placeholder="Detailed description for this category"
                                            sx={{
                                                display: 'flex',
                                                minHeight: 80,
                                                width: '100%',
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': { outline: 'none', ring: 2, ringColor: 'primary.main' },
                                                '&:disabled': { cursor: 'not-allowed', opacity: 0.5 },
                                            }}
                                        />
                                        {errors.description && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.description}
                                            </Typography>
                                        )}
                                    </Box>

                                    <ImageUpload
                                        name="image"
                                        label="Category Image"
                                        helperText="Upload a representative image for this category"
                                        existingImage={category.image}
                                        error={errors.image as string}
                                    />

                                    <ImageUpload
                                        name="icon"
                                        label="Category Icon"
                                        helperText="Upload a .png icon for this category (e.g. from Flaticon)"
                                        existingImage={category.icon}
                                        error={errors.icon as string}
                                        maxSizeMB={2}
                                        acceptedFormats={['image/png']}
                                    />

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.sort_order}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Box
                                            component="input"
                                            type="checkbox"
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={category.is_active}
                                            sx={{ width: 16, height: 16 }}
                                        />
                                        <Label htmlFor="is_active">
                                            Active
                                        </Label>
                                    </Box>

                                    {wasSuccessful && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'success.main' }}>
                                            Category updated successfully!
                                        </Typography>
                                    )}

                                    <Box sx={{ display: 'flex', gap: 1 }}>
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
                                    </Box>
                                </Box>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
