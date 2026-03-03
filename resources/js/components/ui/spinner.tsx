import * as React from "react"
import CircularProgress, { type CircularProgressProps } from "@mui/material/CircularProgress"

interface SpinnerProps extends Omit<CircularProgressProps, "size"> {
  className?: string
}

function Spinner({ className, ...props }: SpinnerProps) {
  return (
    <CircularProgress
      data-slot="spinner"
      role="status"
      aria-label="Loading"
      size={16}
      className={className}
      {...props}
    />
  )
}

export { Spinner }
