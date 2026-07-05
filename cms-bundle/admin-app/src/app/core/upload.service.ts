import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { UploadResult } from './models';

@Injectable({ providedIn: 'root' })
export class UploadService {
  private http = inject(HttpClient);

  upload(file: File): Observable<UploadResult> {
    const form = new FormData();
    form.append('file', file);
    return this.http.post<UploadResult>('/api/admin/uploads', form);
  }
}
