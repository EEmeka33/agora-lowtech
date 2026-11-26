import { getI18n } from "@/lib/i18n/server";
import { formatMoney } from "@/lib/utils";

/*
 * Charts drawn on the server with HTML and CSS. They used to be drawn in the
 * browser by Recharts, a 113 KB (compressed) library for two bar charts.
 * A native tooltip (title) gives the details of a month, and a hidden table
 * gives the numbers to screen readers.
 */

type Month = { label: string; income: number; expense: number };

/** A round top for the scale: 1, 2, 2.5 or 5 times a power of ten. */
function niceMax(value: number) {
  if (value <= 0) return 100;
  const power = 10 ** Math.floor(Math.log10(value));
  for (const step of [1, 2, 2.5, 5]) if (value <= step * power) return step * power;
  return 10 * power;
}

/** Grouped columns: income vs expenses per month (amounts in cents). */
export async function MonthlyChart({ data, currency }: { data: Month[]; currency: string }) {
  const { t, locale } = await getI18n();
  const compact = new Intl.NumberFormat(locale === "fr" ? "fr-FR" : "en-GB", {
    notation: "compact",
    maximumFractionDigits: 1,
  });
  const money = (cents: number) => formatMoney(cents, currency, locale);
  const max = niceMax(Math.max(0, ...data.map((d) => Math.max(d.income, d.expense))));
  const ticks = [1, 0.75, 0.5, 0.25, 0];
  const height = (cents: number) => `${(cents / max) * 100}%`;
  return (
    <div>
      <div className="mb-3 flex items-center gap-4 text-xs text-muted-foreground" aria-hidden>
        <span className="flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-[var(--chart-income)]" /> {t.finance.income}
        </span>
        <span className="flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-[var(--chart-expense)]" /> {t.finance.expenses}
        </span>
      </div>
      <div className="flex h-64 gap-2" aria-hidden>
        <div className="relative mb-6 w-10 shrink-0 text-right text-[11px] text-muted-foreground tabular-nums">
          {ticks.map((f) => (
            <span key={f} className="absolute right-0 -translate-y-1/2" style={{ top: `${(1 - f) * 100}%` }}>
              {compact.format((max * f) / 100)}
            </span>
          ))}
        </div>
        <div className="relative flex-1">
          <div className="absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
            {ticks.map((f) => (
              <div key={f} className="border-t border-border" />
            ))}
          </div>
          <ul className="absolute inset-0 flex">
            {data.map((m) => (
              <li
                key={m.label}
                className="group flex min-w-0 flex-1 flex-col"
                title={`${m.label}\n${t.finance.income}: ${money(m.income)}\n${t.finance.expenses}: ${money(m.expense)}\n${t.finance.balance}: ${money(m.income - m.expense)}`}
              >
                <div className="flex flex-1 items-end justify-center gap-0.5 rounded-t-md px-[18%] group-hover:bg-hover">
                  <span className="w-full max-w-6 rounded-t bg-[var(--chart-income)]" style={{ height: height(m.income) }} />
                  <span className="w-full max-w-6 rounded-t bg-[var(--chart-expense)]" style={{ height: height(m.expense) }} />
                </div>
                <span className="h-6 truncate pt-1.5 text-center text-[11px] text-muted-foreground capitalize">
                  {m.label}
                </span>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <table className="sr-only">
        <caption>{t.finance.monthly}</caption>
        <thead>
          <tr>
            <th scope="col">{t.common.month}</th>
            <th scope="col">{t.finance.income}</th>
            <th scope="col">{t.finance.expenses}</th>
          </tr>
        </thead>
        <tbody>
          {data.map((m) => (
            <tr key={m.label}>
              <th scope="row">{m.label}</th>
              <td>{money(m.income)}</td>
              <td>{money(m.expense)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** Horizontal bars with direct value labels (single series). */
export async function CategoryBars({ data, currency }: { data: { label: string; value: number }[]; currency: string }) {
  const { locale } = await getI18n();
  const max = Math.max(1, ...data.map((d) => d.value));
  const total = data.reduce((s, d) => s + d.value, 0);
  return (
    <ul className="space-y-3">
      {data.map((d) => (
        <li
          key={d.label}
          title={`${d.label}: ${formatMoney(d.value, currency, locale)} (${Math.round((d.value / total) * 100)}%)`}
        >
          <div className="mb-1 flex items-baseline justify-between gap-3 text-xs">
            <span className="truncate">{d.label}</span>
            <span className="shrink-0 font-medium tabular-nums">{formatMoney(d.value, currency, locale)}</span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-r-[4px] bg-[var(--chart-expense)]"
              style={{ width: `${(d.value / max) * 100}%` }}
            />
          </div>
        </li>
      ))}
    </ul>
  );
}
