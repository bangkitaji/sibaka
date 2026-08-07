# Requirements Document

## Introduction

SIBAKA (Sinau Bareng Kamisetembang) is a knowledge sharing portal for alumni of STM Pembangunan Semarang. The platform serves as a central hub for IT professionals in the alumni network to share experiences, discuss technical topics, build professional networks, and support career development. The first phase focuses on establishing a web portal with comprehensive knowledge sharing capabilities while maintaining a safe, code-friendly environment for career and technical incident discussions.

The portal is designed specifically for IT professionals across domains including Software Engineering, DevOps, Cybersecurity, Data, Network, System Admin, UI/UX, and Product Management. Core values include providing a safe space for vulnerable discussions (like company culture and salary), enabling talent mapping across generations of alumni, and fostering a code-friendly environment with proper syntax highlighting and code sharing capabilities.

## Glossary

- **SIBAKA_Portal**: The web-based knowledge sharing platform for STM Pembangunan Semarang alumni
- **Alumni**: Graduates of STM Pembangunan Semarang who have verified their status through the registration system
- **Guest**: Unregistered users who can view limited public content
- **Member**: Verified alumni who have completed profile setup and can contribute content
- **Moderator**: Admin users responsible for verifying registrations, managing content, and enforcing community guidelines
- **Content**: Articles, posts, discussions, and shared documents published on the portal
- **Tech_Stack**: A collection of technologies, frameworks, and tools used in IT projects or daily work
- **IT_Experience_Category**: A classification system for different types of professional experiences shared on the platform (Post-Mortem/Incident Case, Tech Stack & Architecture, Career & Interview, Showcase/Side Project)
- **Anonymous_Sharing**: A feature that allows members to publish content without revealing their identity
- **Threaded_Reply**: A nested comment system that maintains conversation context
- **Accepted_Solution**: A Q&A feature where the original poster marks the most helpful response
- **Batch**: A graduation year cohort of STM Pembangunan Semarang alumni
- **Editor**: The WYSIWYG content editor with Markdown and code syntax highlighting support
- **Tagging_System**: The predefined taxonomy system for categorizing content by technology, experience level, and type
- **Reaction_System**: The meaningful reaction feature providing Insightful, Relatable, Helpful, and Solutif options
- **Directory**: The searchable alumni IT professional directory with filtering capabilities
- **Moderation_Log**: An immutable record of all moderation actions taken on the platform

## Requirements

### Requirement 1: Registration and Verification System

**User Story:** As an alumni of STM Pembangunan Semarang, I want to register and verify my status, so that I can access member-only features.

#### Acceptance Criteria

1. WHEN a visitor navigates to the registration page, THE SIBAKA_Portal SHALL display a form collecting email address, password, full name, graduation year, department, LinkedIn profile URL (optional), and GitHub profile URL (optional)
2. THE SIBAKA_Portal SHALL validate registration form inputs: name (1-100 chars, required), email (valid email format, required), password (minimum 8 chars, required), graduation year (integer between 1979 and current year, required), department (1-100 chars, required), LinkedIn URL (valid URL format up to 200 chars, optional), and GitHub URL (valid URL format up to 200 chars, optional)
3. IF a registration is submitted with an email address already associated with an existing account, THEN THE SIBAKA_Portal SHALL reject the submission and display an error message indicating the email is already registered
4. WHEN the registration form is submitted with valid data, THE SIBAKA_Portal SHALL present two verification pathways: manual admin approval OR entry of an invite code provided by an existing verified member
5. IF the visitor submits an invite code that is invalid, expired, or already used, THEN THE SIBAKA_Portal SHALL reject the code and display an error message indicating the code is not valid
6. WHILE verification is pending, THE SIBAKA_Portal SHALL allow the user to log in with access restricted to public content only, with no member features (content creation, directory access, reactions, or comments) enabled
7. WHEN verification status changes to approved or rejected, THE SIBAKA_Portal SHALL notify the user via their registered email address within 60 seconds of the status change
8. WHEN verification is approved, THE SIBAKA_Portal SHALL grant full member access and update the user's role to "Member"
9. IF verification is rejected, THEN THE SIBAKA_Portal SHALL display an error message indicating the rejection reason and providing contact information for submitting an appeal

### Requirement 2: IT Professional Profile Completion

**User Story:** As a verified alumni, I want to complete my IT professional profile, so that other members can find and connect with me based on my expertise.

#### Acceptance Criteria

