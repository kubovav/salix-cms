import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { UserService } from '@core/user.service';
import { applyApiViolations, resolveFieldError } from '@core/form-errors';
import type { User } from '@core/models';

@Component({
  selector: 'app-user-editor-modal',
  imports: [ReactiveFormsModule],
  templateUrl: './user-editor-modal.html',
})
export class UserEditorModal {
  private fb = inject(FormBuilder);
  private readonly userService = inject(UserService);
  readonly modal = inject(NgbActiveModal);
  private readonly destroyRef = inject(DestroyRef);

  /** Provided by opener */
  user: User | null = null;

  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  // New users default to admin; non-admin (frontend) users are planned for later.
  readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    name: ['', [Validators.required]],
    admin: [true],
    plainPassword: ['', [Validators.minLength(8)]],
  });

  init(): void {
    if (this.user) {
      this.form.patchValue(this.userService.toFormValue(this.user));
    } else {
      // Password is mandatory only when creating an account.
      this.form.controls.plainPassword.addValidators(Validators.required);
    }
  }

  get isEdit(): boolean {
    return this.user !== null;
  }

  private readonly fieldMessages: Record<string, Record<string, string>> = {
    email: { required: 'Email is required.', email: 'Enter a valid email address.' },
    name: { required: 'Name is required.' },
    plainPassword: {
      required: 'Password is required.',
      minlength: 'Password must be at least 8 characters.',
    },
  };

  getError(name: string): string | null {
    return resolveFieldError(this.form.get(name), this.fieldMessages[name]);
  }

  save(): void {
    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }
    const payload = this.userService.buildPayload(this.form.getRawValue());

    this.saving.set(true);
    this.error.set(null);

    const request =
      this.user && this.user.id
        ? this.userService.update(this.user.id, payload)
        : this.userService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (result) => {
        this.saving.set(false);
        this.modal.close(result);
      },
      error: (err: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(
          applyApiViolations(this.form, err, {
            fallback: 'Could not save the user.',
            displayFields: ['email', 'name', 'plainPassword'],
          })
        );
      },
    });
  }
}
