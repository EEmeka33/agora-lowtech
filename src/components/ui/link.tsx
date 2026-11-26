"use client";

import NextLink from "next/link";
import { useRouter } from "next/navigation";
import type { ComponentProps } from "react";

/**
 * A link that prefetches its page when someone shows the intention of opening it
 * (pointer over it, keyboard focus, finger on it), instead of as soon as it
 * appears on the screen.
 *
 * Next.js prefetches every visible link by default: 30 to 40 requests and
 * 55 to 80 KB per page, mostly for pages nobody opens. Navigations stay instant:
 * the time between pointing at a link and clicking it is enough to prefetch it.
 */
export default function Link({
  href,
  prefetch,
  onMouseEnter,
  onTouchStart,
  onFocus,
  ...props
}: ComponentProps<typeof NextLink>) {
  const router = useRouter();
  const warm = () => {
    if (prefetch !== false && typeof href === "string" && href.startsWith("/")) router.prefetch(href);
  };
  return (
    <NextLink
      href={href}
      prefetch={false}
      onMouseEnter={(e) => {
        warm();
        onMouseEnter?.(e);
      }}
      onTouchStart={(e) => {
        warm();
        onTouchStart?.(e);
      }}
      onFocus={(e) => {
        warm();
        onFocus?.(e);
      }}
      {...props}
    />
  );
}
