import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Block } from './models';

@Injectable({ providedIn: 'root' })
export class BlockService {
  private http = inject(HttpClient);
  private base = '/api/blocks';

  create(block: Partial<Block>): Observable<Block> {
    return this.http.post<Block>(this.base, block);
  }

  update(id: number, block: Partial<Block>): Observable<Block> {
    return this.http.put<Block>(`${this.base}/${id}`, block);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
