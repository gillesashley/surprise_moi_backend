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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { FormEvent, useState } from 'react';

interface Advertisement {
    id: number;
    title: string;
    description: string | null;
    image_path: string | null;
    link_url: string | null;
    status: string;
    placement: string;
    display_order: number;
    start_date: string | null;
    end_date: string | null;
}

interface Props {
    advertisement: Advertisement;
}

export default function EditAdvertisement({ advertisement }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Advertisements',
            href: '/dashboard/advertisements',
        },
        {
            title: 'Edit',
            href: `/dashboard/advertisements/${advertisement.id}/edit`,
        },
    ];

    const { data, setData, post, processing, errors } = useForm({
        title: advertisement.title,
        description: advertisement.description || '',
        image: null as File | null,
        link_url: advertisement.link_url || '',
        status: advertisement.status,
        placement: advertisement.placement,
        display_order: advertisement.display_order,
        start_date: advertisement.start_date
            ? new Date(advertisement.start_date).toISOString().slice(0, 16)
            : '',
        end_date: advertisement.end_date
            ? new Date(advertisement.end_date).toISOString().slice(0, 16)
            : '',
        _method: 'PUT',
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        advertisement.image_path
            ? `/storage/${advertisement.image_path}`
            : null,
    );

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
        post(`/dashboard/advertisements/${advertisement.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Advertisement" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                <Box>
                    <Typography variant="h4" sx={{ fontWeight: 700, letterSpacing: '-0.025em' }}>
                        Edit Advertisement
                    </Typography>
                    <Typography sx={{ color: 'text.secondary' }}>
                        Update the advertisement details
                    </Typography>
                </Box>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Advertisement Details</CardTitle>
                            <CardDescription>
                                Update the details for this advertisement
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="title">
                                    Title{' '}
                                    <Box component="span" sx={{ color: 'error.main' }}>*</Box>
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
                                    <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                        {errors.title}
                                    </Typography>
                                )}
                            </Box>

                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                    <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                        {errors.description}
                                    </Typography>
                                )}
                            </Box>

                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="image">Image</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={handleImageChange}
                                />
                                {errors.image && (
                                    <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                        {errors.image}
                                    </Typography>
                                )}
                                {imagePreview && (
                                    <Box sx={{ mt: 1 }}>
                                        <Typography sx={{ mb: 1, fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Current/New Image:
                                        </Typography>
                                        <Box
                                            component="img"
                                            src={imagePreview}
                                            alt="Preview"
                                            sx={{ height: 192, width: 'auto', borderRadius: 1, objectFit: 'cover' }}
                                        />
                                    </Box>
                                )}
                            </Box>

                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                    <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                        {errors.link_url}
                                    </Typography>
                                )}
                            </Box>

                            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' }, gap: 3 }}>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="status">
                                        Status{' '}
                                        <Box component="span" sx={{ color: 'error.main' }}>*</Box>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.status}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="placement">
                                        Placement{' '}
                                        <Box component="span" sx={{ color: 'error.main' }}>*</Box>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.placement}
                                        </Typography>
                                    )}
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Lower numbers appear first
                                </Typography>
                                {errors.display_order && (
                                    <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                        {errors.display_order}
                                    </Typography>
                                )}
                            </Box>

                            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' }, gap: 3 }}>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.start_date}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.end_date}
                                        </Typography>
                                    )}
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', gap: 2 }}>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Updating...'
                                        : 'Update Advertisement'}
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
                            </Box>
                            </Box>
                        </CardContent>
                    </Card>
                </form>
            </Box>
        </AppLayout>
    );
}
