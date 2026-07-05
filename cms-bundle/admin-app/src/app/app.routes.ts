import type { Routes } from '@angular/router';
import { authGuard } from '@core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login').then((m) => m.LoginComponent),
  },
  {
    path: '',
    loadComponent: () => import('./layout/shell').then((m) => m.ShellComponent),
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./pages/dashboard/dashboard').then((m) => m.DashboardComponent),
      },
      {
        path: 'articles',
        loadComponent: () =>
          import('./pages/articles/article-list').then((m) => m.ArticleListComponent),
      },
      {
        path: 'articles/new',
        loadComponent: () =>
          import('./pages/articles/article-edit').then((m) => m.ArticleEditComponent),
      },
      {
        path: 'articles/:id',
        loadComponent: () =>
          import('./pages/articles/article-edit').then((m) => m.ArticleEditComponent),
      },
      {
        path: 'menu',
        loadComponent: () => import('./pages/menu/menu-list').then((m) => m.MenuListComponent),
      },
      {
        path: 'users',
        loadComponent: () => import('./pages/users/user-list').then((m) => m.UserListComponent),
      },
      {
        path: 'settings',
        loadComponent: () => import('./pages/settings/settings').then((m) => m.SettingsComponent),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
