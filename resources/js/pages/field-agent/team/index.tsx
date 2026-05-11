import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
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
    vendors_onboarded: number;
    created_at: string;
};

export default function TeamIndex({ members }: { members: Member[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Team', href: '/field-agent/team' }]}>
            <Head title="My Team" />

            <Box sx={{ p: { xs: 2, md: 3 }, display: 'flex', flexDirection: 'column', gap: 3 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h5" fontWeight={700}>
                        My Team
                    </Typography>
                    <Button asChild>
                        <Link href="/field-agent/team/new">Add member</Link>
                    </Button>
                </Box>

                {members.length === 0 ? (
                    <Card>
                        <CardContent className="text-center py-6">
                            <Typography variant="body2" color="text.secondary">
                                No team members yet. Click <strong>Add member</strong> to create one.
                            </Typography>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0 last:pb-0">
                            <Box sx={{ display: 'flex', flexDirection: 'column' }}>
                                {members.map((m, idx) => (
                                    <Link
                                        key={m.id}
                                        href={`/field-agent/team/${m.id}`}
                                        style={{ textDecoration: 'none', color: 'inherit' }}
                                    >
                                        <Box
                                            sx={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                gap: 2,
                                                px: 2,
                                                py: 2,
                                                borderTop: idx === 0 ? 0 : 1,
                                                borderColor: 'divider',
                                                transition: 'background-color 0.15s',
                                                '&:hover': { bgcolor: 'action.hover' },
                                            }}
                                        >
                                            <Box sx={{ minWidth: 0, flex: 1 }}>
                                                <Typography variant="body1" fontWeight={500} noWrap>
                                                    {m.name}
                                                </Typography>
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                    noWrap
                                                    sx={{ display: 'block' }}
                                                >
                                                    {m.email} · {m.phone} · {m.location ?? '—'}
                                                </Typography>
                                            </Box>
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                                                <Typography variant="body2" color="text.secondary">
                                                    {m.vendors_onboarded} onboarded
                                                </Typography>
                                                <Chip
                                                    label={m.is_active ? 'Active' : 'Inactive'}
                                                    color={m.is_active ? 'success' : 'default'}
                                                    size="small"
                                                />
                                            </Box>
                                        </Box>
                                    </Link>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                )}
            </Box>
        </AppLayout>
    );
}
