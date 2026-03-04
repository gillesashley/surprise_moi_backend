import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Forgot password"
            description="Enter your email to receive a password reset link"
        >
            <Head title="Forgot password" />

            {status && (
                <Typography variant="body2" color="success.main" sx={{ textAlign: 'center', fontWeight: 500 }}>
                    {status}
                </Typography>
            )}

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <Box sx={{ display: 'grid', gap: 1 }}>
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="email@example.com"
                                />

                                <InputError message={errors.email} />
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-start', my: 3 }}>
                                <Button
                                    type="submit"
                                    sx={{ width: '100%' }}
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && <Spinner />}
                                    Email password reset link
                                </Button>
                            </Box>
                        </>
                    )}
                </Form>

                <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center' }}>
                    <span>Or, return to </span>
                    <TextLink href={login()}>log in</TextLink>
                </Typography>
            </Box>
        </AuthLayout>
    );
}
