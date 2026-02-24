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
import { update as musicGenreUpdate } from '@/routes/dashboard/music-genres';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface MusicGenre {
    id: number;
    name: string;
    icon: string | null;
}

interface Props {
    musicGenre: MusicGenre;
}

const breadcrumbs = (genre: MusicGenre): BreadcrumbItem[] => [
    {
        title: 'Content Management',
        href: ContentManagementController.index.url(),
    },
    {
        title: genre.name,
        href: musicGenreUpdate.url(genre.id),
    },
];

export default function MusicGenreEdit({ musicGenre }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(musicGenre)}>
            <Head title={`Edit: ${musicGenre.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={ContentManagementController.index.url({
                                query: { tab: 'music' },
                            })}
                        >
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Content Management
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Edit Music Genre</CardTitle>
                        <CardDescription>
                            Update music genre information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={musicGenreUpdate.url(musicGenre.id)}
                            method="put"
                        >
                            {({ errors, processing, wasSuccessful }) => (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={musicGenre.name}
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
                                            defaultValue={musicGenre.icon || ''}
                                        />
                                        {errors.icon && (
                                            <p className="text-sm text-destructive">
                                                {errors.icon}
                                            </p>
                                        )}
                                    </div>

                                    {wasSuccessful && (
                                        <p className="text-sm text-green-600">
                                            Music genre updated successfully!
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
                                                        query: { tab: 'music' },
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
