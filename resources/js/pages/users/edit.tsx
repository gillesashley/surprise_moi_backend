import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import {
    show as userShow,
    index as usersIndex,
    update as userUpdate,
} from '@/routes/users';
import { type BreadcrumbItem, type User } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';

interface Props {
    user: User;
    roles: string[];
    canEditRole: boolean;
}

const breadcrumbs = (user: User): BreadcrumbItem[] => [
    {
        title: 'Users',
        href: usersIndex().url,
    },
    {
        title: user.name,
        href: userShow.url(user.id),
    },
    {
        title: 'Edit',
        href: userUpdate.url(user.id),
    },
];

const formatRole = (role: string) => {
    return role
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

export default function UserEdit({ user, roles, canEditRole }: Props) {
    useInactivityLock();
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setAvatarPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        } else {
            setAvatarPreview(null);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(user)}>
            <Head title={`Edit: ${user.name}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={userShow.url(user.id)}>
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to User
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Edit User</CardTitle>
                        <CardDescription>
                            Update user information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...userUpdate.form(user.id)}
                            encType="multipart/form-data"
                            resetOnSuccess
                            options={{
                                preserveScroll: true,
                            }}
                        >
                            {({ errors, processing, wasSuccessful }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* Avatar Upload */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label>Profile Image</Label>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                            <Avatar style={{ width: 64, height: 64 }}>
                                                <AvatarImage
                                                    src={
                                                        avatarPreview ||
                                                        user.avatar ||
                                                        undefined
                                                    }
                                                    alt={user.name}
                                                />
                                                <AvatarFallback style={{ fontSize: '1.125rem' }}>
                                                    {user.name
                                                        .split(' ')
                                                        .map((n) => n[0])
                                                        .join('')
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <Box sx={{ flex: 1 }}>
                                                <Input
                                                    id="avatar"
                                                    name="avatar"
                                                    type="file"
                                                    accept="image/*"
                                                    style={{ cursor: 'pointer' }}
                                                    onChange={
                                                        handleAvatarChange
                                                    }
                                                />
                                                <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'text.secondary' }}>
                                                    Upload a new image (JPG,
                                                    PNG, GIF, max 2MB)
                                                </Typography>
                                            </Box>
                                        </Box>
                                        {errors.avatar && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.avatar}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={user.name}
                                            required
                                        />
                                        {errors.name && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.name}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                            required
                                        />
                                        {errors.email && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.email}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            defaultValue={user.phone || ''}
                                        />
                                        {errors.phone && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.phone}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="role">Role</Label>
                                        <Box
                                            component="select"
                                            id="role"
                                            name="role"
                                            defaultValue={
                                                user.role || 'customer'
                                            }
                                            disabled={!canEditRole}
                                            sx={{
                                                display: 'flex',
                                                height: 40,
                                                width: '100%',
                                                borderRadius: 1.5,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': {
                                                    outline: '2px solid',
                                                    outlineColor: 'primary.main',
                                                    outlineOffset: 2,
                                                },
                                                '&:disabled': {
                                                    cursor: 'not-allowed',
                                                    opacity: 0.5,
                                                },
                                            }}
                                        >
                                            {roles.map((role) => (
                                                <option key={role} value={role}>
                                                    {formatRole(role)}
                                                </option>
                                            ))}
                                        </Box>
                                        {!canEditRole && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                You don't have permission to
                                                change this user's role.
                                            </Typography>
                                        )}
                                        {errors.role && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.role}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="date_of_birth">
                                            Date of Birth
                                        </Label>
                                        <Input
                                            id="date_of_birth"
                                            name="date_of_birth"
                                            type="date"
                                            defaultValue={
                                                user.date_of_birth || ''
                                            }
                                        />
                                        {errors.date_of_birth && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.date_of_birth}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="gender">Gender</Label>
                                        <Box
                                            component="select"
                                            id="gender"
                                            name="gender"
                                            defaultValue={user.gender || ''}
                                            sx={{
                                                display: 'flex',
                                                height: 40,
                                                width: '100%',
                                                borderRadius: 1.5,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': {
                                                    outline: '2px solid',
                                                    outlineColor: 'primary.main',
                                                    outlineOffset: 2,
                                                },
                                                '&:disabled': {
                                                    cursor: 'not-allowed',
                                                    opacity: 0.5,
                                                },
                                            }}
                                        >
                                            <option value="">
                                                Select gender
                                            </option>
                                            <option value="male">Male</option>
                                            <option value="female">
                                                Female
                                            </option>
                                            <option value="other">Other</option>
                                        </Box>
                                        {errors.gender && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.gender}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="bio">Bio</Label>
                                        <Box
                                            component="textarea"
                                            id="bio"
                                            name="bio"
                                            defaultValue={user.bio || ''}
                                            rows={4}
                                            sx={{
                                                display: 'flex',
                                                minHeight: 80,
                                                width: '100%',
                                                borderRadius: 1.5,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': {
                                                    outline: '2px solid',
                                                    outlineColor: 'primary.main',
                                                    outlineOffset: 2,
                                                },
                                                '&:disabled': {
                                                    cursor: 'not-allowed',
                                                    opacity: 0.5,
                                                },
                                            }}
                                        />
                                        {errors.bio && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.bio}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <input
                                            type="hidden"
                                            name="is_popular"
                                            value="0"
                                        />
                                        <Box
                                            component="input"
                                            id="is_popular"
                                            name="is_popular"
                                            type="checkbox"
                                            value="1"
                                            defaultChecked={
                                                user.is_popular || false
                                            }
                                            sx={{ width: 16, height: 16, borderRadius: 1, border: 1, borderColor: 'divider' }}
                                        />
                                        <Label
                                            htmlFor="is_popular"
                                            style={{ cursor: 'pointer', fontWeight: 400 }}
                                        >
                                            Mark as Popular
                                        </Label>
                                        {errors.is_popular && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.is_popular}
                                            </Typography>
                                        )}
                                    </Box>

                                    {wasSuccessful && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'success.main' }}>
                                            User updated successfully!
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
                                            <Link href={userShow.url(user.id)}>
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
