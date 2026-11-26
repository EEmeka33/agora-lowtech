import type { Metadata } from "next";
import Link from "@/components/ui/link";
import { ArrowDownRight, ArrowUpRight, Download, Landmark, Pencil, Plus, Scale, Trash2, Wallet } from "lucide-react";
import type { Prisma } from "@/generated/prisma/client";
import { requireContext } from "@/lib/context";
import type { TenantDb } from "@/lib/tenant";
import { withParams } from "@/lib/url";
import { getI18n } from "@/lib/i18n/server";
import { can } from "@/lib/permissions";
import { getAllProjectOptions, getMemberOptions, getPoleOptions } from "@/lib/queries";
import { TRANSACTION_TYPES } from "@/lib/constants";
import { cn, formatDate, formatMoney, nowWall, pct } from "@/lib/utils";
import {
  createTransaction,
  deleteMembership,
  deleteTransaction,
  recordMembership,
  updateTransaction,
} from "@/actions/finance";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { EmptyState, PageHeader, StatCard, TabLinks } from "@/components/ui/page";
import { FormDialog } from "@/components/forms/form-dialog";
import { FilterSelect, SearchInput } from "@/components/forms/filters";
import { RowActions } from "@/components/forms/row-actions";
import { TransactionFields } from "@/components/finance/transaction-fields";
import { DuesFields } from "@/components/finance/dues-fields";
import { CategoryBars, MonthlyChart } from "@/components/finance/charts";

export const metadata: Metadata = { title: "Finance" };

