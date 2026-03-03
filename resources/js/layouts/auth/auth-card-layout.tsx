import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import Box from '@mui/material/Box';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <Box
            sx={{
                display: 'flex',
                minHeight: '100svh',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 3,
                bgcolor: 'action.hover',
                p: { xs: 3, md: 5 },
            }}
        >
            <Box
                sx={{
                    display: 'flex',
                    width: '100%',
                    maxWidth: '28rem',
                    flexDirection: 'column',
                    gap: 3,
                }}
            >
                <Link
                    href={home()}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '0.5rem',
                        alignSelf: 'center',
                        fontWeight: 500,
                        textDecoration: 'none',
                        color: 'inherit',
                    }}
                >
                    <Box
                        sx={{
                            display: 'flex',
                            height: 36,
                            width: 36,
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AppLogoIcon
                            style={{ width: 36, height: 36 }}
                        />
                    </Box>
                </Link>

                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 3,
                    }}
                >
                    <Card style={{ borderRadius: 12 }}>
                        <CardHeader style={{ paddingLeft: 40, paddingRight: 40, paddingTop: 32, paddingBottom: 0, textAlign: 'center' }}>
                            <CardTitle style={{ fontSize: '1.25rem' }}>
                                {title}
                            </CardTitle>
                            <CardDescription>
                                {description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent style={{ paddingLeft: 40, paddingRight: 40, paddingTop: 32, paddingBottom: 32 }}>
                            {children}
                        </CardContent>
                    </Card>
                </Box>
            </Box>
        </Box>
    );
}
