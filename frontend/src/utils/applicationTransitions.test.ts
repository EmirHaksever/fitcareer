import { describe, expect, it } from 'vitest';
import {
  getAllowedNextStatuses,
  isTerminalApplicationStatus,
} from '@/utils/applicationTransitions';

describe('applicationTransitions', () => {
  it('returns allowed next statuses for submitted', () => {
    expect(getAllowedNextStatuses('submitted')).toEqual([
      'under_review',
      'rejected',
      'withdrawn',
    ]);
  });

  it('returns allowed next statuses for under_review', () => {
    expect(getAllowedNextStatuses('under_review')).toEqual([
      'shortlisted',
      'rejected',
      'withdrawn',
    ]);
  });

  it('marks terminal statuses', () => {
    expect(isTerminalApplicationStatus('rejected')).toBe(true);
    expect(isTerminalApplicationStatus('withdrawn')).toBe(true);
    expect(isTerminalApplicationStatus('submitted')).toBe(false);
  });
});
