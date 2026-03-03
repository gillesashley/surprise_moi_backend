import * as React from "react"
import MuiDialog from "@mui/material/Dialog"
import MuiDialogActions from "@mui/material/DialogActions"
import MuiDialogTitle from "@mui/material/DialogTitle"
import IconButton from "@mui/material/IconButton"
import Typography from "@mui/material/Typography"
import Box from "@mui/material/Box"
import { XIcon } from "lucide-react"

// ---------------------------------------------------------------------------
// Context – shares open/close state between compound components
// ---------------------------------------------------------------------------
interface DialogContextValue {
  open: boolean
  onClose: () => void
  onOpen: () => void
}

const DialogContext = React.createContext<DialogContextValue | null>(null)

function useDialogContext(): DialogContextValue {
  const ctx = React.useContext(DialogContext)
  if (!ctx) {
    throw new Error("Dialog compound components must be rendered inside <Dialog>")
  }
  return ctx
}

// ---------------------------------------------------------------------------
// Dialog (root) – context provider, manages open state
// ---------------------------------------------------------------------------
interface DialogProps {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  defaultOpen?: boolean
  children?: React.ReactNode
}

function Dialog({ open: controlledOpen, onOpenChange, defaultOpen = false, children }: DialogProps) {
  const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : uncontrolledOpen

  const onClose = React.useCallback(() => {
    if (onOpenChange) {
      onOpenChange(false)
    }
    if (!isControlled) {
      setUncontrolledOpen(false)
    }
  }, [onOpenChange, isControlled])

  const onOpen = React.useCallback(() => {
    if (onOpenChange) {
      onOpenChange(true)
    }
    if (!isControlled) {
      setUncontrolledOpen(true)
    }
  }, [onOpenChange, isControlled])

  const value = React.useMemo(() => ({ open, onClose, onOpen }), [open, onClose, onOpen])

  return (
    <DialogContext.Provider value={value}>
      {children}
    </DialogContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// DialogTrigger – opens the dialog on click
// ---------------------------------------------------------------------------
interface DialogTriggerProps {
  asChild?: boolean
  children?: React.ReactNode
  className?: string
  [key: string]: unknown
}

function DialogTrigger({ asChild, children, ...props }: DialogTriggerProps) {
  const { onOpen } = useDialogContext()

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      onClick: (...args: unknown[]) => {
        const childProps = (children as React.ReactElement<Record<string, unknown>>).props
        if (typeof childProps.onClick === "function") {
          ;(childProps.onClick as (...a: unknown[]) => void)(...args)
        }
        onOpen()
      },
      "data-slot": "dialog-trigger",
    })
  }

  return (
    <button data-slot="dialog-trigger" onClick={onOpen} {...props}>
      {children}
    </button>
  )
}

// ---------------------------------------------------------------------------
// DialogPortal – MUI handles its own portal, so this is a pass-through
// ---------------------------------------------------------------------------
interface DialogPortalProps {
  children?: React.ReactNode
  [key: string]: unknown
}

function DialogPortal({ children }: DialogPortalProps) {
  return <>{children}</>
}

// ---------------------------------------------------------------------------
// DialogOverlay – MUI handles its own backdrop, so this returns null
// ---------------------------------------------------------------------------
interface DialogOverlayProps {
  className?: string
  [key: string]: unknown
}

function DialogOverlay(_props: DialogOverlayProps) {
  return null
}

// ---------------------------------------------------------------------------
// DialogContent – renders MUI Dialog with close button
// ---------------------------------------------------------------------------
interface DialogContentProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode
  className?: string
  'aria-describedby'?: string | undefined
}

