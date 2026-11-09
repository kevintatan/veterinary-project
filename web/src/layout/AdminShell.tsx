"use client";

import { SidebarProvider, useSidebar } from "@/context/SidebarContext";
import { cn } from "@/utils";
import AppHeader from "./AppHeader";
import AppSidebar from "./AppSidebar";
import Backdrop from "./Backdrop";

export default function AdminShell({ children }: { children: React.ReactNode }) {
  return (
    <SidebarProvider>
      <Shell>{children}</Shell>
    </SidebarProvider>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  const { isExpanded, isHovered, isMobileOpen } = useSidebar();

  return (
    <div className="min-h-screen xl:flex">
      {/* Printing a report (e.g. the daily close): only the page content. */}
      <div className="print:hidden">
        <AppSidebar />
        <Backdrop />
      </div>
      <div
        className={cn(
          "flex-1 transition-all duration-300 ease-in-out print:ml-0",
          isMobileOpen ? "ml-0" : isExpanded || isHovered ? "xl:ml-[290px]" : "xl:ml-[90px]",
        )}
      >
        <AppHeader />
        <main className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6 print:p-0">{children}</main>
      </div>
    </div>
  );
}
