import * as React from 'react';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { XIcon } from 'lucide-react';

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

interface SheetContextValue {
    open: boolean;
    setOpen: (open: boolean) => void;
    onClose: () => void;
}

const SheetContext = React.createContext<SheetContextValue | undefined>(undefined);

function useSheetContext(): SheetContextValue {
    const ctx = React.useContext(SheetContext);
    if (!ctx) {
        throw new Error('Sheet compound components must be used within <Sheet>');
    }
    return ctx;
}

// ---------------------------------------------------------------------------
// Sheet (root provider) — supports controlled & uncontrolled
// ---------------------------------------------------------------------------

interface SheetProps {
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children?: React.ReactNode;
}

function Sheet({ open: controlledOpen, onOpenChange, children }: SheetProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = React.useState(false);

    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : uncontrolledOpen;

    const setOpen = React.useCallback(
        (value: boolean) => {
            if (!isControlled) {
                setUncontrolledOpen(value);
            }
            onOpenChange?.(value);
        },
        [isControlled, onOpenChange],
    );

    const onClose = React.useCallback(() => setOpen(false), [setOpen]);

    const value = React.useMemo<SheetContextValue>(
        () => ({ open, setOpen, onClose }),
        [open, setOpen, onClose],
    );

    return (
        <SheetContext.Provider value={value}>
            {children}
        </SheetContext.Provider>
    );
}

// ---------------------------------------------------------------------------
// SheetTrigger — opens the drawer, supports `asChild`
// ---------------------------------------------------------------------------

interface SheetTriggerProps extends React.HTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
    children?: React.ReactNode;
}

function SheetTrigger({ asChild = false, children, onClick, ...props }: SheetTriggerProps) {
    const { setOpen } = useSheetContext();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        setOpen(true);
        onClick?.(e);
    };

    if (asChild && React.isValidElement(children)) {
        return React.cloneElement(children as React.ReactElement<any>, {
            onClick: (e: React.MouseEvent) => {
                setOpen(true);
                (children as React.ReactElement<any>).props?.onClick?.(e);
            },
        });
    }

    return (
        <button data-slot="sheet-trigger" onClick={handleClick} {...props}>
            {children}
        </button>
    );
}

// ---------------------------------------------------------------------------
// SheetClose — closes the drawer, supports `asChild`
// ---------------------------------------------------------------------------

interface SheetCloseProps extends React.HTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
    children?: React.ReactNode;
}

function SheetClose({ asChild = false, children, onClick, ...props }: SheetCloseProps) {
    const { onClose } = useSheetContext();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onClose();
        onClick?.(e);
    };

    if (asChild && React.isValidElement(children)) {
        return React.cloneElement(children as React.ReactElement<any>, {
            onClick: (e: React.MouseEvent) => {
                onClose();
                (children as React.ReactElement<any>).props?.onClick?.(e);
            },
        });
    }

    return (
        <button data-slot="sheet-close" onClick={handleClick} {...props}>
            {children}
        </button>
    );
}

// ---------------------------------------------------------------------------
// SheetContent — renders MUI Drawer
// ---------------------------------------------------------------------------

type Side = 'top' | 'right' | 'bottom' | 'left';

interface SheetContentProps extends React.HTMLAttributes<HTMLDivElement> {
    side?: Side;
    children?: React.ReactNode;
}

function SheetContent({ side = 'right', className, children, ...props }: SheetContentProps) {
    const { open, onClose } = useSheetContext();

    const isHorizontal = side === 'left' || side === 'right';

    return (
        <Drawer
            data-slot="sheet-content"
            variant="temporary"
            anchor={side}
            open={open}
            onClose={onClose}
            slotProps={{
                paper: {
                    className,
                    sx: {
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 2,
                        ...(isHorizontal
                            ? { width: { xs: '75%', sm: 400 } }
                            : { height: 'auto' }),
                    },
                    ...props,
                },
            }}
        >
            {children}
            <IconButton
                data-slot="sheet-close"
                onClick={onClose}
                aria-label="Close"
                sx={{
                    position: 'absolute',
                    top: 8,
                    right: 8,
                }}
                size="small"
            >
                <XIcon size={16} />
            </IconButton>
        </Drawer>
    );
}

// ---------------------------------------------------------------------------
// SheetHeader
// ---------------------------------------------------------------------------

interface SheetHeaderProps extends React.HTMLAttributes<HTMLDivElement> {
    children?: React.ReactNode;
}

function SheetHeader({ className, children, ...props }: SheetHeaderProps) {
    return (
        <Box
            data-slot="sheet-header"
            className={className}
            sx={{ display: 'flex', flexDirection: 'column', gap: 0.75, p: 2 }}
            {...props}
        >
            {children}
        </Box>
    );
}

// ---------------------------------------------------------------------------
// SheetFooter
// ---------------------------------------------------------------------------

interface SheetFooterProps extends React.HTMLAttributes<HTMLDivElement> {
    children?: React.ReactNode;
}

function SheetFooter({ className, children, ...props }: SheetFooterProps) {
    return (
        <Box
            data-slot="sheet-footer"
            className={className}
            sx={{ mt: 'auto', display: 'flex', flexDirection: 'column', gap: 1, p: 2 }}
            {...props}
        >
            {children}
        </Box>
    );
}

// ---------------------------------------------------------------------------
// SheetTitle
// ---------------------------------------------------------------------------

interface SheetTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
    children?: React.ReactNode;
}

function SheetTitle({ className, children, ...props }: SheetTitleProps) {
    return (
        <Typography
            data-slot="sheet-title"
            variant="h6"
            component="h2"
            className={className}
            {...props}
        >
            {children}
        </Typography>
    );
}

// ---------------------------------------------------------------------------
// SheetDescription
// ---------------------------------------------------------------------------

interface SheetDescriptionProps extends React.HTMLAttributes<HTMLParagraphElement> {
    children?: React.ReactNode;
}

function SheetDescription({ className, children, ...props }: SheetDescriptionProps) {
    return (
        <Typography
            data-slot="sheet-description"
            variant="body2"
            color="text.secondary"
            className={className}
            {...props}
        >
            {children}
        </Typography>
    );
}

// ---------------------------------------------------------------------------
// Exports — identical 8 named exports
// ---------------------------------------------------------------------------

export {
    Sheet,
    SheetTrigger,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetFooter,
    SheetTitle,
    SheetDescription,
};
