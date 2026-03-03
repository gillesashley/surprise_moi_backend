import { submit } from '@/actions/App/Http/Controllers/AccountDeletionController';
import { type SharedData } from '@/types';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import MuiButton from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Gift, Shield, Trash2 } from 'lucide-react';

export default function AccountDeletion() {
    const page = usePage<SharedData>();
    const status = page.props.status as string | undefined;
    const { data, setData, post, processing } = useForm({
        email: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(submit().url);
    }

    return (
        <>
            <Head title="Request Account Deletion" />
            <Box
                sx={{
                    minHeight: '100vh',
                    background: (theme) =>
                        `linear-gradient(135deg, ${theme.palette.primary.main}1a, ${theme.palette.background.default}, ${theme.palette.secondary.main}1a)`,
                }}
            >
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
                    </Box>
                </Box>

                <Box component="main" sx={{ maxWidth: 1200, mx: 'auto', px: 2, py: 6 }}>
                    <Box sx={{ mx: 'auto', maxWidth: 512 }}>
                        <Box sx={{ mb: 4, textAlign: 'center' }}>
                            <Box
                                sx={{
                                    mx: 'auto',
                                    mb: 2,
                                    display: 'flex',
                                    width: 64,
                                    height: 64,
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    borderRadius: '50%',
                                    bgcolor: 'error.main',
                                    opacity: 0.1,
                                    position: 'relative',
                                }}
                            >
                                <Trash2 style={{ width: 32, height: 32 }} />
                            </Box>
                            <Typography variant="h4" fontWeight={700} sx={{ mb: 1 }}>
                                Request Account Deletion
                            </Typography>
                            <Typography color="text.secondary">
                                Delete your SurpriseMoi account and all
                                associated data
                            </Typography>
                        </Box>

                        {status && (
                            <Alert severity="success" sx={{ mb: 3 }}>
                                {status}
                            </Alert>
                        )}

                        <Box
                            sx={{
                                mb: 3,
                                borderRadius: 2,
                                border: 1,
                                borderColor: 'divider',
                                bgcolor: 'background.paper',
                                p: 3,
                                boxShadow: 1,
                            }}
                        >
                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <AlertTriangle
                                    style={{
                                        width: 20,
                                        height: 20,
                                        marginTop: 2,
                                        flexShrink: 0,
                                    }}
                                />
                                <Box>
                                    <Typography
                                        variant="subtitle2"
                                        color="error"
                                        fontWeight={600}
                                        sx={{ mb: 1 }}
                                    >
                                        Warning: This action is irreversible
                                    </Typography>
                                    <Box
                                        component="ul"
                                        sx={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 0.5,
                                            pl: 0,
                                            listStyle: 'none',
                                        }}
                                    >
                                        {[
                                            'Your profile and personal information will be deleted',
                                            'Your order history and saved data will be removed',
                                            'Any active orders or pending transactions may be affected',
                                            'This action cannot be undone',
                                        ].map((item) => (
                                            <Typography
                                                component="li"
                                                variant="body2"
                                                color="text.secondary"
                                                key={item}
                                            >
                                                {item}
                                            </Typography>
                                        ))}
                                    </Box>
                                </Box>
                            </Box>
                        </Box>

                        <Box
                            component="form"
                            onSubmit={handleSubmit}
                            sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}
                        >
                            <TextField
                                type="email"
                                id="email"
                                label="Email Address"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="Enter your registered email"
                                required
                                fullWidth
                                size="small"
                            />

                            <MuiButton
                                type="submit"
                                variant="contained"
                                color="error"
                                disabled={processing}
                                fullWidth
                                sx={{ py: 1.5 }}
                            >
                                {processing
                                    ? 'Processing...'
                                    : 'Delete My Account'}
                            </MuiButton>
                        </Box>

                        <Box
                            sx={{
                                mt: 3,
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: 1.5,
                                borderRadius: 2,
                                bgcolor: 'action.hover',
                                p: 2,
                            }}
                        >
                            <Shield
                                style={{
                                    width: 20,
                                    height: 20,
                                    marginTop: 2,
                                    flexShrink: 0,
                                }}
                            />
                            <Box>
                                <Typography variant="body2" fontWeight={500}>
                                    Your Privacy Matters
                                </Typography>
                                <Typography variant="body2" color="text.secondary">
                                    We take your privacy seriously. Your data
                                    will be permanently removed from our systems
                                    in accordance with our privacy policy.
                                </Typography>
                            </Box>
                        </Box>
                    </Box>
                </Box>

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
                            &copy; {new Date().getFullYear()} SurpriseMoi. All
                            rights reserved.
                        </Typography>
                    </Box>
                </Box>
            </Box>
        </>
    );
}
