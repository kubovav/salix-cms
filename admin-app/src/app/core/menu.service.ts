import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { MenuItem } from './models';

@Injectable({ providedIn: 'root' })
export class MenuService {
  private http = inject(HttpClient);
  private base = '/api/menu_items';

  list(): Observable<MenuItem[]> {
    return this.http.get<MenuItem[]>(this.base);
  }

  get(id: number): Observable<MenuItem> {
    return this.http.get<MenuItem>(`${this.base}/${id}`);
  }

  create(item: Partial<MenuItem>): Observable<MenuItem> {
    return this.http.post<MenuItem>(this.base, item);
  }

  update(id: number, item: Partial<MenuItem>): Observable<MenuItem> {
    return this.http.put<MenuItem>(`${this.base}/${id}`, item);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
