import { cn } from "@/lib/utils";
import { colorOf } from "@/lib/colors";

export function Progress({
  value,
  color,
  className,
  size = "md",
}: {
  value: number;
  color?: string;
  className?: string;
  size?: "sm" | "md";
}) {
  const v = Math.max(0, Math.min(100, value));
  return (
    <div
      role="progressbar"
      aria-valuenow={v}
      aria-valuemin={0}
      aria-valuemax={100}
      className={cn(
        "w-full overflow-hidden rounded-full bg-muted print-exact",
        size === "sm" ? "h-1.5" : "h-2",
        className,
      )}
    >
      <div
        className={cn("h-full rounded-full transition-[width] duration-500", color ? colorOf(color).bar : "bg-primary")}
        style={{ width: `${v}%` }}
      />
    </div>
  );
}

export function ProgressRing({
  value,
  size = 64,
  stroke = 6,
  className,
  label,
}: {
  value: number;
  size?: number;
  stroke?: number;
  className?: string;
  label?: React.ReactNode;
}) {
  const v = Math.max(0, Math.min(100, value));
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  return (
    <div
      className={cn("relative inline-flex shrink-0 items-center justify-center", className)}
      style={{ width: size, height: size }}
    >
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} className="stroke-muted" />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (v / 100) * c}
          className="stroke-primary transition-[stroke-dashoffset] duration-700"
        />
      </svg>
      <span className="absolute text-center font-semibold tabular-nums" style={{ fontSize: size / 4.2 }}>
        {label ?? `${Math.round(v)}%`}
      </span>
    </div>
  );
}
