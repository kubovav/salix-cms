import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { SettingsService } from '../../core/settings.service';
import { PageOption } from '../../core/models';

@Component({
  selector: 'app-settings',
  imports: [ReactiveFormsModule],
  templateUrl: './settings.html',
})
export class SettingsComponent implements OnInit {
  private settings = inject(SettingsService);
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
    this.settings
      .get()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((s) => {
        this.pages.set(s.available_pages);
        this.form.patchValue({ home_page_slug: s.home_page_slug ?? '' });
      });
  }

  save(): void {
    if (this.saving() || this.form.invalid) {
      return;
    }
    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);
    const slug = this.form.getRawValue().home_page_slug;
    this.settings
      .update(slug)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (s) => {
          this.pages.set(s.available_pages);
          this.form.patchValue({ home_page_slug: s.home_page_slug ?? '' });
          this.saving.set(false);
          this.saved.set(true);
        },
        error: () => {
          this.saving.set(false);
          this.error.set('Could not save settings.');
        },
      });
  }
}
