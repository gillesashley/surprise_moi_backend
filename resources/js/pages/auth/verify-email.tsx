import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import Typography from '@mui/material/Typography';
import { Form, Head } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Verify email"
            description="Please verify your email address by clicking on the link we just emailed to you."
        >
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <Typography variant="body2" color="success.main" sx={{ textAlign: 'center', fontWeight: 500 }}>
                    A new verification link has been sent to the email address
                    you provided during registration.
                </Typography>
            )}

            <Form
                {...send.form()}
                style={{ display: 'flex', flexDirection: 'column', gap: 24, textAlign: 'center' }}
            >
                {({ processing }) => (
                    <>
                        <Button type="submit" disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>

                        <TextLink
                            href={logout()}
                            style={{ display: 'block', margin: '0 auto', fontSize: '0.875rem' }}
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
