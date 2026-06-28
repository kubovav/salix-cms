import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { SettingsService } from '@core/settings.service';
import { applyApiViolations, resolveFieldError } from '@core/form-errors';
import type { PageOption } from '@core/models';

@Component({
  selector: 'app-settings',
  imports: [ReactiveFormsModule],
  templateUrl: './settings.html',
})
export class SettingsComponent implements OnInit {
  private settingsService = inject(SettingsService);
  private fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  readonly pages = signal<PageOption[]>([]);
  readonly saving = signal(false);
  readonly saved = signal(false);
  readonly error = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    home_page_slug: ['', Validators.required],
  });

  ngOnInit(): void {
    this.settingsService
      .get()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((s) => {
        this.pages.set(s.available_pages);
        this.form.patchValue({ home_page_slug: s.home_page_slug ?? '' });
      });
  }

  private readonly fieldMessages: Record<string, Record<string, string>> = {
    home_page_slug: { required: 'Please select a home page.' },
  };

  getError(name: string): string | null {
    return resolveFieldError(this.form.get(name), this.fieldMessages[name]);
  }

  save(): void {
    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);
    const slug = this.form.getRawValue().home_page_slug;
    this.settingsService
      .update(slug)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (s) => {
          this.pages.set(s.available_pages);
          this.form.patchValue({ home_page_slug: s.home_page_slug ?? '' });
          this.saving.set(false);
          this.saved.set(true);
        },
        error: (err: HttpErrorResponse) => {
          this.saving.set(false);
          this.error.set(
            applyApiViolations(this.form, err, {
              fallback: 'Could not save settings.',
              displayFields: [],
            })
          );
        },
      });
  }
}
