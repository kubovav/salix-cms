import { Component, DestroyRef, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { UserService } from '@core/user.service';
import { AuthService } from '@core/auth.service';
import type { User } from '@core/models';
import { UserEditorModal } from './user-editor-modal';

@Component({
  selector: 'app-user-list',
  imports: [DatePipe],
  templateUrl: './user-list.html',
})
export class UserListComponent implements OnInit {
  private userService = inject(UserService);
  private authService = inject(AuthService);
  private modal = inject(NgbModal);
  private readonly destroyRef = inject(DestroyRef);

  readonly items = signal<User[]>([]);
  readonly loading = signal(true);

  ngOnInit(): void {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.userService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.items.set(items);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  isAdmin(user: User): boolean {
    return (user.roles ?? []).includes('ROLE_ADMIN');
  }

  /** The signed-in account cannot delete itself. */
  isSelf(user: User): boolean {
    return this.authService.user()?.email === user.email;
  }

  private openEditor(user: User | null): void {
    const ref = this.modal.open(UserEditorModal);
    const editor = ref.componentInstance as UserEditorModal;
    editor.user = user;
    editor.init();
    ref.result.then(
      () => this.load(),
      () => undefined
    );
  }

  add(): void {
    this.openEditor(null);
  }

  edit(user: User): void {
    this.openEditor(user);
  }

  remove(user: User): void {
    if (!user.id || this.isSelf(user) || !confirm(`Delete user "${user.name || user.email}"?`)) {
      return;
    }
    this.userService
      .delete(user.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.load());
  }
}
