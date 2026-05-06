import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Form, Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';

type Member = {
    id: number;
    name: string;
    email: string;
    phone: string;
    location: string | null;
    is_active: boolean;
    must_change_password: boolean;
    created_at: string;
};

type Vendor = {
    id: number;
    business_name: string;
    status: string;
    created_at: string | null;
};

export default function TeamShow({
    member,
    vendors,
}: {
    member: Member;
    vendors: Vendor[];
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Team', href: '/field-agent/team' },
                { title: member.name, href: `/field-agent/team/${member.id}` },
            ]}
        >
            <Head title={member.name} />

            <Box
                sx={{
                    p: { xs: 2, md: 3 },
                    maxWidth: 880,
                    mx: 'auto',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 3,
                }}
            >
                <Box
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 2,
                    }}
                >
                    <Box sx={{ minWidth: 0 }}>
                        <Typography variant="h5" fontWeight={700} noWrap>
                            {member.name}
                        </Typography>
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                            {member.email} · {member.phone} · {member.location ?? '—'}
                        </Typography>
                    </Box>
                    <Chip
                        label={member.is_active ? 'Active' : 'Inactive'}
                        color={member.is_active ? 'success' : 'default'}
                        size="small"
                    />
                </Box>

                <Form
                    action={`/field-agent/team/${member.id}`}
                    method="patch"
                    transform={(data) => ({ ...data, is_active: !member.is_active })}
                >
                    {({ processing }) => (
                        <Box>
                            <Button
                                type="submit"
                                variant={member.is_active ? 'destructive' : 'default'}
                                disabled={processing}
                            >
                                {member.is_active ? 'Deactivate member' : 'Reactivate member'}
                            </Button>
                        </Box>
                    )}
                </Form>

                <Box>
                    <Typography variant="subtitle1" fontWeight={600} sx={{ mb: 1 }}>
                        Onboarded vendors ({vendors.length})
                    </Typography>

                    {vendors.length === 0 ? (
                        <Card>
                            <CardContent sx={{ textAlign: 'center', py: 4 }}>
                                <Typography variant="body2" color="text.secondary">
                                    None yet.
                                </Typography>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent sx={{ p: 0, '&:last-child': { pb: 0 } }}>
                                <Box sx={{ display: 'flex', flexDirection: 'column' }}>
                                    {vendors.map((v, idx) => (
                                        <Box
                                            key={v.id}
                                            sx={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                gap: 2,
                                                px: 2,
                                                py: 1.5,
                                                borderTop: idx === 0 ? 0 : 1,
                                                borderColor: 'divider',
                                            }}
                                        >
                                            <Box sx={{ minWidth: 0, flex: 1 }}>
                                                <Typography variant="body2" fontWeight={500} noWrap>
                                                    {v.business_name}
                                                </Typography>
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                    sx={{ display: 'block' }}
                                                >
                                                    {v.created_at ?? ''}
                                                </Typography>
                                            </Box>
                                            <Chip label={v.status} variant="outlined" size="small" />
                                        </Box>
                                    ))}
                                </Box>
                            </CardContent>
                        </Card>
                    )}
                </Box>
            </Box>
        </AppLayout>
    );
}
