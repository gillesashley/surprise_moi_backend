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
import { store as interestStore } from '@/routes/dashboard/interests';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: 'Create Interest',
        href: interestStore.url(),
    },
];

export default function InterestCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Interest" />
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
                        <CardTitle>Create Interest</CardTitle>
                        <CardDescription>
                            Add a new interest for user personalization
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={interestStore.url()} method="post">
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
                                        <Label htmlFor="icon">
                                            Icon (emoji)
                                        </Label>
                                        <Input id="icon" name="icon" />
                                        {errors.icon && (
                                            <p className="text-sm text-destructive">
                                                {errors.icon}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create Interest'}
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
