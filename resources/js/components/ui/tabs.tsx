import * as React from "react"
import { Tabs as MuiTabs, Tab as MuiTab, Box } from "@mui/material"

interface TabsContextValue {
  value: string
  onChange: (value: string) => void
}

const TabsContext = React.createContext<TabsContextValue>({
  value: "",
  onChange: () => {},
})

interface TabsProps {
  value?: string
  onValueChange?: (value: string) => void
  defaultValue?: string
  className?: string
  children?: React.ReactNode
}

function Tabs({
  value,
  onValueChange,
  defaultValue,
  className,
  children,
}: TabsProps) {
  const [internal, setInternal] = React.useState(defaultValue ?? "")

  const contextValue = React.useMemo<TabsContextValue>(
    () => ({
      value: value ?? internal,
      onChange: onValueChange ?? setInternal,
    }),
    [value, internal, onValueChange]
  )

  return (
    <TabsContext.Provider value={contextValue}>
      <Box
        data-slot="tabs"
        className={className}
        sx={{ display: "flex", flexDirection: "column", gap: 1 }}
      >
        {children}
      </Box>
    </TabsContext.Provider>
  )
}

interface TabsListProps {
  className?: string
  children?: React.ReactNode
}

function TabsList({ className, children }: TabsListProps) {
  const ctx = React.useContext(TabsContext)

  return (
    <MuiTabs
      data-slot="tabs-list"
      value={ctx.value}
      onChange={(_, newValue: string) => ctx.onChange(newValue)}
      className={className}
      variant="fullWidth"
      TabIndicatorProps={{
        sx: {
          backgroundColor: "primary.main",
          height: "100%",
          borderRadius: "0.5rem",
          zIndex: 0,
        },
      }}
      sx={{
        minHeight: "2.25rem",
        backgroundColor: "action.hover",
        borderRadius: "0.5rem",
        padding: "3px",
        "& .MuiTabs-flexContainer": {
          gap: 0,
        },
        "& .MuiTab-root": {
          zIndex: 1,
          minHeight: "calc(2.25rem - 6px)",
          textTransform: "none",
          fontSize: "0.875rem",
          fontWeight: 500,
          color: "text.secondary",
          borderRadius: "0.375rem",
          padding: "0.25rem 0.5rem",
          "&.Mui-selected": {
            color: "primary.contrastText",
          },
        },
      }}
    >
      {children}
    </MuiTabs>
  )
}

interface TabsTriggerProps {
  value: string
  className?: string
  disabled?: boolean
  children?: React.ReactNode
}

function TabsTrigger({
  value,
  className,
  disabled,
  children,
  ...props
}: TabsTriggerProps) {
  return (
    <MuiTab
      data-slot="tabs-trigger"
      value={value}
      label={children}
      disabled={disabled}
      className={className}
      disableRipple
      {...props}
    />
  )
}

interface TabsContentProps {
  value: string
  className?: string
  children?: React.ReactNode
}

function TabsContent({ value, className, children }: TabsContentProps) {
  const ctx = React.useContext(TabsContext)

  if (ctx.value !== value) {
    return null
  }

  return (
    <Box data-slot="tabs-content" className={className} sx={{ flex: 1 }}>
      {children}
    </Box>
  )
}

export { Tabs, TabsList, TabsTrigger, TabsContent }
