import * as React from "react"
import MuiAlert, { type AlertProps as MuiAlertProps } from "@mui/material/Alert"
import MuiAlertTitle from "@mui/material/AlertTitle"
import Typography from "@mui/material/Typography"

type AlertVariant = "default" | "destructive"

interface AlertProps extends Omit<MuiAlertProps, "variant" | "severity"> {
  variant?: AlertVariant
  className?: string
}

function Alert({ variant = "default", className, children, ...props }: AlertProps) {
  const severity = variant === "destructive" ? "error" : "info"

  return (
    <MuiAlert
      data-slot="alert"
      severity={severity}
      variant="outlined"
      className={className}
      {...props}
    >
      {children}
    </MuiAlert>
  )
}

function AlertTitle({ className, children, ...props }: React.ComponentProps<typeof MuiAlertTitle>) {
  return (
    <MuiAlertTitle
      data-slot="alert-title"
      className={className}
      {...props}
    >
      {children}
    </MuiAlertTitle>
  )
}

function AlertDescription({ className, children, ...props }: React.ComponentProps<typeof Typography>) {
  return (
    <Typography
      data-slot="alert-description"
      variant="body2"
      className={className}
      {...props}
    >
      {children}
    </Typography>
  )
}

export { Alert, AlertTitle, AlertDescription }
