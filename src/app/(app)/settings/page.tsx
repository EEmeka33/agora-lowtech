import type { Metadata } from "next";
import { Database, FileJson, FileSpreadsheet, FolderOpen } from "lucide-react";
import { db as globalDb } from "@/lib/db";
import { requireContext } from "@/lib/context";
import { getI18n } from "@/lib/i18n/server";
import { isAdmin } from "@/lib/permissions";
import { mailEnabled } from "@/lib/mail";
import { databaseFile, uploadDir } from "@/lib/uploads";
import { updateMember } from "@/actions/members";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { PageHeader, TabLinks } from "@/components/ui/page";
import { ThemePicker } from "@/components/layout/theme";
import { memberValues } from "@/components/members/member-values";
import { LanguagePicker, PasswordForm, ProfileForm } from "@/components/settings/profile-forms";
import { AssociationForm } from "@/components/settings/association-form";
import {
  AssociationList,
  CalendarLink,
  DeleteAccountForm,
  NotificationSettingsForm,
  SessionList,
} from "@/components/settings/account-forms";

export const metadata: Metadata = { title: "Settings" };

const TABS = ["profile", "security", "notifications", "appearance", "association", "data"] as const;

export default async function SettingsPage(props: PageProps<"/settings">) {
  const { user, association, associations } = await requireContext();
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const admin = isAdmin(user);
  const tabs = TABS.filter((k) => admin || (k !== "association" && k !== "data"));
  const tab = tabs.includes(sp.tab as never) ? (sp.tab as (typeof TABS)[number]) : "profile";
  const me = await globalDb.user.findUniqueOrThrow({
    where: { id: user.id },
    include: { sessions: { where: { expiresAt: { gt: new Date() } }, orderBy: { lastSeenAt: "desc" } } },
  });

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title={t.settings.title} description={t.settings.subtitle} />
      <TabLinks
        items={tabs.map((k) => ({
          href: k === "profile" ? "/settings" : `/settings?tab=${k}`,
          label: t.settings.tabs[k],
          active: tab === k,
        }))}
      />

      {tab === "profile" && (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t.settings.profileTitle}</CardTitle>
              <CardDescription>{t.settings.profileHint}</CardDescription>
            </div>
          </CardHeader>
          <CardContent>
            <ProfileForm
              action={updateMember.bind(null, user.id)}
              member={memberValues({
                ...me,
                title: user.title,
                role: user.role,
                boardPosition: user.boardPosition,
                joinedAt: null,
              })}
            />
          </CardContent>
        </Card>
      )}

      {tab === "security" && (
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{t.settings.securityTitle}</CardTitle>
            </CardHeader>
            <CardContent>
              <PasswordForm />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <div>
                <CardTitle>{t.settings.sessionsTitle}</CardTitle>
                <CardDescription>{t.settings.sessionsHint}</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <SessionList
                sessions={me.sessions.map((s) => ({
                  id: s.id,
                  userAgent: s.userAgent,
                  ip: s.ip,
                  lastSeenAt: s.lastSeenAt,
                  current: s.id === user.sessionId,
                }))}
              />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t.settings.associationsTitle}</CardTitle>
            </CardHeader>
            <CardContent>
              <AssociationList associations={associations} />
            </CardContent>
          </Card>
          <Card className="border-destructive/30">
            <CardHeader>
              <div>
                <CardTitle className="text-destructive">{t.settings.deleteTitle}</CardTitle>
                <CardDescription>{t.settings.deleteText}</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <DeleteAccountForm />
            </CardContent>
          </Card>
        </div>
      )}

      {tab === "notifications" && (
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <div>
                <CardTitle>{t.settings.notificationsTitle}</CardTitle>
                <CardDescription>{t.settings.notificationsHint}</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <NotificationSettingsForm
                emailMode={!me.emailNotifications ? "NEVER" : me.emailDigest ? "DAILY" : "INSTANT"}
                mailEnabled={mailEnabled()}
              />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <div>
                <CardTitle>{t.settings.calendarTitle}</CardTitle>
                <CardDescription>{t.settings.calendarHint}</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <CalendarLink hasLink={!!me.calendarTokenHash} />
            </CardContent>
          </Card>
        </div>
      )}

      {tab === "appearance" && (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t.settings.appearanceTitle}</CardTitle>
              <CardDescription>{t.settings.appearanceHint}</CardDescription>
            </div>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="space-y-2">
              <p className="text-sm font-medium">{t.common.theme}</p>
              <ThemePicker />
            </div>
            <div className="space-y-2">
              <p className="text-sm font-medium">{t.common.language}</p>
              <LanguagePicker />
            </div>
          </CardContent>
        </Card>
      )}

      {tab === "association" && admin && (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t.settings.associationTitle}</CardTitle>
              <CardDescription>{t.settings.associationHint}</CardDescription>
            </div>
          </CardHeader>
          <CardContent>
            <AssociationForm
              value={{
                name: association.name,
                shortName: association.shortName,
                tagline: association.tagline,
                purpose: association.purpose,
                legalForm: association.legalForm,
                registrationNumber: association.registrationNumber,
                siret: association.siret,
                foundedAt: association.foundedAt,
                email: association.email,
                phone: association.phone,
                website: association.website,
                address: association.address,
                postalCode: association.postalCode,
                city: association.city,
                country: association.country,
                accentColor: association.accentColor,
                locale: association.locale,
                currency: association.currency,
                membershipFeeCents: association.membershipFeeCents,
                volunteerHourCents: association.volunteerHourCents,
                fiscalYearStartMonth: association.fiscalYearStartMonth,
              }}
            />
          </CardContent>
        </Card>
      )}

      {tab === "data" && admin && (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t.settings.dataTitle}</CardTitle>
              <CardDescription>{t.settings.dataHint}</CardDescription>
            </div>
          </CardHeader>
          <CardContent className="space-y-5">
            <div className="flex flex-wrap gap-2">
              <a href="/api/export/backup" className={buttonVariants({ variant: "outline" })}>
                <FileJson /> {t.settings.exportJson}
              </a>
              <a href="/api/export/transactions" className={buttonVariants({ variant: "outline" })}>
                <FileSpreadsheet /> {t.settings.exportCsv}
              </a>
              <a href="/api/export/members" className={buttonVariants({ variant: "outline" })}>
                <FileSpreadsheet /> {t.members.exportCsv}
              </a>
            </div>
            <div className="rounded-xl bg-muted/60 p-4 text-sm">
              <p className="mb-2 flex items-center gap-2 font-medium">
                <Database className="size-4" /> {t.settings.backupHint}
              </p>
              <code className="block rounded-lg bg-card px-3 py-2 font-mono text-xs break-all">{databaseFile()}</code>
              <p className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                <FolderOpen className="size-3.5" /> {uploadDir()}
              </p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
