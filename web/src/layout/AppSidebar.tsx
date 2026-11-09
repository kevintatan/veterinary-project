"use client";

import { Logo, LogoMark } from "@/components/brand/Logo";
import { useSession } from "@/components/providers/SessionProvider";
import { useSidebar } from "@/context/SidebarContext";
import {
  BoxCubeIcon,
  BoxIcon,
  CalenderIcon,
  ChatIcon,
  DollarLineIcon,
  GridIcon,
  GroupIcon,
  HorizontaLDots,
  PieChartIcon,
  TaskIcon,
} from "@/icons";
import { fetchInbox, inboxKey } from "@/lib/conversations";
import { useBookingRequestCount } from "@/lib/booking";
import { useQuery } from "@tanstack/react-query";
import { cn } from "@/utils";
import Link from "next/link";
import { usePathname } from "next/navigation";

type NavItem = {
  label: string;
  href: string;
  icon: React.ReactNode;
  exact?: boolean;
  permission?: string;
};

const GROUPS: { title: string; items: NavItem[] }[] = [
  {
    title: "Menú",
    items: [
      {
        label: "Hoy en la clínica",
        href: "/app",
        icon: <GridIcon />,
        exact: true,
      },
      {
        label: "Agenda",
        href: "/app/agenda",
        icon: <CalenderIcon />,
        permission: "appointments.view",
      },
      {
        label: "Tutores y mascotas",
        href: "/app/tutores",
        icon: <GroupIcon />,
        permission: "patients.view",
      },
      {
        label: "WhatsApp",
        href: "/app/whatsapp",
        icon: <ChatIcon />,
        permission: "appointments.view",
      },
      {
        label: "Tareas",
        href: "/app/tareas",
        icon: <TaskIcon />,
        permission: "tasks.view",
      },
      {
        label: "Ventas",
        href: "/app/ventas",
        icon: <DollarLineIcon />,
        permission: "sales.view",
      },
      {
        label: "Reportes",
        href: "/app/reportes",
        icon: <PieChartIcon />,
        permission: "sales.manage",
      },
      {
        label: "Inventario",
        href: "/app/inventario",
        icon: <BoxIcon />,
        permission: "inventory.view",
      },
    ],
  },
  {
    title: "Clínica",
    items: [
      {
        label: "Configuración",
        href: "/app/configuracion",
        icon: <BoxCubeIcon />,
        permission: "branches.manage",
      },
    ],
  },
];

export default function AppSidebar() {
  const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
  const { permissions } = useSession();
  const pathname = usePathname();
  const open = isExpanded || isHovered || isMobileOpen;

  const isActive = (item: NavItem) =>
    item.exact ? pathname === item.href : pathname.startsWith(item.href);
  // UI only: the API enforces every permission anyway.
  // Conversations the bot handed to the team (shares the cache with the inbox page).
  const inbox = useQuery({
    queryKey: inboxKey("attention"),
    queryFn: () => fetchInbox("attention"),
    enabled: permissions.includes("appointments.view"),
    refetchInterval: 60_000,
  });
  const visible = (item: NavItem) =>
    (!item.permission || permissions.includes(item.permission)) &&
    // Only once we know the plan includes it (no flash of "WhatsApp" for plans without the inbox).
    !(item.href === "/app/whatsapp" && inbox.data?.meta.enabled !== true);
  const requests = useBookingRequestCount(permissions.includes("appointments.view"));
  const badges: Record<string, number> = {
    "/app/whatsapp": inbox.data?.meta.attention ?? 0,
    "/app/agenda": requests.data ?? 0, // online booking requests to answer
  };

  return (
    <aside
      className={cn(
        "fixed top-0 left-0 z-50 flex h-full flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900",
        open ? "w-72.5" : "w-22.5",
        isMobileOpen ? "translate-x-0" : "-translate-x-full",
        "xl:translate-x-0",
      )}
      onMouseEnter={() => !isExpanded && setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div
        className={cn(
          "flex py-8",
          open ? "justify-start" : "xl:justify-center",
        )}
      >
        <Link href="/app" aria-label="Zooetia, inicio">
          {open ? <Logo /> : <LogoMark />}
        </Link>
      </div>

      <nav
        aria-label="Menú principal"
        className="no-scrollbar flex flex-col gap-6 overflow-y-auto pb-6"
      >
        {GROUPS.map((group) => {
          const items = group.items.filter(visible);

          if (items.length === 0) {
            return null;
          }

          return (
            <div key={group.title}>
              <h2
                className={cn(
                  "mb-4 flex text-xs leading-5 text-gray-400 uppercase",
                  open ? "justify-start" : "xl:justify-center",
                )}
              >
                {open ? group.title : <HorizontaLDots />}
              </h2>
              <ul className="flex flex-col gap-1">
                {items.map((item) => {
                  const active = isActive(item);

                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        aria-current={active ? "page" : undefined}
                        title={open ? undefined : item.label}
                        className={cn(
                          "group menu-item",
                          active ? "menu-item-active" : "menu-item-inactive",
                          !open && "xl:justify-center",
                        )}
                      >
                        <span
                          className={
                            active
                              ? "menu-item-icon-active"
                              : "menu-item-icon-inactive"
                          }
                        >
                          {item.icon}
                        </span>
                        {open && (
                          <span className="menu-item-text">{item.label}</span>
                        )}
                        {(badges[item.href] ?? 0) > 0 && (
                          <span
                            className={cn(
                              "rounded-full bg-success-500 px-1.5 text-[11px] leading-5 font-semibold text-white tabular",
                              open ? "ml-auto" : "absolute top-1 right-1",
                            )}
                            aria-label={`${badges[item.href]} por responder`}
                          >
                            {badges[item.href]}
                          </span>
                        )}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
      </nav>
    </aside>
  );
}
