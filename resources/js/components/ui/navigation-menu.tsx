import * as React from "react"
import Box from "@mui/material/Box"
import Popover from "@mui/material/Popover"
import ButtonBase from "@mui/material/ButtonBase"
import { ChevronDownIcon } from "lucide-react"

// ---------------------------------------------------------------------------
// Context -- wires together the compound component tree
// ---------------------------------------------------------------------------

interface NavigationMenuContextValue {
  activeItem: string | null
  setActiveItem: (item: string | null) => void
  anchorEl: HTMLElement | null
  setAnchorEl: (el: HTMLElement | null) => void
}

const NavigationMenuContext = React.createContext<NavigationMenuContextValue>({
  activeItem: null,
  setActiveItem: () => {},
  anchorEl: null,
  setAnchorEl: () => {},
})

interface NavigationMenuItemContextValue {
  itemId: string
}

const NavigationMenuItemContext = React.createContext<NavigationMenuItemContextValue>({
  itemId: "",
})

// ---------------------------------------------------------------------------
// navigationMenuTriggerStyle -- kept as a callable stub for export compat
// ---------------------------------------------------------------------------

/**
 * Stub kept for export compatibility.
 * Returns an empty string -- styling is now handled by MUI sx props.
 */
function navigationMenuTriggerStyle(): string {
  return ""
}

// ---------------------------------------------------------------------------
// NavigationMenu (root)
// ---------------------------------------------------------------------------

interface NavigationMenuProps extends React.ComponentProps<"nav"> {
  viewport?: boolean
}

