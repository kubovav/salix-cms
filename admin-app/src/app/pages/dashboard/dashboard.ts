import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { ArticleService } from '@core/article.service';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink],
  templateUrl: './dashboard.html',
})
export class DashboardComponent implements OnInit {
  private articles = inject(ArticleService);
  private readonly destroyRef = inject(DestroyRef);

  readonly count = signal<number | null>(null);
  readonly published = signal<number | null>(null);

  ngOnInit(): void {
    this.articles
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((items) => {
        this.count.set(items.length);
        this.published.set(items.filter((a) => a.published).length);
      });
  }
}
