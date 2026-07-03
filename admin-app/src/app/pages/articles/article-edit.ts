import { Component, DestroyRef, computed, inject, input, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { applyApiViolations, resolveFieldError } from '@core/form-errors';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import type { CdkDragDrop } from '@angular/cdk/drag-drop';
import { DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { ArticleService } from '@core/article.service';
import { BlockService } from '@core/block.service';
import { MetaService } from '@core/meta.service';
import type { Article, Block, BlockTypeOption } from '@core/models';
import { BlockEditorModal } from './block-editor-modal';

@Component({
  selector: 'app-article-edit',
  imports: [ReactiveFormsModule, RouterLink, DragDropModule],
  templateUrl: './article-edit.html',
})
export class ArticleEditComponent implements OnInit {
  private fb = inject(FormBuilder);
  private articleService = inject(ArticleService);
  private blockService = inject(BlockService);
  private metaService = inject(MetaService);
  private modal = inject(NgbModal);
  private router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly id = input<string>();

  readonly article = signal<Article | null>(null);
  readonly blocks = signal<Block[]>([]);
  readonly heroBlocks = computed(() => this.blocks().filter((b) => b.type === 'hero'));
  readonly saving = signal(false);
  readonly settingsError = signal<string | null>(null);
  readonly saved = signal(false);

  private blockTypes: BlockTypeOption[] = [];

  readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required]],
    slug: ['', [Validators.pattern(/^[a-z0-9]+(?:-[a-z0-9]+)*$/)]],
    published: [false],
  });

  ngOnInit(): void {
    this.metaService
      .get()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((m) => (this.blockTypes = m.blockTypes));
    const id = this.id();
    if (id) {
      this.loadArticle(Number(id));
    }
  }

  get isNew(): boolean {
    return !this.id();
  }

  private readonly fieldMessages: Record<string, Record<string, string>> = {
    title: { required: 'Title is required.' },
    slug: { pattern: 'Use lowercase letters, numbers, and hyphens only.' },
  };

  getError(name: string): string | null {
    return resolveFieldError(this.form.get(name), this.fieldMessages[name]);
  }

  private loadArticle(id: number): void {
    this.articleService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((article) => {
        this.article.set(article);
        this.form.patchValue({
          title: article.title,
          slug: article.slug,
          published: article.published,
        });
        this.blocks.set(this.sortBlocks(article.blocks ?? []));
      });
  }

  private reloadBlocks(): void {
    const id = this.article()?.id;
    if (id) {
      this.articleService
        .get(id)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe((article) => {
          this.article.set(article);
          this.blocks.set(this.sortBlocks(article.blocks ?? []));
        });
    }
  }

  private sortBlocks(blocks: Block[]): Block[] {
    return [...blocks].sort(
      (a, b) => Number(b.type === 'hero') - Number(a.type === 'hero') || a.position - b.position
    );
  }

  saveSettings(): void {
    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.settingsError.set(null);
    this.saved.set(false);
    const payload = this.form.getRawValue();
    const existing = this.article();

    const request = existing?.id
      ? this.articleService.update(existing.id, payload)
      : this.articleService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (article) => {
        this.saving.set(false);
        this.saved.set(true);
        if (this.isNew && article.id) {
          this.router.navigate(['/articles', article.id]);
        } else {
          this.article.set(article);
        }
      },
      error: (err: HttpErrorResponse) => {
        this.saving.set(false);
        this.settingsError.set(
          applyApiViolations(this.form, err, {
            fallback: 'Could not save. The slug may already be in use or invalid.',
            displayFields: ['title', 'slug'],
          })
        );
      },
    });
  }

  addBlock(): void {
    const articleId = this.article()?.id;
    if (!articleId) {
      return;
    }
    const ref = this.modal.open(BlockEditorModal, { size: 'lg' });
    const editor = ref.componentInstance as BlockEditorModal;
    editor.articleId = articleId;
    editor.position = this.blocks().length;
    editor.blockTypes = this.blockTypes;
    editor.init();
    ref.result.then(
      () => this.reloadBlocks(),
      () => undefined
    );
  }

  editBlock(block: Block): void {
    const articleId = this.article()?.id;
    if (!articleId) {
      return;
    }
    const ref = this.modal.open(BlockEditorModal, { size: 'lg' });
    const editor = ref.componentInstance as BlockEditorModal;
    editor.block = block;
    editor.articleId = articleId;
    editor.blockTypes = this.blockTypes;
    editor.init();
    ref.result.then(
      () => this.reloadBlocks(),
      () => undefined
    );
  }

  deleteBlock(block: Block): void {
    if (!block.id || !confirm('Delete this block?')) {
      return;
    }
    this.blockService
      .delete(block.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.reloadBlocks());
  }

  drop(event: CdkDragDrop<Block[]>): void {
    const heroCount = this.heroBlocks().length;
    const target = Math.max(event.currentIndex, heroCount);
    if (event.previousIndex === target) {
      return;
    }
    const current = [...this.blocks()];
    moveItemInArray(current, event.previousIndex, target);
    this.blocks.set(current);

    const articleId = this.article()?.id;
    const ids = current.map((b) => b.id).filter((id): id is number => id != null);
    if (articleId) {
      this.articleService
        .reorderBlocks(articleId, ids)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe();
    }
  }

  typeLabel(type: string): string {
    return this.blockTypes.find((t) => t.value === type)?.label ?? type;
  }

  preview(block: Block): string {
    const d = block.data;
    switch (block.type) {
      case 'rich_text':
      case 'text_image':
        return this.stripHtml(block.renderedHtml ?? '');
      case 'hero':
        return (d['heading'] as string) ?? '';
      case 'image':
        return (d['alt'] as string) ?? '';
      case 'cta':
        return `${(d['heading'] as string) ?? ''} → ${(d['button_text'] as string) ?? ''}`;
      case 'pricing_table':
        return ((d['plans'] as { name?: string }[]) ?? [])
          .map((p) => p.name)
          .filter(Boolean)
          .join(' · ');
      default:
        return '';
    }
  }

  private stripHtml(html: string): string {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return (tmp.textContent ?? '').slice(0, 80);
  }
}
