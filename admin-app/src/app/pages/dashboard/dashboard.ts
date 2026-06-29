import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ArticleService } from '@core/article.service';
import type { Article } from '@core/models';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, DatePipe],
  templateUrl: './dashboard.html',
})
export class DashboardComponent implements OnInit {
  private articleService = inject(ArticleService);
  private readonly destroyRef = inject(DestroyRef);

  readonly articles = signal<Article[] | null>(null);

  readonly count = computed(() => this.articles()?.length ?? null);
  readonly published = computed(
    () => this.articles()?.filter((a) => a.published).length ?? null,
  );
  readonly drafts = computed(
    () => this.articles()?.filter((a) => !a.published).length ?? null,
  );

  readonly recent = computed(() =>
    [...(this.articles() ?? [])]
      .sort((a, b) => (b.updatedAt ?? '').localeCompare(a.updatedAt ?? ''))
      .slice(0, 5),
  );

  ngOnInit(): void {
    this.articleService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((items) => this.articles.set(items));
  }
}
