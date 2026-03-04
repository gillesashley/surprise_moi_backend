import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import Fade from '@mui/material/Fade';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/user-password';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <SettingsLayout>
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                    <HeadingSmall
                        title="Update password"
                        description="Ensure your account is using a long, random password to stay secure"
                    />

                    <Form
                        {...PasswordController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                        style={{ display: 'flex', flexDirection: 'column', gap: 24 }}
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="current_password">
                                        Current password
                                    </Label>

                                    <Input
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />

                                    <InputError
                                        message={errors.current_password}
                                    />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="password">
                                        New password
                                    </Label>

                                    <Input
                                        id="password"
                                        ref={passwordInput}
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder="New password"
                                    />

                                    <InputError message={errors.password} />
                                </Box>

                                <Box sx={{ display: 'grid', gap: 1 }}>
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>

                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                    />

                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </Box>

                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="update-password-button"
                                    >
                                        Save password
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
            </SettingsLayout>
        </AppLayout>
    );
}
