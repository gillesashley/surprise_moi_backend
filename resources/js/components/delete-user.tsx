import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { alpha } from '@mui/material/styles';
import { Form } from '@inertiajs/react';
import { useRef } from 'react';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            <HeadingSmall
                title="Delete account"
                description="Delete your account and all of its resources"
            />
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2,
                    borderRadius: 2,
                    border: 1,
                    borderColor: 'error.light',
                    bgcolor: (theme) =>
                        alpha(theme.palette.error.main, 0.05),
                    p: 2,
                }}
            >
                <Box
                    sx={{
                        position: 'relative',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 0.25,
                        color: 'error.main',
                    }}
                >
                    <Typography sx={{ fontWeight: 500 }}>Warning</Typography>
                    <Typography variant="body2">
                        Please proceed with caution, this cannot be undone.
                    </Typography>
                </Box>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 24,
                            }}
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            gap: 1,
                                        }}
                                    >
                                        <Label
                                            htmlFor="password"
                                            style={{
                                                position: 'absolute',
                                                width: 1,
                                                height: 1,
                                                padding: 0,
                                                margin: -1,
                                                overflow: 'hidden',
                                                clip: 'rect(0,0,0,0)',
                                                whiteSpace: 'nowrap',
                                                borderWidth: 0,
                                            }}
                                        >
                                            Password
                                        </Label>

                                        <Input
                                            id="password"
                                            type="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                        />

                                        <InputError message={errors.password} />
                                    </Box>

                                    <DialogFooter style={{ gap: 8 }}>
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </Box>
        </Box>
    );
}
