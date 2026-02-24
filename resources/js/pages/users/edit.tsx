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
import AppLayout from '@/layouts/app-layout';
import {
    show as userShow,
    index as usersIndex,
    update as userUpdate,
} from '@/routes/users';
import { type BreadcrumbItem, type User } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={userShow.url(user.id)}>
                            <ArrowLeft className="mr-2 size-4" />
                            Back to User
                        </Link>
                    </Button>
                </div>

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
                                <div className="space-y-4">
                                    {/* Avatar Upload */}
                                    <div className="space-y-2">
                                        <Label>Profile Image</Label>
                                        <div className="flex items-center gap-4">
                                            <Avatar className="h-16 w-16">
                                                <AvatarImage
                                                    src={
                                                        avatarPreview ||
                                                        user.avatar ||
                                                        undefined
                                                    }
                                                    alt={user.name}
                                                />
                                                <AvatarFallback className="text-lg">
                                                    {user.name
                                                        .split(' ')
                                                        .map((n) => n[0])
                                                        .join('')
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1">
                                                <Input
                                                    id="avatar"
                                                    name="avatar"
                                                    type="file"
                                                    accept="image/*"
                                                    className="cursor-pointer"
                                                    onChange={
                                                        handleAvatarChange
                                                    }
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Upload a new image (JPG,
                                                    PNG, GIF, max 2MB)
                                                </p>
                                            </div>
                                        </div>
                                        {errors.avatar && (
                                            <p className="text-sm text-destructive">
                                                {errors.avatar}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={user.name}
                                            required
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                            required
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-destructive">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            defaultValue={user.phone || ''}
                                        />
                                        {errors.phone && (
                                            <p className="text-sm text-destructive">
                                                {errors.phone}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="role">Role</Label>
                                        <select
                                            id="role"
                                            name="role"
                                            defaultValue={
                                                user.role || 'customer'
                                            }
                                            disabled={!canEditRole}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            {roles.map((role) => (
                                                <option key={role} value={role}>
                                                    {formatRole(role)}
                                                </option>
                                            ))}
                                        </select>
                                        {!canEditRole && (
                                            <p className="text-sm text-muted-foreground">
                                                You don't have permission to
                                                change this user's role.
                                            </p>
                                        )}
                                        {errors.role && (
                                            <p className="text-sm text-destructive">
                                                {errors.role}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
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
                                            <p className="text-sm text-destructive">
                                                {errors.date_of_birth}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="gender">Gender</Label>
                                        <select
                                            id="gender"
                                            name="gender"
                                            defaultValue={user.gender || ''}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="">
                                                Select gender
                                            </option>
                                            <option value="male">Male</option>
                                            <option value="female">
                                                Female
                                            </option>
                                            <option value="other">Other</option>
                                        </select>
                                        {errors.gender && (
                                            <p className="text-sm text-destructive">
                                                {errors.gender}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="bio">Bio</Label>
                                        <textarea
                                            id="bio"
                                            name="bio"
                                            defaultValue={user.bio || ''}
                                            rows={4}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        />
                                        {errors.bio && (
                                            <p className="text-sm text-destructive">
                                                {errors.bio}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="hidden"
                                            name="is_popular"
                                            value="0"
                                        />
                                        <input
                                            id="is_popular"
                                            name="is_popular"
                                            type="checkbox"
                                            value="1"
                                            defaultChecked={
                                                user.is_popular || false
                                            }
                                            className="h-4 w-4 rounded border border-input"
                                        />
                                        <Label
                                            htmlFor="is_popular"
                                            className="cursor-pointer font-normal"
                                        >
                                            Mark as Popular
                                        </Label>
                                        {errors.is_popular && (
                                            <p className="text-sm text-destructive">
                                                {errors.is_popular}
                                            </p>
                                        )}
                                    </div>

                                    {wasSuccessful && (
                                        <p className="text-sm text-green-600">
                                            User updated successfully!
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
                                            <Link href={userShow.url(user.id)}>
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