1. WHEN a newly verified member first logs in, THE SIBAKA_Portal SHALL redirect them to the professional profile completion page with the option to dismiss and complete later
2. WHILE profile completion is less than 80% complete, THE SIBAKA_Portal SHALL display a prominent banner in the header with status indicator
3. THE SIBAKA_Portal SHALL include the following required fields in the Professional Profile: current job title (1-100 chars), company name (1-100 chars), years of experience (integer, 0-50), and primary tech stack (1-200 chars)
4. THE SIBAKA_Portal SHALL include the following optional fields in the Professional Profile: secondary tech stack (1-200 chars), mentorship status (willing/not willing), hiring status (open to hiring/seeking job/internship/none), and availability indicators (immediate/1-month/2-months/3-months+)
5. WHEN a member submits profile changes, THE SIBAKA_Portal SHALL save changes within 2 seconds and update search visibility within 30 seconds
6. IF save fails due to network error, THEN THE SIBAKA_Portal SHALL retry up to 3 times with exponential backoff and after the final retry failure display an error message indicating the save was unsuccessful while preserving the unsaved data in the form
7. THE SIBAKA_Portal SHALL define profile completion percentage as: (number of filled valid fields / total number of fields) × 100, where all required fields must be filled before completion can exceed 50%
8. THE SIBAKA_Portal SHALL display the profile completion banner with orange background with white text showing progress percentage

### Requirement 3: Content Creation with Rich Editor

**User Story:** As a member, I want to create content with a professional editor that supports technical writing, so that I can share high-quality knowledge with the community.

#### Acceptance Criteria

1. WHEN a member navigates to the content creation page, THE SIBAKA_Portal SHALL provide a WYSIWYG editor with Markdown support including syntax highlighting for at least 5 programming languages (JavaScript, Python, Java, Go, PHP)
2. THE Editor SHALL include syntax highlighting for code blocks with language detection from markdown fences, and IF no language is specified in the fence, THEN THE Editor SHALL render the code block as plain preformatted text without syntax highlighting
3. WHERE a member inserts media, THE SIBAKA_Portal SHALL support embedding YouTube videos, GitHub Gists, and Mermaid.js diagrams, and IF the provided URL is invalid or the resource is unreachable, THEN THE SIBAKA_Portal SHALL display an inline error message indicating the embed failed and preserve the original URL text in the editor
4. WHEN content is being written, THE SIBAKA_Portal SHALL auto-save the draft every 10 seconds with a status indicator in the bottom-right corner of the editor showing "Saved" for 2 seconds after each successful save
5. IF auto-save fails, THEN THE SIBAKA_Portal SHALL retry up to 3 times with 2-second delay and display error notification after 5 seconds
6. WHERE a member navigates away with changes made since the last successful auto-save, THE SIBAKA_Portal SHALL prompt them to save before leaving with a modal dialog offering options to save, discard, or cancel navigation
7. THE SIBAKA_Portal SHALL enforce a maximum content length of 50,000 characters per post and display a character counter when content exceeds 45,000 characters
8. THE SIBAKA_Portal SHALL limit media embeds to a maximum of 10 per content item, and IF a member attempts to add more, THEN THE SIBAKA_Portal SHALL display an error message indicating the embed limit has been reached

### Requirement 4: Content Categorization System

**User Story:** As a member, I want to categorize my content appropriately, so that other members can find relevant content easily.

#### Acceptance Criteria

1. WHERE a member creates content, THE SIBAKA_Portal SHALL require selecting exactly one IT_Experience_Category: Post-Mortem/Incident Case, Tech Stack & Architecture, Career & Interview, or Showcase/Side Project
2. IF content is categorized as Post-Mortem/Incident Case, THEN THE SIBAKA_Portal SHALL display a category description indicating suitability for documenting system failures, security incidents, and technical problem-solving experiences
3. IF content is categorized as Tech Stack & Architecture, THEN THE SIBAKA_Portal SHALL display a category description indicating suitability for sharing technology decisions, system designs, and architectural patterns
4. IF content is categorized as Career & Interview, THEN THE SIBAKA_Portal SHALL display a category description indicating suitability for sharing job search experiences, interview questions, and career development advice
5. IF content is categorized as Showcase/Side Project, THEN THE SIBAKA_Portal SHALL display a category description indicating suitability for displaying personal projects, open source contributions, and portfolio work
6. IF a member attempts to publish content without selecting a category, THEN THE SIBAKA_Portal SHALL prevent publishing and display an error message indicating category selection is required
7. WHILE a member is viewing content, THE SIBAKA_Portal SHALL display the selected IT_Experience_Category as a badge positioned directly adjacent to the content title, using a distinct background color per category: one color for Post-Mortem/Incident Case, a second for Tech Stack & Architecture, a third for Career & Interview, and a fourth for Showcase/Side Project
8. WHERE a member edits their own published content, THE SIBAKA_Portal SHALL allow changing the IT_Experience_Category to a different valid category
9. WHERE a member browses content listings, THE SIBAKA_Portal SHALL provide a category filter allowing selection of one or more IT_Experience_Categories to display only matching content
10. WHEN a category filter is applied, THE SIBAKA_Portal SHALL update the content listing to show only content matching the selected categories within 2 seconds

