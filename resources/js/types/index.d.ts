/// <reference types="vite/client" />

// Inertia page props
export interface User {
  id: string;
  name: string;
  email: string;
  graduation_year: number;
  department: string;
  role: UserRole;
  verification_status: VerificationStatus;
  created_at: string;
  last_login_at: string | null;
}

export interface Profile {
  id: string;
  user_id: string;
  job_title: string | null;
  company: string | null;
  years_of_experience: number | null;
  primary_tech_stack: string | null;
  secondary_tech_stack: string | null;
  mentorship_status: MentorshipStatus | null;
  hiring_status: HiringStatus | null;
  availability: Availability | null;
  linkedin_url: string | null;
  github_url: string | null;
  completion_percentage: number;
}

export interface Content {
  id: string;
  author_id: string;
  title: string;
  body: string;
  body_html: string;
  category: ContentCategory;
  is_anonymous: boolean;
  is_qna: boolean;
  accepted_solution_id: string | null;
  status: ContentStatus;
  is_locked: boolean;
  published_at: string | null;
  created_at: string;
  updated_at: string;
  tags: Tag[];
  author?: User;
  reactions_summary?: ReactionSummary;
}

export interface Comment {
  id: string;
  content_id: string;
  author_id: string;
  parent_id: string | null;
  body: string;
  is_anonymous: boolean;
  is_edited: boolean;
  depth: number;
  created_at: string;
  edited_at: string | null;
  author?: User;
  replies?: Comment[];
}

export interface Tag {
  id: string;
  name: string;
  tag_category: TagCategory;
}

export interface Reaction {
  id: string;
  content_id: string;
  user_id: string;
  type: ReactionType;
}

export interface ReactionSummary {
  total: number;
  insightful: number;
  relatable: number;
  helpful: number;
  solutif: number;
  user_reaction: ReactionType | null;
  show_breakdown: boolean;
  is_solutif_recommendation: boolean;
}

export interface InviteCode {
  id: string;
  code: string;
  is_used: boolean;
  expires_at: string;
}

export interface Report {
  id: string;
  content_id: string;
  reporter_id: string;
  reason: ReportReason;
  description: string | null;
  status: string;
  created_at: string;
}

export interface Warning {
  id: string;
  user_id: string;
  issued_by: string;
  message: string;
  created_at: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// Enums
export type UserRole = 'guest' | 'pending' | 'member' | 'moderator' | 'admin';
export type VerificationStatus = 'pending' | 'approved' | 'rejected';
export type ContentCategory = 'post_mortem' | 'tech_stack' | 'career_interview' | 'showcase';
export type ContentStatus = 'draft' | 'published' | 'hidden' | 'deleted';
export type ReactionType = 'insightful' | 'relatable' | 'helpful' | 'solutif';
export type TagCategory = 'tech_stack' | 'experience_level' | 'content_category';
export type ReportReason = 'spam' | 'harassment' | 'misinformation' | 'off_topic' | 'other';
export type ModerationAction = 'remove_content' | 'suspend_user' | 'issue_warning' | 'dismiss';
export type MentorshipStatus = 'willing' | 'not_willing';
export type HiringStatus = 'open_to_hiring' | 'seeking_job' | 'internship' | 'none';
export type Availability = 'immediate' | '1_month' | '2_months' | '3_months_plus';

// Shared Inertia page props
export interface SharedPageProps {
  [key: string]: unknown;
  auth: {
    user: User | null;
  };
  flash: {
    success?: string;
    error?: string;
  };
  errors: Record<string, string>;
}
