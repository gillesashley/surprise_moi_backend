import * as React from "react"
import MuiSelect, { type SelectChangeEvent } from "@mui/material/Select"
import MenuItem from "@mui/material/MenuItem"
import ListSubheader from "@mui/material/ListSubheader"
import Divider from "@mui/material/Divider"

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

interface SelectContextValue {
  value: string | undefined
  onValueChange: ((value: string) => void) | undefined
  disabled: boolean
  name: string | undefined
  required: boolean
  /** Ref-based stores written by SelectTrigger / SelectValue before
   *  SelectContent renders, avoiding state-driven re-render loops. */
  triggerClassNameRef: React.MutableRefObject<string | undefined>
  triggerIdRef: React.MutableRefObject<string | undefined>
  placeholderRef: React.MutableRefObject<string | undefined>
}

const SelectContext = React.createContext<SelectContextValue | null>(null)

function useSelectContext(): SelectContextValue {
  const ctx = React.useContext(SelectContext)
  if (!ctx) {
    throw new Error("Select compound components must be used within <Select>")
  }
  return ctx
}

// ---------------------------------------------------------------------------
// Select (root provider)
// ---------------------------------------------------------------------------

interface SelectProps {
  value?: string
  onValueChange?: (value: string) => void
  defaultValue?: string
  disabled?: boolean
  name?: string
  required?: boolean
  open?: boolean
  onOpenChange?: (open: boolean) => void
  children?: React.ReactNode
}

function Select({
  value: controlledValue,
  onValueChange,
  defaultValue,
  disabled = false,
  name,
  required = false,
  children,
}: SelectProps) {
  const [internalValue, setInternalValue] = React.useState(defaultValue ?? "")

  const isControlled = controlledValue !== undefined
  const currentValue = isControlled ? controlledValue : internalValue

  const handleValueChange = React.useCallback(
    (next: string) => {
      if (!isControlled) {
        setInternalValue(next)
      }
      onValueChange?.(next)
    },
    [isControlled, onValueChange],
  )

  const triggerClassNameRef = React.useRef<string | undefined>(undefined)
  const triggerIdRef = React.useRef<string | undefined>(undefined)
  const placeholderRef = React.useRef<string | undefined>(undefined)

  const ctx = React.useMemo<SelectContextValue>(
    () => ({
      value: currentValue,
      onValueChange: handleValueChange,
      disabled,
      name,
      required,
      triggerClassNameRef,
      triggerIdRef,
      placeholderRef,
    }),
    [currentValue, handleValueChange, disabled, name, required],
  )

  return (
    <SelectContext.Provider value={ctx}>
      <div data-slot="select">{children}</div>
    </SelectContext.Provider>
  )
}

// ---------------------------------------------------------------------------
// SelectTrigger
// ---------------------------------------------------------------------------

interface SelectTriggerProps {
  className?: string
  id?: string
  children?: React.ReactNode
}

function SelectTrigger({ className, id, children }: SelectTriggerProps) {
  const ctx = useSelectContext()
  // Store values via ref so SelectContent can read them synchronously
  ctx.triggerClassNameRef.current = className
  ctx.triggerIdRef.current = id
  // Render children so SelectValue can execute and store its placeholder
  return <>{children}</>
}

// ---------------------------------------------------------------------------
// SelectValue
// ---------------------------------------------------------------------------

interface SelectValueProps {
  placeholder?: string
  children?: React.ReactNode
}

function SelectValue({ placeholder }: SelectValueProps) {
  const ctx = useSelectContext()
  ctx.placeholderRef.current = placeholder
  return null
}

// ---------------------------------------------------------------------------
// SelectContent
// ---------------------------------------------------------------------------

interface SelectContentProps {
  className?: string
  position?: string
  children?: React.ReactNode
}

function SelectContent({ className, children }: SelectContentProps) {
  const ctx = useSelectContext()

  const handleChange = React.useCallback(
    (event: SelectChangeEvent<string>) => {
      ctx.onValueChange?.(event.target.value as string)
    },
    [ctx.onValueChange],
  )

  const placeholder = ctx.placeholderRef.current
  const triggerClassName = ctx.triggerClassNameRef.current
  const triggerId = ctx.triggerIdRef.current

  return (
    <MuiSelect
      data-slot="select-content"
      value={ctx.value ?? ""}
      onChange={handleChange}
      displayEmpty
      size="small"
      fullWidth
      disabled={ctx.disabled}
      name={ctx.name}
      required={ctx.required}
      id={triggerId}
      className={[triggerClassName, className].filter(Boolean).join(" ") || undefined}
      renderValue={(selected) => {
        if (!selected || selected === "") {
          return (
            <span style={{ opacity: 0.5 }}>
              {placeholder ?? ""}
            </span>
          )
        }
        // Find the matching MenuItem child to display its label text
        const items = React.Children.toArray(children)
        for (const child of items) {
          if (
            React.isValidElement<SelectItemProps>(child) &&
            child.props.value === selected
          ) {
            return child.props.children
          }
        }
        return selected
      }}
      sx={{
        "& .MuiSelect-select": {
          display: "flex",
          alignItems: "center",
        },
      }}
    >
      {children}
    </MuiSelect>
  )
}

// ---------------------------------------------------------------------------
// SelectItem
// ---------------------------------------------------------------------------

interface SelectItemProps {
  value: string
  disabled?: boolean
  className?: string
  children?: React.ReactNode
}

function SelectItem({ value, disabled, className, children, ...rest }: SelectItemProps & Omit<React.ComponentProps<typeof MenuItem>, keyof SelectItemProps>) {
  return (
    <MenuItem
      data-slot="select-item"
      value={value}
      disabled={disabled}
      className={className}
      {...rest}
    >
      {children}
    </MenuItem>
  )
}

// ---------------------------------------------------------------------------
// SelectGroup
// ---------------------------------------------------------------------------

interface SelectGroupProps {
  className?: string
  children?: React.ReactNode
}

function SelectGroup({ className, children }: SelectGroupProps) {
  return (
    <div data-slot="select-group" className={className}>
      {children}
    </div>
  )
}

// ---------------------------------------------------------------------------
// SelectLabel
// ---------------------------------------------------------------------------

interface SelectLabelProps {
  className?: string
  children?: React.ReactNode
}

function SelectLabel({ className, children }: SelectLabelProps) {
  return (
    <ListSubheader data-slot="select-label" className={className} component="div">
      {children}
    </ListSubheader>
  )
}

// ---------------------------------------------------------------------------
// SelectSeparator
// ---------------------------------------------------------------------------

interface SelectSeparatorProps {
  className?: string
}

function SelectSeparator({ className }: SelectSeparatorProps) {
  return <Divider data-slot="select-separator" className={className} />
}

// ---------------------------------------------------------------------------
// SelectScrollUpButton / SelectScrollDownButton  (MUI handles scrolling)
// ---------------------------------------------------------------------------

function SelectScrollUpButton() {
  return null
}

function SelectScrollDownButton() {
  return null
}

// ---------------------------------------------------------------------------
// Exports
// ---------------------------------------------------------------------------

export {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
}
