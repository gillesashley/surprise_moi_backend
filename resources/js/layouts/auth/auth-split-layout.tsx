import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { type SharedData } from '@/types';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { name, quote } = usePage<SharedData>().props;

    return (
        <Box
            sx={{
                position: 'relative',
                display: 'grid',
                height: '100dvh',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                px: { xs: 4, sm: 0 },
                gridTemplateColumns: { lg: '1fr 1fr' },
            }}
        >
            <Box
                sx={{
                    position: 'relative',
                    display: { xs: 'none', lg: 'flex' },
                    height: '100%',
                    flexDirection: 'column',
                    bgcolor: 'grey.900',
                    p: 5,
                    color: 'white',
                    borderRight: { lg: 1 },
                    borderColor: 'divider',
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        bgcolor: 'grey.900',
                    }}
                />
                <Link
                    href={home()}
                    style={{
                        position: 'relative',
                        zIndex: 20,
                        display: 'flex',
                        alignItems: 'center',
                        fontSize: '1.125rem',
                        fontWeight: 500,
                        textDecoration: 'none',
                        color: 'inherit',
                    }}
                >
                    <AppLogoIcon
                        style={{
                            marginRight: '0.5rem',
                            width: 32,
                            height: 32,
                        }}
                    />
                    {name}
                </Link>
                {quote && (
                    <Box
                        sx={{
                            position: 'relative',
                            zIndex: 20,
                            mt: 'auto',
                        }}
                    >
                        <Box component="blockquote" sx={{ '& > * + *': { mt: 1 } }}>
                            <Typography
                                variant="body1"
                                sx={{ fontSize: '1.125rem' }}
                            >
                                &ldquo;{quote.message}&rdquo;
                            </Typography>
                            <Typography
                                component="footer"
                                variant="body2"
                                sx={{ color: 'grey.400' }}
                            >
                                {quote.author}
                            </Typography>
                        </Box>
                    </Box>
                )}
            </Box>
            <Box sx={{ width: '100%', p: { lg: 4 } }}>
                <Box
                    sx={{
                        mx: 'auto',
                        display: 'flex',
                        width: { xs: '100%', sm: 350 },
                        flexDirection: 'column',
                        justifyContent: 'center',
                        '& > * + *': { mt: 3 },
                    }}
                >
                    <Link
                        href={home()}
                        style={{
                            position: 'relative',
                            zIndex: 20,
                            textDecoration: 'none',
                            color: 'inherit',
                        }}
                    >
                        <Box
                            sx={{
                                display: { xs: 'flex', lg: 'none' },
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <AppLogoIcon
                                style={{ height: 40 }}
                            />
                        </Box>
                    </Link>
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: { xs: 'flex-start', sm: 'center' },
                            gap: 1,
                            textAlign: { xs: 'left', sm: 'center' },
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
                            sx={{ textWrap: 'balance' }}
                        >
                            {description}
                        </Typography>
                    </Box>
                    {children}
                </Box>
            </Box>
        </Box>
    );
}
