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
  private readonly menuService = inject(MenuService);
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
      this.form.patchValue(this.menuService.toFormValue(this.item));
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

  save(): void {
    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }
    const payload = this.menuService.buildPayload(this.form.getRawValue());

    this.saving.set(true);
    this.error.set(null);

    const request =
      this.item && this.item.id
        ? this.menuService.update(this.item.id, payload)
        : this.menuService.create(payload);

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
