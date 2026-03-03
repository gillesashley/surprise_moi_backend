import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import Box from '@mui/material/Box';
import MuiButton from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import { Head, Link, usePage } from '@inertiajs/react';
import { Gift, Package, Sparkles, Users } from 'lucide-react';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome to SurpriseMoi" />
            <Box
                sx={{
                    minHeight: '100vh',
                    background: (theme) =>
                        `linear-gradient(135deg, ${theme.palette.primary.main}1a, ${theme.palette.background.default}, ${theme.palette.secondary.main}1a)`,
                }}
            >
                {/* Header Navigation */}
                <Box
                    component="header"
                    sx={{
                        borderBottom: 1,
                        borderColor: 'divider',
                        bgcolor: 'rgba(255,255,255,0.95)',
                        backdropFilter: 'blur(8px)',
                    }}
                >
                    <Box
                        sx={{
                            maxWidth: 1200,
                            mx: 'auto',
                            display: 'flex',
                            height: 64,
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            px: 2,
                        }}
                    >
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Gift style={{ width: 24, height: 24 }} />
                            <Typography variant="h6" fontWeight={700}>
                                SurpriseMoi
                            </Typography>
                        </Box>
                        <Box component="nav" sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                            {auth.user ? (
                                <MuiButton
                                    component={Link}
                                    href={dashboard().url}
                                    variant="contained"
                                    size="small"
                                >
                                    Dashboard
                                </MuiButton>
                            ) : (
                                <>
                                    <MuiButton
                                        component={Link}
                                        href={login().url}
                                        variant="text"
                                        size="small"
                                    >
                                        Log in
                                    </MuiButton>
                                    {canRegister && (
                                        <MuiButton
                                            component={Link}
                                            href={register().url}
                                            variant="contained"
                                            size="small"
                                        >
                                            Register
                                        </MuiButton>
                                    )}
                                </>
                            )}
                        </Box>
                    </Box>
                </Box>

                {/* Hero Section */}
                <Box component="main" sx={{ maxWidth: 1200, mx: 'auto', px: 2, py: 10 }}>
                    <Box sx={{ mx: 'auto', maxWidth: 896, textAlign: 'center' }}>
                        <Box
                            sx={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 1,
                                borderRadius: 99,
                                bgcolor: 'primary.main',
                                color: 'primary.contrastText',
                                px: 2,
                                py: 1,
                                mb: 3,
                            }}
                        >
                            <Sparkles style={{ width: 16, height: 16 }} />
                            <Typography variant="body2" fontWeight={500}>
                                Create Memorable Moments
                            </Typography>
                        </Box>

                        <Typography
                            variant="h2"
                            fontWeight={700}
                            sx={{
                                mb: 3,
                                letterSpacing: '-0.02em',
                                fontSize: { xs: '2.5rem', sm: '3.5rem', md: '4rem' },
                            }}
                        >
                            Welcome to{' '}
                            <Box
                                component="span"
                                sx={{
                                    background: (theme) =>
                                        `linear-gradient(90deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
                                    WebkitBackgroundClip: 'text',
                                    WebkitTextFillColor: 'transparent',
                                }}
                            >
                                SurpriseMoi
                            </Box>
                        </Typography>

                        <Typography
                            variant="h6"
                            color="text.secondary"
                            fontWeight={400}
                            sx={{ mb: 6 }}
                        >
                            Your one-stop platform for creating unforgettable surprises and connecting with amazing vendors.
                            Discover unique gifts, services, and experiences tailored just for you.
                        </Typography>

                        <Box
                            sx={{
                                display: 'flex',
                                flexDirection: { xs: 'column', sm: 'row' },
                                alignItems: 'center',
                                justifyContent: 'center',
                                gap: 2,
                            }}
                        >
                            {!auth.user && (
                                <>
                                    <MuiButton
                                        component={Link}
                                        href={register().url}
                                        variant="contained"
                                        size="large"
                                        sx={{ px: 4 }}
                                    >
                                        Get Started
                                    </MuiButton>
                                    <MuiButton
                                        component={Link}
                                        href={login().url}
                                        variant="outlined"
                                        size="large"
                                        sx={{ px: 4 }}
                                    >
                                        Sign In
                                    </MuiButton>
                                </>
                            )}
                            {auth.user && (
                                <MuiButton
                                    component={Link}
                                    href={dashboard().url}
                                    variant="contained"
                                    size="large"
                                    sx={{ px: 4 }}
                                >
                                    Go to Dashboard
                                </MuiButton>
                            )}
                        </Box>
                    </Box>

                    {/* Features Grid */}
                    <Box
                        sx={{
                            mx: 'auto',
                            mt: 12,
                            display: 'grid',
                            maxWidth: 1024,
                            gap: 4,
                            gridTemplateColumns: { md: 'repeat(3, 1fr)' },
                        }}
                    >
                        {[
                            {
                                icon: Gift,
                                title: 'Unique Surprises',
                                description: 'Discover and create personalized surprises with our curated selection of gifts and experiences.',
                                color: 'primary.main',
                            },
                            {
                                icon: Package,
                                title: 'Quality Products',
                                description: 'Browse thousands of high-quality products from verified vendors across multiple categories.',
                                color: 'secondary.main',
                            },
                            {
                                icon: Users,
                                title: 'Trusted Vendors',
                                description: 'Connect with reliable vendors offering exceptional services and products.',
                                color: 'success.main',
                            },
                        ].map((feature) => (
                            <Box
                                key={feature.title}
                                sx={{
                                    borderRadius: 3,
                                    border: 1,
                                    borderColor: 'divider',
                                    bgcolor: 'background.paper',
                                    p: 3,
                                    textAlign: 'center',
                                    boxShadow: 1,
                                    transition: 'all 0.2s',
                                    '&:hover': { boxShadow: 3 },
                                }}
                            >
                                <Box
                                    sx={{
                                        mx: 'auto',
                                        mb: 2,
                                        display: 'flex',
                                        width: 56,
                                        height: 56,
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        borderRadius: '50%',
                                        bgcolor: `${feature.color}`,
                                        opacity: 0.1,
                                        position: 'relative',
                                    }}
                                >
                                    <feature.icon style={{ width: 28, height: 28 }} />
                                </Box>
                                <Typography variant="h6" fontWeight={600} sx={{ mb: 1 }}>
                                    {feature.title}
                                </Typography>
                                <Typography variant="body2" color="text.secondary">
                                    {feature.description}
                                </Typography>
                            </Box>
                        ))}
                    </Box>
                </Box>

                {/* Footer */}
                <Box
                    component="footer"
                    sx={{
                        borderTop: 1,
                        borderColor: 'divider',
                        bgcolor: 'rgba(255,255,255,0.95)',
                        backdropFilter: 'blur(8px)',
                    }}
                >
                    <Box
                        sx={{
                            maxWidth: 1200,
                            mx: 'auto',
                            px: 2,
                            py: 4,
                            textAlign: 'center',
                        }}
                    >
                        <Typography variant="body2" color="text.secondary">
                            &copy; {new Date().getFullYear()} SurpriseMoi. All rights reserved.
                        </Typography>
                    </Box>
                </Box>
            </Box>
        </>
    );
}
