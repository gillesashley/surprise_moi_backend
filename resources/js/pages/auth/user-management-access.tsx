import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { verify } from '@/routes/user-management-access';
import Box from '@mui/material/Box';
import { Form, Head } from '@inertiajs/react';

export default function UserManagementAccess() {
    return (
        <AuthLayout
            title="User management access"
            description="Enter the access code to continue to user management."
        >
            <Head title="User management access" />

            <Form {...verify.form()} resetOnSuccess={['code']}>
                {({ processing, errors }) => (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                        <Box sx={{ display: 'grid', gap: 1 }}>
                            <Label htmlFor="code">Access code</Label>
                            <Input
                                id="code"
                                type="password"
                                name="code"
                                placeholder="Access code"
                                autoFocus
                            />

                            <InputError message={errors.code} />
                        </Box>

                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                            <Button
                                sx={{ width: '100%' }}
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Verify access
                            </Button>
                        </Box>
                    </Box>
                )}
            </Form>
        </AuthLayout>
    );
}
