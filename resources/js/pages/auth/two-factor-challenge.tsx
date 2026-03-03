import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/two-factor/login/index';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Recovery Code',
                description:
                    'Please confirm access to your account by entering one of your emergency recovery codes.',
                toggleText: 'login using an authentication code',
            };
        }

        return {
            title: 'Authentication Code',
            description:
                'Enter the authentication code provided by your authenticator application.',
            toggleText: 'login using a recovery code',
        };
    }, [showRecoveryInput]);

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <AuthLayout
            title={authConfigContent.title}
            description={authConfigContent.description}
        >
            <Head title="Two-Factor Authentication" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                <Form
                    {...store.form()}
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <>
                                    <Input
                                        name="recovery_code"
                                        type="text"
                                        placeholder="Enter recovery code"
                                        autoFocus={showRecoveryInput}
                                        required
                                    />
                                    <InputError
                                        message={errors.recovery_code}
                                    />
                                </>
                            ) : (
                                <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 1.5, textAlign: 'center' }}>
                                    <Box sx={{ display: 'flex', width: '100%', alignItems: 'center', justifyContent: 'center' }}>
                                        <InputOTP
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </Box>
                                    <InputError message={errors.code} />
                                </Box>
                            )}

                            <Button
                                type="submit"
                                sx={{ width: '100%' }}
                                disabled={processing}
                            >
                                Continue
                            </Button>

                            <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center' }}>
                                <span>or you can </span>
                                <Box
                                    component="button"
                                    type="button"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                    sx={{
                                        cursor: 'pointer',
                                        color: 'text.primary',
                                        textDecoration: 'underline',
                                        textUnderlineOffset: '4px',
                                        transition: 'text-decoration-color 0.3s ease-out',
                                        background: 'none',
                                        border: 'none',
                                        padding: 0,
                                        font: 'inherit',
                                        '&:hover': {
                                            textDecorationColor: 'currentcolor',
                                        },
                                    }}
                                >
                                    {authConfigContent.toggleText}
                                </Box>
                            </Typography>
                        </>
                    )}
                </Form>
            </Box>
        </AuthLayout>
    );
}
