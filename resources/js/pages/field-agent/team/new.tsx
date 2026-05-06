import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

export default function TeamNew() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Team', href: '/field-agent/team' },
                { title: 'Add member', href: '/field-agent/team/new' },
            ]}
        >
            <Head title="Add team member" />

            <Box
                sx={{
                    p: { xs: 2, md: 3 },
                    maxWidth: 640,
                    mx: 'auto',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2.5,
                }}
            >
                <Box>
                    <Typography variant="h5" fontWeight={700}>
                        Add team member
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        The member's default password will be their phone number. They will be
                        required to change it on first login.
                    </Typography>
                </Box>

                <Card>
                    <CardContent>
                        <Form action="/field-agent/team" method="post" resetOnSuccess>
                            {({ processing, errors }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
                                    <TextField
                                        id="name"
                                        name="name"
                                        label="Full name"
                                        required
                                        fullWidth
                                        size="small"
                                        error={Boolean(errors.name)}
                                        helperText={errors.name as string | undefined}
                                    />

                                    <TextField
                                        id="email"
                                        name="email"
                                        type="email"
                                        label="Email"
                                        required
                                        fullWidth
                                        size="small"
                                        error={Boolean(errors.email)}
                                        helperText={errors.email as string | undefined}
                                    />

                                    <TextField
                                        id="phone"
                                        name="phone"
                                        label="Phone (default password)"
                                        placeholder="0551234567"
                                        required
                                        fullWidth
                                        size="small"
                                        error={Boolean(errors.phone)}
                                        helperText={errors.phone as string | undefined}
                                    />

                                    <TextField
                                        id="location"
                                        name="location"
                                        label="Location"
                                        required
                                        fullWidth
                                        size="small"
                                        error={Boolean(errors.location)}
                                        helperText={errors.location as string | undefined}
                                    />

                                    <Box sx={{ display: 'flex', gap: 1.5, mt: 1 }}>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Adding…' : 'Add member'}
                                        </Button>
                                        <Button type="button" variant="ghost" asChild>
                                            <Link href="/field-agent/team">Cancel</Link>
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