### Requirement 5: Anonymous Sharing Feature

**User Story:** As a member, I want to share sensitive information anonymously, so that I can discuss controversial or personal topics without fear of career impact.

#### Acceptance Criteria

1. WHERE a member creates content, THE SIBAKA_Portal SHALL provide an option to publish anonymously with a toggle switch visible before submission
2. WHEN content is published anonymously, THE SIBAKA_Portal SHALL remove all identifying information including author name, profile image URL, member ID, email address, and contact information from the public-facing content display
3. WHILE anonymous content is displayed, THE SIBAKA_Portal SHALL show "Anonymous Member" as the author with no link to actual profile, and SHALL NOT correlate or visually link multiple anonymous posts by the same member
4. IF anonymous content violates community guidelines, THEN THE Moderator SHALL be able to identify and remove the content using internal identifiers (internal user ID, IP hash) without exposing the author's identity to other members
5. WHERE anonymous sharing is enabled, THE SIBAKA_Portal SHALL still collect technical data (IP address, browser fingerprint, user agent, timestamp) for moderation purposes only, accessible exclusively to moderators
6. THE SIBAKA_Portal SHALL retain anonymous sharing technical data for 90 days and then automatically purge it
7. WHEN content is published anonymously, THE SIBAKA_Portal SHALL prevent the author from converting it to non-anonymous after publication
8. WHERE a member participates in a discussion thread, THE SIBAKA_Portal SHALL allow the member to reply anonymously using the same toggle switch, independent of whether the parent content is anonymous
9. IF a member publishes more than 5 anonymous posts within a 24-hour period, THEN THE SIBAKA_Portal SHALL reject the submission and display an error message indicating the anonymous posting limit has been reached

### Requirement 6: Taxonomy and Tagging System

**User Story:** As a member, I want to use a comprehensive tagging system, so that content can be discovered by relevant topics and expertise levels.

#### Acceptance Criteria

