# Implementation Plan: SIBAKA Portal

## Overview

This implementation plan covers the full SIBAKA knowledge sharing portal for IT alumni of STM Pembangunan Semarang. The backend uses Laravel 11+ (PHP 8.3+) with PostgreSQL 16, Redis, and Sanctum authentication. The frontend uses React 18 + TypeScript + Vite + Inertia.js with TipTap editor. Tasks are ordered to build foundational layers first, then features incrementally with property-based testing throughout.

## Tasks

- [x] 1. Project scaffolding and core infrastructure
  - [x] 1.1 Initialize Laravel project with base configuration
    - Create Laravel 11 project with PHP 8.3+ requirement
    - Configure PostgreSQL connection, Redis for cache/queue/session
    - Install and configure Inertia.js adapter (server-side)
    - Install Laravel Sanctum for SPA authentication
    - Set up Pest PHP testing framework with phpunit-quickcheck
    - Configure `.env.example` with all required variables
    - _Requirements: 10.1, 11.2_

  - [x] 1.2 Set up React frontend with Vite, TypeScript, and Inertia.js
    - Install React 18, TypeScript, Vite, @inertiajs/react
    - Install Tailwind CSS and shadcn/ui component library
    - Configure `vite.config.ts` for Laravel integration
    - Create `app.tsx` bootstrap with Inertia `createInertiaApp`
    - Set up `AppLayout.tsx` and `AuthLayout.tsx` base layouts
    - Install Vitest and fast-check for frontend testing
    - _Requirements: 10.1, 10.5, 10.6_

  - [x] 1.3 Create Enums and shared type definitions
    - Create PHP Enums: `UserRole`, `VerificationStatus`, `ContentCategory`, `ContentStatus`, `ReactionType`, `TagCategory`, `ReportReason`, `ModerationAction`
    - Create TypeScript type definitions in `resources/js/types/index.d.ts`
    - _Requirements: 1.6, 4.1, 8.1, 12.2_

  - [x] 1.4 Create database migrations for all entities
    - Write migrations for: users, profiles, contents, comments, reactions, tags, content_tag, invite_codes, anonymous_metadata, reports, warnings, audit_logs, moderation_logs, drafts, portal_messages
    - Add PostgreSQL immutability trigger on moderation_logs table
    - Add full-text search index on contents (title, body) and profiles (job_title, company, primary_tech_stack)
    - _Requirements: 11.5, 12.5_

  - [x] 1.5 Create Eloquent models with relationships and scopes
    - Implement all models: User, Profile, Content, Comment, Reaction, Tag, ContentTag, InviteCode, Report, Warning, AuditLog, ModerationLog, AnonymousMetadata, Draft, PortalMessage
    - Define relationships (hasOne, hasMany, belongsTo, belongsToMany)
    - Add SoftDeletes trait to Content and Comment models
    - Add UUID trait for primary keys
    - Define query scopes (published, locked, byCategory, etc.)
    - _Requirements: 1.1, 7.1_

  - [x] 1.6 Create database seeders and factories
    - Create model factories for all entities using Pest/PHPUnit
    - Create seeders for predefined tags (tech_stack, experience_level, category)
    - Create admin user seeder for initial moderator account
    - _Requirements: 6.2, 6.3, 6.4_

