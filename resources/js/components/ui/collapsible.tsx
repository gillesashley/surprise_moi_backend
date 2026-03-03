import * as React from "react"
import Collapse from "@mui/material/Collapse"

interface CollapsibleContextValue {
  open: boolean
  toggle: () => void
  disabled: boolean
}

const CollapsibleContext = React.createContext<CollapsibleContextValue | null>(null)

function useCollapsibleContext() {
  const ctx = React.useContext(CollapsibleContext)
  if (!ctx) throw new Error("Collapsible compound components must be used within <Collapsible>")
  return ctx
}

interface CollapsibleProps {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  defaultOpen?: boolean
  disabled?: boolean
  children?: React.ReactNode
  className?: string
  [key: string]: unknown
}

function Collapsible({
  open: controlledOpen,
  onOpenChange,
  defaultOpen = false,
  disabled = false,
  children,
  className,
  ...props
}: CollapsibleProps) {
  const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen)
  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : uncontrolledOpen

  const toggle = React.useCallback(() => {
    if (disabled) return
    const next = !open
    if (!isControlled) setUncontrolledOpen(next)
    onOpenChange?.(next)
  }, [open, disabled, isControlled, onOpenChange])

  const value = React.useMemo(() => ({ open, toggle, disabled }), [open, toggle, disabled])

  return (
    <CollapsibleContext.Provider value={value}>
      <div
        data-slot="collapsible"
        data-state={open ? "open" : "closed"}
        className={className}
        {...props}
      >
        {children}
      </div>
    </CollapsibleContext.Provider>
  )
}

function CollapsibleTrigger({
  asChild,
  children,
  ...props
}: {
  asChild?: boolean
  children?: React.ReactNode
  [key: string]: unknown
}) {
  const { toggle, disabled } = useCollapsibleContext()

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      onClick: (...args: unknown[]) => {
        const childProps = (children as React.ReactElement<Record<string, unknown>>).props
        if (typeof childProps.onClick === "function") {
          ;(childProps.onClick as (...a: unknown[]) => void)(...args)
        }
        toggle()
      },
      "data-slot": "collapsible-trigger",
      disabled,
    })
  }

  return (
    <button data-slot="collapsible-trigger" onClick={toggle} disabled={disabled} {...props}>
      {children}
    </button>
  )
}

function CollapsibleContent({
  children,
  className,
  ...props
}: {
  children?: React.ReactNode
  className?: string
  [key: string]: unknown
}) {
  const { open } = useCollapsibleContext()

  return (
    <Collapse data-slot="collapsible-content" in={open} className={className} {...props}>
      {children}
    </Collapse>
  )
}

export { Collapsible, CollapsibleTrigger, CollapsibleContent }
