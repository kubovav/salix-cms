import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import type { User } from './models';

/** Raw value of the user editor form. */
export interface UserFormValue {
  email: string;
  name: string;
  /** True when the user has ROLE_ADMIN. Frontend (non-admin) users are planned. */
  admin: boolean;
  /** Empty on edit means "keep the current password". */
  plainPassword: string;
}

@Injectable({ providedIn: 'root' })
export class UserService {
  private http = inject(HttpClient);
  private base = '/api/users';

  /** Map a stored user to the raw form value the editor binds to. */
  toFormValue(user: User): UserFormValue {
    return {
      email: user.email,
      name: user.name ?? '',
      admin: (user.roles ?? []).includes('ROLE_ADMIN'),
      plainPassword: '',
    };
  }

  /** Build the write payload from raw form values (admin flag → roles, blank password omitted). */
  buildPayload(value: UserFormValue): Partial<User> {
    const payload: Partial<User> = {
      email: value.email,
      name: value.name,
      roles: value.admin ? ['ROLE_ADMIN'] : [],
    };
    if (value.plainPassword) {
      payload.plainPassword = value.plainPassword;
    }
    return payload;
  }

  list(): Observable<User[]> {
    return this.http.get<User[]>(this.base);
  }

  get(id: number): Observable<User> {
    return this.http.get<User>(`${this.base}/${id}`);
  }

  create(user: Partial<User>): Observable<User> {
    return this.http.post<User>(this.base, user);
  }

  update(id: number, user: Partial<User>): Observable<User> {
    return this.http.patch<User>(`${this.base}/${id}`, user);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