- [x] 2. Authentication and registration system
  - [x] 2.1 Implement AuthService and registration flow
    - Create `AuthService.php` implementing `AuthServiceInterface`
    - Implement `register()` with validation, duplicate email check, and user creation
    - Create `RegisterRequest` form request with all validation rules
    - Create `RegisterController` with `store` method
    - Implement invite code verification path and admin approval path
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 2.2 Write property tests for registration validation (Property 1)
    - **Property 1: Registration and Profile Validation Schema Correctness**
    - Test that registration validator accepts valid inputs and rejects invalid inputs across randomized data
    - Use Pest datasets with phpunit-quickcheck generators for name, email, password, graduation_year, department, URLs
    - **Validates: Requirements 1.2, 2.3, 2.4**

  - [x] 2.3 Write property test for invite code validation (Property 2)
    - **Property 2: Invite Code Validation**
    - Test invite codes are accepted iff they exist, are unused, and unexpired
    - Generate random code strings, states (used/unused), and expiry timestamps
    - **Validates: Requirements 1.5**

  - [x] 2.4 Implement login, logout, and session management
    - Create `LoginController` with `store` (login) and `destroy` (logout)
    - Implement failed login tracking with account lockout (5 attempts in 15 min → 30 min lock)
    - Configure Sanctum session lifetime to 30 minutes of inactivity
    - Send email notification on account lock
    - _Requirements: 1.6, 11.7, 11.8_

  - [x] 2.5 Write property test for account lockout (Property 27)
    - **Property 27: Account Lockout**
    - Test that accounts lock at exactly 5 failed attempts within 15 min and unlock after 30 min
    - Generate sequences of login attempts with varying timestamps
    - **Validates: Requirements 11.8**

  - [x] 2.6 Write property test for session expiry (Property 26)
    - **Property 26: Session Expiry**
    - Test that sessions expire after 30 minutes of inactivity and remain valid within 30 minutes
    - Generate random inactivity durations and verify behavior at boundary
    - **Validates: Requirements 11.7**

  - [x] 2.7 Implement verification workflow and notifications
    - Create `VerificationController` with invite code verification and admin approval endpoints
    - Create `InviteCodeController` for generating invite codes (member-only)
    - Implement `VerificationApproved` and `VerificationRejected` notifications (email within 60s)
    - Create middleware `EnsureVerified` and `EnsureNotSuspended`
    - _Requirements: 1.4, 1.5, 1.7, 1.8, 1.9_

  - [x] 2.8 Implement role-based access control (RBAC) policies
    - Create `ContentPolicy`, `CommentPolicy`, `ModerationPolicy`
    - Implement Gate definitions for: pending (public read only), member (CRUD), moderator (moderation actions), admin (logs access)
    - Register policies in `AuthServiceProvider`
    - _Requirements: 1.6, 8.8, 12.4, 12.5_

  - [x] 2.9 Write property test for RBAC (Property 3)
    - **Property 3: Role-Based Access Control**
    - Test that each role gets access to exactly its allowed actions across all permission combinations
    - Generate random users with roles and random actions, verify authorization matches spec
    - **Validates: Requirements 1.6, 8.8, 12.4, 12.5**

  - [x] 2.10 Build registration and login frontend pages
    - Create `Pages/Auth/Login.tsx`, `Pages/Auth/Register.tsx`, `Pages/Auth/VerifyPending.tsx`
    - Implement form validation with Inertia form helper
    - Add invite code input with real-time feedback
    - Style with Tailwind CSS + shadcn/ui form components
    - _Requirements: 1.1, 1.4, 1.5_

