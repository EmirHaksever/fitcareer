import type { ApplicationStatus } from '@/types/application';

export const APPLICATION_TRANSITIONS: Record<ApplicationStatus, ApplicationStatus[]> = {
  submitted: ['under_review', 'rejected', 'withdrawn'],
  under_review: ['shortlisted', 'rejected', 'withdrawn'],
  shortlisted: ['interview', 'rejected', 'withdrawn'],
  interview: ['offered', 'rejected', 'withdrawn'],
  offered: ['rejected', 'withdrawn'],
  rejected: [],
  withdrawn: [],
};

export function getAllowedNextStatuses(status: ApplicationStatus): ApplicationStatus[] {
  return APPLICATION_TRANSITIONS[status] ?? [];
}

export function isTerminalApplicationStatus(status: ApplicationStatus): boolean {
  return getAllowedNextStatuses(status).length === 0;
}
