import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Lock, ShieldAlert } from 'lucide-react';
import { type ReactNode, useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'um_unlock_ts';
const UNLOCK_DURATION_MS = 20 * 60 * 1000; // 20 minutes
const SECRET_CODE = 'urZTl65tXkK32rMN';

function isUnlocked(): boolean {
    const ts = sessionStorage.getItem(STORAGE_KEY);
    if (!ts) {
        return false;
    }
    const elapsed = Date.now() - parseInt(ts, 10);
    return elapsed < UNLOCK_DURATION_MS;
}

interface UserManagementGuardProps {
    children: ReactNode;
}

export default function UserManagementGuard({ children }: UserManagementGuardProps) {
    const [unlocked, setUnlocked] = useState(() => isUnlocked());
    const [code, setCode] = useState('');
    const [error, setError] = useState('');

    // Re-check expiry periodically (every 30 seconds)
    useEffect(() => {
        if (!unlocked) {
            return;
        }

        const interval = setInterval(() => {
            if (!isUnlocked()) {
                setUnlocked(false);
                sessionStorage.removeItem(STORAGE_KEY);
            }
        }, 30_000);

        return () => clearInterval(interval);
    }, [unlocked]);

    const handleSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            if (code === SECRET_CODE) {
                sessionStorage.setItem(STORAGE_KEY, Date.now().toString());
                setUnlocked(true);
                setError('');
                setCode('');
            } else {
                setError('Invalid access code. Please try again.');
                setCode('');
            }
        },
        [code],
    );

    if (unlocked) {
        return <>{children}</>;
    }

    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                minHeight: '100vh',
                bgcolor: 'background.default',
                p: 2,
            }}
        >
            <Box
                sx={{
                    maxWidth: 420,
                    width: '100%',
                    textAlign: 'center',
                }}
            >
                <Box
                    sx={{
                        display: 'flex',
                        justifyContent: 'center',
                        mb: 3,
                    }}
                >
                    <Box
                        sx={{
                            bgcolor: 'action.hover',
                            borderRadius: '50%',
                            p: 2.5,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <Lock size={40} style={{ opacity: 0.7 }} />
                    </Box>
                </Box>

                <Typography variant="h5" fontWeight={700} gutterBottom>
                    Restricted Area
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 4 }}>
                    User Management contains sensitive data. Enter the access code to continue.
                </Typography>

                <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    <Input
                        type="password"
                        placeholder="Enter access code"
                        value={code}
                        onChange={(e) => {
                            setCode(e.target.value);
                            setError('');
                        }}
                        autoFocus
                    />

                    {error && (
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'error.main' }}>
                            <ShieldAlert size={16} />
                            <Typography variant="body2" color="error">
                                {error}
                            </Typography>
                        </Box>
                    )}

                    <Button type="submit" disabled={!code.trim()}>
                        Unlock
                    </Button>
                </Box>

                <Typography variant="caption" color="text.disabled" sx={{ mt: 3, display: 'block' }}>
                    Access expires after 20 minutes of inactivity.
                </Typography>
            </Box>
        </Box>
    );
}
