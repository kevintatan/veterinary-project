import type { Metadata } from "next";
import InboxView from "./InboxView";

export const metadata: Metadata = { title: "WhatsApp" };

export default function Page() {
  return <InboxView />;
}
