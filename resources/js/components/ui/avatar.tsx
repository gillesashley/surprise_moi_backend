import * as React from "react"
import Box from "@mui/material/Box"

function Avatar({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <Box
      component="span"
      data-slot="avatar"
      className={className}
      sx={{
        position: "relative",
        display: "flex",
        width: 32,
        height: 32,
        flexShrink: 0,
        overflow: "hidden",
        borderRadius: "50%",
      }}
      {...props}
    />
  )
}

function AvatarImage({ className, ...props }: React.ComponentProps<"img">) {
  return (
    <Box
      component="img"
      data-slot="avatar-image"
      className={className}
      sx={{
        aspectRatio: "1/1",
        width: "100%",
        height: "100%",
        objectFit: "cover",
      }}
      {...props}
    />
  )
}

function AvatarFallback({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <Box
      component="span"
      data-slot="avatar-fallback"
      className={className}
      sx={{
        display: "flex",
        width: "100%",
        height: "100%",
        alignItems: "center",
        justifyContent: "center",
        borderRadius: "50%",
        bgcolor: "action.hover",
      }}
      {...props}
    />
  )
}

export { Avatar, AvatarImage, AvatarFallback }
