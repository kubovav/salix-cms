import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import { shareReplay } from 'rxjs';
import type { Meta } from './models';

@Injectable({ providedIn: 'root' })
export class MetaService {
  private http = inject(HttpClient);
  private cache?: Observable<Meta>;

  get(): Observable<Meta> {
    if (!this.cache) {
      this.cache = this.http.get<Meta>('/api/admin/meta').pipe(shareReplay(1));
    }
    return this.cache;
  }
}