- [x] 3. Checkpoint - Verify auth system
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Profile system and alumni directory
  - [x] 4.1 Implement ProfileController and DirectoryService
    - Create `ProfileController` with `show` and `update` methods
    - Create `UpdateProfileRequest` form request with validation rules
    - Implement `DirectoryService` with PostgreSQL full-text search
    - Create `DirectoryController` with search, filter, and pagination (20 per page)
    - Implement profile completion percentage calculation logic
    - _Requirements: 2.1, 2.3, 2.4, 2.5, 2.7, 9.1, 9.2, 9.3_

  - [x] 4.2 Write property test for profile completion percentage (Property 4)
    - **Property 4: Profile Completion Percentage Calculation**
    - Test that percentage = (filled / total) × 100 with cap at 50% if required fields missing
    - Generate random combinations of filled/unfilled fields
    - **Validates: Requirements 2.7**

  - [x] 4.3 Write property test for directory search and filter (Property 21)
    - **Property 21: Directory Search and Filter Correctness**
    - Test that all returned results match text search AND all active filter criteria
    - Generate random alumni datasets, queries, and filter combinations
    - Verify pagination at 20 items per page and contact option visibility
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.5, 9.6**

  - [x] 4.4 Build profile edit and directory frontend pages
    - Create `Pages/Profile/Edit.tsx` with profile completion banner (orange bg, white text, progress %)
    - Create `Pages/Directory/Index.tsx` with search, filters, and pagination
    - Implement profile modal with contact options (message, LinkedIn, GitHub)
    - Implement retry logic for profile save (3 retries, exponential backoff)
    - _Requirements: 2.1, 2.2, 2.5, 2.6, 2.8, 9.1, 9.4, 9.5, 9.6, 9.7_

  - [x] 4.5 Write property test for retry mechanism (Property 5)
    - **Property 5: Retry Mechanism Correctness**
    - Frontend test with fast-check: test that retry attempts = min(K+1, 4) and uses exponential backoff
    - Generate sequences of success/failure responses and verify retry counts and delays
    - **Validates: Requirements 2.6, 3.5, 10.3**

- [x] 5. Content creation and rich editor
  - [x] 5.1 Implement ContentService and content CRUD
    - Create `ContentService.php` implementing `ContentServiceInterface`
    - Create `StoreContentRequest` and `UpdateContentRequest` form requests
    - Create `ContentController` with index, show, store, update, destroy
    - Implement content body length validation (1-50,000 chars)
    - Implement embed limit validation (max 10)
    - Implement soft-delete for content removal
    - _Requirements: 3.1, 3.7, 3.8, 4.1_

  - [x] 5.2 Write property test for content length and embed limits (Property 6)
    - **Property 6: Content Length and Embed Limit Validation**
    - Test that content is accepted iff body is 1-50000 chars and embeds ≤ 10
    - Generate random body lengths and embed counts
    - **Validates: Requirements 3.7, 3.8**

  - [x] 5.3 Write property test for content category validation (Property 7)
    - **Property 7: Content Category Validation**
    - Test exactly one category required, filtering returns only matching categories
    - Generate content with 0, 1, or multiple categories and verify acceptance/rejection
    - **Validates: Requirements 4.1, 4.6, 4.9, 4.10**

  - [x] 5.4 Implement TipTap editor component with code highlighting
    - Create `Components/Editor/TipTapEditor.tsx` with TipTap + Markdown support
    - Add `CodeBlockLowlight` extension with syntax highlighting (JS, Python, Java, Go, PHP minimum)
    - Create `Components/Editor/EmbedNode.tsx` for YouTube, GitHub Gist, Mermaid embeds
    - Implement character counter (visible at 45,000+) and embed limit indicator
    - _Requirements: 3.1, 3.2, 3.3, 3.7, 3.8_

  - [x] 5.5 Implement auto-save draft system
    - Create `DraftController` with update (auto-save) and show (restore) endpoints
    - Create `DraftService` using Redis for fast TTL-based storage
    - Implement frontend `useAutoSave.ts` hook: save every 10 seconds, show status indicator (Saving/Saved/Failed)
    - Implement retry logic: 3 retries with 2-second delay on failure
    - Implement draft restoration on editor reopen
    - Implement "unsaved changes" modal on navigation away
    - _Requirements: 3.4, 3.5, 3.6, 10.2, 10.3, 10.4_

  - [x] 5.6 Write property test for draft round-trip persistence (Property 22)
    - **Property 22: Draft Round-Trip Persistence**
    - Frontend test with fast-check: test that saved draft content is identical when restored
    - Generate random content strings (including special chars, unicode, code blocks)
    - **Validates: Requirements 10.4**

  - [x] 5.7 Build content listing and detail frontend pages
    - Create `Pages/Content/Index.tsx` with category filter, pagination, and tag facets
    - Create `Pages/Content/Show.tsx` with content display, category badge, tags, reactions
    - Create `Pages/Content/Create.tsx` and `Pages/Content/Edit.tsx` with TipTap integration
    - Create `Components/Content/ContentCard.tsx` and `Components/Content/CategoryBadge.tsx`
    - Implement category badge with distinct colors per IT_Experience_Category
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.7, 4.8, 4.9, 4.10_

