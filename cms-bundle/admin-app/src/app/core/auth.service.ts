import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import { tap } from 'rxjs';
import type { User } from './models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);

  readonly user = signal<User | null>(null);

  login(email: string, password: string): Observable<User> {
    return this.http
      .post<User>('/api/auth/login', { email, password })
      .pipe(tap((user) => this.user.set(user)));
  }

  logout(): Observable<void> {
    return this.http.post<void>('/api/auth/logout', {}).pipe(tap(() => this.user.set(null)));
  }

  me(): Observable<User> {
    return this.http.get<User>('/api/auth/me').pipe(tap((user) => this.user.set(user)));
  }
}
