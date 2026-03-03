import * as React from "react"
import { OTPInput, OTPInputContext } from "input-otp"
import { Minus } from "lucide-react"
import Box from "@mui/material/Box"

const InputOTP = React.forwardRef<
  React.ElementRef<typeof OTPInput>,
  React.ComponentPropsWithoutRef<typeof OTPInput>
>(({ className, containerClassName, ...props }, ref) => (
  <OTPInput
    ref={ref}
    containerClassName={containerClassName}
    className={className}
    data-slot="input-otp"
    {...props}
  />
))
InputOTP.displayName = "InputOTP"

function InputOTPGroup({ className, children, ...props }: React.ComponentProps<"div">) {
  return (
    <Box
      data-slot="input-otp-group"
      className={className}
      sx={{ display: "flex", alignItems: "center" }}
      {...props}
    >
      {children}
    </Box>
  )
}

function InputOTPSlot({
  index,
  className,
  ...props
}: React.ComponentProps<"div"> & { index: number }) {
  const inputOTPContext = React.useContext(OTPInputContext)
  const { char, hasFakeCaret, isActive } = inputOTPContext.slots[index]

  return (
    <Box
      data-slot="input-otp-slot"
      className={className}
      sx={{
        position: "relative",
        display: "flex",
        height: 36,
        width: 36,
        alignItems: "center",
        justifyContent: "center",
        borderTop: "1px solid",
        borderBottom: "1px solid",
        borderRight: "1px solid",
        borderColor: "divider",
        fontSize: "0.875rem",
        boxShadow: 1,
        transition: "all 150ms",
        "&:first-of-type": {
          borderLeft: "1px solid",
          borderColor: "divider",
          borderTopLeftRadius: "0.375rem",
          borderBottomLeftRadius: "0.375rem",
        },
        "&:last-of-type": {
          borderTopRightRadius: "0.375rem",
          borderBottomRightRadius: "0.375rem",
        },
        ...(isActive && {
          zIndex: 10,
          outline: "2px solid",
          outlineColor: "primary.main",
        }),
      }}
      {...props}
    >
      {char}
      {hasFakeCaret && (
        <Box
          sx={{
            pointerEvents: "none",
            position: "absolute",
            inset: 0,
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <Box
            sx={{
              height: 16,
              width: 1,
              bgcolor: "text.primary",
              animation: "caretBlink 1s step-end infinite",
              "@keyframes caretBlink": {
                "0%, 100%": { opacity: 1 },
                "50%": { opacity: 0 },
              },
            }}
          />
        </Box>
      )}
    </Box>
  )
}

function InputOTPSeparator({ ...props }: React.ComponentProps<"div">) {
  return (
    <div role="separator" data-slot="input-otp-separator" {...props}>
      <Minus />
    </div>
  )
}

export { InputOTP, InputOTPGroup, InputOTPSlot, InputOTPSeparator }
