import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

const disabledSolid =
  "disabled:border-border disabled:bg-muted disabled:text-muted-foreground aria-disabled:border-border aria-disabled:bg-muted aria-disabled:text-muted-foreground dark:disabled:bg-muted dark:aria-disabled:bg-muted"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          `bg-primary text-primary-foreground hover:bg-primary/90 ${disabledSolid}`,
        brand:
          `bg-brand text-brand-foreground hover:bg-brand/90 ${disabledSolid}`,
        destructive:
          `bg-destructive text-white hover:bg-destructive/90 dark:bg-destructive/60 ${disabledSolid}`,
        outline:
          `border bg-card text-foreground hover:bg-secondary ${disabledSolid}`,
        secondary:
          `bg-secondary text-secondary-foreground hover:bg-secondary/80 ${disabledSolid}`,
        ghost:
          "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 disabled:text-muted-foreground aria-disabled:text-muted-foreground",
        link: "text-brand-text underline-offset-4 hover:underline disabled:text-muted-foreground aria-disabled:text-muted-foreground",
      },
      size: {
        "default": "h-control px-4 py-2 has-[>svg]:px-3",
        "sm": "h-control-sm rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg": "h-10 rounded-md px-6 has-[>svg]:px-4",
        "icon": "size-control",
        "icon-sm": "size-control-sm",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
