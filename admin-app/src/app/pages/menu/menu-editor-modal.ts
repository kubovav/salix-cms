import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { MenuService } from '@core/menu.service';
import { Article, MenuItem } from '@core/models';

@Component({
  selector: 'app-menu-editor-modal',
  imports: [ReactiveFormsModule],
  templateUrl: './menu-editor-modal.html',
})
export class MenuEditorModal {
  private fb = inject(FormBuilder);
  private menu = inject(MenuService);
  readonly modal = inject(NgbActiveModal);
  private readonly destroyRef = inject(DestroyRef);

  /** Provided by opener */
  item: MenuItem | null = null;
  pages: Article[] = [];
  parents: MenuItem[] = [];

  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    label: ['', [Validators.required]],
    menuName: ['main', [Validators.required]],
    page: [''],
    url: [''],
    parent: [''],
    position: [0],
    enabled: [true],
  });

  init(): void {
    if (this.item) {
      this.form.patchValue({
        label: this.item.label,
        menuName: this.item.menuName,
        page: this.iri(this.item.page),
        url: this.item.url ?? '',
        parent: this.iri(this.item.parent),
        position: this.item.position,
        enabled: this.item.enabled,
      });
    }

    this.syncParentState(this.form.controls.menuName.value);
    this.form.controls.menuName.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((menuName) => this.syncParentState(menuName));
  }

  /** Footer items cannot be nested, so disable and clear the parent selector. */
  private syncParentState(menuName: string): void {
    const parent = this.form.controls.parent;
    if (menuName === 'footer') {
      parent.setValue('');
      parent.disable({ emitEvent: false });
    } else {
      parent.enable({ emitEvent: false });
    }
  }

  get isEdit(): boolean {
    return this.item !== null;
  }

  private iri(ref: unknown): string {
    if (!ref) {
      return '';
    }
    if (typeof ref === 'string') {
      return ref;
    }
    const obj = ref as { '@id'?: string; id?: number };
    return obj['@id'] ?? (obj.id != null ? `/api/menu_items/${obj.id}` : '');
  }

  save(): void {
    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    const payload: Partial<MenuItem> = {
      label: v.label,
      menuName: v.menuName,
      url: v.url || null,
      page: v.page || null,
      parent: v.parent || null,
      position: Number(v.position),
      enabled: v.enabled,
    };

    this.saving.set(true);
    this.error.set(null);

    const request =
      this.item && this.item.id
        ? this.menu.update(this.item.id, payload)
        : this.menu.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (result) => {
        this.saving.set(false);
        this.modal.close(result);
      },
      error: () => {
        this.saving.set(false);
        this.error.set('Could not save the menu item.');
      },
    });
  }
}
