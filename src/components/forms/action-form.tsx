"use client";

import * as React from "react";
import { toast } from "@/components/ui/toast";
import { Loader2 } from "lucide-react";
import type { ActionState } from "@/lib/action-utils";
import { Button, type ButtonProps } from "@/components/ui/button";
import { useT } from "@/lib/i18n/client";

export type FormAction = (prev: ActionState, formData: FormData) => Promise<ActionState>;

type FormCtx = { state: ActionState; pending: boolean };
const FormContext = React.createContext<FormCtx>({ state: null, pending: false });

export function useFormCtx() {
  return React.useContext(FormContext);
}

export function useFieldError(name?: string) {
  const { state } = useFormCtx();
  return name ? state?.fieldErrors?.[name] : undefined;
}

/**
 * A <form> bound to a server action. Keeps typed values on validation errors
 * (no automatic reset), shows toasts and exposes errors to its fields.
 */
export function ActionForm({
  action,
  children,
  className,
  resetOnSuccess = false,
  successToast = true,
  errorToast = true,
  onSuccess,
  id,
}: {
  action: FormAction;
  children: React.ReactNode;
  className?: string;
  resetOnSuccess?: boolean;
  successToast?: boolean;
  errorToast?: boolean;
  onSuccess?: (state: NonNullable<ActionState>) => void;
  id?: string;
}) {
  const t = useT();
  const [state, formAction, pending] = React.useActionState(action, null);
  const ref = React.useRef<HTMLFormElement>(null);
  const onSuccessRef = React.useRef(onSuccess);
  React.useEffect(() => {
    onSuccessRef.current = onSuccess;
  });

  React.useEffect(() => {
    if (!state?.ts) return;
    if (state.ok) {
      if (successToast) toast.success(state.message ?? t.common.saved);
      if (resetOnSuccess) ref.current?.reset();
      onSuccessRef.current?.(state);
    } else if (errorToast && state.error && !state.fieldErrors) {
      toast.error(state.error);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state?.ts]);

  return (
    <FormContext value={{ state, pending }}>
      <form
        ref={ref}
        id={id}
        className={className}
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          React.startTransition(() => formAction(fd));
        }}
      >
        {state && !state.ok && state.fieldErrors && state.error && (
          <p className="sr-only" role="alert">
            {state.error}
          </p>
        )}
        {children}
      </form>
    </FormContext>
  );
}

export function SubmitButton({ children, pendingLabel, ...props }: ButtonProps & { pendingLabel?: string }) {
  const { pending } = useFormCtx();
  return (
    <Button type="submit" disabled={pending || props.disabled} {...props}>
      {pending && <Loader2 className="animate-spin" />}
      {pending && pendingLabel ? pendingLabel : children}
    </Button>
  );
}
