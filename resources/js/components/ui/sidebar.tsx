import * as React from "react"
import { PanelLeftIcon } from "lucide-react"

import Box from "@mui/material/Box"
import { useTheme } from "@mui/material/styles"

import { useIsMobile } from "@/hooks/use-mobile"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"

const SIDEBAR_COOKIE_NAME = "sidebar_state"
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
const SIDEBAR_WIDTH = "16rem"
const SIDEBAR_WIDTH_MOBILE = "18rem"
const SIDEBAR_WIDTH_ICON = "3rem"
const SIDEBAR_KEYBOARD_SHORTCUT = "b"

type SidebarContext = {
  state: "expanded" | "collapsed"
  open: boolean
  setOpen: (open: boolean) => void
  openMobile: boolean
  setOpenMobile: (open: boolean) => void
  isMobile: boolean
  toggleSidebar: () => void
}

const SidebarContext = React.createContext<SidebarContext | null>(null)

function useSidebar() {
  const context = React.useContext(SidebarContext)
  if (!context) {
    throw new Error("useSidebar must be used within a SidebarProvider.")
  }

  return context
}

// Context for sidebar-level config (collapsible, variant, side)
// so sub-components can detect collapsed icon state without CSS group selectors
interface SidebarConfigValue {
  collapsible: "offcanvas" | "icon" | "none"
  variant: "sidebar" | "floating" | "inset"
  side: "left" | "right"
}

const SidebarConfigContext = React.createContext<SidebarConfigValue>({
  collapsible: "offcanvas",
  variant: "sidebar",
  side: "left",
})

function useSidebarConfig() {
  return React.useContext(SidebarConfigContext)
}

const srOnlyStyle: React.CSSProperties = {
  position: "absolute",
  width: 1,
  height: 1,
  padding: 0,
  margin: -1,
  overflow: "hidden",
  clip: "rect(0,0,0,0)",
  whiteSpace: "nowrap",
  borderWidth: 0,
}

