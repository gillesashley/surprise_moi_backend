import * as React from "react"
import {
  createContext,
  useContext,
  useState,
  useEffect,
  type ReactNode,
  type ReactElement,
} from "react"
import MuiTooltip from "@mui/material/Tooltip"

/**
 * Maps Radix side/align to MUI placement.
 */
function mapPlacement(
  side?: "top" | "right" | "bottom" | "left",
  align?: "start" | "center" | "end"
): "top" | "top-start" | "top-end" | "right" | "right-start" | "right-end" | "bottom" | "bottom-start" | "bottom-end" | "left" | "left-start" | "left-end" {
  const s = side ?? "top"
  const a = align ?? "center"
  if (a === "center") {
    return s
  }
  return `${s}-${a}` as ReturnType<typeof mapPlacement>
}

interface TooltipContextValue {
  content: ReactNode
  setContent: (content: ReactNode) => void
  side: "top" | "right" | "bottom" | "left"
  setSide: (side: "top" | "right" | "bottom" | "left") => void
  align: "start" | "center" | "end"
  setAlign: (align: "start" | "center" | "end") => void
  hidden: boolean
  setHidden: (hidden: boolean) => void
}

const TooltipCtx = createContext<TooltipContextValue>({
  content: null,
  setContent: () => {},
  side: "top",
  setSide: () => {},
  align: "center",
  setAlign: () => {},
  hidden: false,
  setHidden: () => {},
})

function TooltipProvider({
  children,
}: {
  delayDuration?: number
  children?: ReactNode
  [key: string]: unknown
}) {
  return <>{children}</>
}

function Tooltip({
  children,
}: {
  children?: ReactNode
  [key: string]: unknown
}) {
  const [content, setContent] = useState<ReactNode>(null)
  const [side, setSide] = useState<"top" | "right" | "bottom" | "left">("top")
  const [align, setAlign] = useState<"start" | "center" | "end">("center")
  const [hidden, setHidden] = useState(false)

  return (
    <TooltipCtx.Provider
      value={{ content, setContent, side, setSide, align, setAlign, hidden, setHidden }}
    >
      {children}
    </TooltipCtx.Provider>
  )
}

function TooltipTrigger({
  asChild,
  children,
  ...props
}: {
  asChild?: boolean
  children?: ReactNode
  [key: string]: unknown
}) {
  const { content, side, align, hidden } = useContext(TooltipCtx)
  const placement = mapPlacement(side, align)

  // MUI Tooltip needs a single child element that can hold a ref.
  // When asChild is true, we use the child directly (must be a single element).
  // Otherwise, we wrap in a span.
  let trigger: ReactElement
  if (asChild && React.isValidElement(children)) {
    trigger = children as ReactElement
  } else {
    trigger = (
      <span data-slot="tooltip-trigger" {...props}>
        {children}
      </span>
    )
  }

  if (hidden || content == null) {
    return trigger
  }

  return (
    <MuiTooltip
      title={content}
      arrow
      enterDelay={0}
      placement={placement}
      slotProps={{
        tooltip: {
          sx: {
            bgcolor: "var(--color-primary, #171717)",
            color: "var(--color-primary-foreground, #fff)",
            fontSize: "0.75rem",
            maxWidth: "24rem",
            borderRadius: "0.375rem",
            px: "0.75rem",
            py: "0.375rem",
          },
        },
        arrow: {
          sx: {
            color: "var(--color-primary, #171717)",
          },
        },
      }}
    >
      {trigger}
    </MuiTooltip>
  )
}

function TooltipContent({
  children,
  side,
  align,
  hidden,
}: {
  children?: ReactNode
  className?: string
  sideOffset?: number
  side?: "top" | "right" | "bottom" | "left"
  align?: "start" | "center" | "end"
  hidden?: boolean
  [key: string]: unknown
}) {
  const ctx = useContext(TooltipCtx)

  useEffect(() => {
    ctx.setContent(children)
  }, [children, ctx.setContent])

  useEffect(() => {
    if (side != null) {
      ctx.setSide(side)
    }
  }, [side, ctx.setSide])

  useEffect(() => {
    if (align != null) {
      ctx.setAlign(align)
    }
  }, [align, ctx.setAlign])

  useEffect(() => {
    if (hidden != null) {
      ctx.setHidden(hidden)
    }
  }, [hidden, ctx.setHidden])

  // TooltipContent renders nothing — MUI Tooltip handles the popup display
  return null
}

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider }
