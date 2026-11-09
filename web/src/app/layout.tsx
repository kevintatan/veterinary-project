import QueryProvider from "@/components/providers/QueryProvider";
import { THEME_INIT_SCRIPT, ThemeProvider } from "@/context/ThemeContext";
import "flatpickr/dist/flatpickr.css";
import type { Metadata } from "next";
import { Plus_Jakarta_Sans } from "next/font/google";
import "simplebar-react/dist/simplebar.min.css";
import "./globals.css";

const jakarta = Plus_Jakarta_Sans({
  subsets: ["latin"],
  variable: "--font-jakarta",
  display: "swap",
});

export const metadata: Metadata = {
  title: { default: "Zooetia", template: "%s · Zooetia" },
  description: "Software para clínicas veterinarias en Colombia.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="es-CO" className={jakarta.variable} suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: THEME_INIT_SCRIPT }} />
      </head>
      <body className="dark:bg-gray-900">
        <ThemeProvider>
          <QueryProvider>{children}</QueryProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
