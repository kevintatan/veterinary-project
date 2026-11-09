import type { Metadata } from "next";
import TasksView from "./TasksView";

export const metadata: Metadata = { title: "Tareas" };

export default function Page() {
  return <TasksView />;
}