function NavigationMenu({
  className,
  children,
  viewport: _viewport = true,
  ...props
}: NavigationMenuProps) {
  const [activeItem, setActiveItem] = React.useState<string | null>(null)
  const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null)

  const value = React.useMemo(
    () => ({ activeItem, setActiveItem, anchorEl, setAnchorEl }),
    [activeItem, anchorEl],
  )

  return (
    <NavigationMenuContext.Provider value={value}>
      <Box
        component="nav"
        data-slot="navigation-menu"
        className={className}
        sx={{
          position: "relative",
          display: "flex",
          maxWidth: "max-content",
          flex: 1,
          alignItems: "center",
          justifyContent: "center",
        }}
        {...props}
      >
        {children}
      </Box>
    </NavigationMenuContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuList
// ---------------------------------------------------------------------------

function NavigationMenuList({
  className,
  children,
  ...props
}: React.ComponentProps<"ul">) {
  return (
    <Box
      component="ul"
      data-slot="navigation-menu-list"
      className={className}
      sx={{
        display: "flex",
        flex: 1,
        listStyle: "none",
        alignItems: "center",
        justifyContent: "center",
        gap: "0.25rem",
        m: 0,
        p: 0,
      }}
      {...props}
    >
      {children}
    </Box>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuItem
// ---------------------------------------------------------------------------

let itemIdCounter = 0

function NavigationMenuItem({
  className,
  children,
  ...props
}: React.ComponentProps<"li">) {
  const itemId = React.useMemo(() => `nav-menu-item-${++itemIdCounter}`, [])

  return (
    <NavigationMenuItemContext.Provider value={{ itemId }}>
      <Box
        component="li"
        data-slot="navigation-menu-item"
        className={className}
        sx={{ position: "relative" }}
        {...props}
      >
        {children}
      </Box>
    </NavigationMenuItemContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuTrigger
// ---------------------------------------------------------------------------

function NavigationMenuTrigger({
  className,
  children,
  ...props
}: React.ComponentProps<typeof ButtonBase>) {
  const { activeItem, setActiveItem, setAnchorEl } =
    React.useContext(NavigationMenuContext)
  const { itemId } = React.useContext(NavigationMenuItemContext)
  const isOpen = activeItem === itemId

  const handleClick = React.useCallback(
    (event: React.MouseEvent<HTMLElement>) => {
      if (isOpen) {
        setActiveItem(null)
        setAnchorEl(null)
      } else {
        setActiveItem(itemId)
        setAnchorEl(event.currentTarget)
      }
    },
    [isOpen, itemId, setActiveItem, setAnchorEl],
  )

  return (
    <ButtonBase
      data-slot="navigation-menu-trigger"
      data-state={isOpen ? "open" : "closed"}
      className={className}
      onClick={handleClick}
      sx={{
        display: "inline-flex",
        height: 36,
        width: "max-content",
        alignItems: "center",
        justifyContent: "center",
        borderRadius: "0.375rem",
        px: 2,
        py: 1,
        fontSize: "0.875rem",
        fontWeight: 500,
        transition: "color 150ms, background-color 150ms",
        "&:hover": {
          bgcolor: "action.hover",
        },
        ...(isOpen && {
          bgcolor: "action.selected",
        }),
      }}
      {...props}
    >
      {children}{" "}
      <ChevronDownIcon
        style={{
          position: "relative",
          top: 1,
          marginLeft: 4,
          width: 12,
          height: 12,
          transition: "transform 300ms",
          transform: isOpen ? "rotate(180deg)" : "rotate(0deg)",
        }}
        aria-hidden="true"
      />
    </ButtonBase>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuContent -- dropdown rendered via MUI Popover
// ---------------------------------------------------------------------------

interface NavigationMenuContentProps extends React.ComponentProps<"div"> {}

function NavigationMenuContent({
  className,
  children,
  ...props
}: NavigationMenuContentProps) {
  const { activeItem, setActiveItem, anchorEl, setAnchorEl } =
    React.useContext(NavigationMenuContext)
  const { itemId } = React.useContext(NavigationMenuItemContext)
  const isOpen = activeItem === itemId

  const handleClose = React.useCallback(() => {
    setActiveItem(null)
    setAnchorEl(null)
  }, [setActiveItem, setAnchorEl])

  return (
    <Popover
      data-slot="navigation-menu-content"
      open={isOpen && Boolean(anchorEl)}
      anchorEl={anchorEl}
      onClose={handleClose}
      anchorOrigin={{ vertical: "bottom", horizontal: "left" }}
      transformOrigin={{ vertical: "top", horizontal: "left" }}
      slotProps={{
        paper: {
          className,
          sx: {
            borderRadius: "0.375rem",
            boxShadow: 6,
            mt: 0.75,
            p: 1,
            pr: 1.25,
            backgroundImage: "none",
          },
        },
      }}
      disableRestoreFocus
    >
      <div {...props}>{children}</div>
    </Popover>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuLink
// ---------------------------------------------------------------------------

interface NavigationMenuLinkProps extends React.ComponentProps<"a"> {
  asChild?: boolean
  active?: boolean
}

function NavigationMenuLink({
  className,
  children,
  asChild,
  active,
  ...props
}: NavigationMenuLinkProps) {
  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(
      children as React.ReactElement<Record<string, unknown>>,
      {
        "data-slot": "navigation-menu-link",
        "data-active": active || undefined,
        className,
        ...props,
      },
    )
  }

  return (
    <Box
      component="a"
      data-slot="navigation-menu-link"
      data-active={active || undefined}
      className={className}
      sx={{
        display: "flex",
        flexDirection: "column",
        gap: "0.25rem",
        borderRadius: "0.25rem",
        p: 1,
        fontSize: "0.875rem",
        transition: "color 150ms, background-color 150ms",
        textDecoration: "none",
        color: "text.primary",
        "&:hover": {
          bgcolor: "action.hover",
        },
        "& svg:not([class*='size-'])": {
          width: "1rem",
          height: "1rem",
        },
      }}
      {...props}
    >
      {children}
    </Box>
  )
}

// ---------------------------------------------------------------------------
// NavigationMenuViewport -- MUI Popover handles positioning; this is a no-op
// ---------------------------------------------------------------------------

function NavigationMenuViewport(_props: React.ComponentProps<"div">) {
  return null
}

// ---------------------------------------------------------------------------
// NavigationMenuIndicator -- MUI handles its own indicators; this is a no-op
// ---------------------------------------------------------------------------

function NavigationMenuIndicator(_props: React.ComponentProps<"div">) {
  return null
}

// ---------------------------------------------------------------------------
// Exports
// ---------------------------------------------------------------------------

export {
  NavigationMenu,
  NavigationMenuList,
  NavigationMenuItem,
  NavigationMenuContent,
  NavigationMenuTrigger,
  NavigationMenuLink,
  NavigationMenuIndicator,
  NavigationMenuViewport,
  navigationMenuTriggerStyle,
}
