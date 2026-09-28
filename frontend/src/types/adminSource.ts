export type AdminSourceHealthStatus = 'healthy' | 'degraded' | 'pending' | 'stale' | 'error' | 'inactive';

export interface AdminSourceLatestRun {
  id: number;
  status: string;
  started_at: string | null;
  finished_at: string | null;
  items_found: number;
  items_created: number;
  items_updated: number;
  items_failed: number;
  error_log: string[] | null;
}

export interface AdminJobSourceHealth {
  source_id: number;
  source_name: string;
  provider: string | null;
  base_url: string | null;
  type: string | null;
  is_active: boolean;
  health_status: AdminSourceHealthStatus;
  refresh_interval_minutes: number;
  stale_after_hours: number;
  last_run_at: string | null;
  last_success_at: string | null;
  last_failure_at: string | null;
  last_error: string | null;
  consecutive_failures: number | null;
  last_items_found: number | null;
  last_items_created: number | null;
  last_items_updated: number | null;
  active_published_jobs_count: number;
  latest_run: AdminSourceLatestRun | null;
}

export interface AdminSourceHealthResponse {
  items: AdminJobSourceHealth[];
  summary: {
    total: number;
    active: number;
    healthy: number;
    attention: number;
  };
}
