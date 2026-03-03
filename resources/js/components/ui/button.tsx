import * as React from "react"
import MuiButton from "@mui/material/Button"
import type { ButtonProps as MuiButtonProps } from "@mui/material/Button"

type ButtonVariant = "default" | "destructive" | "outline" | "secondary" | "ghost" | "link"
type ButtonSize = "default" | "sm" | "lg" | "icon"

interface ButtonProps extends Omit<React.ComponentProps<"button">, "color"> {
  variant?: ButtonVariant | null
  size?: ButtonSize | null
  asChild?: boolean
}

const variantMap: Record<string, { variant: MuiButtonProps["variant"]; color?: MuiButtonProps["color"]; sx?: MuiButtonProps["sx"] }> = {
  default: { variant: "contained" },
  destructive: { variant: "contained", color: "error" },
  outline: { variant: "outlined" },
  secondary: { variant: "contained", color: "secondary" },
  ghost: { variant: "text" },
  link: {
    variant: "text",
    sx: {
      textDecoration: "underline",
      textUnderlineOffset: "4px",
      "&:hover": {
        textDecoration: "underline",
      },
    },
  },
}

const sizeMap: Record<string, MuiButtonProps["size"]> = {
  default: "medium",
  sm: "small",
  lg: "large",
}

const iconSizeSx = {
  minWidth: "36px",
  width: "36px",
  height: "36px",
  padding: 0,
  borderRadius: "6px",
} as const

/**
 * Stub kept for export compatibility.
 * Not used by any external consumer -- only existed for the old cva-based internals.
 */
function buttonVariants(_opts?: {
  variant?: ButtonVariant | null
  size?: ButtonSize | null
  className?: string
}): string {
  return _opts?.className ?? ""
}

function Button({
  className,
  variant,
  size,
  asChild = false,
  children,
  disabled,
  type,
  ...props
}: ButtonProps) {
  const resolvedVariant = variant ?? "default"
  const resolvedSize = size ?? "default"

  const mapped = variantMap[resolvedVariant] ?? variantMap.default
  const muiSize = sizeMap[resolvedSize] ?? "medium"

  const isIcon = resolvedSize === "icon"

  const sxProp: MuiButtonProps["sx"] = [
    ...(mapped.sx ? (Array.isArray(mapped.sx) ? mapped.sx : [mapped.sx]) : []),
    ...(isIcon ? [iconSizeSx] : []),
  ]

  // Build common MUI props
  const muiProps: Record<string, unknown> = {
    "data-slot": "button",
    variant: mapped.variant,
    ...(mapped.color ? { color: mapped.color } : {}),
    size: muiSize,
    disabled,
    className,
    ...(sxProp.length > 0 ? { sx: sxProp } : {}),
  }

  // asChild: render the child element as the root via MUI's `component` prop
  if (asChild && React.isValidElement(children)) {
    const child = children as React.ReactElement<Record<string, unknown>>
    return (
      <MuiButton
        {...muiProps}
        component={child.type as React.ElementType}
        {...(child.props as Record<string, unknown>)}
      >
        {(child.props as Record<string, unknown>).children as React.ReactNode}
      </MuiButton>
    )
  }

  return (
    <MuiButton
      type={type ?? "button"}
      {...muiProps}
      {...props}
    >
      {children}
    </MuiButton>
  )
}

export { Button, buttonVariants }
