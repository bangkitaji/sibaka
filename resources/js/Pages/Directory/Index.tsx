import { type FormEventHandler, useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent } from '@/Components/UI/card';
import AlumniProfileModal from '@/Components/Directory/AlumniProfileModal';
import type { PaginatedResponse, Profile, User } from '@/types/index.d';

interface AlumniResult {
  id: string;
  user_id: string;
  name: string;
  graduation_year: number;
  department: string;
  job_title: string | null;
  company: string | null;
  primary_tech_stack: string | null;
  secondary_tech_stack: string | null;
  years_of_experience: number | null;
  mentorship_status: string | null;
  hiring_status: string | null;
  availability: string | null;
  linkedin_url: string | null;
  github_url: string | null;
  completion_percentage: number;
}

interface DirectoryFilters {
  q: string;
  batch: string | null;
  role: string | null;
  tech_stack: string | null;
  experience_level: string | null;
}

interface DirectoryPageProps {
  results: PaginatedResponse<AlumniResult>;
  filters: DirectoryFilters;
}

/** Map AlumniResult to the shape expected by AlumniProfileModal */
function toModalAlumni(alumni: AlumniResult): (User & { profile: Profile }) | null {
  if (!alumni) return null;

  return {
    id: alumni.user_id ?? alumni.id,
    name: alumni.name,
    email: '',
    graduation_year: alumni.graduation_year,
    department: alumni.department,
    role: 'member',
    verification_status: 'approved',
    created_at: '',
    last_login_at: null,
    profile: {
      id: alumni.id,
      user_id: alumni.user_id ?? alumni.id,
      job_title: alumni.job_title,
      company: alumni.company,
      years_of_experience: alumni.years_of_experience,
      primary_tech_stack: alumni.primary_tech_stack,
      secondary_tech_stack: alumni.secondary_tech_stack,
      mentorship_status: alumni.mentorship_status as Profile['mentorship_status'],
      hiring_status: alumni.hiring_status as Profile['hiring_status'],
      availability: alumni.availability as Profile['availability'],
      linkedin_url: alumni.linkedin_url,
      github_url: alumni.github_url,
      completion_percentage: alumni.completion_percentage,
    },
  };
}

