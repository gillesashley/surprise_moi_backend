import * as React from "react"
import MuiCheckbox from "@mui/material/Checkbox"

interface CheckboxProps {
  checked?: boolean | 'indeterminate'
  onCheckedChange?: (checked: boolean | 'indeterminate') => void
  defaultChecked?: boolean
  disabled?: boolean
  required?: boolean
  name?: string
  value?: string
  id?: string
  className?: string
  tabIndex?: number
  'aria-label'?: string
  'aria-labelledby'?: string
}

function Checkbox({
  checked,
  onCheckedChange,
  className,
  ...props
}: CheckboxProps) {
  const isIndeterminate = checked === 'indeterminate'

  return (
    <MuiCheckbox
      data-slot="checkbox"
      checked={isIndeterminate ? false : checked}
      indeterminate={isIndeterminate}
      onChange={(_, isChecked) => onCheckedChange?.(isChecked)}
      className={className}
      size="small"
      sx={{ p: 0 }}
      {...props}
    />
  )
}

export { Checkbox }
