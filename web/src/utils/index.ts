import { ClassValue, clsx } from "clsx";
import { extendTailwindMerge } from "tailwind-merge";

/**
 * tailwind-merge must know TailAdmin's custom font sizes (globals.css @theme);
 * otherwise "text-title-sm" is taken for a color and dropped next to "text-gray-800".
 */
const twMerge = extendTailwindMerge({
  extend: {
    classGroups: {
      "font-size": [{ text: ["title-2xl", "title-xl", "title-lg", "title-md", "title-sm", "theme-xl", "theme-sm", "theme-xs"] }],
    },
  },
});

/**
 * Combines and merges Tailwind CSS class names with conditional logic.
 * @example
 * cn("bg-white", isActive && "text-black", "px-4") → "bg-white text-black px-4"
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(...inputs));
}
