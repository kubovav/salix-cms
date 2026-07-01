import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import type { FormGroup } from '@angular/forms';
import { FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { QuillModule } from 'ngx-quill';
import type { Block, BlockTypeOption } from '@core/models';
import { BlockService } from '@core/block.service';
import { applyApiViolations, resolveFieldError } from '@core/form-errors';
import { UploadService } from '@core/upload.service';

@Component({
  selector: 'app-block-editor-modal',
  imports: [ReactiveFormsModule, QuillModule],
  templateUrl: './block-editor-modal.html',
})
export class BlockEditorModal {
  private fb = inject(FormBuilder);
  private blockService = inject(BlockService);
  private uploadService = inject(UploadService);
  readonly modal = inject(NgbActiveModal);
  private readonly destroyRef = inject(DestroyRef);

  block: Block | null = null;
  articleId = 0;
  position = 0;
  blockTypes: BlockTypeOption[] = [];

  readonly type = signal<string>('');
  readonly filename = signal<string | null>(null);
  readonly uploading = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  form!: FormGroup;

  readonly anchorControl = new FormControl('', {
    nonNullable: true,
    validators: [Validators.pattern(/^[A-Za-z][A-Za-z0-9_-]*$/)],
  });

  init(): void {
    if (this.block) {
      this.type.set(this.block.type);
      this.filename.set((this.block.data['filename'] as string) ?? null);
      this.anchorControl.setValue(this.block.anchor ?? '');
      this.buildForm(this.block.type, this.block.data);
    }
  }

  selectType(type: string): void {
    this.type.set(type);
    this.filename.set(null);
    this.buildForm(type, {});
  }

  get isEdit(): boolean {
    return this.block !== null;
  }

  get requiresImage(): boolean {
    return this.type() === 'image' || this.type() === 'text_image';
  }

  get allowsImage(): boolean {
    return this.requiresImage || this.type() === 'hero';
  }

  get isRichText(): boolean {
    return this.type() === 'rich_text' || this.type() === 'text_image';
  }

  get imagePreview(): string | null {
    return this.filename() ? `/uploads/images/${this.filename()}` : null;
  }

  private buildForm(type: string, data: Record<string, unknown>): void {
    const s = (key: string) => (data[key] as string) ?? '';
    switch (type) {
      case 'rich_text':
        this.form = this.fb.group({ html: [s('html')] });
        break;
      case 'image':
        this.form = this.fb.group({
          alt: [s('alt'), Validators.required],
          caption: [s('caption')],
          size: [s('size') || 'full'],
          link_full: [!!data['link_full']],
        });
        break;
      case 'hero':
        this.form = this.fb.group({
          heading: [s('heading'), Validators.required],
          subtext: [s('subtext')],
          cta_text: [s('cta_text')],
          cta_url: [s('cta_url')],
          image_alt: [s('image_alt')],
        });
        break;
      case 'text_image':
        this.form = this.fb.group({
          html: [s('html')],
          image_side: [s('image_side') || 'left', Validators.required],
          image_alt: [s('image_alt'), Validators.required],
          size: [s('size') || 'full'],
          link_full: [!!data['link_full']],
        });
        break;
      case 'cta':
        this.form = this.fb.group({
          heading: [s('heading'), Validators.required],
          button_text: [s('button_text'), Validators.required],
          button_url: [s('button_url'), Validators.required],
        });
        break;
      default:
        this.form = this.fb.group({});
    }
  }

  onFileSelected(event: Event): void {
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
          this.filename.set(res.filename);
          this.uploading.set(false);
        },
        error: () => {
          this.error.set('Image upload failed.');
          this.uploading.set(false);
        },
      });
  }

  private readonly fieldMessages: Record<string, Record<string, string>> = {
    heading: { required: 'Heading is required.' },
    button_text: { required: 'Button text is required.' },
    button_url: { required: 'Button URL is required.' },
    alt: { required: 'Alt text is required.' },
    image_alt: { required: 'Image alt text is required.' },
  };

  getError(name: string): string | null {
    return resolveFieldError(this.form.get(name), this.fieldMessages[name]);
  }

  get anchorError(): string | null {
    return this.anchorControl.touched && this.anchorControl.invalid
      ? 'Letters, numbers, hyphens or underscores; must start with a letter.'
      : null;
  }

  save(): void {
    console.log('Saving block...');
    if (this.form.invalid || this.anchorControl.invalid) {
      this.form.markAllAsTouched();
      this.anchorControl.markAsTouched();
      return;
    }
    if (this.requiresImage && !this.filename()) {
      this.error.set('Please upload an image.');
      return;
    }

    const data: Record<string, unknown> = { ...this.form.getRawValue() };
    if (this.allowsImage && this.filename()) {
      data['filename'] = this.filename();
    }

    const payload: Partial<Block> = {
      type: this.type(),
      data,
      anchor: this.anchorControl.value.trim() || null,
    };
    console.log(payload);
    this.saving.set(true);
    this.error.set(null);

    const request =
      this.block && this.block.id
        ? this.blockService.update(this.block.id, payload)
        : this.blockService.create({ ...payload, position: this.position }, this.articleId);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (result) => {
        this.saving.set(false);
        this.modal.close(result);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(
          applyApiViolations(this.form, err, {
            fallback: 'Could not save the block. Please check the fields.',
            mapPath: (path) => (path.startsWith('data.') ? path.slice(5) : path),
            displayFields: ['heading', 'button_text', 'button_url', 'alt', 'image_alt'],
          })
        );
        this.saving.set(false);
      },
    });
  }
}