1. WHEN content is created or edited, THE SIBAKA_Portal SHALL display a tag selection interface with a search input that filters available tags after the member types at least 2 characters, showing a maximum of 10 matching suggestions using prefix matching
2. THE Tagging_System SHALL include Tech Stack tags (e.g., #kubernetes, #python, #react, #aws) with a minimum of 1 and maximum of 3 Tech Stack tags required per content
3. THE Tagging_System SHALL include Experience Level tags (#beginner, #intermediate, #advanced, #architecture) with exactly 1 tag required per content
4. THE Tagging_System SHALL include Category tags for experience types (#incident, #architecture, #career, #project) with exactly 1 tag required per content, mapping to the IT_Experience_Category selected in content categorization
5. WHEN tags are selected, THE SIBAKA_Portal SHALL display them as labeled badges adjacent to the content title on content pages and include them as filterable facets in search results
6. IF a member submits content without selecting the required tags (at least 1 Tech Stack tag, exactly 1 Experience Level tag, and exactly 1 Category tag), THEN THE SIBAKA_Portal SHALL prevent submission and display an error message indicating which tag categories are missing
7. IF a member attempts to enter a tag not present in the predefined list, THEN THE SIBAKA_Portal SHALL reject the input, clear the search field, and display an inline message indicating only predefined tags are accepted
8. WHEN a member removes a previously selected tag, THE SIBAKA_Portal SHALL immediately update the tag display and re-validate against the minimum tag requirements before allowing submission

### Requirement 7: Discussion and Q&A Features

**User Story:** As a member, I want to discuss content with threaded replies and Q&A features, so that I can engage in meaningful conversations and help solve problems.

#### Acceptance Criteria

1. WHERE content is published, THE SIBAKA_Portal SHALL provide a comment section with threaded reply support up to 5 levels of nesting, and SHALL display replies at maximum depth as flat responses within the 5th level
2. THE SIBAKA_Portal SHALL maintain conversation context in the comment system with visual indentation for nested replies using left border highlighting
3. WHERE a member creates content in Q&A format, THE SIBAKA_Portal SHALL provide an "Accepted Solution" feature accessible only to the original poster, limited to one accepted solution per thread, with the ability to change or unmark the selection
4. WHEN an accepted solution is marked, THE SIBAKA_Portal SHALL highlight the solution with green border and award the author 50 reputation points
5. IF an accepted solution is unmarked or changed, THEN THE SIBAKA_Portal SHALL revoke the 50 reputation points from the previous solution author and award them to the new solution author if applicable
6. WHERE a thread has no new comments or replies for 90 days, THE SIBAKA_Portal SHALL automatically lock it to prevent new comments
7. THE SIBAKA_Portal SHALL validate comment text input length between 1 and 5000 characters, excluding leading and trailing whitespace from the count
8. IF a comment submission contains fewer than 1 or more than 5000 characters after trimming, THEN THE SIBAKA_Portal SHALL reject the submission and display an error message indicating the allowed character range
9. IF a locked thread receives a new comment attempt, THEN THE SIBAKA_Portal SHALL reject the comment and display "Thread locked due to inactivity" message
10. WHERE a member has authored a comment, THE SIBAKA_Portal SHALL allow the author to edit the comment within 15 minutes of posting and to delete their own comment at any time, displaying an "edited" indicator on modified comments

### Requirement 8: Meaningful Reactions

**User Story:** As a member, I want to provide meaningful reactions to content, so that I can express appreciation without cluttering the conversation.

#### Acceptance Criteria

1. WHERE content is displayed, THE SIBAKA_Portal SHALL provide reaction buttons: Insightful, Relatable, Helpful, and Solutif, allowing a maximum of one reaction type per member per content item
2. WHEN a reaction is applied, THE SIBAKA_Portal SHALL increment the reaction counter by 1, show user's reaction indicator, and confirm server response within 2 seconds
3. WHERE a member has already reacted, THE SIBAKA_Portal SHALL allow changing their reaction or removing it entirely with UI update within 100 milliseconds (optimistic update)
4. WHERE content receives 50 or more total reactions, THE Reaction_System SHALL display a reaction breakdown showing the count and percentage of each reaction type, visible to all members viewing that content
5. WHERE content receives 10 or more Solutif reactions, THE SIBAKA_Portal SHALL highlight it as a "Solutif Recommendation" with special badge
6. IF reaction operation fails, THEN THE SIBAKA_Portal SHALL revert the UI to the member's previous reaction state and display an error message indicating the operation could not be completed
7. IF analytics data is unavailable, THEN THE SIBAKA_Portal SHALL display "Analytics unavailable" message and retry up to 3 times with 2-second delay between attempts
8. THE SIBAKA_Portal SHALL restrict reaction functionality to authenticated members only

### Requirement 9: Alumni IT Directory

**User Story:** As a member, I want to search and filter the alumni IT directory, so that I can find professionals with specific expertise for networking or collaboration.

#### Acceptance Criteria

1. WHERE a member accesses the directory page, THE SIBAKA_Portal SHALL display a list of all verified alumni with opt-in profiles with pagination (20 items per page) and a text search field that matches against name, job role, company, and tech stack fields
2. WHERE a member accesses the directory page, THE SIBAKA_Portal SHALL provide filters for Batch (graduation year), Job Role, Tech Stack, and Experience Level that update results within 2 seconds upon filter selection
3. WHERE filters or search terms are applied and matching alumni exist, THE SIBAKA_Portal SHALL display results sorted by relevance with matching count displayed above the list
4. WHEN a directory entry is clicked, THE SIBAKA_Portal SHALL display a modal within 1 second with profile summary (name, role, company, tech stack, batch) and available contact options
5. WHERE a profile modal is displayed, THE SIBAKA_Portal SHALL show contact options based on the alumni's opt-in status and provided data: send message through portal (limited to 1000 characters, maximum 10 messages per day per sender), view LinkedIn profile (if URL provided), and view GitHub profile (if URL provided)
6. IF a contact option URL (LinkedIn or GitHub) is not provided by the alumni, THEN THE SIBAKA_Portal SHALL hide that contact option from the modal rather than displaying a broken link
7. IF directory search or filter returns zero matching results, THEN THE SIBAKA_Portal SHALL display an empty state message indicating no alumni match the current criteria and suggest broadening the search or adjusting filters

### Requirement 10: Performance and User Experience

**User Story:** As a member, I want a fast and responsive experience, so that I can use the portal efficiently without delays.

#### Acceptance Criteria

1. WHEN a page loads, THE SIBAKA_Portal SHALL reach first contentful paint in under 2 seconds for 95% of requests measured on connections of 5 Mbps or greater
2. WHILE content is being edited, THE SIBAKA_Portal SHALL auto-save drafts every 10 seconds and display a status indicator in the bottom-right corner of the editor showing "Saving", "Saved" (displayed for 2 seconds), or "Save failed"
3. IF auto-save fails, THEN THE SIBAKA_Portal SHALL retry up to 3 times with 2-second delay and display "Save failed" status after all retries are exhausted
4. WHEN a member reopens the editor after browser close, navigation away, session timeout, or unexpected disconnection, THE SIBAKA_Portal SHALL offer to restore the last successfully saved draft
5. THE SIBAKA_Portal SHALL support dark mode toggled via user preference setting with system preference (prefers-color-scheme) as the default for new members
6. THE SIBAKA_Portal SHALL render all content without horizontal scrolling and with minimum touch target size of 44x44 pixels for mobile (320px-767px), tablet (768px-1023px), and desktop (1024px+) viewports

### Requirement 11: Security and Privacy

**User Story:** As a member, I want my content and profile to be secure, so that I can trust the platform with my professional information.

#### Acceptance Criteria

1. WHERE code or documents are uploaded, THE SIBAKA_Portal SHALL sanitize all content to prevent XSS attacks by stripping HTML tags, escaping special characters (<, >, &, ", '), validating file types (allow: .md, .txt, .pdf, .png, .jpg, .jpeg, .gif), and rejecting files exceeding 10 MB per file with a maximum of 5 files per upload
2. WHEN data is transmitted, THE SIBAKA_Portal SHALL use HTTPS with TLS 1.3 encryption for all requests
3. IF an anonymous member is identified as violating policies, THEN THE SIBAKA_Portal SHALL allow the Moderator to issue warnings, remove content, or suspend the account using internal ID mapping without exposing the member's identity in any public-facing interface
4. WHERE alumni profiles are displayed publicly, THE SIBAKA_Portal SHALL implement anti-crawling measures including rate limiting (100 requests/minute per IP address), and IF requests from a single IP exceed 100 per minute, THEN THE SIBAKA_Portal SHALL present a CAPTCHA challenge before serving further requests
5. THE SIBAKA_Portal SHALL log all authentication and moderation actions for audit purposes with retention period of 365 days
6. THE SIBAKA_Portal SHALL include in audit log entries: user ID, action type, timestamp, IP address, and affected resource
7. IF a user session is inactive for more than 30 minutes, THEN THE SIBAKA_Portal SHALL expire the session and require re-authentication
8. IF 5 consecutive failed login attempts occur from the same account within 15 minutes, THEN THE SIBAKA_Portal SHALL lock the account for 30 minutes and notify the account owner via registered email

### Requirement 12: Content Moderation

**User Story:** As a moderator, I want tools to maintain community standards, so that the portal remains a safe and valuable space for all members.

#### Acceptance Criteria

1. WHEN content is flagged or reported by a member, THE SIBAKA_Portal SHALL deliver a notification to the Moderator within 30 seconds, ordered by a priority queue based on report count (content with 3+ reports ranked highest)
2. WHEN content violates guidelines, THE Moderator SHALL be able to remove content (soft-delete, hidden from members but retained for audit), suspend the violating user (1 to 30 days), or issue a written warning visible to the user
3. THE SIBAKA_Portal SHALL provide a Moderator Dashboard with statistics on content volume (total posts, active users), engagement metrics (reactions, comments), and moderation actions (flags, suspensions, warnings) refreshed every 60 seconds
4. WHEN a user is suspended, THE SIBAKA_Portal SHALL block all write actions (posting, commenting, reacting, messaging) while preserving read-only access to public content, and notify the user via email within 60 seconds including suspension duration and reason
5. THE Moderation_Log SHALL be immutable (no edit or delete operations permitted) and accessible only to admin-level moderators with log entry retention of 365 days
6. THE SIBAKA_Portal SHALL support auto-flagging content matching predefined patterns (swear words, spam patterns, malicious links) and place auto-flagged content in a review queue with status "pending review" visible only to moderators until manual confirmation or dismissal within 24 hours
7. IF a member accumulates 3 warnings within a 90-day period, THEN THE SIBAKA_Portal SHALL automatically escalate to a 7-day suspension and notify both the member and the moderator team
8. WHEN a member selects "Report" on any content, THE SIBAKA_Portal SHALL require selecting a report reason (spam, harassment, misinformation, off-topic, other) and allow an optional description (up to 500 characters) before submitting the report
