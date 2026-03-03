import * as React from "react"
import MuiPopover from "@mui/material/Popover"
import Box from "@mui/material/Box"

// ---------------------------------------------------------------------------
// Context – shares anchorEl and open/close state between compound components
// ---------------------------------------------------------------------------
interface PopoverContextValue {
  anchorEl: HTMLElement | null
  setAnchorEl: (el: HTMLElement | null) => void
  open: boolean
  onClose: () => void
  onOpen: (el: HTMLElement) => void
}

const PopoverContext = React.createContext<PopoverContextValue | null>(null)

function usePopoverContext(): PopoverContextValue {
  const ctx = React.useContext(PopoverContext)
  if (!ctx) {
    throw new Error("Popover compound components must be rendered inside <Popover>")
  }
  return ctx
}

// ---------------------------------------------------------------------------
// Popover (root) – context provider, manages open state and anchor element
// ---------------------------------------------------------------------------
interface PopoverProps {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  defaultOpen?: boolean
  children?: React.ReactNode
}

function Popover({ open: controlledOpen, onOpenChange, defaultOpen = false, children }: PopoverProps) {
  const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen)
  const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : uncontrolledOpen

  const onClose = React.useCallback(() => {
    if (onOpenChange) {
      onOpenChange(false)
    }
    if (!isControlled) {
      setUncontrolledOpen(false)
    }
    setAnchorEl(null)
  }, [onOpenChange, isControlled])

  const onOpen = React.useCallback((el: HTMLElement) => {
    setAnchorEl(el)
    if (onOpenChange) {
      onOpenChange(true)
    }
    if (!isControlled) {
      setUncontrolledOpen(true)
    }
  }, [onOpenChange, isControlled])

  const value = React.useMemo(
    () => ({ anchorEl, setAnchorEl, open, onClose, onOpen }),
    [anchorEl, open, onClose, onOpen],
  )

  return (
    <PopoverContext.Provider value={value}>
      {children}
    </PopoverContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// PopoverTrigger – toggles the popover on click
// ---------------------------------------------------------------------------
interface PopoverTriggerProps {
  asChild?: boolean
  children?: React.ReactNode
  className?: string
  [key: string]: unknown
}

function PopoverTrigger({ asChild, children, ...props }: PopoverTriggerProps) {
  const { open, onOpen, onClose } = usePopoverContext()

  const handleClick = React.useCallback(
    (event: React.MouseEvent<HTMLElement>) => {
      if (open) {
        onClose()
      } else {
        onOpen(event.currentTarget)
      }
    },
    [open, onOpen, onClose],
  )

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      onClick: (event: React.MouseEvent<HTMLElement>, ...args: unknown[]) => {
        const childProps = (children as React.ReactElement<Record<string, unknown>>).props
        if (typeof childProps.onClick === "function") {
          ;(childProps.onClick as (...a: unknown[]) => void)(event, ...args)
        }
        handleClick(event)
      },
      "data-slot": "popover-trigger",
    })
  }

  return (
    <button data-slot="popover-trigger" onClick={handleClick} {...props}>
      {children}
    </button>
  )
}

// ---------------------------------------------------------------------------
// Alignment mapping – converts Radix align prop to MUI origin props
// ---------------------------------------------------------------------------
type Align = "start" | "center" | "end"

function getOrigins(align: Align): {
  anchorOrigin: { vertical: "bottom"; horizontal: "left" | "center" | "right" }
  transformOrigin: { vertical: "top"; horizontal: "left" | "center" | "right" }
} {
  const horizontalMap: Record<Align, "left" | "center" | "right"> = {
    start: "left",
    center: "center",
    end: "right",
  }

  return {
    anchorOrigin: { vertical: "bottom", horizontal: horizontalMap[align] },
    transformOrigin: { vertical: "top", horizontal: horizontalMap[align] },
  }
}

// ---------------------------------------------------------------------------
// PopoverContent – renders MUI Popover anchored to the trigger
// ---------------------------------------------------------------------------
interface PopoverContentProps extends React.HTMLAttributes<HTMLDivElement> {
  align?: Align
  sideOffset?: number
  className?: string
  children?: React.ReactNode
  ref?: React.Ref<HTMLDivElement>
}

function PopoverContent({
  align = "center",
  sideOffset: _sideOffset,
  className,
  children,
  ref,
  ...props
}: PopoverContentProps) {
  const { anchorEl, open, onClose } = usePopoverContext()
  const { anchorOrigin, transformOrigin } = getOrigins(align)

  return (
    <MuiPopover
      data-slot="popover-content"
      open={open && Boolean(anchorEl)}
      anchorEl={anchorEl}
      onClose={onClose}
      anchorOrigin={anchorOrigin}
      transformOrigin={transformOrigin}
      slotProps={{
        paper: {
          ref,
          className,
          sx: {
            borderRadius: "0.375rem",
            boxShadow: 6,
            backgroundImage: "none",
            mt: 0.5,
          },
          ...props,
        },
      }}
    >
      <Box data-slot="popover-content-inner">
        {children}
      </Box>
    </MuiPopover>
  )
}

// ---------------------------------------------------------------------------
// Exports – identical to the original 3 named exports
// ---------------------------------------------------------------------------
export { Popover, PopoverTrigger, PopoverContent }
