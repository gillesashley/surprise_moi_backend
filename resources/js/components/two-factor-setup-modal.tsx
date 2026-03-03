import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { confirm } from '@/routes/two-factor';
import Box from '@mui/material/Box';
import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from './alert-error';
import { Spinner } from './ui/spinner';

function GridScanIcon() {
    return (
        <Box
            sx={{
                mb: 1.5,
                borderRadius: '50%',
                border: 1,
                borderColor: 'divider',
                bgcolor: 'background.paper',
                p: 0.25,
                boxShadow: 1,
            }}
        >
            <Box
                sx={{
                    position: 'relative',
                    overflow: 'hidden',
                    borderRadius: '50%',
                    border: 1,
                    borderColor: 'divider',
                    bgcolor: 'action.hover',
                    p: 1.25,
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        display: 'grid',
                        gridTemplateColumns: 'repeat(5, 1fr)',
                        opacity: 0.5,
                    }}
                >
                    {Array.from({ length: 5 }, (_, i) => (
                        <Box
                            key={`col-${i + 1}`}
                            sx={{
                                borderRight: i < 4 ? 1 : 0,
                                borderColor: 'divider',
                            }}
                        />
                    ))}
                </Box>
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        display: 'grid',
                        gridTemplateRows: 'repeat(5, 1fr)',
                        opacity: 0.5,
                    }}
                >
                    {Array.from({ length: 5 }, (_, i) => (
                        <Box
                            key={`row-${i + 1}`}
                            sx={{
                                borderBottom: i < 4 ? 1 : 0,
                                borderColor: 'divider',
                            }}
                        />
                    ))}
                </Box>
                <ScanLine
                    style={{
                        position: 'relative',
                        zIndex: 20,
                        width: 24,
                        height: 24,
                    }}
                />
            </Box>
        </Box>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    return (
        <>
            {errors?.length ? (
                <AlertError errors={errors} />
            ) : (
                <>
                    <Box
                        sx={{
                            mx: 'auto',
                            display: 'flex',
                            maxWidth: 'md',
                            overflow: 'hidden',
                        }}
                    >
                        <Box
                            sx={{
                                mx: 'auto',
                                aspectRatio: '1 / 1',
                                width: 256,
                                borderRadius: 2,
                                border: 1,
                                borderColor: 'divider',
                            }}
                        >
                            <Box
                                sx={{
                                    zIndex: 10,
                                    display: 'flex',
                                    height: '100%',
                                    width: '100%',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    p: 2.5,
                                }}
                            >
                                {qrCodeSvg ? (
                                    <div
                                        dangerouslySetInnerHTML={{
                                            __html: qrCodeSvg,
                                        }}
                                    />
                                ) : (
                                    <Spinner />
                                )}
                            </Box>
                        </Box>
                    </Box>

                    <Box
                        sx={{
                            display: 'flex',
                            width: '100%',
                            gap: 2.5,
                        }}
                    >
                        <Button
                            onClick={onNextStep}
                            style={{ width: '100%' }}
                        >
                            {buttonText}
                        </Button>
                    </Box>

                    <Box
                        sx={{
                            position: 'relative',
                            display: 'flex',
                            width: '100%',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <Box
                            sx={{
                                position: 'absolute',
                                inset: 0,
                                top: '50%',
                                height: '1px',
                                width: '100%',
                                bgcolor: 'divider',
                            }}
                        />
                        <Box
                            component="span"
                            sx={{
                                position: 'relative',
                                bgcolor: 'background.paper',
                                px: 1,
                                py: 0.5,
                            }}
                        >
                            or, enter the code manually
                        </Box>
                    </Box>

                    <Box
                        sx={{
                            display: 'flex',
                            width: '100%',
                            gap: 1,
                        }}
                    >
                        <Box
                            sx={{
                                display: 'flex',
                                width: '100%',
                                alignItems: 'stretch',
                                overflow: 'hidden',
                                borderRadius: 3,
                                border: 1,
                                borderColor: 'divider',
                            }}
                        >
                            {!manualSetupKey ? (
                                <Box
                                    sx={{
                                        display: 'flex',
                                        height: '100%',
                                        width: '100%',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        bgcolor: 'action.hover',
                                        p: 1.5,
                                    }}
                                >
                                    <Spinner />
                                </Box>
                            ) : (
                                <>
                                    <Box
                                        component="input"
                                        type="text"
                                        readOnly
                                        value={manualSetupKey}
                                        sx={{
                                            height: '100%',
                                            width: '100%',
                                            bgcolor: 'background.paper',
                                            color: 'text.primary',
                                            p: 1.5,
                                            outline: 'none',
                                            border: 'none',
                                        }}
                                    />
                                    <Box
                                        component="button"
                                        onClick={() => copy(manualSetupKey)}
                                        sx={{
                                            borderLeft: 1,
                                            borderColor: 'divider',
                                            px: 1.5,
                                            display: 'flex',
                                            alignItems: 'center',
                                            bgcolor: 'transparent',
                                            cursor: 'pointer',
                                            border: 'none',
                                            borderLeftWidth: '1px',
                                            borderLeftStyle: 'solid',
                                            borderLeftColor: 'divider',
                                            '&:hover': {
                                                bgcolor: 'action.hover',
                                            },
                                        }}
                                    >
                                        <IconComponent
                                            style={{ width: 16 }}
                                        />
                                    </Box>
                                </>
                            )}
                        </Box>
                    </Box>
                </>
            )}
        </>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState<string>('');
    const pinInputContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    return (
        <Form
            {...confirm.form()}
            onSuccess={() => onClose()}
            resetOnError
            resetOnSuccess
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <>
                    <Box
                        ref={pinInputContainerRef}
                        sx={{
                            position: 'relative',
                            width: '100%',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 1.5,
                        }}
                    >
                        <Box
                            sx={{
                                display: 'flex',
                                width: '100%',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 1.5,
                                py: 1,
                            }}
                        >
                            <InputOTP
                                id="otp"
                                name="code"
                                maxLength={OTP_MAX_LENGTH}
                                onChange={setCode}
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
                            <InputError
                                message={
                                    errors?.confirmTwoFactorAuthentication?.code
                                }
                            />
                        </Box>

                        <Box
                            sx={{
                                display: 'flex',
                                width: '100%',
                                gap: 2.5,
                            }}
                        >
                            <Button
                                type="button"
                                variant="outline"
                                style={{ flex: 1 }}
                                onClick={onBack}
                                disabled={processing}
                            >
                                Back
                            </Button>
                            <Button
                                type="submit"
                                style={{ flex: 1 }}
                                disabled={
                                    processing || code.length < OTP_MAX_LENGTH
                                }
                            >
                                Confirm
                            </Button>
                        </Box>
                    </Box>
                </>
            )}
        </Form>
    );
}

