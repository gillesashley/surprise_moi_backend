import ContentManagementController from '@/actions/App/Http/Controllers/ContentManagementController';
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
import AppLayout from '@/layouts/app-layout';
import { update as interestUpdate } from '@/routes/dashboard/interests';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft } from 'lucide-react';

interface Interest {
    id: number;
    name: string;
    icon: string | null;
}

interface Props {
    interest: Interest;
}

const breadcrumbs = (interest: Interest): BreadcrumbItem[] => [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: interest.name,
        href: interestUpdate.url(interest.id),
    },
];

export default function InterestEdit({ interest }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(interest)}>
            <Head title={`Edit: ${interest.name}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'interests' },
                            })}
                        >
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Content Management
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Edit Interest</CardTitle>
                        <CardDescription>
                            Update interest information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={interestUpdate.url(interest.id)}
                            method="put"
                        >
                            {({ errors, processing, wasSuccessful }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={interest.name}
                                            required
                                        />
                                        {errors.name && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.name}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="icon">
                                            Icon (emoji)
                                        </Label>
                                        <Input
                                            id="icon"
                                            name="icon"
                                            defaultValue={interest.icon || ''}
                                        />
                                        {errors.icon && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.icon}
                                            </Typography>
                                        )}
                                    </Box>

                                    {wasSuccessful && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'success.main' }}>
                                            Interest updated successfully!
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
                                                            tab: 'interests',
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
