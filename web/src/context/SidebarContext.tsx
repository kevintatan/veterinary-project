"use client";

import { usePathname } from "next/navigation";
import { createContext, useContext, useState, useSyncExternalStore } from "react";

type SidebarContextType = {
  isExpanded: boolean;
  isMobileOpen: boolean;
  isHovered: boolean;
  toggleSidebar: () => void;
  toggleMobileSidebar: () => void;
  setIsHovered: (isHovered: boolean) => void;
};

const SidebarContext = createContext<SidebarContextType | undefined>(undefined);

const MOBILE_QUERY = "(max-width: 767px)";

function subscribe(onChange: () => void): () => void {
  const media = window.matchMedia(MOBILE_QUERY);
  media.addEventListener("change", onChange);
  return () => media.removeEventListener("change", onChange);
}

export function SidebarProvider({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const isMobile = useSyncExternalStore(subscribe, () => window.matchMedia(MOBILE_QUERY).matches, () => false);
  const [isExpanded, setIsExpanded] = useState(true);
  const [isHovered, setIsHovered] = useState(false);
  // The mobile drawer belongs to the page where it was opened: navigating closes it
  // (derived, instead of resetting state in an effect).
  const [mobileOpenAt, setMobileOpenAt] = useState<string | null>(null);
  const isMobileOpen = mobileOpenAt === pathname;

  return (
    <SidebarContext.Provider
      value={{
        isExpanded: isMobile ? false : isExpanded,
        isMobileOpen,
        isHovered,
        toggleSidebar: () => setIsExpanded((v) => !v),
        toggleMobileSidebar: () => setMobileOpenAt(isMobileOpen ? null : pathname),
        setIsHovered,
      }}
    >
      {children}
    </SidebarContext.Provider>
  );
}

export function useSidebar(): SidebarContextType {
  const context = useContext(SidebarContext);
  if (!context) {
    throw new Error("useSidebar must be used within a SidebarProvider");
  }
  return context;
}
