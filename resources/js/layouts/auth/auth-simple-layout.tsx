import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <Box
            sx={{
                display: 'flex',
                minHeight: '100svh',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 3,
                bgcolor: 'background.default',
                p: { xs: 3, md: 5 },
            }}
        >
            <Box sx={{ width: '100%', maxWidth: '24rem' }}>
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 4,
                    }}
                >
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: 2,
                        }}
                    >
                        <Link
                            href={home()}
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: '0.5rem',
                                fontWeight: 500,
                                textDecoration: 'none',
                                color: 'inherit',
                            }}
                        >
                            <Box
                                sx={{
                                    mb: 0.5,
                                    display: 'flex',
                                    height: 36,
                                    width: 36,
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    borderRadius: 1,
                                }}
                            >
                                <AppLogoIcon
                                    style={{ width: 36, height: 36 }}
                                />
                            </Box>
                            <Box
                                component="span"
                                sx={{
                                    position: 'absolute',
                                    width: 1,
                                    height: 1,
                                    p: 0,
                                    m: -1,
                                    overflow: 'hidden',
                                    clip: 'rect(0,0,0,0)',
                                    whiteSpace: 'nowrap',
                                    border: 0,
                                }}
                            >
                                {title}
                            </Box>
                        </Link>

                        <Box
                            sx={{
                                textAlign: 'center',
                                '& > * + *': { mt: 1 },
                            }}
                        >
                            <Typography
                                variant="h5"
                                sx={{ fontWeight: 500 }}
                            >
                                {title}
                            </Typography>
                            <Typography
                                variant="body2"
                                color="text.secondary"
                                sx={{ textAlign: 'center' }}
                            >
                                {description}
                            </Typography>
                        </Box>
                    </Box>
                    {children}
                </Box>
            </Box>
        </Box>
    );
}
