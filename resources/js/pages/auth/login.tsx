import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Form, Head } from '@inertiajs/react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: LoginProps) {
    return (
        <AuthLayout
            title="Log in to your account"
            description="Enter your email and password below to log in"
        >
            <Head title="Log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                style={{ display: 'flex', flexDirection: 'column', gap: 24 }}
            >
                {({ processing, errors }) => (
                    <>
                        <Box sx={{ display: 'grid', gap: 3 }}>
                            <Box sx={{ display: 'grid', gap: 1 }}>
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </Box>

                            <Box sx={{ display: 'grid', gap: 1 }}>
                                <Box sx={{ display: 'flex', alignItems: 'center' }}>
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            style={{ marginLeft: 'auto', fontSize: '0.875rem' }}
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </Box>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </Box>

                            <Button
                                type="submit"
                                sx={{ mt: 2, width: '100%' }}
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </Box>

                        {canRegister && (
                            <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center' }}>
                                Don't have an account?{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    Sign up
                                </TextLink>
                            </Typography>
                        )}
                    </>
                )}
            </Form>

            {status && (
                <Typography variant="body2" color="success.main" sx={{ textAlign: 'center', fontWeight: 500 }}>
                    {status}
                </Typography>
            )}
        </AuthLayout>
    );
}
