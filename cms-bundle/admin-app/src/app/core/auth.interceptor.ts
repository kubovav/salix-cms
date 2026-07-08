import type { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

/**
 * Sends the Symfony session cookie with every request (same-origin),
 * asks the API for JSON, and redirects to the login page on 401.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);

  const setHeaders: Record<string, string> = {};
  if (!req.headers.has('Accept')) {
    setHeaders['Accept'] = 'application/json';
  }

  const apiReq = req.clone({
    withCredentials: true,
    setHeaders,
  });

  return next(apiReq).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401 && !req.url.includes('/api/auth/')) {
        router.navigate(['/login']);
      }
      return throwError(() => error);
    })
  );
};
