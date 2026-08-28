import type { ApplicationStatus } from '@/types/application';
import type { CompanyApplication } from '@/types/companyApplication';

const NEEDS_REVIEW: ApplicationStatus[] = ['submitted', 'under_review'];
const TERMINAL: ApplicationStatus[] = ['rejected', 'withdrawn'];

export function averageCompletedMatchScore(applications: CompanyApplication[]): number | null {
  const scores = applications
    .map((application) => application.match_score)
    .filter((score): score is number => score !== null && Number.isFinite(score));

  if (scores.length === 0) {
    return null;
  }

  return Math.round(scores.reduce((sum, score) => sum + score, 0) / scores.length);
}

export function averageMatchScoreForJob(
  applications: CompanyApplication[],
  jobId: number,
): number | null {
  return averageCompletedMatchScore(applications.filter((application) => application.job_id === jobId));
}

export function applicationCountForJob(
  applications: CompanyApplication[],
  jobId: number,
  persistedCount?: number,
): number {
  if (typeof persistedCount === 'number' && Number.isFinite(persistedCount)) {
    return persistedCount;
  }

  return applications.filter((application) => application.job_id === jobId).length;
}

export function selectPriorityApplications(
  applications: CompanyApplication[],
  limit = 5,
): CompanyApplication[] {
  return [...applications]
    .filter((application) => !TERMINAL.includes(application.status))
    .sort((left, right) => {
      const leftReview = NEEDS_REVIEW.includes(left.status) ? 0 : 1;
      const rightReview = NEEDS_REVIEW.includes(right.status) ? 0 : 1;
      if (leftReview !== rightReview) {
        return leftReview - rightReview;
      }

      const leftScore = left.match_score ?? Number.NEGATIVE_INFINITY;
      const rightScore = right.match_score ?? Number.NEGATIVE_INFINITY;
      if (leftScore !== rightScore) {
        return rightScore - leftScore;
      }

      return new Date(right.applied_at).getTime() - new Date(left.applied_at).getTime();
    })
    .slice(0, limit);
}

export function highestCompletedMatch(applications: CompanyApplication[]): CompanyApplication | null {
  return (
    [...applications]
      .filter((application) => application.match_score !== null)
      .sort((left, right) => (right.match_score ?? 0) - (left.match_score ?? 0))[0] ?? null
  );
}
