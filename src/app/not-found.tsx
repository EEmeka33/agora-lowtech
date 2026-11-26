import Link from "@/components/ui/link";

export default function NotFound() {
  return (
    <main className="flex min-h-dvh flex-col items-center justify-center gap-3 px-6 text-center">
      <p className="text-6xl font-semibold tracking-tight text-primary">404</p>
      <h1 className="text-xl font-semibold">Page not found · Page introuvable</h1>
      <Link href="/" className="mt-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
        Agora →
      </Link>
    </main>
  );
}
