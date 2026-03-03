import * as React from "react"
import FormLabel from "@mui/material/FormLabel"

function Label({
  className,
  color: _color,
  ...props
}: React.ComponentProps<"label">) {
  return (
    <FormLabel
      data-slot="label"
      className={className}
      sx={{
        fontSize: '0.875rem',
        lineHeight: 1,
        fontWeight: 500,
        userSelect: 'none',
        color: 'text.primary',
        '&.Mui-disabled': {
          pointerEvents: 'none',
          opacity: 0.5,
        },
      }}
      {...(props as Omit<React.ComponentProps<typeof FormLabel>, "color">)}
    />
  )
}

export { Label }
