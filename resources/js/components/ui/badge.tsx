import * as React from "react"
import Chip, { type ChipProps } from "@mui/material/Chip"

type BadgeVariant = "default" | "secondary" | "destructive" | "outline"

interface BadgeProps extends Omit<React.ComponentProps<"span">, "color"> {
  variant?: BadgeVariant
  asChild?: boolean
}

function getChipProps(variant: BadgeVariant = "default"): Pick<ChipProps, "color" | "variant"> {
  switch (variant) {
    case "secondary":
      return { color: "secondary", variant: "filled" }
    case "destructive":
      return { color: "error", variant: "filled" }
    case "outline":
      return { variant: "outlined" }
    case "default":
    default:
      return { color: "primary", variant: "filled" }
  }
}

/**
 * Stub kept for backward-compatible exports.
 * Previously powered by cva; now unused internally.
 */
function badgeVariants(_props?: { variant?: BadgeVariant }): string {
  return ""
}

function Badge({
  className,
  variant,
  asChild: _asChild = false,
  children,
  ...props
}: BadgeProps) {
  const chipProps = getChipProps(variant)

  return (
    <Chip
      {...chipProps}
      component="span"
      size="small"
      label={children}
      data-slot="badge"
      className={className}
      {...(props as Omit<ChipProps<"span">, "label" | "color" | "variant" | "component" | "size">)}
    />
  )
}

export { Badge, badgeVariants }