- [x] 6. Taxonomy and tagging system
  - [x] 6.1 Implement TagService and tag validation
    - Create `TagService.php` with prefix-matching search (2+ chars, max 10 results)
    - Implement tag validation in `StoreContentRequest`: 1-3 tech_stack, 1 experience_level, 1 category
    - Enforce category tag mapping to content's IT_Experience_Category
    - Reject tags not in predefined list
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.6, 6.7_

  - [x] 6.2 Write property test for tag validation rules (Property 12)
    - **Property 12: Tag Validation Rules**
    - Test acceptance iff: tech_stack 1-3, exactly 1 experience_level, exactly 1 category, all tags exist
    - Generate random tag combinations with valid/invalid counts and names
    - **Validates: Requirements 6.2, 6.3, 6.4, 6.6, 6.7**

  - [x] 6.3 Write property test for tag search prefix matching (Property 13)
    - **Property 13: Tag Search Prefix Matching**
    - Test that all returned tags are prefix matches (case-insensitive), max 10 results, from predefined list
    - Generate random query strings of 2+ chars against a tag dataset
    - **Validates: Requirements 6.1**

  - [x] 6.4 Build tag selection UI component
    - Create tag search input with autocomplete dropdown (prefix match, 2+ chars trigger)
    - Display selected tags as badges with remove button
    - Show validation errors for missing tag categories
    - Reject and clear input for non-predefined tags with inline error message
    - _Requirements: 6.1, 6.5, 6.7, 6.8_

- [x] 7. Checkpoint - Verify content and tagging system
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Anonymous sharing feature
  - [x] 8.1 Implement AnonymityService
    - Create `AnonymityService.php` implementing `AnonymityServiceInterface`
    - Implement `publishAnonymously()`: strip identity, store metadata (IP hash, fingerprint, user agent)
    - Implement `canPublishAnonymously()`: check 5-post/24-hour rate limit
    - Implement irreversibility check: reject `is_anonymous=false` on anonymous content
    - Implement `getAuthorForModeration()`: return author ID to moderators only
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.7, 5.9_

  - [x] 8.2 Write property test for anonymous identity stripping (Property 8)
    - **Property 8: Anonymous Content Identity Stripping**
    - Test that public view of anonymous content contains zero identifying info
    - Generate random user profiles and verify none of their data appears in anonymous view
    - **Validates: Requirements 5.2, 5.3, 5.4, 11.3**

  - [x] 8.3 Write property test for anonymous posting rate limit (Property 9)
    - **Property 9: Anonymous Posting Rate Limit**
    - Test that post N+1 is accepted if N < 5 in 24h, rejected if N >= 5
    - Generate random post counts and timestamps within 24h window
    - **Validates: Requirements 5.9**

  - [x] 8.4 Write property test for anonymous irreversibility (Property 10)
    - **Property 10: Anonymous Content Irreversibility**
    - Test that changing is_anonymous from true to false is always rejected
    - Generate random update attempts on anonymous content
    - **Validates: Requirements 5.7**

  - [x] 8.5 Implement anonymous metadata purge job
    - Create `PurgeAnonymousMetadata` job: delete records older than 90 days
    - Register in Laravel Scheduler (daily at 3 AM)
    - _Requirements: 5.6_

  - [x] 8.6 Write property test for metadata purge (Property 11)
    - **Property 11: Anonymous Metadata Purge**
    - Test that purge deletes records > 90 days old and retains records ≤ 90 days
    - Generate random metadata records with varied creation timestamps
    - **Validates: Requirements 5.6**

  - [x] 8.7 Build anonymous toggle UI for content and comments
    - Add anonymous toggle switch to content creation and comment forms
    - Render "Anonymous Member" with no profile link for anonymous content
    - Ensure no visual correlation between multiple anonymous posts by same author
    - _Requirements: 5.1, 5.2, 5.3, 5.8_

