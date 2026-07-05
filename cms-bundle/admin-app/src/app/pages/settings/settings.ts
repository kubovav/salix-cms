import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { SettingsService } from '@core/settings.service';
import { UploadService } from '@core/upload.service';
import { applyApiViolations, resolveFieldError } from '@core/form-errors';
import type { PageOption } from '@core/models';

@Component({
  selector: 'app-settings',
  imports: [ReactiveFormsModule],
  templateUrl: './settings.html',
})
export class SettingsComponent implements OnInit {
  private settingsService = inject(SettingsService);
  private uploadService = inject(UploadService);
  private fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  readonly pages = signal<PageOption[]>([]);
  readonly brandLogo = signal<string | null>(null);
  readonly uploading = signal(false);
  readonly saving = signal(false);
  readonly saved = signal(false);
  readonly error = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    home_page_slug: ['', Validators.required],
    site_name: [''],
    meta_description: ['', [Validators.maxLength(255)]],
  });

  readonly metaDescriptionLength = toSignal(
    this.form.controls.meta_description.valueChanges.pipe(map((v) => v.length)),
    { initialValue: 0 }
  );
  readonly metaDescriptionCountClass = computed(() => {
    const len = this.metaDescriptionLength();
    if (len >= 150 && len <= 160) {
      return 'text-success';
    }
    if (len > 160) {
      return 'text-warning';
    }
    return 'text-muted';
  });

  ngOnInit(): void {
    this.settingsService
      .get()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((s) => {
        this.pages.set(s.available_pages);
        this.brandLogo.set(s.brand_logo);
        this.form.patchValue({
          home_page_slug: s.home_page_slug ?? '',
          site_name: s.site_name ?? '',
          meta_description: s.meta_description ?? '',
        });
      });
  }

  onLogoSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
      return;
    }
    this.uploading.set(true);
    this.error.set(null);
    this.uploadService
      .upload(file)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.brandLogo.set(res.filename);
          this.uploading.set(false);
        },
        error: () => {
          this.error.set('Logo upload failed.');
          this.uploading.set(false);
        },
      });
  }

  removeLogo(): void {
    this.brandLogo.set(null);
  }

  private readonly fieldMessages: Record<string, Record<string, string>> = {
    home_page_slug: { required: 'Please select a home page.' },
    meta_description: { maxlength: 'Keep the meta description under 255 characters.' },
  };

  getError(name: string): string | null {
    return resolveFieldError(this.form.get(name), this.fieldMessages[name]);
  }

  save(): void {
    if (this.form.invalid || this.saving() || this.uploading()) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);
    const { home_page_slug, site_name, meta_description } = this.form.getRawValue();
    this.settingsService
      .update({
        home_page_slug,
        site_name: site_name.trim() || null,
        brand_logo: this.brandLogo(),
        meta_description: meta_description.trim() || null,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (s) => {
          this.pages.set(s.available_pages);
          this.brandLogo.set(s.brand_logo);
          this.form.patchValue({
            home_page_slug: s.home_page_slug ?? '',
            site_name: s.site_name ?? '',
          });
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
