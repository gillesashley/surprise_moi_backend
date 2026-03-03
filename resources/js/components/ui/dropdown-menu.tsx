import * as React from "react"
import Menu from "@mui/material/Menu"
import MuiMenuItem from "@mui/material/MenuItem"
import ListItemIcon from "@mui/material/ListItemIcon"
import Divider from "@mui/material/Divider"
import Typography from "@mui/material/Typography"
import MuiCheckbox from "@mui/material/Checkbox"
import { CheckIcon } from "lucide-react"

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

interface DropdownMenuContextValue {
  anchorEl: HTMLElement | null
  open: boolean
  onOpen: (el: HTMLElement) => void
  onClose: () => void
}

const DropdownMenuContext = React.createContext<DropdownMenuContextValue>({
  anchorEl: null,
  open: false,
  onOpen: () => {},
  onClose: () => {},
})

function useDropdownMenu() {
  return React.useContext(DropdownMenuContext)
}

// ---------------------------------------------------------------------------
// Sub-menu context (for nested menus)
// ---------------------------------------------------------------------------

interface SubMenuContextValue {
  anchorEl: HTMLElement | null
  open: boolean
  onOpen: (el: HTMLElement) => void
  onClose: () => void
}

const SubMenuContext = React.createContext<SubMenuContextValue | null>(null)

// ---------------------------------------------------------------------------
// DropdownMenu
// ---------------------------------------------------------------------------

interface DropdownMenuProps {
  children?: React.ReactNode
  open?: boolean
  defaultOpen?: boolean
  onOpenChange?: (open: boolean) => void
  modal?: boolean
  dir?: "ltr" | "rtl"
}

function DropdownMenu({
  children,
  open: controlledOpen,
  defaultOpen = false,
  onOpenChange,
  ...props
}: DropdownMenuProps) {
  const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null)
  const [internalOpen, setInternalOpen] = React.useState(defaultOpen)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : internalOpen

  const handleOpen = React.useCallback(
    (el: HTMLElement) => {
      setAnchorEl(el)
      if (!isControlled) {
        setInternalOpen(true)
      }
      onOpenChange?.(true)
    },
    [isControlled, onOpenChange],
  )

  const handleClose = React.useCallback(() => {
    setAnchorEl(null)
    if (!isControlled) {
      setInternalOpen(false)
    }
    onOpenChange?.(false)
  }, [isControlled, onOpenChange])

  const value = React.useMemo(
    () => ({ anchorEl, open, onOpen: handleOpen, onClose: handleClose }),
    [anchorEl, open, handleOpen, handleClose],
  )

  return (
    <DropdownMenuContext.Provider value={value}>
      <span data-slot="dropdown-menu" {...props}>
        {children}
      </span>
    </DropdownMenuContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuPortal (no-op wrapper — MUI Menu portals by default)
// ---------------------------------------------------------------------------

interface DropdownMenuPortalProps {
  children?: React.ReactNode
  container?: HTMLElement | null
  forceMount?: true
}

function DropdownMenuPortal({
  children,
  ...props
}: DropdownMenuPortalProps) {
  return <>{children}</>
}

// ---------------------------------------------------------------------------
// DropdownMenuTrigger
// ---------------------------------------------------------------------------

interface DropdownMenuTriggerProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  asChild?: boolean
}

