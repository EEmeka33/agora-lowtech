"use client";

import { createContext, use } from "react";
import type { Locale } from "../constants";
import type { Dictionary } from "./en";

/*
 * Texts for client components. Each dictionary is a separate file that the
 * browser downloads only for the language it needs: a French speaker used to
 * download the English dictionary too (22 KB compressed, both together).
 */
const loaders: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("./en").then((m) => m.en),
  fr: () => import("./fr").then((m) => m.fr),
};
const loading = new Map<Locale, Promise<Dictionary>>();
function load(locale: Locale) {
  let promise = loading.get(locale);
  if (!promise) {
    promise = loaders[locale]();
    loading.set(locale, promise);
  }
  return promise;
}

const I18nContext = createContext<{ locale: Locale; t: Dictionary } | null>(null);

export function I18nProvider({ locale, children }: { locale: Locale; children: React.ReactNode }) {
  const t = use(load(locale));
  return <I18nContext value={{ locale, t }}>{children}</I18nContext>;
}

function useI18n() {
  const value = use(I18nContext);
  if (!value) throw new Error("useT() and useLocale() need an <I18nProvider>");
  return value;
}

export function useLocale() {
  return useI18n().locale;
}

/** Dictionary for the current locale, usable in client components. */
export function useT() {
  return useI18n().t;
}
