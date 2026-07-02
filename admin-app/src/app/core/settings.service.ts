import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { Settings, SettingsPatch } from './models';

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private http = inject(HttpClient);
  private base = '/api/admin/settings';

  get(): Observable<Settings> {
    return this.http.get<Settings>(this.base);
  }

  update(patch: SettingsPatch): Observable<Settings> {
    return this.http.patch<Settings>(this.base, patch);
  }
}
