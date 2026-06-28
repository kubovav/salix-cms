import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { MenuService } from '@core/menu.service';
import { ArticleService } from '@core/article.service';
import type { Article, MenuItem, MenuTreeNode } from '@core/models';
import { MenuEditorModal } from './menu-editor-modal';

@Component({
  selector: 'app-menu-list',
  imports: [],
  templateUrl: './menu-list.html',
})
export class MenuListComponent implements OnInit {
  private menuService = inject(MenuService);
  private articleService = inject(ArticleService);
  private modal = inject(NgbModal);
  private readonly destroyRef = inject(DestroyRef);

  readonly items = signal<MenuItem[]>([]);
  private pages: Article[] = [];

  private readonly loadingMenuItems = signal(true);
  private readonly loadingPages = signal(true);
  readonly loading = computed(() => this.loadingMenuItems() || this.loadingPages());

  readonly main = computed(() => this.tree('main'));
  readonly footer = computed(() => this.tree('footer'));

  ngOnInit(): void {
    this.loadMenuItems();
    this.loadPages();
  }

  private loadMenuItems(): void {
    this.loadingMenuItems.set(true);
    this.menuService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.items.set(items);
          this.loadingMenuItems.set(false);
        },
        error: () => this.loadingMenuItems.set(false),
      });
  }

  private loadPages(): void {
    this.loadingPages.set(true);
    this.articleService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (pages) => {
          this.pages = pages;
          this.loadingPages.set(false);
        },
        error: () => this.loadingPages.set(false),
      });
  }

  private parentId(item: MenuItem): number | null {
    const p = item.parent;
    if (!p || typeof p === 'string') {
      return null;
    }
    return (p as { id?: number }).id ?? null;
  }

  private tree(menuName: string): MenuTreeNode[] {
    const scoped = this.items().filter((i) => i.menuName === menuName);
    const roots = scoped
      .filter((i) => this.parentId(i) === null)
      .map((i) => this.toNode(i, scoped));
    return roots.sort((a, b) => a.position - b.position);
  }

  private toNode(item: MenuItem, scoped: MenuItem[]): MenuTreeNode {
    const children = scoped
      .filter((c) => this.parentId(c) === item.id)
      .map((c) => this.toNode(c, scoped))
      .sort((a, b) => a.position - b.position);
    return { ...item, treeChildren: children };
  }

  private openEditor(item: MenuItem | null): void {
    const ref = this.modal.open(MenuEditorModal, { size: 'lg' });
    const editor = ref.componentInstance as MenuEditorModal;
    editor.item = item;
    editor.pages = this.pages;
    editor.parents = this.items().filter((i) => this.parentId(i) === null && i.id !== item?.id);
    editor.init();
    ref.result.then(
      () => this.loadMenuItems(),
      () => undefined
    );
  }

  add(): void {
    this.openEditor(null);
  }

  edit(item: MenuItem): void {
    this.openEditor(item);
  }

  remove(item: MenuItem): void {
    if (!item.id || !confirm(`Delete menu item "${item.label}"?`)) {
      return;
    }
    this.menuService
      .delete(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadMenuItems());
  }
}
