import { Component, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { QuillModule } from 'ngx-quill';
import { Block, BlockTypeOption } from '../../core/models';
import { BlockService } from '../../core/block.service';
import { UploadService } from '../../core/upload.service';

@Component({
  selector: 'app-block-editor',
  imports: [ReactiveFormsModule, QuillModule],
  templateUrl: './block-editor.html',
})
export class BlockEditorComponent {
  private fb = inject(FormBuilder);
  private blocks = inject(BlockService);
  private uploads = inject(UploadService);
  readonly modal = inject(NgbActiveModal);

  /** Provided by the opener */
  block: Block | null = null;
  articleIri = '';
  position = 0;
  blockTypes: BlockTypeOption[] = [];

  readonly type = signal<string>('');
  readonly filename = signal<string | null>(null);
  readonly uploading = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  form!: FormGroup;

  /** Called by the opener after setting inputs. */
  init(): void {
    if (this.block) {
      this.type.set(this.block.type);
      this.filename.set((this.block.data['filename'] as string) ?? null);
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
    this.uploads.upload(file).subscribe({
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

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
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
    };

    this.saving.set(true);
    this.error.set(null);

    const request =
      this.block && this.block.id
        ? this.blocks.update(this.block.id, payload)
        : this.blocks.create({ ...payload, position: this.position, page: this.articleIri });

    request.subscribe({
      next: (result) => {
        this.saving.set(false);
        this.modal.close(result);
      },
      error: () => {
        this.error.set('Could not save the block. Please check the fields.');
        this.saving.set(false);
      },
    });
  }
}