function DialogContent({ className, children, "aria-describedby": ariaDescribedBy, ...props }: DialogContentProps) {
  const { open, onClose } = useDialogContext()

  // Determine if aria-describedby was explicitly set to undefined (to suppress MUI's warning)
  const ariaDescribedByProp = ariaDescribedBy === undefined
    ? { "aria-describedby": undefined }
    : ariaDescribedBy
      ? { "aria-describedby": ariaDescribedBy }
      : {}

  return (
    <MuiDialog
      data-slot="dialog-content"
      open={open}
      onClose={(_event: object, _reason: string) => onClose()}
      maxWidth="sm"
      fullWidth
      slotProps={{
        paper: {
          className,
          sx: {
            borderRadius: "0.5rem",
            padding: "1.5rem",
            display: "grid",
            gap: "1rem",
            position: "relative",
            boxShadow: 24,
            maxWidth: "32rem",
            width: "100%",
            m: "1rem",
            backgroundImage: "none",
          },
          ...props,
        },
      }}
      {...ariaDescribedByProp}
    >
      {children}
      <IconButton
        data-slot="dialog-close"
        onClick={onClose}
        aria-label="Close"
        sx={{
          position: "absolute",
          top: "1rem",
          right: "1rem",
          opacity: 0.7,
          transition: "opacity 150ms",
          padding: "0.25rem",
          "&:hover": {
            opacity: 1,
          },
        }}
        size="small"
      >
        <XIcon style={{ width: "1rem", height: "1rem" }} />
      </IconButton>
    </MuiDialog>
  )
}

// ---------------------------------------------------------------------------
// DialogHeader – wrapper div for title + description
// ---------------------------------------------------------------------------
interface DialogHeaderProps extends React.HTMLAttributes<HTMLDivElement> {
  className?: string
}

function DialogHeader({ className, ...props }: DialogHeaderProps) {
  return (
    <Box
      data-slot="dialog-header"
      component="div"
      className={className}
      sx={{
        display: "flex",
        flexDirection: "column",
        gap: "0.5rem",
        textAlign: { xs: "center", sm: "left" },
      }}
      {...props}
    />
  )
}

// ---------------------------------------------------------------------------
// DialogTitle – modal title text
// ---------------------------------------------------------------------------
interface DialogTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
  className?: string
  children?: React.ReactNode
}

function DialogTitle({ className, children, ...props }: DialogTitleProps) {
  return (
    <MuiDialogTitle
      data-slot="dialog-title"
      className={className}
      sx={{
        fontSize: "1.125rem",
        lineHeight: 1,
        fontWeight: 600,
        padding: 0,
      }}
      {...props}
    >
      {children}
    </MuiDialogTitle>
  )
}

// ---------------------------------------------------------------------------
// DialogDescription – modal description text
// ---------------------------------------------------------------------------
interface DialogDescriptionProps extends React.HTMLAttributes<HTMLParagraphElement> {
  className?: string
  children?: React.ReactNode
}

function DialogDescription({ className, children, ...props }: DialogDescriptionProps) {
  return (
    <Typography
      data-slot="dialog-description"
      variant="body2"
      className={className}
      sx={{
        color: "text.secondary",
        fontSize: "0.875rem",
      }}
      component="p"
      {...props}
    >
      {children}
    </Typography>
  )
}

// ---------------------------------------------------------------------------
// DialogFooter – action buttons area
// ---------------------------------------------------------------------------
interface DialogFooterProps extends React.HTMLAttributes<HTMLDivElement> {
  className?: string
}

function DialogFooter({ className, ...props }: DialogFooterProps) {
  return (
    <MuiDialogActions
      data-slot="dialog-footer"
      className={className}
      sx={{
        padding: 0,
        display: "flex",
        flexDirection: { xs: "column-reverse", sm: "row" },
        justifyContent: "flex-end",
        gap: "0.5rem",
      }}
      {...props}
    />
  )
}

// ---------------------------------------------------------------------------
// DialogClose – closes dialog on click
// ---------------------------------------------------------------------------
interface DialogCloseProps {
  asChild?: boolean
  children?: React.ReactNode
  className?: string
  [key: string]: unknown
}

function DialogClose({ asChild, children, ...props }: DialogCloseProps) {
  const { onClose } = useDialogContext()

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      onClick: (...args: unknown[]) => {
        const childProps = (children as React.ReactElement<Record<string, unknown>>).props
        if (typeof childProps.onClick === "function") {
          ;(childProps.onClick as (...a: unknown[]) => void)(...args)
        }
        onClose()
      },
      "data-slot": "dialog-close",
    })
  }

  return (
    <button data-slot="dialog-close" onClick={onClose} {...props}>
      {children}
    </button>
  )
}

// ---------------------------------------------------------------------------
// Exports – identical to the original 10 named exports
// ---------------------------------------------------------------------------
export {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  DialogTrigger,
}
