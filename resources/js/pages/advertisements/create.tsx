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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Advertisements',
        href: '/dashboard/advertisements',
    },
    {
        title: 'Create',
        href: '/dashboard/advertisements/create',
    },
];

export default function CreateAdvertisement() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        image: null as File | null,
        link_url: '',
        status: 'inactive',
        placement: 'feed',
        display_order: 0,
        start_date: '',
        end_date: '',
        target_audience: [],
    });

    const [imagePreview, setImagePreview] = useState<string | null>(null);

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setImagePreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/dashboard/advertisements');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Advertisement" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Create Advertisement
                    </h1>
                    <p className="text-muted-foreground">
                        Create a new advertisement to display in the app
                    </p>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Advertisement Details</CardTitle>
                            <CardDescription>
                                Fill in the details for the new advertisement
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="title">
                                    Title{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    placeholder="Enter advertisement title"
                                    required
                                />
                                {errors.title && (
                                    <p className="text-sm text-red-500">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Enter advertisement description"
                                    rows={4}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="image">Image</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={handleImageChange}
                                />
                                {errors.image && (
                                    <p className="text-sm text-red-500">
                                        {errors.image}
                                    </p>
                                )}
                                {imagePreview && (
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="mt-2 h-48 w-auto rounded-md object-cover"
                                    />
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="link_url">Link URL</Label>
                                <Input
                                    id="link_url"
                                    type="url"
                                    value={data.link_url}
                                    onChange={(e) =>
                                        setData('link_url', e.target.value)
                                    }
                                    placeholder="https://example.com"
                                />
                                {errors.link_url && (
                                    <p className="text-sm text-red-500">
                                        {errors.link_url}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="status">
                                        Status{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(value) =>
                                            setData('status', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                Active
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Inactive
                                            </SelectItem>
                                            <SelectItem value="scheduled">
                                                Scheduled
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && (
                                        <p className="text-sm text-red-500">
                                            {errors.status}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="placement">
                                        Placement{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.placement}
                                        onValueChange={(value) =>
                                            setData('placement', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select placement" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="home_banner">
                                                Home Banner
                                            </SelectItem>
                                            <SelectItem value="feed">
                                                Feed
                                            </SelectItem>
                                            <SelectItem value="popup">
                                                Popup
                                            </SelectItem>
                                            <SelectItem value="sidebar">
                                                Sidebar
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.placement && (
                                        <p className="text-sm text-red-500">
                                            {errors.placement}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="display_order">
                                    Display Order
                                </Label>
                                <Input
                                    id="display_order"
                                    type="number"
                                    min="0"
                                    value={data.display_order}
                                    onChange={(e) =>
                                        setData(
                                            'display_order',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    placeholder="0"
                                />
                                <p className="text-sm text-muted-foreground">
                                    Lower numbers appear first
                                </p>
                                {errors.display_order && (
                                    <p className="text-sm text-red-500">
                                        {errors.display_order}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="start_date">
                                        Start Date
                                    </Label>
                                    <Input
                                        id="start_date"
                                        type="datetime-local"
                                        value={data.start_date}
                                        onChange={(e) =>
                                            setData(
                                                'start_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.start_date && (
                                        <p className="text-sm text-red-500">
                                            {errors.start_date}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="end_date">End Date</Label>
                                    <Input
                                        id="end_date"
                                        type="datetime-local"
                                        value={data.end_date}
                                        onChange={(e) =>
                                            setData('end_date', e.target.value)
                                        }
                                    />
                                    {errors.end_date && (
                                        <p className="text-sm text-red-500">
                                            {errors.end_date}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Advertisement'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            '/dashboard/advertisements',
                                        )
                                    }
                                >
                                    Cancel
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