export default function DirectoryIndex({ results, filters }: DirectoryPageProps) {
  const [search, setSearch] = useState(filters.q ?? '');
  const [batch, setBatch] = useState(filters.batch ?? '');
  const [role, setRole] = useState(filters.role ?? '');
  const [techStack, setTechStack] = useState(filters.tech_stack ?? '');
  const [experienceLevel, setExperienceLevel] = useState(filters.experience_level ?? '');

  const [selectedAlumni, setSelectedAlumni] = useState<AlumniResult | null>(null);
  const [modalOpen, setModalOpen] = useState(false);

  const applyFilters = useCallback(
    (overrides: Partial<DirectoryFilters> = {}) => {
      const params: Record<string, string> = {};

      const q = overrides.q ?? search;
      const b = overrides.batch ?? batch;
      const r = overrides.role ?? role;
      const ts = overrides.tech_stack ?? techStack;
      const el = overrides.experience_level ?? experienceLevel;

      if (q) params.q = q;
      if (b) params.batch = b;
      if (r) params.role = r;
      if (ts) params.tech_stack = ts;
      if (el) params.experience_level = el;

      router.get('/directory', params, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [search, batch, role, techStack, experienceLevel]
  );

  const handleSearch: FormEventHandler = (e) => {
    e.preventDefault();
    applyFilters();
  };

  const handleFilterChange = (filterName: keyof DirectoryFilters, value: string) => {
    switch (filterName) {
      case 'batch':
        setBatch(value);
        applyFilters({ batch: value });
        break;
      case 'role':
        setRole(value);
        applyFilters({ role: value });
        break;
      case 'tech_stack':
        setTechStack(value);
        applyFilters({ tech_stack: value });
        break;
      case 'experience_level':
        setExperienceLevel(value);
        applyFilters({ experience_level: value });
        break;
    }
  };

  const clearFilters = () => {
    setSearch('');
    setBatch('');
    setRole('');
    setTechStack('');
    setExperienceLevel('');
    router.get('/directory', {}, { preserveState: true, preserveScroll: true });
  };

  const goToPage = (page: number) => {
    const params: Record<string, string> = { page: String(page) };
    if (search) params.q = search;
    if (batch) params.batch = batch;
    if (role) params.role = role;
    if (techStack) params.tech_stack = techStack;
    if (experienceLevel) params.experience_level = experienceLevel;

    router.get('/directory', params, { preserveState: true, preserveScroll: true });
  };

  const openProfile = (alumni: AlumniResult) => {
    setSelectedAlumni(alumni);
    setModalOpen(true);
  };

  const closeProfile = () => {
    setSelectedAlumni(null);
    setModalOpen(false);
  };

  const hasActiveFilters = !!batch || !!role || !!techStack || !!experienceLevel || !!search;

  // Generate batch year options (1979 to current year)
  const currentYear = new Date().getFullYear();
  const batchYears: number[] = [];
  for (let y = currentYear; y >= 1979; y--) {
    batchYears.push(y);
  }

  return (
    <AppLayout title="Alumni Directory">
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-foreground">Alumni Directory</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Find and connect with IT professionals from STM Pembangunan Semarang.
          </p>
        </div>

        {/* Search Bar */}
        <form onSubmit={handleSearch} className="flex gap-2">
          <div className="flex-1">
            <Label htmlFor="directory-search" className="sr-only">
              Search alumni
            </Label>
            <Input
              id="directory-search"
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by name, job role, company, or tech stack..."
              aria-label="Search alumni directory"
            />
          </div>
          <Button type="submit">Search</Button>
        </form>

        {/* Filters - Req 9.2: Batch, Job Role, Tech Stack, Experience Level */}
        <Card>
          <CardContent className="p-4">
            <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-4">
              {/* Batch (Graduation Year) */}
              <div className="space-y-1">
                <Label htmlFor="filter-batch" className="text-xs text-muted-foreground">
                  Batch (Graduation Year)
                </Label>
                <select
                  id="filter-batch"
                  value={batch}
                  onChange={(e) => handleFilterChange('batch', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-label="Filter by graduation year"
                >
                  <option value="">All batches</option>
                  {batchYears.map((year) => (
                    <option key={year} value={String(year)}>
                      {year}
                    </option>
                  ))}
                </select>
              </div>

              {/* Job Role */}
              <div className="space-y-1">
                <Label htmlFor="filter-role" className="text-xs text-muted-foreground">
                  Job Role
                </Label>
                <Input
                  id="filter-role"
                  type="text"
                  value={role}
                  onChange={(e) => setRole(e.target.value)}
                  onBlur={() => handleFilterChange('role', role)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      handleFilterChange('role', role);
                    }
                  }}
                  placeholder="e.g. Backend Engineer"
                  aria-label="Filter by job role"
                />
              </div>

              {/* Tech Stack */}
              <div className="space-y-1">
                <Label htmlFor="filter-tech-stack" className="text-xs text-muted-foreground">
                  Tech Stack
                </Label>
                <Input
                  id="filter-tech-stack"
                  type="text"
                  value={techStack}
                  onChange={(e) => setTechStack(e.target.value)}
                  onBlur={() => handleFilterChange('tech_stack', techStack)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      handleFilterChange('tech_stack', techStack);
                    }
                  }}
                  placeholder="e.g. React, Go"
                  aria-label="Filter by tech stack"
                />
              </div>

              {/* Experience Level */}
              <div className="space-y-1">
                <Label htmlFor="filter-experience" className="text-xs text-muted-foreground">
                  Experience Level
                </Label>
                <select
                  id="filter-experience"
                  value={experienceLevel}
                  onChange={(e) => handleFilterChange('experience_level', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-label="Filter by experience level"
                >
                  <option value="">All levels</option>
                  <option value="beginner">Beginner (0-2 years)</option>
                  <option value="intermediate">Intermediate (3-5 years)</option>
                  <option value="advanced">Advanced (6-10 years)</option>
                  <option value="architecture">Architecture (11+ years)</option>
                </select>
              </div>
            </div>

            {hasActiveFilters && (
              <div className="mt-3 flex items-center justify-between">
                <span className="text-sm text-muted-foreground">
                  {results.total} alumni found
                </span>
                <Button variant="ghost" size="sm" onClick={clearFilters}>
                  Clear all filters
                </Button>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Results */}
        {results.data.length > 0 ? (
          <>
            {/* Results count */}
            {!hasActiveFilters && (
              <p className="text-sm text-muted-foreground">
                Showing {results.data.length} of {results.total} alumni
              </p>
            )}

            {/* Alumni list */}
            <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
              {results.data.map((alumni) => (
                <Card
                  key={alumni.id}
                  className="cursor-pointer transition-shadow hover:shadow-md"
                >
                  <CardContent className="p-4">
                    <button
                      type="button"
                      onClick={() => openProfile(alumni)}
                      className="w-full text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-md min-h-touch"
                      aria-label={`View profile of ${alumni.name}`}
                    >
                      <div className="space-y-2">
                        <div>
                          <h3 className="font-semibold text-foreground">
                            {alumni.name}
                          </h3>
                          <p className="text-sm text-muted-foreground">
                            Batch {alumni.graduation_year}
                          </p>
                        </div>

                        {alumni.job_title && (
                          <p className="text-sm text-foreground">{alumni.job_title}</p>
                        )}
                        {alumni.company && (
                          <p className="text-sm text-muted-foreground">
                            at {alumni.company}
                          </p>
                        )}
                        {alumni.primary_tech_stack && (
                          <p className="text-xs text-muted-foreground truncate">
                            {alumni.primary_tech_stack}
                          </p>
                        )}
                      </div>
                    </button>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Pagination - Req 9.1: 20 items per page */}
            {results.last_page > 1 && (
              <nav aria-label="Directory pagination" className="flex items-center justify-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => goToPage(results.current_page - 1)}
                  disabled={results.current_page <= 1}
                  aria-label="Previous page"
                >
                  Previous
                </Button>

                <span className="text-sm text-muted-foreground px-3">
                  Page {results.current_page} of {results.last_page}
                </span>

                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => goToPage(results.current_page + 1)}
                  disabled={results.current_page >= results.last_page}
                  aria-label="Next page"
                >
                  Next
                </Button>
              </nav>
            )}
          </>
        ) : (
          /* Empty State - Req 9.7 */
          <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12 px-4 text-center">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="48"
              height="48"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              className="text-muted-foreground/50 mb-4"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <h3 className="text-lg font-medium text-foreground">No alumni found</h3>
            <p className="mt-1 text-sm text-muted-foreground max-w-sm">
              No alumni match your current search criteria. Try broadening your search
              or adjusting the filters.
            </p>
            {hasActiveFilters && (
              <Button variant="outline" className="mt-4" onClick={clearFilters}>
                Clear all filters
              </Button>
            )}
          </div>
        )}

        {/* Profile Modal - Req 9.4, 9.5, 9.6 */}
        <AlumniProfileModal
          alumni={toModalAlumni(selectedAlumni!)}
          isOpen={modalOpen}
          onClose={closeProfile}
        />
      </div>
    </AppLayout>
  );
}
