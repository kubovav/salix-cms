import { Component, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ArticleService } from '../../core/article.service';
import { Article } from '../../core/models';

@Component({
  selector: 'app-article-list',
  imports: [RouterLink, DatePipe],
  templateUrl: './article-list.html',
})
export class ArticleListComponent {
  private articles = inject(ArticleService);

  readonly items = signal<Article[]>([]);
  readonly loading = signal(true);

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.articles.list().subscribe({
      next: (items) => {
        this.items.set(items);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  remove(article: Article): void {
    if (!article.id || !confirm(`Delete article "${article.title}"?`)) {
      return;
    }
    this.articles.delete(article.id).subscribe(() => this.load());
  }
}
