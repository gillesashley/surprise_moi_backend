import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import Fade from '@mui/material/Fade';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import { Form, Head, Link, usePage } from '@inertiajs/react';

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                    <HeadingSmall
                        title="Profile information"
                        description="Update your profile information"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        style={{ display: 'flex', flexDirection: 'column', gap: 24 }}
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        message={errors.name}
                                    />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        message={errors.email}
                                    />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="phone">Phone number</Label>

                                    <Input
                                        id="phone"
                                        type="tel"
                                        defaultValue={auth.user.phone || ''}
                                        name="phone"
                                        autoComplete="tel"
                                        placeholder="Phone number"
                                    />

                                    <InputError
                                        message={errors.phone}
                                    />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="gender">Gender</Label>

                                    <Select
                                        name="gender"
                                        defaultValue={auth.user.gender || ''}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="male">
                                                Male
                                            </SelectItem>
                                            <SelectItem value="female">
                                                Female
                                            </SelectItem>
                                            <SelectItem value="other">
                                                Other
                                            </SelectItem>
                                            <SelectItem value="prefer_not_to_say">
                                                Prefer not to say
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        message={errors.gender}
                                    />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="date_of_birth">
                                        Date of birth
                                    </Label>

                                    <Input
                                        id="date_of_birth"
                                        type="date"
                                        defaultValue={
                                            auth.user.date_of_birth || ''
                                        }
                                        name="date_of_birth"
                                        autoComplete="bday"
                                    />

                                    <InputError
                                        message={errors.date_of_birth}
                                    />
                                </Box>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <Box>
                                            <Typography variant="body2" color="text.secondary" sx={{ mt: -2 }}>
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    style={{
                                                        textDecoration: 'underline',
                                                        textUnderlineOffset: '4px',
                                                        color: 'inherit',
                                                    }}
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </Typography>

                                            {status ===
                                                'verification-link-sent' && (
                                                <Typography variant="body2" color="success.main" sx={{ mt: 1, fontWeight: 500 }}>
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </Typography>
                                            )}
                                        </Box>
                                    )}

                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Fade in={recentlySuccessful}>
                                        <Typography variant="body2" color="text.secondary">
                                            Saved
                                        </Typography>
                                    </Fade>
                                </Box>
                            </>
                        )}
                    </Form>
                </Box>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
