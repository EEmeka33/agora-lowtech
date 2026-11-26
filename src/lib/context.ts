import "server-only";
import { cache } from "react";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { db } from "./db";
import { getAccount, type Account } from "./auth";
import { tenantDb, type TenantDb } from "./tenant";
import { ASSOCIATION_COOKIE } from "./session-cookie";
import { scheduleMaintenance } from "./maintenance";
import type { Role } from "./constants";
import type { Association } from "@/generated/prisma/client";

/** The signed-in person *inside* the current association. */
export type CurrentUser = Account & {
  role: Role;
  boardPosition: string | null;
  title: string | null;
  memberId: string;
};

export type Context = {
  user: CurrentUser;
  association: Association;
  /** Prisma client restricted to the current association. */
  db: TenantDb;
  /** All associations the person belongs to (for the switcher). */
  associations: { id: string; name: string; role: string }[];
};

/** Current account + association, or null when signed out or without association. */
export const getContext = cache(async (): Promise<Context | null> => {
  const account = await getAccount();
  if (!account) return null;
  const memberships = await db.member.findMany({
    where: { userId: account.id, status: "ACTIVE" },
    include: { association: true },
    orderBy: { createdAt: "asc" },
  });
  if (memberships.length === 0) return null;
  const wanted = (await cookies()).get(ASSOCIATION_COOKIE)?.value;
  const m = memberships.find((x) => x.associationId === wanted) ?? memberships[0];
  return {
    user: {
      ...account,
      role: m.role as Role,
      boardPosition: m.boardPosition,
      title: m.title,
      memberId: m.id,
    },
    association: m.association,
    db: tenantDb(m.associationId),
    associations: memberships.map((x) => ({ id: x.associationId, name: x.association.name, role: x.role })),
  };
});

/** Use in pages: redirects to /login or /welcome when needed. */
export async function requireContext(): Promise<Context> {
  const account = await getAccount();
  if (!account) {
    const count = await db.user.count();
    redirect(count === 0 ? "/signup" : "/login");
  }
  const ctx = await getContext();
  if (!ctx) redirect("/welcome");
  await scheduleMaintenance();
  return ctx;
}
