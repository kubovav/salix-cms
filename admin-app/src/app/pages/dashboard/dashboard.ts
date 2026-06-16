import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ArticleService } from '../../core/article.service';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink],
  templateUrl: './dashboard.html',
})
export class DashboardComponent {
  private articles = inject(ArticleService);

  readonly count = signal<number | null>(null);
  readonly published = signal<number | null>(null);

  constructor() {
    this.articles.list().subscribe((items) => {
      this.count.set(items.length);
      this.published.set(items.filter((a) => a.published).length);
    });
  }
}
