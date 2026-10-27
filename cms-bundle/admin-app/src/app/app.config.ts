import type { ApplicationConfig } from '@angular/core';
import { provideBrowserGlobalErrorListeners, provideZonelessChangeDetection } from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideQuillConfig } from 'ngx-quill/config';

import { routes } from './app.routes';
import { authInterceptor } from '@core/auth.interceptor';
import { BADGE_VARIANTS } from '@core/quill-badge-variants';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZonelessChangeDetection(),
    provideRouter(routes, withComponentInputBinding()),
    provideHttpClient(withInterceptors([authInterceptor])),
    provideQuillConfig({
      format: 'object',
      beforeRender: async () => {
        const { registerBadgeFormat } = await import('@core/quill-badge');
        registerBadgeFormat();
      },
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ badge: [...BADGE_VARIANTS, false] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ indent: '-1' }, { indent: '+1' }],
          [{ align: [] }],
          ['blockquote', 'code-block'],
          ['link'],
          ['clean'],
        ],
      },
    }),
  ],
};
