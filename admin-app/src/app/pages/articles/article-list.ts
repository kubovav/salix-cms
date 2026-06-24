import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ArticleService } from '../../core/article.service';
import { Article } from '../../core/models';

@Component({
  selector: 'app-article-list',
  imports: [RouterLink, DatePipe],
  templateUrl: './article-list.html',
})
export class ArticleListComponent implements OnInit {
  private articles = inject(ArticleService);
  private readonly destroyRef = inject(DestroyRef);

  readonly items = signal<Article[]>([]);
  readonly loading = signal(true);

  ngOnInit(): void {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.articles
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
    this.articles
      .delete(article.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.load());
  }
}
