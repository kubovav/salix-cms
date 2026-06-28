import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { Settings } from './models';

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private http = inject(HttpClient);
  private base = '/api/admin/settings';

  get(): Observable<Settings> {
    return this.http.get<Settings>(this.base);
  }

  update(homePageSlug: string | null): Observable<Settings> {
    return this.http.put<Settings>(this.base, { home_page_slug: homePageSlug });
  }
}
