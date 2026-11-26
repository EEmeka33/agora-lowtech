"use client";

import * as React from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Search, X } from "lucide-react";
import { Select } from "@/components/ui/input";
import { cn } from "@/lib/utils";

function useUpdateParam() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  return React.useCallback(
    (key: string, value: string | null) => {
      const next = new URLSearchParams(params.toString());
      if (value) next.set(key, value);
      else next.delete(key);
      next.delete("new");
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [params, pathname, router],
  );
}

/** A select that writes its value into the URL (?param=value). */
export function FilterSelect({
  param,
  options,
  placeholder,
  className,
}: {
  param: string;
  options: { value: string; label: string }[];
  placeholder: string;
  className?: string;
}) {
  const params = useSearchParams();
  const update = useUpdateParam();
  const value = params.get(param) ?? "";
  return (
    <Select
      value={value}
      onChange={(e) => update(param, e.target.value || null)}
      className={cn(
        "h-8 w-auto max-w-52 min-w-32 text-[13px] print:hidden",
        value && "border-primary/50 bg-primary-soft/40",
        className,
      )}
    >
      <option value="">{placeholder}</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </Select>
  );
}

/** Debounced search box bound to ?q= */
export function SearchInput({
  placeholder,
  param = "q",
  className,
}: {
  placeholder: string;
  param?: string;
  className?: string;
}) {
  const params = useSearchParams();
  const update = useUpdateParam();
  const [value, setValue] = React.useState(params.get(param) ?? "");
  const first = React.useRef(true);

  React.useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }
    const timer = setTimeout(() => update(param, value.trim() || null), 250);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  return (
    <div className={cn("relative print:hidden", className)}>
      <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
      <input
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder={placeholder}
        className="h-8 w-full rounded-lg border border-input bg-card pr-7 pl-8 text-[13px] shadow-xs placeholder:text-muted-foreground/70 focus:border-ring focus:ring-3 focus:ring-ring/20 focus:outline-none"
      />
      {value && (
        <button
          type="button"
          onClick={() => setValue("")}
          className="absolute top-1/2 right-1.5 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:bg-hover"
          aria-label="Clear"
        >
          <X className="size-3.5" />
        </button>
      )}
    </div>
  );
}
