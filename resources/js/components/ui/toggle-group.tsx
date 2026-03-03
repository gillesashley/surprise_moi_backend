import * as React from "react"
import MuiToggleButtonGroup from "@mui/material/ToggleButtonGroup"
import MuiToggleButton from "@mui/material/ToggleButton"

type ToggleVariant = "default" | "outline"
type ToggleSize = "default" | "sm" | "lg"

interface ToggleGroupContextValue {
  variant: ToggleVariant
  size: ToggleSize
}

const ToggleGroupContext = React.createContext<ToggleGroupContextValue>({
  variant: "default",
  size: "default",
})

const sizeMap: Record<ToggleSize, string> = {
  default: "36px",
  sm: "32px",
  lg: "40px",
}

interface ToggleGroupProps {
  type?: "single" | "multiple"
  value?: string | string[]
  onValueChange?: (value: string | string[]) => void
  defaultValue?: string | string[]
  variant?: ToggleVariant
  size?: ToggleSize
  disabled?: boolean
  className?: string
  children?: React.ReactNode
  [key: string]: unknown
}

function ToggleGroup({
  type = "single",
  value: controlledValue,
  onValueChange,
  defaultValue,
  variant = "default",
  size = "default",
  disabled,
  className,
  children,
  ...props
}: ToggleGroupProps) {
  const [uncontrolledValue, setUncontrolledValue] = React.useState(
    defaultValue ?? (type === "multiple" ? [] : ""),
  )
  const isControlled = controlledValue !== undefined
  const value = isControlled ? controlledValue : uncontrolledValue

  const handleChange = React.useCallback(
    (_event: React.MouseEvent<HTMLElement>, newValue: string | string[] | null) => {
      const val = newValue ?? (type === "multiple" ? [] : "")
      if (!isControlled) setUncontrolledValue(val)
      onValueChange?.(val as string | string[])
    },
    [isControlled, onValueChange, type],
  )

  return (
    <MuiToggleButtonGroup
      data-slot="toggle-group"
      data-variant={variant}
      data-size={size}
      exclusive={type === "single"}
      value={value}
      onChange={handleChange}
      disabled={disabled}
      className={className}
      sx={{
        borderRadius: "0.375rem",
        "& .MuiToggleButtonGroup-grouped": {
          borderRadius: 0,
          "&:first-of-type": {
            borderTopLeftRadius: "0.375rem",
            borderBottomLeftRadius: "0.375rem",
          },
          "&:last-of-type": {
            borderTopRightRadius: "0.375rem",
            borderBottomRightRadius: "0.375rem",
          },
        },
      }}
      {...props}
    >
      <ToggleGroupContext.Provider value={{ variant, size }}>
        {children}
      </ToggleGroupContext.Provider>
    </MuiToggleButtonGroup>
  )
}

interface ToggleGroupItemProps {
  value: string
  disabled?: boolean
  className?: string
  children?: React.ReactNode
  variant?: ToggleVariant
  size?: ToggleSize
  [key: string]: unknown
}

function ToggleGroupItem({
  value,
  className,
  children,
  variant,
  size,
  ...props
}: ToggleGroupItemProps) {
  const context = React.useContext(ToggleGroupContext)
  const effectiveVariant = variant ?? context.variant
  const effectiveSize = size ?? context.size

  return (
    <MuiToggleButton
      data-slot="toggle-group-item"
      data-variant={effectiveVariant}
      data-size={effectiveSize}
      value={value}
      className={className}
      sx={{
        minWidth: sizeMap[effectiveSize],
        height: sizeMap[effectiveSize],
        textTransform: "none",
        fontSize: "0.875rem",
        fontWeight: 500,
        ...(effectiveVariant === "default" && {
          border: "none",
        }),
        "& svg": {
          width: "1rem",
          height: "1rem",
          flexShrink: 0,
        },
      }}
      {...props}
    >
      {children}
    </MuiToggleButton>
  )
}

export { ToggleGroup, ToggleGroupItem }
