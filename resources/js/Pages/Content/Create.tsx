import { type FormEventHandler, useCallback, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import { TipTapEditor } from '@/Components/Editor/TipTapEditor';
import { AutoSaveStatusIndicator } from '@/Components/Editor/AutoSaveStatusIndicator';
import { UnsavedChangesModal } from '@/Components/Editor/UnsavedChangesModal';
import { useAutoSave } from '@/Hooks/useAutoSave';
import { useUnsavedChanges } from '@/Hooks/useUnsavedChanges';
import type { ContentCategory, Tag } from '@/types/index.d';

interface ContentCreateProps {
  /** Draft content ID for auto-save (server should generate one on page load) */
  contentId?: string;
  availableTags: Tag[];
}

const CATEGORY_OPTIONS: { value: ContentCategory; label: string; description: string }[] = [
  {
    value: 'post_mortem',
    label: 'Post-Mortem / Incident Case',
    description: 'Document system failures, security incidents, and technical problem-solving experiences.',
  },
  {
    value: 'tech_stack',
    label: 'Tech Stack & Architecture',
    description: 'Share technology decisions, system designs, and architectural patterns.',
  },
  {
    value: 'career_interview',
    label: 'Career & Interview',
    description: 'Share job search experiences, interview questions, and career development advice.',
  },
  {
    value: 'showcase',
    label: 'Showcase / Side Project',
    description: 'Display personal projects, open source contributions, and portfolio work.',
  },
];

export default function ContentCreate({ contentId, availableTags }: ContentCreateProps) {
  const { data, setData, post, processing, errors } = useForm({
    title: '',
    body: '',
    category: '' as ContentCategory | '',
    is_anonymous: false,
    is_qna: false,
    'tags.tech_stack': [] as string[],
    'tags.experience_level': '',
    'tags.category': '',
  });

  const [editorContent, setEditorContent] = useState('');
  const [tagSearch, setTagSearch] = useState('');

  // Auto-save hook
  const autoSave = useAutoSave({
    contentId: contentId ?? 'new',
    content: editorContent,
    enabled: !!contentId,
  });

  // Unsaved changes guard
  const unsavedChanges = useUnsavedChanges({
    hasUnsavedChanges: autoSave.hasUnsavedChanges,
    onSave: autoSave.save,
    enabled: !!contentId,
  });

  const handleEditorChange = useCallback(
    (html: string) => {
      setEditorContent(html);
      setData('body', html);
    },
    [setData]
  );

  const handleTechStackToggle = (tagName: string) => {
    const current = data['tags.tech_stack'];
    if (current.includes(tagName)) {
      setData('tags.tech_stack', current.filter((t) => t !== tagName));
    } else if (current.length < 3) {
      setData('tags.tech_stack', [...current, tagName]);
    }
  };

  const techStackTags = availableTags?.filter((t) => t.tag_category === 'tech_stack') ?? [];
  const experienceLevelTags = availableTags?.filter((t) => t.tag_category === 'experience_level') ?? [];

  const filteredTechStackTags =
    tagSearch.length >= 2
      ? techStackTags.filter((t) =>
          t.name.toLowerCase().startsWith(tagSearch.toLowerCase())
        ).slice(0, 10)
      : techStackTags.slice(0, 10);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post('/content', {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Create Content">
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Create Content</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Share your knowledge with the SIBAKA community.
          </p>
        </div>

        <form onSubmit={submit} className="space-y-6" noValidate>
          {/* Title */}
          <div className="space-y-2">
            <Label htmlFor="title">Title</Label>
            <Input
              id="title"
              type="text"
              value={data.title}
              onChange={(e) => setData('title', e.target.value)}
              placeholder="Enter a descriptive title..."
              maxLength={200}
              required
              aria-invalid={!!errors.title}
              aria-describedby={errors.title ? 'title-error' : undefined}
            />
            {errors.title && (
              <p id="title-error" className="text-sm text-destructive" role="alert">
                {errors.title}
              </p>
            )}
          </div>

          {/* Category selection */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Category</CardTitle>
            </CardHeader>
            <CardContent>
              <fieldset>
                <legend className="sr-only">Select a content category</legend>
                <div className="grid grid-cols-1 gap-3 tablet:grid-cols-2">
                  {CATEGORY_OPTIONS.map((option) => (
                    <label
                      key={option.value}
                      className={`relative flex cursor-pointer rounded-lg border p-4 transition-colors focus-within:ring-2 focus-within:ring-ring min-h-touch ${
                        data.category === option.value
                          ? 'border-primary bg-primary/5'
                          : 'border-input hover:bg-accent/50'
                      }`}
                    >
                      <input
                        type="radio"
                        name="category"
                        value={option.value}
                        checked={data.category === option.value}
                        onChange={(e) => setData('category', e.target.value as ContentCategory)}
                        className="sr-only"
                      />
                      <div className="space-y-1">
                        <span className="text-sm font-medium text-foreground">
                          {option.label}
                        </span>
                        <p className="text-xs text-muted-foreground">
                          {option.description}
                        </p>
                      </div>
                    </label>
                  ))}
                </div>
                {errors.category && (
                  <p className="mt-2 text-sm text-destructive" role="alert">
                    {errors.category}
                  </p>
                )}
              </fieldset>
            </CardContent>
          </Card>

          {/* Editor */}
          <div className="space-y-2">
            <Label>Content Body</Label>
            <TipTapEditor
              content={editorContent}
              onChange={handleEditorChange}
              placeholder="Write your content here... Supports Markdown, code blocks, and embeds."
            />
            {errors.body && (
              <p className="text-sm text-destructive" role="alert">
                {errors.body}
              </p>
            )}
          </div>

          {/* Tags */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Tags</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Tech Stack Tags (1-3 required) */}
              <div className="space-y-2">
                <Label>
                  Tech Stack Tags ({data['tags.tech_stack'].length}/3)
                </Label>
                <Input
                  type="text"
                  value={tagSearch}
                  onChange={(e) => setTagSearch(e.target.value)}
                  placeholder="Search tech stack tags (type 2+ chars)..."
                  aria-label="Search tech stack tags"
                />
                {/* Selected tags */}
                {data['tags.tech_stack'].length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {data['tags.tech_stack'].map((tagName) => (
                      <span
                        key={tagName}
                        className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                      >
                        #{tagName}
                        <button
                          type="button"
                          onClick={() => handleTechStackToggle(tagName)}
                          className="ml-0.5 text-primary/60 hover:text-primary focus-visible:outline-none"
                          aria-label={`Remove tag ${tagName}`}
                        >
                          &times;
                        </button>
                      </span>
                    ))}
                  </div>
                )}
                {/* Available tags */}
                <div className="flex flex-wrap gap-1.5">
                  {filteredTechStackTags
                    .filter((t) => !data['tags.tech_stack'].includes(t.name))
                    .map((tag) => (
                      <button
                        key={tag.id}
                        type="button"
                        onClick={() => handleTechStackToggle(tag.name)}
                        disabled={data['tags.tech_stack'].length >= 3}
                        className="inline-flex items-center rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground hover:bg-secondary/80 disabled:opacity-50 disabled:cursor-not-allowed min-h-[32px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label={`Add tag ${tag.name}`}
                      >
                        #{tag.name}
                      </button>
                    ))}
                </div>
                {errors['tags.tech_stack'] && (
                  <p className="text-sm text-destructive" role="alert">
                    {errors['tags.tech_stack']}
                  </p>
                )}
              </div>

              {/* Experience Level Tag (exactly 1) */}
              <div className="space-y-2">
                <Label htmlFor="experience-level">Experience Level</Label>
                <select
                  id="experience-level"
                  value={data['tags.experience_level']}
                  onChange={(e) => setData('tags.experience_level', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-invalid={!!errors['tags.experience_level']}
                >
                  <option value="">Select experience level...</option>
                  {experienceLevelTags.map((tag) => (
                    <option key={tag.id} value={tag.name}>
                      {tag.name.charAt(0).toUpperCase() + tag.name.slice(1)}
                    </option>
                  ))}
                </select>
                {errors['tags.experience_level'] && (
                  <p className="text-sm text-destructive" role="alert">
                    {errors['tags.experience_level']}
                  </p>
                )}
              </div>

              {/* Category Tag (auto-mapped from category in validation) */}
              {errors['tags.category'] && (
                <p className="text-sm text-destructive" role="alert">
                  {errors['tags.category']}
                </p>
              )}
            </CardContent>
          </Card>

          {/* Options */}
          <Card>
            <CardContent className="p-4 space-y-4">
              {/* Anonymous toggle */}
              <div className="flex items-center gap-3">
                <input
                  id="is-anonymous"
                  type="checkbox"
                  checked={data.is_anonymous}
                  onChange={(e) => setData('is_anonymous', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                />
                <Label htmlFor="is-anonymous" className="cursor-pointer text-sm font-normal">
                  Publish anonymously
                </Label>
              </div>

              {/* Q&A toggle */}
              <div className="flex items-center gap-3">
                <input
                  id="is-qna"
                  type="checkbox"
                  checked={data.is_qna}
                  onChange={(e) => setData('is_qna', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                />
                <Label htmlFor="is-qna" className="cursor-pointer text-sm font-normal">
                  Enable Q&A (allow accepted solution)
                </Label>
              </div>
            </CardContent>
          </Card>

          {/* Submit */}
          <div className="flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit('/content')}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing} aria-busy={processing}>
              {processing ? 'Publishing...' : 'Publish'}
            </Button>
          </div>
        </form>

        {/* Auto-save status indicator */}
        {contentId && (
          <AutoSaveStatusIndicator
            status={autoSave.status}
            lastSaved={autoSave.lastSaved}
          />
        )}

        {/* Unsaved changes modal */}
        <UnsavedChangesModal
          isOpen={unsavedChanges.showModal}
          onSave={unsavedChanges.confirmSave}
          onDiscard={unsavedChanges.confirmDiscard}
          onCancel={unsavedChanges.cancelNavigation}
        />
      </div>
    </AppLayout>
  );
}
