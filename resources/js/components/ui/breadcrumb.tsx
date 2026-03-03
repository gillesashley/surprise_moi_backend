import * as React from "react"
import Box from "@mui/material/Box"
import { ChevronRight, MoreHorizontal } from "lucide-react"

function Breadcrumb({ ...props }: React.ComponentProps<"nav">) {
  return <nav aria-label="breadcrumb" data-slot="breadcrumb" {...props} />
}

function BreadcrumbList({ className, ...props }: React.ComponentProps<"ol">) {
  return (
    <Box
      component="ol"
      data-slot="breadcrumb-list"
      className={className}
      sx={{
        color: "text.secondary",
        display: "flex",
        flexWrap: "wrap",
        alignItems: "center",
        gap: "0.375rem",
        fontSize: "0.875rem",
        lineHeight: "1.25rem",
        overflowWrap: "break-word",
        listStyle: "none",
        margin: 0,
        padding: 0,
        "@media (min-width: 640px)": {
          gap: "0.625rem",
        },
      }}
      {...props}
    />
  )
}

function BreadcrumbItem({ className, ...props }: React.ComponentProps<"li">) {
  return (
    <Box
      component="li"
      data-slot="breadcrumb-item"
      className={className}
      sx={{
        display: "inline-flex",
        alignItems: "center",
        gap: "0.375rem",
      }}
      {...props}
    />
  )
}

function BreadcrumbLink({
  asChild,
  className,
  children,
  ...props
}: React.ComponentProps<"a"> & { asChild?: boolean }) {
  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
      "data-slot": "breadcrumb-link",
      className,
      ...props,
    })
  }

  return (
    <Box
      component="a"
      data-slot="breadcrumb-link"
      className={className}
      sx={{
        transition: "color 150ms",
        "&:hover": {
          color: "text.primary",
        },
      }}
      {...props}
    >
      {children}
    </Box>
  )
}

function BreadcrumbPage({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <Box
      component="span"
      data-slot="breadcrumb-page"
      role="link"
      aria-disabled="true"
      aria-current="page"
      className={className}
      sx={{
        color: "text.primary",
        fontWeight: 400,
      }}
      {...props}
    />
  )
}

function BreadcrumbSeparator({
  children,
  className,
  ...props
}: React.ComponentProps<"li">) {
  return (
    <Box
      component="li"
      data-slot="breadcrumb-separator"
      role="presentation"
      aria-hidden="true"
      className={className}
      sx={{
        "& > svg": {
          width: "0.875rem",
          height: "0.875rem",
        },
      }}
      {...props}
    >
      {children ?? <ChevronRight />}
    </Box>
  )
}

function BreadcrumbEllipsis({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <Box
      component="span"
      data-slot="breadcrumb-ellipsis"
      role="presentation"
      aria-hidden="true"
      className={className}
      sx={{
        display: "flex",
        width: "2.25rem",
        height: "2.25rem",
        alignItems: "center",
        justifyContent: "center",
      }}
      {...props}
    >
      <MoreHorizontal style={{ width: "1rem", height: "1rem" }} />
      <Box component="span" sx={{ position: "absolute", width: 1, height: 1, overflow: "hidden", clip: "rect(0,0,0,0)", whiteSpace: "nowrap", border: 0 }}>
        More
      </Box>
    </Box>
  )
}

export {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbPage,
  BreadcrumbSeparator,
  BreadcrumbEllipsis,
}
