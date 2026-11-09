import { toPng } from "html-to-image";

/**
 * One PNG with both sides of the card (good for WhatsApp or a print shop),
 * 4× resolution. Used by the clinic's designer and the owner portal.
 */
export async function downloadCard(front: HTMLElement, back: HTMLElement, petName: string): Promise<void> {
  const options = { pixelRatio: 4, cacheBust: true, backgroundColor: "transparent" };
  const sides = await Promise.all([toPng(front, options), toPng(back, options)]);
  const [imgA, imgB] = await Promise.all(
    sides.map(
      (src) =>
        new Promise<HTMLImageElement>((resolve) => {
          const img = new Image();
          img.onload = () => resolve(img);
          img.src = src;
        }),
    ),
  );

  const gap = 80;
  const canvas = document.createElement("canvas");
  canvas.width = Math.max(imgA.width, imgB.width) + gap * 2;
  canvas.height = imgA.height + imgB.height + gap * 3;
  const ctx = canvas.getContext("2d")!;
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.drawImage(imgA, gap, gap);
  ctx.drawImage(imgB, gap, imgA.height + gap * 2);

  const link = document.createElement("a");
  link.download = `carne-${petName.toLowerCase().replace(/[^a-z0-9]+/gi, "-")}.png`;
  link.href = canvas.toDataURL("image/png");
  link.click();
}