- [x] 9. Comments and Q&A system
  - [x] 9.1 Implement CommentService with threading
    - Create `CommentService.php` implementing `CommentServiceInterface`
    - Implement threaded comments with depth limit (5 levels, flat at max depth)
    - Create `StoreCommentRequest` with trimmed length validation (1-5000 chars)
    - Implement edit window (15 minutes) and delete (anytime by author)
    - Implement locked thread rejection
    - Create `CommentController` with index, store, update, destroy, accept, unaccept
    - _Requirements: 7.1, 7.2, 7.3, 7.7, 7.8, 7.9, 7.10_

  - [x] 9.2 Write property test for comment depth constraint (Property 14)
    - **Property 14: Comment Thread Depth Constraint**
    - Test that no comment exceeds depth 5, replies at depth 5 stay at depth 5
    - Generate random comment trees with various nesting patterns
    - **Validates: Requirements 7.1**

  - [x] 9.3 Write property test for comment validation (Property 15)
    - **Property 15: Comment Validation**
    - Test acceptance iff trimmed length is 1-5000, and locked threads reject all comments
    - Generate random strings with leading/trailing whitespace and varied lengths
    - **Validates: Requirements 7.7, 7.8, 7.9**

  - [x] 9.4 Write property test for comment edit time window (Property 17)
    - **Property 17: Comment Edit Time Window**
    - Test that edit is accepted iff elapsed time ≤ 15 minutes, delete accepted always
    - Generate random elapsed times and verify boundary behavior
    - **Validates: Requirements 7.10**

  - [x] 9.5 Implement accepted solution (Q&A) feature
    - Implement `markAcceptedSolution()` and `unmarkAcceptedSolution()` in CommentService
    - Enforce one accepted solution per thread, changeable by content author only
    - Implement reputation point logic: +50 on mark, -50 on unmark, transfer on change
    - _Requirements: 7.3, 7.4, 7.5_

  - [x] 9.6 Write property test for accepted solution uniqueness (Property 16)
    - **Property 16: Accepted Solution Uniqueness and Reputation Consistency**
    - Test at most one accepted solution per thread, reputation conservation (+/-50)
    - Generate random sequences of mark/unmark/change operations
    - **Validates: Requirements 7.3, 7.4, 7.5**

  - [x] 9.7 Implement thread auto-lock job
    - Create `AutoLockThreads` job: lock threads with no activity for 90 days
    - Register in Laravel Scheduler (daily at 4 AM)
    - _Requirements: 7.6_

  - [x] 9.8 Write property test for thread auto-lock (Property 18)
    - **Property 18: Thread Auto-Lock**
    - Test that threads with last activity > 90 days are locked, others remain unlocked
    - Generate random threads with varied last activity timestamps
    - **Validates: Requirements 7.6**

  - [x] 9.9 Build threaded comments UI
    - Create `Components/Comments/ThreadedComments.tsx` with visual indentation (left border)
    - Create `Components/Comments/CommentForm.tsx` with character count and anonymous toggle
    - Implement accepted solution highlight (green border) and "edited" indicator
    - Handle locked thread state (disable form, show message)
    - _Requirements: 7.1, 7.2, 7.4, 7.6, 7.9, 7.10_

