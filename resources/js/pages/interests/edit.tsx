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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'interests' },
                            })}
                        >
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Content Management
                        </Link>
                    </Button>
                </div>

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
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={interest.name}
                                            required
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="icon">
                                            Icon (emoji)
                                        </Label>
                                        <Input
                                            id="icon"
                                            name="icon"
                                            defaultValue={interest.icon || ''}
                                        />
                                        {errors.icon && (
                                            <p className="text-sm text-destructive">
                                                {errors.icon}
                                            </p>
                                        )}
                                    </div>

                                    {wasSuccessful && (
                                        <p className="text-sm text-green-600">
                                            Interest updated successfully!
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
                                                            tab: 'interests',
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