function DropdownMenuTrigger({
  asChild = false,
  children,
  onClick,
  ...props
}: DropdownMenuTriggerProps) {
  const { onOpen, open } = useDropdownMenu()
  const triggerRef = React.useRef<HTMLElement>(null)

  const handleClick = React.useCallback(
    (e: React.MouseEvent<HTMLElement>) => {
      onOpen(e.currentTarget)
      ;(onClick as React.MouseEventHandler<HTMLElement> | undefined)?.(e as React.MouseEvent<HTMLButtonElement>)
    },
    [onOpen, onClick],
  )

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      "data-slot": "dropdown-menu-trigger",
      "data-state": open ? "open" : "closed",
      ref: triggerRef,
      onClick: (e: React.MouseEvent<HTMLElement>) => {
        handleClick(e)
        const childOnClick = (children as React.ReactElement<Record<string, unknown>>).props
          .onClick as ((e: React.MouseEvent<HTMLElement>) => void) | undefined
        childOnClick?.(e)
      },
    })
  }

  return (
    <button
      data-slot="dropdown-menu-trigger"
      data-state={open ? "open" : "closed"}
      ref={triggerRef as React.Ref<HTMLButtonElement>}
      onClick={handleClick}
      {...props}
    >
      {children}
    </button>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuContent
// ---------------------------------------------------------------------------

interface DropdownMenuContentProps extends React.HTMLAttributes<HTMLDivElement> {
  sideOffset?: number
  side?: "top" | "right" | "bottom" | "left"
  align?: "start" | "center" | "end"
  alignOffset?: number
  forceMount?: true
  loop?: boolean
  onCloseAutoFocus?: (event: Event) => void
  onEscapeKeyDown?: (event: KeyboardEvent) => void
  onPointerDownOutside?: (event: PointerEvent) => void
  onFocusOutside?: (event: FocusEvent) => void
  onInteractOutside?: (event: Event) => void
}

function DropdownMenuContent({
  className,
  children,
  sideOffset,
  side = "bottom",
  align = "start",
  alignOffset,
  forceMount,
  loop,
  onCloseAutoFocus,
  onEscapeKeyDown,
  onPointerDownOutside,
  onFocusOutside,
  onInteractOutside,
  ...props
}: DropdownMenuContentProps) {
  const { anchorEl, open, onClose } = useDropdownMenu()

  type HOrigin = "left" | "center" | "right"
  type VOrigin = "top" | "center" | "bottom"

  // Map side + align to MUI anchorOrigin / transformOrigin
  const anchorOrigin = React.useMemo((): { vertical: VOrigin; horizontal: HOrigin } => {
    const vertical: VOrigin = side === "top" ? "top" : side === "bottom" ? "bottom" : "center"
    const horizontal: HOrigin = align === "start" ? "left" : align === "end" ? "right" : "center"

    if (side === "left") {
      return { vertical: "center", horizontal: "left" }
    }
    if (side === "right") {
      return { vertical: "center", horizontal: "right" }
    }

    return { vertical, horizontal }
  }, [side, align])

  const transformOrigin = React.useMemo((): { vertical: VOrigin; horizontal: HOrigin } => {
    if (side === "bottom") {
      return { vertical: "top", horizontal: anchorOrigin.horizontal }
    }
    if (side === "top") {
      return { vertical: "bottom", horizontal: anchorOrigin.horizontal }
    }
    if (side === "left") {
      return { vertical: "center", horizontal: "right" }
    }
    if (side === "right") {
      return { vertical: "center", horizontal: "left" }
    }
    return { vertical: "top", horizontal: anchorOrigin.horizontal }
  }, [side, anchorOrigin.horizontal])

  return (
    <Menu
      data-slot="dropdown-menu-content"
      anchorEl={anchorEl}
      open={open && Boolean(anchorEl)}
      onClose={onClose}
      anchorOrigin={anchorOrigin}
      transformOrigin={transformOrigin}
      className={className}
      slotProps={{
        paper: {
          "data-slot": "dropdown-menu-content",
          className,
          ...props,
        } as Record<string, unknown>,
      }}
    >
      {children}
    </Menu>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuGroup
// ---------------------------------------------------------------------------

interface DropdownMenuGroupProps extends React.HTMLAttributes<HTMLDivElement> {}

function DropdownMenuGroup({ children, ...props }: DropdownMenuGroupProps) {
  return (
    <div data-slot="dropdown-menu-group" {...props}>
      {children}
    </div>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuItem
// ---------------------------------------------------------------------------

interface DropdownMenuItemProps
  extends Omit<React.HTMLAttributes<HTMLLIElement>, "onSelect"> {
  asChild?: boolean
  disabled?: boolean
  onSelect?: (event: Event) => void
  inset?: boolean
  variant?: "default" | "destructive"
  textValue?: string
}

function DropdownMenuItem({
  asChild = false,
  children,
  className,
  disabled = false,
  inset,
  variant = "default",
  onClick,
  onSelect,
  textValue,
  ...props
}: DropdownMenuItemProps) {
  const { onClose } = useDropdownMenu()

  const handleClick = React.useCallback(
    (e: React.MouseEvent<HTMLLIElement>) => {
      onClick?.(e)
      if (onSelect) {
        const syntheticEvent = new Event("select", { bubbles: true })
        onSelect(syntheticEvent)
      }
      onClose()
    },
    [onClick, onSelect, onClose],
  )

  const sxStyles = React.useMemo(() => {
    const styles: Record<string, unknown> = {}
    if (variant === "destructive") {
      styles.color = "error.main"
    }
    if (inset) {
      styles.pl = 4
    }
    return styles
  }, [variant, inset])

  if (asChild && React.isValidElement(children)) {
    const child = children as React.ReactElement<Record<string, unknown>>
    return (
      <MuiMenuItem
        data-slot="dropdown-menu-item"
        data-inset={inset || undefined}
        data-variant={variant}
        component={child.type as React.ElementType}
        {...(child.props as Record<string, unknown>)}
        className={className || (child.props.className as string | undefined)}
        disabled={disabled}
        onClick={(e: React.MouseEvent<HTMLLIElement>) => {
          const childOnClick = child.props.onClick as
            | ((e: React.MouseEvent<HTMLLIElement>) => void)
            | undefined
          childOnClick?.(e)
          onClick?.(e)
          onClose()
        }}
        sx={sxStyles}
        {...props}
      />
    )
  }

  return (
    <MuiMenuItem
      data-slot="dropdown-menu-item"
      data-inset={inset || undefined}
      data-variant={variant}
      className={className}
      disabled={disabled}
      onClick={handleClick}
      sx={sxStyles}
      {...props}
    >
      {children}
    </MuiMenuItem>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuCheckboxItem
// ---------------------------------------------------------------------------

interface DropdownMenuCheckboxItemProps
  extends Omit<React.HTMLAttributes<HTMLLIElement>, "onSelect"> {
  checked?: boolean | "indeterminate"
  onCheckedChange?: (checked: boolean) => void
  disabled?: boolean
  onSelect?: (event: Event) => void
  textValue?: string
}

function DropdownMenuCheckboxItem({
  className,
  children,
  checked = false,
  onCheckedChange,
  disabled = false,
  onClick,
  onSelect,
  ...props
}: DropdownMenuCheckboxItemProps) {
  const { onClose } = useDropdownMenu()

  const handleClick = React.useCallback(
    (e: React.MouseEvent<HTMLLIElement>) => {
      onClick?.(e)
      const newChecked = checked === "indeterminate" ? true : !checked
      onCheckedChange?.(newChecked)
      if (onSelect) {
        const syntheticEvent = new Event("select", { bubbles: true })
        onSelect(syntheticEvent)
      }
    },
    [onClick, checked, onCheckedChange, onSelect],
  )

  return (
    <MuiMenuItem
      data-slot="dropdown-menu-checkbox-item"
      className={className}
      disabled={disabled}
      onClick={handleClick}
      {...props}
    >
      <ListItemIcon sx={{ minWidth: 28 }}>
        {checked === true && <CheckIcon size={16} />}
        {checked === "indeterminate" && (
          <MuiCheckbox
            checked
            indeterminate
            size="small"
            sx={{ p: 0 }}
            tabIndex={-1}
            disableRipple
          />
        )}
      </ListItemIcon>
      {children}
    </MuiMenuItem>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuRadioGroup
// ---------------------------------------------------------------------------

interface RadioGroupContextValue {
  value?: string
  onValueChange?: (value: string) => void
}

const RadioGroupContext = React.createContext<RadioGroupContextValue>({})

interface DropdownMenuRadioGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  value?: string
  onValueChange?: (value: string) => void
}

function DropdownMenuRadioGroup({
  children,
  value,
  onValueChange,
  ...props
}: DropdownMenuRadioGroupProps) {
  const ctx = React.useMemo(() => ({ value, onValueChange }), [value, onValueChange])

  return (
    <RadioGroupContext.Provider value={ctx}>
      <div data-slot="dropdown-menu-radio-group" {...props}>
        {children}
      </div>
    </RadioGroupContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuRadioItem
// ---------------------------------------------------------------------------

interface DropdownMenuRadioItemProps
  extends Omit<React.HTMLAttributes<HTMLLIElement>, "onSelect"> {
  value: string
  disabled?: boolean
  onSelect?: (event: Event) => void
  textValue?: string
}

function DropdownMenuRadioItem({
  className,
  children,
  value,
  disabled = false,
  onClick,
  onSelect,
  ...props
}: DropdownMenuRadioItemProps) {
  const { onClose } = useDropdownMenu()
  const radioCtx = React.useContext(RadioGroupContext)
  const isSelected = radioCtx.value === value

  const handleClick = React.useCallback(
    (e: React.MouseEvent<HTMLLIElement>) => {
      onClick?.(e)
      radioCtx.onValueChange?.(value)
      if (onSelect) {
        const syntheticEvent = new Event("select", { bubbles: true })
        onSelect(syntheticEvent)
      }
      onClose()
    },
    [onClick, radioCtx, value, onSelect, onClose],
  )

  return (
    <MuiMenuItem
      data-slot="dropdown-menu-radio-item"
      className={className}
      disabled={disabled}
      selected={isSelected}
      onClick={handleClick}
      {...props}
    >
      <ListItemIcon sx={{ minWidth: 28 }}>
        {isSelected && (
          <span
            style={{
              display: "inline-block",
              width: 8,
              height: 8,
              borderRadius: "50%",
              backgroundColor: "currentColor",
            }}
          />
        )}
      </ListItemIcon>
      {children}
    </MuiMenuItem>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuLabel
// ---------------------------------------------------------------------------

interface DropdownMenuLabelProps extends React.HTMLAttributes<HTMLLIElement> {
  inset?: boolean
}

function DropdownMenuLabel({
  className,
  inset,
  children,
  ...props
}: DropdownMenuLabelProps) {
  return (
    <MuiMenuItem
      data-slot="dropdown-menu-label"
      data-inset={inset || undefined}
      className={className}
      disabled
      sx={{
        opacity: "1 !important",
        fontWeight: 600,
        fontSize: "0.875rem",
        "&.Mui-disabled": {
          opacity: 1,
        },
        ...(inset ? { pl: 4 } : {}),
      }}
      {...props}
    >
      {children}
    </MuiMenuItem>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuSeparator
// ---------------------------------------------------------------------------

interface DropdownMenuSeparatorProps extends React.HTMLAttributes<HTMLHRElement> {}

function DropdownMenuSeparator({
  className,
  ...props
}: DropdownMenuSeparatorProps) {
  return (
    <Divider
      data-slot="dropdown-menu-separator"
      className={className}
      {...props}
    />
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuShortcut
// ---------------------------------------------------------------------------

function DropdownMenuShortcut({
  className,
  ...props
}: React.ComponentProps<"span">) {
  return (
    <Typography
      component="span"
      variant="caption"
      data-slot="dropdown-menu-shortcut"
      className={className}
      sx={{
        ml: "auto",
        fontSize: "0.75rem",
        letterSpacing: "0.1em",
        color: "text.secondary",
      }}
      {...props}
    />
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuSub
// ---------------------------------------------------------------------------

interface DropdownMenuSubProps {
  children?: React.ReactNode
  open?: boolean
  defaultOpen?: boolean
  onOpenChange?: (open: boolean) => void
}

function DropdownMenuSub({
  children,
  open: controlledOpen,
  defaultOpen = false,
  onOpenChange,
}: DropdownMenuSubProps) {
  const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null)
  const [internalOpen, setInternalOpen] = React.useState(defaultOpen)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : internalOpen

  const handleOpen = React.useCallback(
    (el: HTMLElement) => {
      setAnchorEl(el)
      if (!isControlled) {
        setInternalOpen(true)
      }
      onOpenChange?.(true)
    },
    [isControlled, onOpenChange],
  )

  const handleClose = React.useCallback(() => {
    setAnchorEl(null)
    if (!isControlled) {
      setInternalOpen(false)
    }
    onOpenChange?.(false)
  }, [isControlled, onOpenChange])

  const value = React.useMemo(
    () => ({ anchorEl, open, onOpen: handleOpen, onClose: handleClose }),
    [anchorEl, open, handleOpen, handleClose],
  )

  return (
    <SubMenuContext.Provider value={value}>
      <span data-slot="dropdown-menu-sub">{children}</span>
    </SubMenuContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuSubTrigger
// ---------------------------------------------------------------------------

interface DropdownMenuSubTriggerProps
  extends React.HTMLAttributes<HTMLLIElement> {
  inset?: boolean
  disabled?: boolean
}

function DropdownMenuSubTrigger({
  className,
  inset,
  children,
  disabled = false,
  onClick,
  ...props
}: DropdownMenuSubTriggerProps) {
  const subCtx = React.useContext(SubMenuContext)

  const handleClick = React.useCallback(
    (e: React.MouseEvent<HTMLLIElement>) => {
      onClick?.(e)
      subCtx?.onOpen(e.currentTarget)
    },
    [onClick, subCtx],
  )

  return (
    <MuiMenuItem
      data-slot="dropdown-menu-sub-trigger"
      data-inset={inset || undefined}
      className={className}
      disabled={disabled}
      onClick={handleClick}
      sx={inset ? { pl: 4 } : undefined}
      {...props}
    >
      {children}
      <Typography
        component="span"
        sx={{ ml: "auto", fontSize: "1rem" }}
        aria-hidden
      >
        &#x203A;
      </Typography>
    </MuiMenuItem>
  )
}

// ---------------------------------------------------------------------------
// DropdownMenuSubContent
// ---------------------------------------------------------------------------

interface DropdownMenuSubContentProps
  extends React.HTMLAttributes<HTMLDivElement> {
  forceMount?: true
  sideOffset?: number
  alignOffset?: number
  loop?: boolean
}

function DropdownMenuSubContent({
  className,
  children,
  forceMount,
  sideOffset,
  alignOffset,
  loop,
  ...props
}: DropdownMenuSubContentProps) {
  const subCtx = React.useContext(SubMenuContext)
  const { onClose: onParentClose } = useDropdownMenu()

  if (!subCtx) {
    return null
  }

  return (
    <Menu
      data-slot="dropdown-menu-sub-content"
      anchorEl={subCtx.anchorEl}
      open={subCtx.open && Boolean(subCtx.anchorEl)}
      onClose={() => {
        subCtx.onClose()
      }}
      anchorOrigin={{ vertical: "top", horizontal: "right" }}
      transformOrigin={{ vertical: "top", horizontal: "left" }}
      className={className}
      slotProps={{
        paper: {
          "data-slot": "dropdown-menu-sub-content",
          className,
          ...props,
        } as Record<string, unknown>,
      }}
    >
      {children}
    </Menu>
  )
}

// ---------------------------------------------------------------------------
// Exports
// ---------------------------------------------------------------------------

export {
  DropdownMenu,
  DropdownMenuPortal,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuItem,
  DropdownMenuCheckboxItem,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubTrigger,
  DropdownMenuSubContent,
}
