import { Component, DestroyRef, inject, input, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { ArticleService } from '../../core/article.service';
import { BlockService } from '../../core/block.service';
import { MetaService } from '../../core/meta.service';
import { Article, Block, BlockTypeOption } from '../../core/models';
import { BlockEditorComponent } from './block-editor';

@Component({
  selector: 'app-article-edit',
  imports: [ReactiveFormsModule, RouterLink, DragDropModule],
  templateUrl: './article-edit.html',
})
export class ArticleEditComponent implements OnInit {
  private fb = inject(FormBuilder);
  private articles = inject(ArticleService);
  private blockService = inject(BlockService);
  private meta = inject(MetaService);
  private modal = inject(NgbModal);
  private router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  /** Route param, undefined for "new". */
  readonly id = input<string>();

  readonly article = signal<Article | null>(null);
  readonly blocks = signal<Block[]>([]);
  readonly saving = signal(false);
  readonly settingsError = signal<string | null>(null);
  readonly saved = signal(false);

  private blockTypes: BlockTypeOption[] = [];

  readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required]],
    slug: ['', [Validators.required, Validators.pattern(/^[a-z0-9]+(?:-[a-z0-9]+)*$/)]],
    published: [false],
  });

  ngOnInit(): void {
    this.meta
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

  private loadArticle(id: number): void {
    this.articles
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((article) => {
        this.article.set(article);
        this.form.patchValue({
          title: article.title,
          slug: article.slug,
          published: article.published,
        });
        this.blocks.set([...(article.blocks ?? [])].sort((a, b) => a.position - b.position));
      });
  }

  private reloadBlocks(): void {
    const id = this.article()?.id;
    if (id) {
      this.articles
        .get(id)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe((article) => {
          this.article.set(article);
          this.blocks.set([...(article.blocks ?? [])].sort((a, b) => a.position - b.position));
        });
    }
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
      ? this.articles.update(existing.id, payload)
      : this.articles.create(payload);

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
      error: () => {
        this.saving.set(false);
        this.settingsError.set('Could not save. The slug may already be in use or invalid.');
      },
    });
  }

  addBlock(): void {
    const article = this.article();
    if (!article?.['@id']) {
      return;
    }
    const ref = this.modal.open(BlockEditorComponent, { size: 'lg' });
    const editor = ref.componentInstance as BlockEditorComponent;
    editor.articleIri = article['@id'];
    editor.position = this.blocks().length;
    editor.blockTypes = this.blockTypes;
    editor.init();
    ref.result.then(
      () => this.reloadBlocks(),
      () => undefined
    );
  }

  editBlock(block: Block): void {
    const article = this.article();
    if (!article?.['@id']) {
      return;
    }
    const ref = this.modal.open(BlockEditorComponent, { size: 'lg' });
    const editor = ref.componentInstance as BlockEditorComponent;
    editor.block = block;
    editor.articleIri = article['@id'];
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
    if (event.previousIndex === event.currentIndex) {
      return;
    }
    const current = [...this.blocks()];
    moveItemInArray(current, event.previousIndex, event.currentIndex);
    this.blocks.set(current);

    const articleId = this.article()?.id;
    const ids = current.map((b) => b.id).filter((id): id is number => id != null);
    if (articleId) {
      this.articles
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
        return this.stripHtml((d['html'] as string) ?? '');
      case 'hero':
        return (d['heading'] as string) ?? '';
      case 'image':
        return (d['alt'] as string) ?? '';
      case 'cta':
        return `${(d['heading'] as string) ?? ''} → ${(d['button_text'] as string) ?? ''}`;
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
