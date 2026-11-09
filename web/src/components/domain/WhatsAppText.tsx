/** WhatsApp formatting as the owner sees it on the phone: *bold* → bold. */
export default function WhatsAppText({ text }: { text: string }) {
  return (
    <>
      {text.split(/(\*[^*\n]+\*)/g).map((part, i) =>
        part.startsWith("*") && part.endsWith("*") && part.length > 2 ? <b key={i}>{part.slice(1, -1)}</b> : <span key={i}>{part}</span>,
      )}
    </>
  );
}
