import type { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

/**
 * Sends the Symfony session cookie with every request (same-origin),
 * asks the API for JSON, and redirects to the login page on 401.
 * PATCH writes carry the merge-patch content type API Platform requires.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);

  const setHeaders: Record<string, string> = {};
  if (!req.headers.has('Accept')) {
    setHeaders['Accept'] = 'application/json';
  }
  if (req.method === 'PATCH' && !req.headers.has('Content-Type')) {
    setHeaders['Content-Type'] = 'application/merge-patch+json';
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
