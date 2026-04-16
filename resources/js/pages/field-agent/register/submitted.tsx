import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';
import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export default function FieldAgentRegisterSubmitted() {
    return (
        <>
            <Head title="Application received" />
            <Box
                sx={{
                    display: 'flex',
                    minHeight: '100svh',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    bgcolor: 'background.default',
                    p: { xs: 2, md: 5 },
                }}
            >
                <Box sx={{ width: '100%', maxWidth: '28rem' }}>
                    <Box
                        sx={{
                            bgcolor: 'background.paper',
                            borderRadius: 3,
                            boxShadow: (theme) => theme.shadows[2],
                            p: { xs: 3, md: 4 },
                        }}
                    >
                        <Stack spacing={2} sx={{ alignItems: 'center', textAlign: 'center' }}>
                            <Link href={home()} style={{ color: 'inherit', textDecoration: 'none' }}>
                                <AppLogoIcon style={{ width: 40, height: 40 }} />
                            </Link>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                Application received
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                Thank you for applying to become a Surprise Moi field agent. Our team will review your
                                submission and contact you by email and SMS once the review is complete.
                            </Typography>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ pt: 1, width: '100%' }}>
                                <Button asChild variant="outline" sx={{ flex: 1 }}>
                                    <Link href={home()}>Back to home</Link>
                                </Button>
                                <Button asChild sx={{ flex: 1 }}>
                                    <Link href={login()}>Sign in</Link>
                                </Button>
                            </Stack>
                        </Stack>
                    </Box>
                </Box>
            </Box>
        </>
    );
}
