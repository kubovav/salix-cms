import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { Article } from './models';

@Injectable({ providedIn: 'root' })
export class ArticleService {
  private http = inject(HttpClient);
  private base = '/api/articles';

  list(): Observable<Article[]> {
    return this.http.get<Article[]>(this.base);
  }

  get(id: number): Observable<Article> {
    return this.http.get<Article>(`${this.base}/${id}`);
  }

  create(article: Partial<Article>): Observable<Article> {
    return this.http.post<Article>(this.base, article);
  }

  update(id: number, article: Partial<Article>): Observable<Article> {
    return this.http.patch<Article>(`${this.base}/${id}`, article);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }

  reorderBlocks(id: number, ids: number[]): Observable<void> {
    return this.http.post<void>(`/api/admin/articles/${id}/reorder-blocks`, { ids });
  }
}