export default async function FinancePage(props: PageProps<"/finance">) {
  const { user, db, association } = await requireContext();
  const { t, locale } = await getI18n();
  const sp = await props.searchParams;
  const get = (k: string) => (typeof sp[k] === "string" ? (sp[k] as string) : "");
  const currentYear = nowWall().getUTCFullYear();
  const year = /^\d{4}$/.test(get("year")) ? Number(get("year")) : currentYear;
  const tab = get("tab") === "dues" ? "dues" : "transactions";
  const yearRange = { gte: new Date(Date.UTC(year, 0, 1)), lt: new Date(Date.UTC(year + 1, 0, 1)) };

  const currency = association.currency;
  const money = (c: number | null | undefined) => formatMoney(c, currency, locale);
  const canManage = can.manageFinance(user);

  const [yearTx, totals, firstTx, budgets, projects, poles, members] = await Promise.all([
    db.transaction.findMany({
      where: { date: yearRange },
      select: { type: true, amountCents: true, category: true, date: true },
    }),
    db.transaction.groupBy({ by: ["type"], _sum: { amountCents: true } }),
    db.transaction.findFirst({ orderBy: { date: "asc" }, select: { date: true } }),
    db.project.findMany({
      where: { budgetCents: { gt: 0 }, status: { not: "CANCELLED" } },
      select: {
        id: true,
        title: true,
        color: true,
        budgetCents: true,
        transactions: { where: { type: "EXPENSE" }, select: { amountCents: true } },
      },
      orderBy: { title: "asc" },
    }),
    getAllProjectOptions(db),
    getPoleOptions(db),
    getMemberOptions(db),
  ]);

  const sum = (type: string) => yearTx.filter((x) => x.type === type).reduce((s, x) => s + x.amountCents, 0);
  const income = sum("INCOME");
  const expenses = sum("EXPENSE");
  const allIncome = totals.find((x) => x.type === "INCOME")?._sum.amountCents ?? 0;
  const allExpense = totals.find((x) => x.type === "EXPENSE")?._sum.amountCents ?? 0;

  const months = Array.from({ length: 12 }, (_, m) => {
    const inMonth = yearTx.filter((x) => x.date.getUTCMonth() === m);
    return {
      label: formatDate(new Date(Date.UTC(year, m, 1)), locale, { month: "short" }).replace(".", ""),
      income: inMonth.filter((x) => x.type === "INCOME").reduce((s, x) => s + x.amountCents, 0),
      expense: inMonth.filter((x) => x.type === "EXPENSE").reduce((s, x) => s + x.amountCents, 0),
    };
  });
  const byCategory = Object.entries(
    yearTx
      .filter((x) => x.type === "EXPENSE")
      .reduce<Record<string, number>>(
        (acc, x) => ({ ...acc, [x.category]: (acc[x.category] ?? 0) + x.amountCents }),
        {},
      ),
  )
    .map(([k, v]) => ({ label: t.enums.category[k] ?? k, value: v }))
    .sort((a, b) => b.value - a.value);

  const firstYear = Math.min(firstTx?.date.getUTCFullYear() ?? currentYear, currentYear);
  const years = Array.from({ length: currentYear - firstYear + 2 }, (_, i) => currentYear + 1 - i).filter(
    (y) => y >= firstYear,
  );
  const projectOpts = projects.map((p) => ({ id: p.id, name: p.title }));
  const qs = (patch: Record<string, string | null>) => withParams("/finance", sp, patch);

  return (
    <>
      <PageHeader
        title={t.finance.title}
        description={t.finance.subtitle}
        actions={
          <>
            <FilterSelect
              param="year"
              placeholder={String(currentYear)}
              options={years.filter((y) => y !== currentYear).map((y) => ({ value: String(y), label: String(y) }))}
              className="h-9"
            />
            <a href={`/api/export/transactions?year=${year}`} className={buttonVariants({ variant: "outline" })}>
              <Download /> {t.finance.exportCsv}
            </a>
            {canManage && (
              <FormDialog
                title={t.finance.newTransaction}
                action={createTransaction}
                submitLabel={t.common.create}
                trigger={
                  <Button>
                    <Plus /> {t.finance.newTransaction}
                  </Button>
                }
              >
                <TransactionFields projects={projectOpts} poles={poles} currency={currency} />
              </FormDialog>
            )}
          </>
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={`${t.finance.income} ${year}`} value={money(income)} icon={<ArrowUpRight />} />
        <StatCard label={`${t.finance.expenses} ${year}`} value={money(expenses)} icon={<ArrowDownRight />} />
        <StatCard
          label={t.finance.yearBalance(year)}
          value={money(income - expenses)}
          tone={income - expenses < 0 ? "danger" : "success"}
          hint={`${t.finance.income} − ${t.finance.expenses}`}
          icon={<Scale />}
        />
        <StatCard
          label={t.finance.allTimeBalance}
          value={money(allIncome - allExpense)}
          hint={t.finance.allTimeHint}
          icon={<Landmark />}
        />
      </div>

      <div className="mb-6 grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t.finance.monthly}</CardTitle>
          </CardHeader>
          <CardContent>
            <MonthlyChart data={months} currency={currency} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t.finance.byCategory}</CardTitle>
          </CardHeader>
          <CardContent>
            {byCategory.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t.finance.noCategoryData}</p>
            ) : (
              <CategoryBars data={byCategory.slice(0, 8)} currency={currency} />
            )}
          </CardContent>
        </Card>
      </div>

      {budgets.length > 0 && (
        <Card className="mb-8">
          <CardHeader>
            <CardTitle>{t.finance.projectBudgets}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-x-8 gap-y-4 md:grid-cols-2">
            {budgets.map((p) => {
              const spent = p.transactions.reduce((s, x) => s + x.amountCents, 0);
              const over = spent > (p.budgetCents ?? 0);
              return (
                <Link key={p.id} href={`/projects/${p.id}?tab=budget`} className="group block">
                  <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                    <span className="truncate font-medium group-hover:text-primary">{p.title}</span>
                    <span
                      className={cn(
                        "shrink-0 text-xs tabular-nums",
                        over ? "font-semibold text-destructive" : "text-muted-foreground",
                      )}
                    >
                      {money(spent)} / {money(p.budgetCents)}
                      {over && ` · ${t.finance.budgetOver}`}
                    </span>
                  </div>
                  <Progress value={pct(spent, p.budgetCents ?? 0)} color={over ? "rose" : p.color} size="sm" />
                </Link>
              );
            })}
          </CardContent>
        </Card>
      )}

      <TabLinks
        items={[
          { href: qs({ tab: null }), label: t.finance.transactions, active: tab === "transactions" },
          { href: qs({ tab: "dues" }), label: t.finance.dues, active: tab === "dues" },
        ]}
      />

      {tab === "transactions" ? (
        <TransactionsTab
          db={db}
          sp={{ type: get("type"), category: get("category"), q: get("q") }}
          yearRange={yearRange}
          canManage={canManage}
          projects={projectOpts}
          poles={poles}
          currency={currency}
          t={t}
          locale={locale}
        />
      ) : (
        <DuesTab
          db={db}
          year={year}
          canManage={canManage}
          members={members}
          fee={association.membershipFeeCents}
          currency={currency}
          t={t}
          locale={locale}
        />
      )}
    </>
  );
}