- [x] 10. Reaction system
  - [x] 10.1 Implement ReactionService
    - Create `ReactionService.php` implementing `ReactionServiceInterface`
    - Implement `react()`, `removeReaction()`, `changeReaction()` with unique constraint (1 per user per content)
    - Implement `getReactionSummary()` with type breakdown and total count
    - Implement threshold logic: breakdown visible at 50+ reactions, "Solutif Recommendation" badge at 10+ Solutif
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 10.2 Write property test for reaction uniqueness and counter (Property 19)
    - **Property 19: Reaction Uniqueness and Counter Accuracy**
    - Test at most one reaction per user per content, total count = distinct users, type change doesn't change total
    - Generate random sequences of react/change/remove operations
    - **Validates: Requirements 8.1, 8.2, 8.3**

  - [x] 10.3 Write property test for reaction threshold badges (Property 20)
    - **Property 20: Reaction Threshold Badges**
    - Test breakdown visible iff total >= 50, Solutif badge iff Solutif count >= 10
    - Generate random reaction counts and types
    - **Validates: Requirements 8.4, 8.5**

  - [x] 10.4 Build reaction UI with optimistic updates
    - Create `Components/Content/ReactionBar.tsx` with Insightful, Relatable, Helpful, Solutif buttons
    - Implement `useOptimisticReaction.ts` hook: UI updates within 100ms, revert on failure
    - Display reaction breakdown when total >= 50
    - Show "Solutif Recommendation" badge when Solutif count >= 10
    - Create `ReactionController` with store and destroy endpoints
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [x] 11. Checkpoint - Verify content features
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Security, rate limiting, and audit logging
  - [x] 12.1 Implement XSS sanitization and input security
    - Create content sanitizer: strip HTML tags, escape special chars (<, >, &, ", ')
    - Implement file upload validation: allowed types (.md, .txt, .pdf, .png, .jpg, .jpeg, .gif), max 10MB, max 5 files
    - Apply sanitization to all user-generated content fields
    - _Requirements: 11.1_

  - [x] 12.2 Write property test for XSS sanitization (Property 23)
    - **Property 23: XSS Sanitization**
    - Backend: test that output never contains `<script>`, `on*` handlers, or `javascript:` URLs
    - Frontend (fast-check): test sanitization of random strings with injected script patterns
    - **Validates: Requirements 11.1**

  - [x] 12.3 Implement rate limiting middleware
    - Create `RateLimitByIp` middleware: 100 requests/minute per IP
    - Present CAPTCHA challenge when limit exceeded
    - Configure Laravel rate limiter with Redis backend
    - _Requirements: 11.4_

  - [x] 12.4 Write property test for rate limiting (Property 24)
    - **Property 24: Rate Limiting**
    - Test that requests are served normally if N ≤ 100/minute, CAPTCHA presented if N > 100
    - Generate random request counts per IP within 1-minute windows
    - **Validates: Requirements 11.4**

  - [x] 12.5 Implement audit logging service
    - Create `AuditLog` creation logic: user_id, action_type, timestamp, IP, affected_resource
    - Log all authentication events and moderation actions
    - Enforce 365-day retention (prune via scheduled task)
    - Ensure no null fields on any audit entry
    - _Requirements: 11.5, 11.6_

  - [x] 12.6 Write property test for audit log completeness (Property 25)
    - **Property 25: Audit Log Completeness**
    - Test that all audit entries contain: user_id, action_type, timestamp, IP, affected_resource (no nulls)
    - Generate random audit events and verify all fields are present
    - **Validates: Requirements 11.6**

- [x] 13. Content moderation system
  - [x] 13.1 Implement ModerationService
    - Create `ModerationService.php` implementing `ModerationServiceInterface`
    - Implement `reportContent()` with reason and optional description (500 chars max)
    - Implement `reviewFlag()` for moderator review (remove/dismiss/warn)
    - Implement `suspendUser()` with duration (1-30 days), reason, and email notification
    - Implement `issueWarning()` with escalation check (3 warnings in 90 days → 7-day auto-suspension)
    - Implement `getDashboardStats()` with content volume, engagement, moderation metrics
    - Implement `getModerationQueue()` ordered by priority (3+ reports first, then by timestamp)
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.7, 12.8_

  - [x] 13.2 Write property test for moderation queue ordering (Property 28)
    - **Property 28: Moderation Priority Queue Ordering**
    - Test that items with 3+ reports appear before items with fewer, within tiers ordered by recency
    - Generate random sets of flagged content with varied report counts and timestamps
    - **Validates: Requirements 12.1**

  - [x] 13.3 Implement auto-flagging system
    - Create `AutoFlagContent` job: pattern matching for swear words, spam, malicious links
    - Place auto-flagged content in review queue with "pending review" status
    - Content hidden from members until moderator confirms or dismisses (within 24h)
    - _Requirements: 12.6_

  - [x] 13.4 Write property test for auto-flagging (Property 29)
    - **Property 29: Auto-Flagging Pattern Detection**
    - Test that content is flagged iff it contains ≥ 1 pattern match, not flagged otherwise
    - Generate random content with/without pattern matches
    - **Validates: Requirements 12.6**

  - [x] 13.5 Write property test for warning escalation (Property 30)
    - **Property 30: Warning Escalation**
    - Test that auto-suspension triggers iff N+1 >= 3 warnings within 90 days
    - Generate random warning histories with varied counts and timestamps
    - **Validates: Requirements 12.7**

  - [x] 13.6 Implement moderation controllers and routes
    - Create `ReportController`, `QueueController`, `SuspensionController`, `WarningController`, `DashboardController`
    - Create `ReportContentRequest` form request
    - Implement notification delivery to moderators within 30 seconds of report
    - Implement `AccountSuspended` notification (email within 60s with duration and reason)
    - _Requirements: 12.1, 12.2, 12.4, 12.8_

  - [x] 13.7 Build moderation dashboard and queue frontend
    - Create `Pages/Moderation/Dashboard.tsx` with stats (content volume, engagement, moderation actions)
    - Create `Pages/Moderation/Queue.tsx` with priority-ordered flagged content list
    - Implement review actions: remove content, suspend user, issue warning, dismiss
    - Auto-refresh dashboard stats every 60 seconds
    - _Requirements: 12.1, 12.2, 12.3_

  - [x] 13.8 Implement immutable moderation log
    - Create PostgreSQL trigger to prevent UPDATE/DELETE on moderation_logs table
    - Ensure all moderation actions create log entries
    - Restrict log access to admin-level moderators only
    - _Requirements: 12.5_

- [x] 14. Checkpoint - Verify moderation and security
  - Ensure all tests pass, ask the user if questions arise.

- [x] 15. Portal messaging and notifications
  - [x] 15.1 Implement portal messaging
    - Create `MessageController` with store endpoint
    - Validate: body max 1000 chars, max 10 messages per day per sender
    - Mark messages as read/unread
    - _Requirements: 9.5_

  - [x] 15.2 Implement notification system
    - Create all Laravel Notifications: `VerificationApproved`, `VerificationRejected`, `AccountSuspended`, `AccountLocked`, `ContentFlagged`
    - Configure queue for async email delivery (notifications queue)
    - Ensure delivery within 60 seconds via queue priority
    - Configure retry: 3 attempts with backoff [30s, 60s, 120s]
    - _Requirements: 1.7, 12.4_

- [x] 16. Dark mode and responsive design
  - [x] 16.1 Implement dark mode support
    - Create `useDarkMode.ts` hook with system preference detection (`prefers-color-scheme`)
    - Store user preference in localStorage/profile
    - Apply Tailwind dark mode classes throughout all components
    - _Requirements: 10.5_

  - [x] 16.2 Implement responsive layout
    - Ensure all pages render without horizontal scroll on mobile (320px+), tablet (768px+), desktop (1024px+)
    - Enforce minimum touch target size 44x44px for interactive elements
    - Test layouts with Tailwind responsive breakpoints
    - _Requirements: 10.6_

- [x] 17. Deployment configuration
  - [x] 17.1 Create deployment configuration files
    - Write Nginx configuration file (`sibaka.conf`) with SSL, gzip, static file serving, PHP-FPM pass
    - Write Supervisor configuration for queue workers (default + notifications) and scheduler
    - Write Laravel Envoy deployment script with zero-downtime release strategy
    - Write backup script (daily pg_dump + storage tar, 30-day retention)
    - Configure Laravel scheduler in `routes/console.php` (purge metadata, auto-lock threads, refresh stats, session GC, prune failed jobs)
    - _Requirements: 10.1, 11.2_

- [x] 18. Integration wiring and final assembly
  - [x] 18.1 Wire Inertia.js middleware and shared data
    - Create `HandleInertiaRequests` middleware with shared props (auth user, flash messages, errors)
    - Register all middleware in correct order (auth, verified, not-suspended, rate-limit)
    - Configure CORS and Sanctum stateful domains
    - _Requirements: 1.6, 11.2_

  - [x] 18.2 Define all routes (web.php and api.php)
    - Register Inertia page routes in `web.php` for all pages
    - Register API resource routes for content, comments, reactions, directory, profile, moderation
    - Apply route middleware groups (auth, verified, moderator)
    - _Requirements: 1.6, 8.8_

  - [x] 18.3 Implement graceful degradation handlers
    - Redis unavailable: fallback to database sessions and file cache
    - Filesystem full: disable uploads, return 507 with friendly message
    - Email service down: queue for later delivery, show in-app notification
    - _Requirements: 10.1_

  - [x] 18.4 Write integration tests for full request flows
    - Test complete registration → verification → profile flow
    - Test content creation → publish → view → react → comment flow
    - Test anonymous posting → moderation identification flow
    - Test moderator: flag → queue → review → action flow
    - _Requirements: All_

- [x] 19. Final checkpoint - Full system verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The tech stack is: Laravel 11+ (PHP 8.3+), React 18 + TypeScript + Vite + Inertia.js, PostgreSQL 16, Redis, TipTap + lowlight
- Backend property tests use Pest PHP + phpunit-quickcheck
- Frontend property tests use Vitest + fast-check
- All 30 correctness properties from the design are covered by property test tasks

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4", "1.5"] },
    { "id": 2, "tasks": ["1.6", "2.1", "2.4"] },
    { "id": 3, "tasks": ["2.2", "2.3", "2.5", "2.6", "2.7", "2.8"] },
    { "id": 4, "tasks": ["2.9", "2.10"] },
    { "id": 5, "tasks": ["4.1", "5.1"] },
    { "id": 6, "tasks": ["4.2", "4.3", "5.2", "5.3", "5.4", "6.1"] },
    { "id": 7, "tasks": ["4.4", "4.5", "5.5", "5.7", "6.2", "6.3"] },
    { "id": 8, "tasks": ["5.6", "6.4", "8.1"] },
    { "id": 9, "tasks": ["8.2", "8.3", "8.4", "8.5", "9.1"] },
    { "id": 10, "tasks": ["8.6", "8.7", "9.2", "9.3", "9.4", "9.5"] },
    { "id": 11, "tasks": ["9.6", "9.7", "9.9", "10.1"] },
    { "id": 12, "tasks": ["9.8", "10.2", "10.3", "10.4"] },
    { "id": 13, "tasks": ["12.1", "12.3", "12.5", "13.1"] },
    { "id": 14, "tasks": ["12.2", "12.4", "12.6", "13.2", "13.3"] },
    { "id": 15, "tasks": ["13.4", "13.5", "13.6", "13.7", "13.8"] },
    { "id": 16, "tasks": ["15.1", "15.2", "16.1", "16.2"] },
    { "id": 17, "tasks": ["17.1", "18.1", "18.2", "18.3"] },
    { "id": 18, "tasks": ["18.4"] }
  ]
}
```
