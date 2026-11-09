import { Logo } from "@/components/brand/Logo";
import GridShape from "@/components/common/GridShape";
import ThemeTogglerTwo from "@/components/common/ThemeTogglerTwo";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
      <div className="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
        <div className="flex w-full flex-1 flex-col lg:w-1/2">
          <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">{children}</div>
        </div>

        <aside className="hidden h-full w-full items-center bg-brand-950 lg:grid lg:w-1/2 dark:bg-white/5">
          <div className="relative z-1 flex items-center justify-center">
            <GridShape />
            <div className="flex max-w-sm flex-col items-center text-center">
              <Logo inverted className="mb-6" />
              <p className="text-base text-white/70">
                Agenda, historias clínicas y recordatorios para clínicas veterinarias.
              </p>
            </div>
          </div>
        </aside>

        <div className="fixed right-6 bottom-6 z-50 hidden sm:block">
          <ThemeTogglerTwo />
        </div>
      </div>
    </div>
  );
}