type T = Awaited<ReturnType<typeof getI18n>>["t"];

async function TransactionsTab({
  db,
  sp,
  yearRange,
  canManage,
  projects,
  poles,
  currency,
  t,
  locale,
}: {
  db: TenantDb;
  sp: { type: string; category: string; q: string };
  yearRange: { gte: Date; lt: Date };
  canManage: boolean;
  projects: { id: string; name: string }[];
  poles: { id: string; name: string }[];
  currency: string;
  t: T;
  locale: string;
}) {
  const where: Prisma.TransactionWhereInput = { date: yearRange };
  if (TRANSACTION_TYPES.includes(sp.type as never)) where.type = sp.type;
  if (sp.category) where.category = sp.category;
  if (sp.q) where.label = { contains: sp.q };
  const rows = await db.transaction.findMany({
    where,
    orderBy: [{ date: "desc" }, { createdAt: "desc" }],
    include: { project: { select: { id: true, title: true } }, pole: { select: { name: true, color: true } } },
    take: 300,
  });
  const money = (c: number) => formatMoney(c, currency, locale);
  return (
    <>
      <div className="mb-4 flex flex-wrap gap-2">
        <SearchInput placeholder={t.common.searchPlaceholder} className="w-full sm:w-56" />
        <FilterSelect
          param="type"
          placeholder={t.common.type}
          options={TRANSACTION_TYPES.map((v) => ({ value: v, label: t.enums.transactionType[v] }))}
        />
        <FilterSelect
          param="category"
          placeholder={t.common.category}
          options={Object.entries(t.enums.category).map(([value, label]) => ({ value, label }))}
        />
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={<Wallet />} title={t.finance.noTransactions} />
      ) : (
        <div className="overflow-hidden rounded-xl border bg-card card-shadow">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <thead>
                <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                  <th className="px-4 py-2.5 font-medium">{t.common.date}</th>
                  <th className="px-3 py-2.5 font-medium">{t.finance.label}</th>
                  <th className="px-3 py-2.5 font-medium">{t.common.category}</th>
                  <th className="px-3 py-2.5 font-medium">{t.common.project}</th>
                  <th className="px-3 py-2.5 text-right font-medium">{t.common.amount}</th>
                  <th className="w-12 px-3 py-2.5" />
                </tr>
              </thead>
              <tbody className="divide-y">
                {rows.map((tx) => (
                  <tr key={tx.id} className="hover:bg-hover/40">
                    <td className="px-4 py-2.5 whitespace-nowrap text-muted-foreground">
                      {formatDate(tx.date, locale)}
                    </td>
                    <td className="px-3 py-2.5">
                      <p className="font-medium">{tx.label}</p>
                      {(tx.reference || tx.paymentMethod) && (
                        <p className="text-xs text-muted-foreground">
                          {[tx.paymentMethod && t.enums.paymentMethod[tx.paymentMethod], tx.reference]
                            .filter(Boolean)
                            .join(" · ")}
                        </p>
                      )}
                    </td>
                    <td className="px-3 py-2.5">
                      <Badge color="slate">{t.enums.category[tx.category] ?? tx.category}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-muted-foreground">
                      {tx.project ? (
                        <Link
                          href={`/projects/${tx.project.id}?tab=budget`}
                          className="hover:text-foreground hover:underline"
                        >
                          {tx.project.title}
                        </Link>
                      ) : (
                        (tx.pole?.name ?? "—")
                      )}
                    </td>
                    <td
                      className={cn(
                        "px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums",
                        tx.type === "INCOME" ? "text-success" : "",
                      )}
                    >
                      {tx.type === "INCOME" ? "+" : "−"}
                      {money(tx.amountCents)}
                    </td>
                    <td className="px-3 py-2.5 text-right">
                      {canManage && (
                        <RowActions
                          items={[
                            {
                              kind: "dialog",
                              label: t.common.edit,
                              icon: <Pencil />,
                              title: t.finance.editTransaction,
                              action: updateTransaction.bind(null, tx.id),
                              content: (
                                <TransactionFields
                                  tx={{
                                    label: tx.label,
                                    type: tx.type,
                                    amountCents: tx.amountCents,
                                    category: tx.category,
                                    date: tx.date,
                                    paymentMethod: tx.paymentMethod,
                                    reference: tx.reference,
                                    notes: tx.notes,
                                    projectId: tx.projectId,
                                    poleId: tx.poleId,
                                  }}
                                  projects={projects}
                                  poles={poles}
                                  currency={currency}
                                />
                              ),
                            },
                            {
                              kind: "confirm",
                              label: t.common.delete,
                              icon: <Trash2 />,
                              title: t.finance.deleteTransaction,
                              action: deleteTransaction.bind(null, tx.id),
                              destructive: true,
                            },
                          ]}
                        />
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  );
}

async function DuesTab({
  db,
  year,
  canManage,
  members,
  fee,
  currency,
  t,
  locale,
}: {
  db: TenantDb;
  year: number;
  canManage: boolean;
  members: { id: string; name: string; avatarColor: string }[];
  fee: number | null;
  currency: string;
  t: T;
  locale: string;
}) {
  const memberships = await db.dues.findMany({ where: { year } });
  const paidIds = new Set(memberships.map((m) => m.userId));
  const paid = members.filter((m) => paidIds.has(m.id)).length;
  const unpaidMembers = members.filter((m) => !paidIds.has(m.id));
  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>
            {t.finance.dues} {year}
          </CardTitle>
          <CardDescription>
            {t.finance.paidCount(paid, members.length)} ·{" "}
            {fee ? t.finance.duesInfo(formatMoney(fee, currency, locale)) : t.finance.duesNoFee}
          </CardDescription>
        </div>
        {canManage && unpaidMembers.length > 0 && (
          <FormDialog
            title={t.finance.recordPayment}
            action={recordMembership}
            submitLabel={t.common.save}
            trigger={
              <Button size="sm">
                <Plus /> {t.finance.recordPayment}
              </Button>
            }
          >
            <DuesFields members={unpaidMembers} year={year} feeCents={fee} currency={currency} />
          </FormDialog>
        )}
      </CardHeader>
      <CardContent>
        <Progress value={pct(paid, members.length)} color="emerald" className="mb-5" />
        <ul className="grid gap-x-6 sm:grid-cols-2">
          {members.map((m) => {
            const ms = memberships.find((x) => x.userId === m.id);
            return (
              <li key={m.id} className="flex items-center gap-3 border-b py-2.5">
                <Avatar name={m.name} color={m.avatarColor} size="sm" />
                <Link href={`/members/${m.id}`} className="min-w-0 flex-1 truncate text-sm hover:underline">
                  {m.name}
                </Link>
                {ms ? (
                  <>
                    <span className="hidden text-xs text-muted-foreground sm:inline">
                      {formatDate(ms.paidAt, locale, { day: "numeric", month: "short" })}
                    </span>
                    <Badge color="emerald" dot>
                      {formatMoney(ms.amountCents, currency, locale)}
                    </Badge>
                    {canManage && (
                      <RowActions
                        items={[
                          {
                            kind: "confirm",
                            label: t.common.delete,
                            icon: <Trash2 />,
                            title: t.common.confirmDelete,
                            action: deleteMembership.bind(null, ms.id),
                            destructive: true,
                          },
                        ]}
                      />
                    )}
                  </>
                ) : canManage ? (
                  <FormDialog
                    title={`${t.finance.recordPayment} — ${m.name}`}
                    action={recordMembership}
                    submitLabel={t.common.save}
                    trigger={
                      <Button size="xs" variant="soft">
                        {t.members.recordDues}
                      </Button>
                    }
                  >
                    <DuesFields members={[m]} userId={m.id} year={year} feeCents={fee} currency={currency} />
                  </FormDialog>
                ) : (
                  <Badge color="amber" dot>
                    {t.members.duesUnpaid}
                  </Badge>
                )}
              </li>
            );
          })}
        </ul>
      </CardContent>
    </Card>
  );
}
