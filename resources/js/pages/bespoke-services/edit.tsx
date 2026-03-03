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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'bespoke' },
                            })}
                        >
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Content Management
                        </Link>
                    </Button>
                </Box>

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
                                            defaultValue={bespokeService.name}
                                            required
                                        />
                                        {errors.name && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.name}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.description}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.icon}
                                            </Typography>
                                        )}
                                    </Box>

                                    <ImageUpload
                                        name="image"
                                        label="Service Image"
                                        helperText="Upload a representative image for this service"
                                        existingImage={bespokeService.image}
                                        error={errors.image as string}
                                    />

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.sort_order}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={
                                                bespokeService.is_active
                                            }
                                            style={{ width: 16, height: 16, borderRadius: 4 }}
                                        />
                                        <Label htmlFor="is_active">
                                            Active
                                        </Label>
                                    </Box>

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
                                                            tab: 'bespoke',
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
