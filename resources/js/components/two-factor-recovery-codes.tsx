import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
import Box from '@mui/material/Box';
import Collapse from '@mui/material/Collapse';
import Skeleton from '@mui/material/Skeleton';
import Typography from '@mui/material/Typography';
import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from './alert-error';

interface TwoFactorRecoveryCodesProps {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
}

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: TwoFactorRecoveryCodesProps) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <Card>
            <CardHeader>
                <CardTitle
                    style={{
                        display: 'flex',
                        gap: 12,
                        alignItems: 'center',
                    }}
                >
                    <LockKeyhole
                        style={{ width: 16, height: 16 }}
                        aria-hidden="true"
                    />
                    2FA Recovery Codes
                </CardTitle>
                <CardDescription>
                    Recovery codes let you regain access if you lose your 2FA
                    device. Store them in a secure password manager.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 1.5,
                        userSelect: 'none',
                        sm: {
                            flexDirection: 'row',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                        },
                    }}
                >
                    <Button
                        onClick={toggleCodesVisibility}
                        style={{ width: 'fit-content' }}
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        <RecoveryCodeIconComponent
                            style={{ width: 16, height: 16 }}
                            aria-hidden="true"
                        />
                        {codesAreVisible ? 'Hide' : 'View'} Recovery Codes
                    </Button>

                    {canRegenerateCodes && (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={fetchRecoveryCodes}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="secondary"
                                    type="submit"
                                    disabled={processing}
                                    aria-describedby="regenerate-warning"
                                >
                                    <RefreshCw /> Regenerate Codes
                                </Button>
                            )}
                        </Form>
                    )}
                </Box>
                <Collapse
                    in={codesAreVisible}
                    id="recovery-codes-section"
                    aria-hidden={!codesAreVisible}
                >
                    <Box
                        sx={{
                            mt: 1.5,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 1.5,
                        }}
                    >
                        {errors?.length ? (
                            <AlertError errors={errors} />
                        ) : (
                            <>
                                <Box
                                    ref={codesSectionRef}
                                    sx={{
                                        display: 'grid',
                                        gap: 0.5,
                                        borderRadius: 2,
                                        bgcolor: 'action.hover',
                                        p: 2,
                                        fontFamily: 'monospace',
                                        fontSize: '0.875rem',
                                    }}
                                    role="list"
                                    aria-label="Recovery codes"
                                >
                                    {recoveryCodesList.length ? (
                                        recoveryCodesList.map((code, index) => (
                                            <Box
                                                key={index}
                                                role="listitem"
                                                sx={{ userSelect: 'text' }}
                                            >
                                                {code}
                                            </Box>
                                        ))
                                    ) : (
                                        <Box
                                            sx={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 1,
                                            }}
                                            aria-label="Loading recovery codes"
                                        >
                                            {Array.from(
                                                { length: 8 },
                                                (_, index) => (
                                                    <Skeleton
                                                        key={index}
                                                        variant="rectangular"
                                                        height={16}
                                                        aria-hidden="true"
                                                    />
                                                ),
                                            )}
                                        </Box>
                                    )}
                                </Box>

                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{ userSelect: 'none' }}
                                >
                                    <span id="regenerate-warning">
                                        Each recovery code can be used once to
                                        access your account and will be removed
                                        after use. If you need more, click{' '}
                                        <Box
                                            component="span"
                                            sx={{ fontWeight: 700 }}
                                        >
                                            Regenerate Codes
                                        </Box>{' '}
                                        above.
                                    </span>
                                </Typography>
                            </>
                        )}
                    </Box>
                </Collapse>
            </CardContent>
        </Card>
    );
}
