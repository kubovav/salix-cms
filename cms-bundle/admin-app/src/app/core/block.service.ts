import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { Block } from './models';

@Injectable({ providedIn: 'root' })
export class BlockService {
  private http = inject(HttpClient);
  private base = '/api/blocks';
  private articleBase = '/api/articles';

  /** Create a block on the given article; the owning-article IRI is built here from the raw id. */
  create(block: Partial<Block>, articleId: number): Observable<Block> {
    return this.http.post<Block>(this.base, { ...block, page: `${this.articleBase}/${articleId}` });
  }

  update(id: number, block: Partial<Block>): Observable<Block> {
    return this.http.patch<Block>(`${this.base}/${id}`, block);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
