import { describe, expect, it } from 'vitest';
import { buildApplicationTrend, buildJobStatusSegments, buildPipelineSegments } from '@/utils/dashboardCharts';

describe('dashboardCharts', () => {
  it('builds 14-day application trend buckets', () => {
    const today = new Date();
    const todayKey = today.toISOString().slice(0, 10);

    const points = buildApplicationTrend([
      {
        id: 1,
        candidate_profile_id: 1,
        job_id: 1,
        status: 'submitted',
        cover_letter: null,
        match_score: 80,
        trust_score: 90,
        resume_snapshot_path: null,
        applied_at: `${todayKey}T10:00:00+03:00`,
        status_updated_at: null,
      },
    ]);

    expect(points).toHaveLength(14);
    expect(points.at(-1)?.count).toBe(1);
  });

  it('builds pipeline segments with labels', () => {
    const segments = buildPipelineSegments([
      { status: 'submitted', count: 3 },
      { status: 'interview', count: 1 },
    ]);

    expect(segments).toHaveLength(2);
    expect(segments[0]?.value).toBe(3);
    expect(segments[0]?.label).toBeTruthy();
  });

  it('aggregates job status segments', () => {
    const segments = buildJobStatusSegments([
      { status: 'draft' },
      { status: 'published' },
      { status: 'published' },
    ]);

    expect(segments).toHaveLength(2);
    expect(segments.find((segment) => segment.key === 'published')?.value).toBe(2);
  });
});