function SidebarProvider({
  defaultOpen = true,
  open: openProp,
  onOpenChange: setOpenProp,
  className,
  style,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  defaultOpen?: boolean
  open?: boolean
  onOpenChange?: (open: boolean) => void
}) {
  const isMobile = useIsMobile()
  const [openMobile, setOpenMobile] = React.useState(false)

  // This is the internal state of the sidebar.
  // We use openProp and setOpenProp for control from outside the component.
  const [_open, _setOpen] = React.useState(defaultOpen)
  const open = openProp ?? _open
  const setOpen = React.useCallback(
    (value: boolean | ((value: boolean) => boolean)) => {
      const openState = typeof value === "function" ? value(open) : value
      if (setOpenProp) {
        setOpenProp(openState)
      } else {
        _setOpen(openState)
      }

      // This sets the cookie to keep the sidebar state.
      document.cookie = `${SIDEBAR_COOKIE_NAME}=${openState}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
    },
    [setOpenProp, open]
  )

  // Helper to toggle the sidebar.
  const toggleSidebar = React.useCallback(() => {
    return isMobile ? setOpenMobile((open) => !open) : setOpen((open) => !open)
  }, [isMobile, setOpen, setOpenMobile])

  // Adds a keyboard shortcut to toggle the sidebar.
  React.useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (
        event.key === SIDEBAR_KEYBOARD_SHORTCUT &&
        (event.metaKey || event.ctrlKey)
      ) {
        event.preventDefault()
        toggleSidebar()
      }
    }

    window.addEventListener("keydown", handleKeyDown)
    return () => window.removeEventListener("keydown", handleKeyDown)
  }, [toggleSidebar])

  // We add a state so that we can do data-state="expanded" or "collapsed".
  const state = open ? "expanded" : "collapsed"

  const contextValue = React.useMemo<SidebarContext>(
    () => ({
      state,
      open,
      setOpen,
      isMobile,
      openMobile,
      setOpenMobile,
      toggleSidebar,
    }),
    [state, open, setOpen, isMobile, openMobile, setOpenMobile, toggleSidebar]
  )

  return (
    <SidebarContext.Provider value={contextValue}>
      <TooltipProvider delayDuration={0}>
        <Box
          data-slot="sidebar-wrapper"
          className={className}
          sx={{
            display: "flex",
            minHeight: "100svh",
            width: "100%",
            "--sidebar-width": SIDEBAR_WIDTH,
            "--sidebar-width-icon": SIDEBAR_WIDTH_ICON,
            ...style,
          }}
          {...props}
        >
          {children}
        </Box>
      </TooltipProvider>
    </SidebarContext.Provider>
  )
}

function Sidebar({
  side = "left",
  variant = "sidebar",
  collapsible = "offcanvas",
  className,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  side?: "left" | "right"
  variant?: "sidebar" | "floating" | "inset"
  collapsible?: "offcanvas" | "icon" | "none"
}) {
  const { isMobile, state, openMobile, setOpenMobile } = useSidebar()

  const configValue = React.useMemo<SidebarConfigValue>(
    () => ({ collapsible, variant, side }),
    [collapsible, variant, side]
  )

  if (collapsible === "none") {
    return (
      <SidebarConfigContext.Provider value={configValue}>
        <Box
          data-slot="sidebar"
          className={className}
          sx={{
            bgcolor: "background.paper",
            color: "text.primary",
            display: "flex",
            height: "100%",
            width: "var(--sidebar-width)",
            flexDirection: "column",
          }}
          {...props}
        >
          {children}
        </Box>
      </SidebarConfigContext.Provider>
    )
  }

  if (isMobile) {
    return (
      <SidebarConfigContext.Provider value={configValue}>
        <Sheet open={openMobile} onOpenChange={setOpenMobile} {...props}>
          <SheetHeader style={srOnlyStyle}>
            <SheetTitle>Sidebar</SheetTitle>
            <SheetDescription>Displays the mobile sidebar.</SheetDescription>
          </SheetHeader>
          <SheetContent
            data-sidebar="sidebar"
            data-slot="sidebar"
            data-mobile="true"
            className={className}
            style={
              {
                "--sidebar-width": SIDEBAR_WIDTH_MOBILE,
                backgroundColor: "var(--sidebar)",
                color: "var(--sidebar-foreground)",
                width: "var(--sidebar-width)",
                padding: 0,
              } as React.CSSProperties
            }
            side={side}
          >
            <Box sx={{ display: "flex", height: "100%", width: "100%", flexDirection: "column" }}>
              {children}
            </Box>
          </SheetContent>
        </Sheet>
      </SidebarConfigContext.Provider>
    )
  }

  const isCollapsed = state === "collapsed"
  const isOffcanvas = collapsible === "offcanvas"
  const isIcon = collapsible === "icon"
  const isFloatingOrInset = variant === "floating" || variant === "inset"

  // Gap width calculation
  let gapWidth: string = "var(--sidebar-width)"
  if (isCollapsed && isOffcanvas) {
    gapWidth = "0px"
  } else if (isCollapsed && isIcon) {
    gapWidth = isFloatingOrInset
      ? "calc(var(--sidebar-width-icon) + 16px)"
      : "var(--sidebar-width-icon)"
  }

  // Fixed sidebar width calculation
  let fixedWidth: string = "var(--sidebar-width)"
  if (isCollapsed && isIcon) {
    fixedWidth = isFloatingOrInset
      ? "calc(var(--sidebar-width-icon) + 18px)"
      : "var(--sidebar-width-icon)"
  }

  return (
    <SidebarConfigContext.Provider value={configValue}>
      <Box
        data-state={state}
        data-collapsible={isCollapsed ? collapsible : ""}
        data-variant={variant}
        data-side={side}
        data-slot="sidebar"
        sx={{
          color: "text.primary",
          display: { xs: "none", md: "block" },
        }}
      >
        {/* This is what handles the sidebar gap on desktop */}
        <Box
          sx={{
            position: "relative",
            height: "100svh",
            width: gapWidth,
            bgcolor: "transparent",
            transition: "width 200ms linear",
            ...(side === "right" && { transform: "rotate(180deg)" }),
          }}
        />
        <Box
          className={className}
          sx={{
            position: "fixed",
            top: 0,
            bottom: 0,
            zIndex: 10,
            display: { xs: "none", md: "flex" },
            height: "100svh",
            width: fixedWidth,
            transition: "left 200ms linear, right 200ms linear, width 200ms linear",
            ...(side === "left"
              ? {
                  left: isCollapsed && isOffcanvas
                    ? "calc(var(--sidebar-width) * -1)"
                    : 0,
                }
              : {
                  right: isCollapsed && isOffcanvas
                    ? "calc(var(--sidebar-width) * -1)"
                    : 0,
                }),
            ...(isFloatingOrInset
              ? { p: "8px" }
              : {
                  ...(side === "left"
                    ? { borderRight: 1, borderColor: "divider" }
                    : { borderLeft: 1, borderColor: "divider" }),
                }),
          }}
          {...props}
        >
          <Box
            data-sidebar="sidebar"
            sx={{
              bgcolor: "background.paper",
              display: "flex",
              height: "100%",
              width: "100%",
              flexDirection: "column",
              ...(variant === "floating" && {
                borderRadius: 2,
                border: 1,
                borderColor: "divider",
                boxShadow: 1,
              }),
            }}
          >
            {children}
          </Box>
        </Box>
      </Box>
    </SidebarConfigContext.Provider>
  )
}

function SidebarTrigger({
  className,
  onClick,
  ...props
}: React.ComponentProps<typeof Button>) {
  const { toggleSidebar } = useSidebar()

  return (
    <Button
      data-sidebar="trigger"
      data-slot="sidebar-trigger"
      variant="ghost"
      size="icon"
      className={className}
      style={{ width: 28, height: 28 }}
      onClick={(event) => {
        onClick?.(event)
        toggleSidebar()
      }}
      {...props}
    >
      <PanelLeftIcon />
      <span style={srOnlyStyle}>Toggle Sidebar</span>
    </Button>
  )
}

function SidebarRail({ className, ...props }: React.ComponentProps<"button">) {
  const { toggleSidebar } = useSidebar()
  const { collapsible, side } = useSidebarConfig()
  const { state } = useSidebar()

  const isCollapsed = state === "collapsed"
  const isOffcanvas = collapsible === "offcanvas"

  return (
    <Box
      component="button"
      data-sidebar="rail"
      data-slot="sidebar-rail"
      aria-label="Toggle Sidebar"
      tabIndex={-1}
      onClick={toggleSidebar}
      title="Toggle Sidebar"
      className={className}
      sx={{
        position: "absolute",
        top: 0,
        bottom: 0,
        zIndex: 20,
        display: { xs: "none", sm: "flex" },
        width: 16,
        transform: "translateX(-50%)",
        transition: "all 200ms linear",
        cursor: side === "left"
          ? (isCollapsed ? "e-resize" : "w-resize")
          : (isCollapsed ? "w-resize" : "e-resize"),
        ...(side === "left"
          ? { right: -16 }
          : { left: 0 }),
        "&::after": {
          content: '""',
          position: "absolute",
          top: 0,
          bottom: 0,
          left: "50%",
          width: 2,
        },
        "&:hover::after": {
          bgcolor: "divider",
        },
        ...(isOffcanvas && {
          transform: "translateX(0)",
          "&::after": {
            content: '""',
            position: "absolute",
            top: 0,
            bottom: 0,
            left: "100%",
            width: 2,
          },
          "&:hover": {
            bgcolor: "background.paper",
          },
          ...(side === "left" ? { right: -8 } : { left: -8 }),
        }),
        bgcolor: "transparent",
        border: "none",
        p: 0,
      }}
      {...props}
    />
  )
}

function SidebarInset({ className, ...props }: React.ComponentProps<"main">) {
  return (
    <Box
      component="main"
      data-slot="sidebar-inset"
      className={className}
      sx={{
        bgcolor: "background.default",
        position: "relative",
        display: "flex",
        maxWidth: "100%",
        minHeight: "100svh",
        flex: 1,
        flexDirection: "column",
      }}
      {...props}
    />
  )
}

function SidebarInput({
  className,
  ...props
}: React.ComponentProps<typeof Input>) {
  return (
    <Input
      data-slot="sidebar-input"
      data-sidebar="input"
      className={className}
      style={{ height: 32, width: "100%", boxShadow: "none", backgroundColor: "var(--background, inherit)" }}
      {...props}
    />
  )
}

function SidebarHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <Box
      data-slot="sidebar-header"
      data-sidebar="header"
      className={className}
      sx={{
        display: "flex",
        flexDirection: "column",
        gap: 1,
        p: 1,
      }}
      {...props}
    />
  )
}

function SidebarFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <Box
      data-slot="sidebar-footer"
      data-sidebar="footer"
      className={className}
      sx={{
        display: "flex",
        flexDirection: "column",
        gap: 1,
        p: 1,
      }}
      {...props}
    />
  )
}

function SidebarSeparator({
  className,
  ...props
}: React.ComponentProps<typeof Separator>) {
  return (
    <Separator
      data-slot="sidebar-separator"
      data-sidebar="separator"
      className={className}
      style={{ marginLeft: 8, marginRight: 8, width: "auto" }}
      {...props}
    />
  )
}

function SidebarContent({ className, ...props }: React.ComponentProps<"div">) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  return (
    <Box
      data-slot="sidebar-content"
      data-sidebar="content"
      className={className}
      sx={{
        display: "flex",
        minHeight: 0,
        flex: 1,
        flexDirection: "column",
        gap: 1,
        overflow: isIconCollapsed ? "hidden" : "auto",
      }}
      {...props}
    />
  )
}

function SidebarGroup({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <Box
      data-slot="sidebar-group"
      data-sidebar="group"
      className={className}
      sx={{
        position: "relative",
        display: "flex",
        width: "100%",
        minWidth: 0,
        flexDirection: "column",
        p: 1,
      }}
      {...props}
    />
  )
}

function SidebarGroupLabel({
  className,
  asChild = false,
  ...props
}: React.ComponentProps<"div"> & { asChild?: boolean }) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  const dataProps = {
    "data-slot": "sidebar-group-label",
    "data-sidebar": "group-label",
  }

  const sxStyles = {
    display: "flex",
    height: 32,
    flexShrink: 0,
    alignItems: "center",
    borderRadius: 1,
    px: 1,
    fontSize: "0.75rem",
    fontWeight: 500,
    color: "text.secondary",
    outline: "none",
    transition: "margin 200ms linear, opacity 200ms linear",
    "&:focus-visible": {
      boxShadow: "0 0 0 2px",
    },
    "& > svg": {
      width: 16,
      height: 16,
      flexShrink: 0,
    },
    ...(isIconCollapsed && {
      mt: "-32px",
      opacity: 0,
      userSelect: "none",
      pointerEvents: "none",
    }),
  }

  if (asChild && React.isValidElement(props.children)) {
    return React.cloneElement(props.children as React.ReactElement<Record<string, unknown>>, {
      ...dataProps,
      className,
      style: {
        ...(isIconCollapsed && {
          marginTop: -32,
          opacity: 0,
          userSelect: "none",
          pointerEvents: "none",
        }),
      },
    })
  }

  return (
    <Box
      {...dataProps}
      className={className}
      sx={sxStyles}
      {...props}
    />
  )
}

function SidebarGroupAction({
  className,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> & { asChild?: boolean }) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  const dataProps = {
    "data-slot": "sidebar-group-action",
    "data-sidebar": "group-action",
  }

  const sxStyles = {
    color: "text.primary",
    position: "absolute",
    top: 14,
    right: 12,
    display: "flex",
    aspectRatio: "1",
    width: 20,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 1,
    p: 0,
    outline: "none",
    transition: "transform 200ms",
    bgcolor: "transparent",
    border: "none",
    cursor: "pointer",
    "&:hover": {
      bgcolor: "action.hover",
    },
    "&:focus-visible": {
      boxShadow: "0 0 0 2px",
    },
    "& > svg": {
      width: 16,
      height: 16,
      flexShrink: 0,
    },
    // Increases the hit area of the button on mobile
    "&::after": {
      content: '""',
      position: "absolute",
      inset: -8,
      display: { md: "none" },
    },
    ...(isIconCollapsed && { display: "none" }),
  }

  if (asChild && React.isValidElement(props.children)) {
    return React.cloneElement(props.children as React.ReactElement<Record<string, unknown>>, {
      ...dataProps,
      className,
      style: {
        ...(isIconCollapsed && { display: "none" }),
      },
    })
  }

  return (
    <Box
      component="button"
      {...dataProps}
      className={className}
      sx={sxStyles}
      {...props}
    />
  )
}

function SidebarGroupContent({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <Box
      data-slot="sidebar-group-content"
      data-sidebar="group-content"
      className={className}
      sx={{
        width: "100%",
        fontSize: "0.875rem",
      }}
      {...props}
    />
  )
}

function SidebarMenu({ className, ...props }: React.ComponentProps<"ul">) {
  return (
    <Box
      component="ul"
      data-slot="sidebar-menu"
      data-sidebar="menu"
      className={className}
      sx={{
        display: "flex",
        width: "100%",
        minWidth: 0,
        flexDirection: "column",
        gap: 0.5,
        listStyle: "none",
        p: 0,
        m: 0,
      }}
      {...props}
    />
  )
}

function SidebarMenuItem({ className, ...props }: React.ComponentProps<"li">) {
  return (
    <Box
      component="li"
      data-slot="sidebar-menu-item"
      data-sidebar="menu-item"
      className={className}
      sx={{
        position: "relative",
        listStyle: "none",
      }}
      {...props}
    />
  )
}

function SidebarMenuButton({
  asChild = false,
  isActive = false,
  variant = "default",
  size = "default",
  tooltip,
  className,
  children,
  ...props
}: React.ComponentProps<"button"> & {
  asChild?: boolean
  isActive?: boolean
  tooltip?: string | React.ComponentProps<typeof TooltipContent>
  variant?: "default" | "outline" | null
  size?: "default" | "sm" | "lg" | null
}) {
  const { isMobile, state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const theme = useTheme()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  const dataProps = {
    "data-slot": "sidebar-menu-button",
    "data-sidebar": "menu-button",
    "data-size": size,
    "data-active": isActive,
  }

  // Base styles
  const baseSx = {
    display: "flex",
    width: "100%",
    alignItems: "center",
    gap: 1,
    overflow: "hidden",
    borderRadius: 1,
    p: 1,
    textAlign: "left",
    fontSize: "0.875rem",
    outline: "none",
    transition: "width 200ms, height 200ms, padding 200ms",
    bgcolor: "transparent",
    border: "none",
    cursor: "pointer",
    color: "inherit",
    textDecoration: "none",
    "&:hover": {
      bgcolor: "action.hover",
    },
    "&:focus-visible": {
      boxShadow: "0 0 0 2px",
    },
    "&:active": {
      bgcolor: "action.hover",
    },
    "&:disabled, &[aria-disabled=true]": {
      pointerEvents: "none",
      opacity: 0.5,
    },
    "& > svg": {
      width: 16,
      height: 16,
      flexShrink: 0,
    },
    "& > span:last-child": {
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap",
    },
    ...(isActive && {
      bgcolor: "action.selected",
      fontWeight: 500,
    }),
    // Variant styles
    ...(variant === "outline" && {
      bgcolor: "background.paper",
      boxShadow: "0 0 0 1px var(--sidebar-border, rgba(0,0,0,0.12))",
      "&:hover": {
        bgcolor: "action.hover",
        boxShadow: "0 0 0 1px var(--sidebar-accent, rgba(0,0,0,0.12))",
      },
    }),
    // Size styles
    ...(size === "default" && {
      height: 32,
      fontSize: "0.875rem",
    }),
    ...(size === "sm" && {
      height: 28,
      fontSize: "0.75rem",
    }),
    ...(size === "lg" && {
      height: 48,
      fontSize: "0.875rem",
      ...(isIconCollapsed && { p: "0 !important" }),
    }),
    // Icon-collapsed state
    ...(isIconCollapsed && {
      width: "32px !important",
      height: "32px !important",
      p: "8px !important",
    }),
  }

  let button: React.ReactNode

  if (asChild && React.isValidElement(children)) {
    button = React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      ...dataProps,
      className,
      style: {
        display: "flex",
        width: "100%",
        alignItems: "center",
        gap: 8,
        overflow: "hidden",
        whiteSpace: "nowrap" as const,
        textOverflow: "ellipsis",
        borderRadius: 6,
        padding: 8,
        textAlign: "left" as const,
        fontSize: "0.875rem",
        outline: "none",
        transition: "width 200ms, height 200ms, padding 200ms",
        backgroundColor: "transparent",
        border: "none",
        cursor: "pointer",
        color: "inherit",
        textDecoration: "none",
        ...(isActive && { fontWeight: 500, backgroundColor: theme.palette.action.selected }),
        ...(size === "default" && { height: 32, fontSize: "0.875rem" }),
        ...(size === "sm" && { height: 28, fontSize: "0.75rem" }),
        ...(size === "lg" && { height: 48, fontSize: "0.875rem" }),
        ...(isIconCollapsed && {
          width: 32,
          height: 32,
          padding: 8,
        }),
      },
      ...props,
    })
  } else {
    button = (
      <Box
        component="button"
        {...dataProps}
        className={className}
        sx={baseSx}
        {...props}
      >
        {children}
      </Box>
    )
  }

  if (!tooltip) {
    return button
  }

  if (typeof tooltip === "string") {
    tooltip = {
      children: tooltip,
    }
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>{button}</TooltipTrigger>
      <TooltipContent
        side="right"
        align="center"
        hidden={state !== "collapsed" || isMobile}
        {...tooltip}
      />
    </Tooltip>
  )
}

function SidebarMenuAction({
  className,
  asChild = false,
  showOnHover = false,
  ...props
}: React.ComponentProps<"button"> & {
  asChild?: boolean
  showOnHover?: boolean
}) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  const dataProps = {
    "data-slot": "sidebar-menu-action",
    "data-sidebar": "menu-action",
  }

  const sxStyles = {
    color: "text.primary",
    position: "absolute",
    top: 6,
    right: 4,
    display: "flex",
    aspectRatio: "1",
    width: 20,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 1,
    p: 0,
    outline: "none",
    transition: "transform 200ms",
    bgcolor: "transparent",
    border: "none",
    cursor: "pointer",
    "&:hover": {
      bgcolor: "action.hover",
    },
    "&:focus-visible": {
      boxShadow: "0 0 0 2px",
    },
    "& > svg": {
      width: 16,
      height: 16,
      flexShrink: 0,
    },
    // Increases the hit area of the button on mobile
    "&::after": {
      content: '""',
      position: "absolute",
      inset: -8,
      display: { md: "none" },
    },
    ...(isIconCollapsed && { display: "none" }),
    ...(showOnHover && {
      opacity: { md: 0 },
      "&:focus-within, &:hover, &[data-state=open]": {
        opacity: 1,
      },
    }),
  }

  if (asChild && React.isValidElement(props.children)) {
    return React.cloneElement(props.children as React.ReactElement<Record<string, unknown>>, {
      ...dataProps,
      className,
      style: {
        ...(isIconCollapsed && { display: "none" }),
      },
    })
  }

  return (
    <Box
      component="button"
      {...dataProps}
      className={className}
      sx={sxStyles}
      {...props}
    />
  )
}

function SidebarMenuBadge({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  return (
    <Box
      data-slot="sidebar-menu-badge"
      data-sidebar="menu-badge"
      className={className}
      sx={{
        color: "text.primary",
        pointerEvents: "none",
        position: "absolute",
        right: 4,
        display: "flex",
        height: 20,
        minWidth: 20,
        alignItems: "center",
        justifyContent: "center",
        borderRadius: 1,
        px: 0.5,
        fontSize: "0.75rem",
        fontWeight: 500,
        fontVariantNumeric: "tabular-nums",
        userSelect: "none",
        ...(isIconCollapsed && { display: "none" }),
      }}
      {...props}
    />
  )
}

function SidebarMenuSkeleton({
  className,
  showIcon = false,
  ...props
}: React.ComponentProps<"div"> & {
  showIcon?: boolean
}) {
  // wrapping in useState to ensure the width is stable across renders
  // also ensures we have a stable reference to the style object
  const [skeletonStyle] = React.useState(() => (
    {
      "--skeleton-width": `${Math.floor(Math.random() * 40) + 50}%` // Random width between 50 to 90%.
    } as React.CSSProperties
  ))

  return (
    <Box
      data-slot="sidebar-menu-skeleton"
      data-sidebar="menu-skeleton"
      className={className}
      sx={{
        display: "flex",
        height: 32,
        alignItems: "center",
        gap: 1,
        borderRadius: 1,
        px: 1,
      }}
      {...props}
    >
      {showIcon && (
        <Skeleton
          data-sidebar="menu-skeleton-icon"
          style={{ width: 16, height: 16, borderRadius: 4 }}
        />
      )}
      <Skeleton
        data-sidebar="menu-skeleton-text"
        style={{ height: 16, flex: 1, maxWidth: "var(--skeleton-width)", ...skeletonStyle }}
      />
    </Box>
  )
}

function SidebarMenuSub({ className, ...props }: React.ComponentProps<"ul">) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  return (
    <Box
      component="ul"
      data-slot="sidebar-menu-sub"
      data-sidebar="menu-sub"
      className={className}
      sx={{
        mx: 1.75,
        display: "flex",
        minWidth: 0,
        transform: "translateX(1px)",
        flexDirection: "column",
        gap: 0.5,
        borderLeft: 1,
        borderColor: "divider",
        px: 1.25,
        py: 0.25,
        listStyle: "none",
        ...(isIconCollapsed && { display: "none" }),
      }}
      {...props}
    />
  )
}

function SidebarMenuSubItem({
  className,
  ...props
}: React.ComponentProps<"li">) {
  return (
    <Box
      component="li"
      data-slot="sidebar-menu-sub-item"
      data-sidebar="menu-sub-item"
      className={className}
      sx={{
        position: "relative",
        listStyle: "none",
      }}
      {...props}
    />
  )
}

function SidebarMenuSubButton({
  asChild = false,
  size = "md",
  isActive = false,
  className,
  children,
  ...props
}: React.ComponentProps<"a"> & {
  asChild?: boolean
  size?: "sm" | "md"
  isActive?: boolean
}) {
  const { state } = useSidebar()
  const { collapsible } = useSidebarConfig()
  const theme = useTheme()
  const isIconCollapsed = state === "collapsed" && collapsible === "icon"

  const dataProps = {
    "data-slot": "sidebar-menu-sub-button",
    "data-sidebar": "menu-sub-button",
    "data-size": size,
    "data-active": isActive,
  }

  const sxStyles = {
    color: "text.primary",
    display: "flex",
    height: 28,
    minWidth: 0,
    transform: "translateX(-1px)",
    alignItems: "center",
    gap: 1,
    overflow: "hidden",
    borderRadius: 1,
    px: 1,
    outline: "none",
    textDecoration: "none",
    "&:hover": {
      bgcolor: "action.hover",
    },
    "&:focus-visible": {
      boxShadow: "0 0 0 2px",
    },
    "&:active": {
      bgcolor: "action.hover",
    },
    "&:disabled, &[aria-disabled=true]": {
      pointerEvents: "none",
      opacity: 0.5,
    },
    "& > svg": {
      width: 16,
      height: 16,
      flexShrink: 0,
    },
    "& > span:last-child": {
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap",
    },
    ...(isActive && {
      bgcolor: "action.selected",
    }),
    ...(size === "sm" && { fontSize: "0.75rem" }),
    ...(size === "md" && { fontSize: "0.875rem" }),
    ...(isIconCollapsed && { display: "none" }),
  }

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      ...dataProps,
      className,
      style: {
        display: "flex",
        height: 28,
        minWidth: 0,
        transform: "translateX(-1px)",
        alignItems: "center",
        gap: 8,
        overflow: "hidden",
        whiteSpace: "nowrap" as const,
        textOverflow: "ellipsis",
        borderRadius: 6,
        paddingLeft: 8,
        paddingRight: 8,
        outline: "none",
        textDecoration: "none",
        color: "inherit",
        ...(size === "sm" && { fontSize: "0.75rem" }),
        ...(size === "md" && { fontSize: "0.875rem" }),
        ...(isActive && { fontWeight: 500, backgroundColor: theme.palette.action.selected }),
        ...(isIconCollapsed && { display: "none" }),
      },
      ...props,
    })
  }

  return (
    <Box
      component="a"
      {...dataProps}
      className={className}
      sx={sxStyles}
      {...props}
    >
      {children}
    </Box>
  )
}

export {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupAction,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInput,
  SidebarInset,
  SidebarMenu,
  SidebarMenuAction,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarRail,
  SidebarSeparator,
  SidebarTrigger,
  useSidebar,
}
