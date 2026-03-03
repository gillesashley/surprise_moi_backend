import * as React from "react"
import Divider, { type DividerProps } from "@mui/material/Divider"

interface SeparatorProps extends Omit<DividerProps, "orientation"> {
  orientation?: "horizontal" | "vertical"
  decorative?: boolean
  className?: string
}

function Separator({
  className,
  orientation = "horizontal",
  decorative = true,
  ...props
}: SeparatorProps) {
  return (
    <Divider
      data-slot="separator-root"
      orientation={orientation}
      aria-hidden={decorative}
      className={className}
      sx={{
        flexShrink: 0,
      }}
      {...props}
    />
  )
}

export { Separator }
