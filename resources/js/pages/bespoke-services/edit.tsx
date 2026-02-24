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
import { update as bespokeServiceUpdate } from '@/routes/dashboard/bespoke-services';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface BespokeService {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
}

interface Props {
    bespokeService: BespokeService;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: 'Edit Bespoke Service',
        href: '#',
    },
];

export default function BespokeServiceEdit({ bespokeService }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${bespokeService.name}`} />
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
                        <CardTitle>Edit Bespoke Service</CardTitle>
                        <CardDescription>
                            Update the bespoke service details
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={bespokeServiceUpdate.url(bespokeService.id)}
                            method="post"
                            encType="multipart/form-data"
                        >
                            {({ errors, processing }) => (
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
                                            defaultValue={bespokeService.name}
                                            required
                                        />
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
                                            defaultValue={
                                                bespokeService.description || ''
                                            }
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
                                        <Input
                                            id="icon"
                                            name="icon"
                                            defaultValue={
                                                bespokeService.icon || ''
                                            }
                                        />
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
                                        existingImage={bespokeService.image}
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
                                            defaultValue={
                                                bespokeService.sort_order
                                            }
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
                                            defaultChecked={
                                                bespokeService.is_active
                                            }
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
