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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { store as bespokeServiceStore } from '@/routes/dashboard/bespoke-services';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: 'Create Bespoke Service',
        href: bespokeServiceStore.url(),
    },
];

export default function BespokeServiceCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Bespoke Service" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'bespoke' },
                            })}
                        >
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Content Management
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Create Bespoke Service</CardTitle>
                        <CardDescription>
                            Add a new bespoke service that vendors can offer
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={bespokeServiceStore.url()}
                            method="post"
                            encType="multipart/form-data"
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" name="name" required />
                                        {errors.name && (
                                            <p className="text-sm text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={3}
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-destructive">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="icon">
                                            Icon (emoji or text)
                                        </Label>
                                        <Input id="icon" name="icon" />
                                        {errors.icon && (
                                            <p className="text-sm text-destructive">
                                                {errors.icon}
                                            </p>
                                        )}
                                    </div>

                                    <ImageUpload
                                        name="image"
                                        label="Service Image"
                                        helperText="Upload a representative image for this service"
                                        error={errors.image as string}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="sort_order">
                                            Sort Order
                                        </Label>
                                        <Input
                                            id="sort_order"
                                            name="sort_order"
                                            type="number"
                                            min="0"
                                            defaultValue="0"
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
                                            defaultChecked
                                            className="h-4 w-4 rounded border-gray-300"
                                        />
                                        <Label htmlFor="is_active">
                                            Active
                                        </Label>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create Service'}
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
                                                            tab: 'bespoke',
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
