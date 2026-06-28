import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { MenuItem } from './models';
import { idFromRef } from './iri';

/** Raw value of the menu editor form: page/parent are entity ids (or '' for none). */
export interface MenuItemFormValue {
  label: string;
  menuName: string;
  page: string;
  url: string;
  parent: string;
  position: number;
  enabled: boolean;
}

@Injectable({ providedIn: 'root' })
export class MenuService {
  private http = inject(HttpClient);
  private base = '/api/menu_items';
  private articleBase = '/api/articles';

  /** Map a stored menu item (IRI/object refs) to the raw form value the editor binds to. */
  toFormValue(item: MenuItem): MenuItemFormValue {
    return {
      label: item.label,
      menuName: item.menuName,
      page: idFromRef(item.page),
      url: item.url ?? '',
      parent: idFromRef(item.parent),
      position: item.position,
      enabled: item.enabled,
    };
  }

  /** Build the write payload from raw form values (ids → IRIs, '' → null). */
  buildPayload(value: MenuItemFormValue): Partial<MenuItem> {
    return {
      label: value.label,
      menuName: value.menuName,
      url: value.url || null,
      page: value.page ? `${this.articleBase}/${value.page}` : null,
      parent: value.parent ? `${this.base}/${value.parent}` : null,
      position: Number(value.position),
      enabled: value.enabled,
    };
  }

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