interface TwoFactorSetupModalProps {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
}

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: TwoFactorSetupModalProps) {
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);

    const modalConfig = useMemo<{
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                title: 'Two-Factor Authentication Enabled',
                description:
                    'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
                buttonText: 'Close',
            };
        }

        if (showVerificationStep) {
            return {
                title: 'Verify Authentication Code',
                description:
                    'Enter the 6-digit code from your authenticator app',
                buttonText: 'Continue',
            };
        }

        return {
            title: 'Enable Two-Factor Authentication',
            description:
                'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);
            return;
        }

        clearSetupData();
        onClose();
    }, [requiresConfirmation, clearSetupData, onClose]);

    const resetModalState = useCallback(() => {
        setShowVerificationStep(false);

        if (twoFactorEnabled) {
            clearSetupData();
        }
    }, [twoFactorEnabled, clearSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            fetchSetupData();
        }
    }, [isOpen, qrCodeSvg, fetchSetupData]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent>
                <DialogHeader
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <GridScanIcon />
                    <DialogTitle>{modalConfig.title}</DialogTitle>
                    <DialogDescription style={{ textAlign: 'center' }}>
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 2.5,
                    }}
                >
                    {showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={onClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <TwoFactorSetupStep
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            buttonText={modalConfig.buttonText}
                            onNextStep={handleModalNextStep}
                            errors={errors}
                        />
                    )}
                </Box>
            </DialogContent>
        </Dialog>
    );
}
