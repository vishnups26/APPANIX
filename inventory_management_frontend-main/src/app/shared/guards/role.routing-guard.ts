import { CanActivateFn } from '@angular/router';

export const roleRoutingGuard: CanActivateFn = (route, state) => {
  return true;
};
