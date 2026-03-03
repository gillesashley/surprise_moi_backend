import * as React from "react"
import MuiSkeleton, { type SkeletonProps } from "@mui/material/Skeleton"

interface SkeletonComponentProps extends SkeletonProps {
  className?: string
}

function Skeleton({ className, ...props }: SkeletonComponentProps) {
  return (
    <MuiSkeleton
      data-slot="skeleton"
      variant="rounded"
      animation="pulse"
      className={className}
      {...props}
    />
  )
}

export { Skeleton }
