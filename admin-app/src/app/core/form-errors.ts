import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormGroup } from '@angular/forms';

interface ApiViolation {
  propertyPath?: string;
  message?: string;
}

interface ApplyViolationsOptions {
  /** Banner message for a non-validation error or an unexpected response shape. */
  fallback?: string;
  /** Adapts a server `propertyPath` to a form control path (e.g. strips a `data.` prefix). */
  mapPath?: (propertyPath: string) => string | null;
  /**
   * Control paths (after `mapPath`) whose template renders the `server` error. A violation is
   * attached to its control only when listed here; everything else is returned for the banner,
   * so a message is never silently mapped to a field that can't display it. Omit to attach to
   * any matching control (legacy behaviour).
   */
  displayFields?: string[];
}

/**
 * Maps a failed write response onto a reactive form and returns a message for the caller's
 * general error banner (or `null` when every error was attached to a field).
 *
 * - API Platform validation (422) returns a `violations` array: each violation whose
 *   `propertyPath` resolves to a displayable control is attached as a `server` error (shown via
 *   the control's invalid-feedback, cleared automatically when the user edits the field).
 *   Violations that aren't displayable on a field are joined and returned for the banner.
 * - Custom endpoints that return a single `{ error }` string have it returned as-is.
 * - Anything else (network failure, unexpected shape) returns `fallback`.
 */
export function applyApiViolations(
  form: FormGroup,
  err: HttpErrorResponse,
  options: ApplyViolationsOptions = {}
): string | null {
  const {
    fallback = 'Could not save. Please try again.',
    mapPath = (path) => path,
    displayFields,
  } = options;

  const body: { violations?: ApiViolation[]; error?: unknown } = err.error ?? {};
  const violations = Array.isArray(body.violations) ? body.violations : [];

  if (violations.length === 0) {
    return typeof body.error === 'string' ? body.error : fallback;
  }

  const unmatched: string[] = [];
  for (const { propertyPath, message } of violations) {
    if (!message) {
      continue;
    }
    const path = propertyPath ? mapPath(propertyPath) : null;
    const displayable =
      path !== null && (displayFields === undefined || displayFields.includes(path));
    const control = displayable ? form.get(path) : null;
    if (control) {
      control.setErrors({ server: message });
      control.markAsTouched();
    } else {
      unmatched.push(message);
    }
  }

  return unmatched.length > 0 ? unmatched.join(' ') : null;
}

/**
 * Resolves the single error message to show for a touched, invalid control, or `null` when there
 * is nothing to show. A `server` error (set by {@link applyApiViolations}) always wins; otherwise
 * the first matching client error key is looked up in `messages`.
 *
 * Drives both the field's `is-invalid` state (truthy when there's an error) and its message, so a
 * template needs a single `getError('field')` call instead of a separate invalid check plus an
 * inline `errors?.['server'] ?? '…'` fallback.
 */
export function resolveFieldError(
  control: AbstractControl | null,
  messages: Record<string, string> = {}
): string | null {
  if (!control || !control.touched || !control.errors) {
    return null;
  }

  if (typeof control.errors['server'] === 'string') {
    return control.errors['server'];
  }

  for (const key of Object.keys(control.errors)) {
    if (messages[key]) {
      return messages[key];
    }
  }

  return null;
}
