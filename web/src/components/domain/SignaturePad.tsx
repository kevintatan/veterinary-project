"use client";

import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from "react";

export type SignaturePadHandle = {
  /** PNG with a transparent background, or null if nothing was drawn. */
  toDataUrl: () => string | null;
  clear: () => void;
};

/**
 * Sign with a finger, a stylus or the mouse (pointer events). Drawn at the
 * screen's real pixel density so the stroke is sharp in the PDF, with
 * smoothed curves and a slightly thinner line when the hand moves fast, like
 * a pen. No library: ~80 lines we fully control.
 */
const SignaturePad = forwardRef<SignaturePadHandle, { onChange?: (hasInk: boolean) => void; className?: string }>(function SignaturePad({ onChange, className }, ref) {
  const canvas = useRef<HTMLCanvasElement>(null);
  const points = useRef<{ x: number; y: number; t: number }[]>([]);
  const width = useRef(2.6);
  const [hasInk, setHasInk] = useState(false);
  const lastWidth = useRef(0);
  // Kept in a ref: a new callback on every parent render must not re-run the effect (it would clear the pad).
  const notify = useRef(onChange);
  useEffect(() => {
    notify.current = onChange;
  }, [onChange]);

  const ctx = () => canvas.current?.getContext("2d") ?? null;

  const resize = useCallback((force = true) => {
    const el = canvas.current;
    if (!el) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const { width: w, height: h } = el.getBoundingClientRect();
    // On phones the address bar showing/hiding fires "resize" with the same width: keep the signature.
    if (!force && Math.round(w) === lastWidth.current) return;
    lastWidth.current = Math.round(w);
    el.width = Math.round(w * ratio);
    el.height = Math.round(h * ratio);
    const c = el.getContext("2d")!;
    c.scale(ratio, ratio);
    c.lineCap = "round";
    c.lineJoin = "round";
    c.strokeStyle = "#101828";
    setHasInk(false);
    notify.current?.(false);
  }, []);

  useEffect(() => {
    resize();
    const onResize = () => resize(false);
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, [resize]);

  useImperativeHandle(ref, () => ({
    toDataUrl: () => (hasInk && canvas.current ? canvas.current.toDataURL("image/png") : null),
    clear: () => resize(),
  }));

  const point = (e: React.PointerEvent) => {
    const r = canvas.current!.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top, t: e.timeStamp };
  };

  const down = (e: React.PointerEvent) => {
    e.preventDefault();
    // Keep receiving the stroke if the finger leaves the box. Some browsers throw for
    // pointers they don't track (e.g. some styluses): the stroke must still work.
    try {
      canvas.current!.setPointerCapture(e.pointerId);
    } catch {
      /* drawing works without capture */
    }
    const p = point(e);
    points.current = [p];
    const c = ctx()!;
    c.beginPath();
    c.arc(p.x, p.y, 1.3, 0, Math.PI * 2);
    c.fillStyle = "#101828";
    c.fill();
  };

  const move = (e: React.PointerEvent) => {
    if (points.current.length === 0) return;
    const p = point(e);
    const prev = points.current[points.current.length - 1];
    const speed = Math.hypot(p.x - prev.x, p.y - prev.y) / Math.max(p.t - prev.t, 1);
    // Faster stroke → thinner line (eased so it doesn't flicker).
    width.current = width.current * 0.7 + Math.max(1.4, 3.2 - speed * 1.1) * 0.3;
    points.current.push(p);

    const c = ctx()!;
    c.lineWidth = width.current;
    const pts = points.current;
    if (pts.length === 2) {
      // First stretch: from where the finger landed to the middle of the first move.
      c.beginPath();
      c.moveTo(pts[0].x, pts[0].y);
      c.lineTo((pts[0].x + p.x) / 2, (pts[0].y + p.y) / 2);
      c.stroke();
    } else if (pts.length >= 3) {
      const a = pts[pts.length - 3];
      const b = pts[pts.length - 2];
      c.beginPath();
      c.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
      c.quadraticCurveTo(b.x, b.y, (b.x + p.x) / 2, (b.y + p.y) / 2);
      c.stroke();
    }
    if (!hasInk && pts.length > 4) {
      setHasInk(true);
      notify.current?.(true);
    }
  };

  const up = () => {
    // Last stretch: from the middle of the last move to where the finger lifted.
    const pts = points.current;
    if (pts.length >= 2) {
      const a = pts[pts.length - 2];
      const b = pts[pts.length - 1];
      const c = ctx()!;
      c.beginPath();
      c.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
      c.lineTo(b.x, b.y);
      c.stroke();
    }
    points.current = [];
  };

  return (
    <canvas
      ref={canvas}
      onPointerDown={down}
      onPointerMove={move}
      onPointerUp={up}
      onPointerCancel={up}
      onPointerLeave={up}
      className={className}
      style={{ touchAction: "none" }}
      aria-label="Recuadro para firmar"
      role="img"
    />
  );
});

export default SignaturePad;
