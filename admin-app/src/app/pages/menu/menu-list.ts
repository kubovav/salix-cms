import { Component, computed, inject, signal } from '@angular/core';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { MenuService } from '../../core/menu.service';
import { ArticleService } from '../../core/article.service';
import { Article, MenuItem, MenuTreeNode } from '../../core/models';
import { MenuEditorComponent } from './menu-editor';

@Component({
  selector: 'app-menu-list',
  imports: [],
  templateUrl: './menu-list.html',
})
export class MenuListComponent {
  private menu = inject(MenuService);
  private articles = inject(ArticleService);
  private modal = inject(NgbModal);

  readonly items = signal<MenuItem[]>([]);
  readonly loading = signal(true);
  private pages: Article[] = [];

  readonly main = computed(() => this.tree('main'));
  readonly footer = computed(() => this.tree('footer'));

  constructor() {
    this.articles.list().subscribe((p) => (this.pages = p));
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.menu.list().subscribe({
      next: (items) => {
        this.items.set(items);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
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
    const ref = this.modal.open(MenuEditorComponent, { size: 'lg' });
    const editor = ref.componentInstance as MenuEditorComponent;
    editor.item = item;
    editor.pages = this.pages;
    editor.parents = this.items().filter((i) => this.parentId(i) === null && i.id !== item?.id);
    editor.init();
    ref.result.then(
      () => this.load(),
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
    this.menu.delete(item.id).subscribe(() => this.load());
  }
}
