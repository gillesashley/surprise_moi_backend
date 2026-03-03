import * as React from "react"
import MuiToggleButton from "@mui/material/ToggleButton"

type ToggleVariant = "default" | "outline"
type ToggleSize = "default" | "sm" | "lg"

// Stub for backward compatibility (some files import toggleVariants)
const toggleVariants = (() => "") as unknown as {
  (props?: { variant?: ToggleVariant; size?: ToggleSize; className?: string }): string
}

const sizeMap: Record<ToggleSize, string> = {
  default: "36px",
  sm: "32px",
  lg: "40px",
}

interface ToggleProps {
  pressed?: boolean
  onPressedChange?: (pressed: boolean) => void
  defaultPressed?: boolean
  variant?: ToggleVariant
  size?: ToggleSize
  disabled?: boolean
  className?: string
  children?: React.ReactNode
  value?: string
  [key: string]: unknown
}

function Toggle({
  pressed: controlledPressed,
  onPressedChange,
  defaultPressed = false,
  variant = "default",
  size = "default",
  className,
  children,
  value = "__toggle__",
  ...props
}: ToggleProps) {
  const [uncontrolledPressed, setUncontrolledPressed] = React.useState(defaultPressed)
  const isControlled = controlledPressed !== undefined
  const pressed = isControlled ? controlledPressed : uncontrolledPressed

  const handleChange = React.useCallback(() => {
    const next = !pressed
    if (!isControlled) setUncontrolledPressed(next)
    onPressedChange?.(next)
  }, [pressed, isControlled, onPressedChange])

  return (
    <MuiToggleButton
      data-slot="toggle"
      data-state={pressed ? "on" : "off"}
      value={value}
      selected={pressed}
      onChange={handleChange}
      className={className}
      sx={{
        minWidth: sizeMap[size],
        height: sizeMap[size],
        borderRadius: "0.375rem",
        textTransform: "none",
        fontSize: "0.875rem",
        fontWeight: 500,
        gap: "0.5rem",
        ...(variant === "default" && {
          border: "none",
        }),
        "& svg": {
          width: "1rem",
          height: "1rem",
          flexShrink: 0,
          pointerEvents: "none",
        },
      }}
      {...props}
    >
      {children}
    </MuiToggleButton>
  )
}

export { Toggle, toggleVariants }
