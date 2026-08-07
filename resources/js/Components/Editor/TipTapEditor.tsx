import React, { useCallback, useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import Placeholder from '@tiptap/extension-placeholder';
import { common, createLowlight } from 'lowlight';
import javascript from 'highlight.js/lib/languages/javascript';
import python from 'highlight.js/lib/languages/python';
import java from 'highlight.js/lib/languages/java';
import go from 'highlight.js/lib/languages/go';
import php from 'highlight.js/lib/languages/php';
import { cn } from '@/lib/utils';
import { EmbedNodeExtension } from './EmbedNode';

// Configure lowlight with required languages
const lowlight = createLowlight(common);
lowlight.register('javascript', javascript);
lowlight.register('js', javascript);
lowlight.register('python', python);
lowlight.register('py', python);
lowlight.register('java', java);
lowlight.register('go', go);
lowlight.register('golang', go);
lowlight.register('php', php);

const MAX_CONTENT_LENGTH = 50_000;
const CHAR_COUNTER_THRESHOLD = 45_000;
const MAX_EMBEDS = 10;

export interface TipTapEditorProps {
  content?: string;
  onChange?: (content: string) => void;
  placeholder?: string;
  className?: string;
  editable?: boolean;
}

interface ToolbarButtonProps {
  onClick: () => void;
  isActive?: boolean;
  disabled?: boolean;
  'aria-label': string;
  children: React.ReactNode;
}

function ToolbarButton({
  onClick,
  isActive = false,
  disabled = false,
  'aria-label': ariaLabel,
  children,
}: ToolbarButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={ariaLabel}
      aria-pressed={isActive}
      className={cn(
        'min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-md px-2 text-sm font-medium transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        'disabled:pointer-events-none disabled:opacity-50',
        isActive
          ? 'bg-accent text-accent-foreground'
          : 'hover:bg-accent hover:text-accent-foreground'
      )}
    >
      {children}
    </button>
  );
}

function countEmbeds(content: string): number {
  const embedPattern = /<embed-node/g;
  const matches = content.match(embedPattern);
  return matches ? matches.length : 0;
}

export function TipTapEditor({
  content = '',
  onChange,
  placeholder = 'Start writing your content...',
  className,
  editable = true,
}: TipTapEditorProps) {
  const [embedCount, setEmbedCount] = useState(0);
  const [charCount, setCharCount] = useState(0);

  const handleUpdate = useCallback(
    ({ editor }: { editor: ReturnType<typeof useEditor> extends infer E ? NonNullable<E> : never }) => {
      const text = editor.getText();
      const html = editor.getHTML();
      setCharCount(text.length);
      setEmbedCount(countEmbeds(html));
      onChange?.(html);
    },
    [onChange]
  );

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        codeBlock: false, // We use CodeBlockLowlight instead
      }),
      CodeBlockLowlight.configure({
        lowlight,
        defaultLanguage: null, // No language = plain preformatted text
      }),
      Placeholder.configure({
        placeholder,
      }),
      EmbedNodeExtension,
    ],
    content,
    editable,
    onUpdate: handleUpdate,
    onCreate: ({ editor }) => {
      const text = editor.getText();
      const html = editor.getHTML();
      setCharCount(text.length);
      setEmbedCount(countEmbeds(html));
    },
  });

  const addEmbed = useCallback(() => {
    if (!editor) return;
    if (embedCount >= MAX_EMBEDS) return;

    const url = window.prompt('Enter embed URL (YouTube, GitHub Gist, or Mermaid):');
    if (!url) return;

    editor
      .chain()
      .focus()
      .insertContent({
        type: 'embedNode',
        attrs: { url },
      })
      .run();
  }, [editor, embedCount]);

  if (!editor) {
    return null;
  }

  const showCharCounter = charCount > CHAR_COUNTER_THRESHOLD;
  const isOverLimit = charCount > MAX_CONTENT_LENGTH;

  return (
    <div
      className={cn(
        'border border-input rounded-lg overflow-hidden bg-background',
        'dark:border-gray-700',
        className
      )}
    >
      {/* Toolbar */}
      <div
        className="flex flex-wrap items-center gap-1 border-b border-input p-2 bg-muted/50 dark:bg-gray-800/50 dark:border-gray-700"
        role="toolbar"
        aria-label="Editor toolbar"
      >
        <ToolbarButton
          onClick={() => editor.chain().focus().toggleBold().run()}
          isActive={editor.isActive('bold')}
          aria-label="Bold"
        >
          <strong>B</strong>
        </ToolbarButton>

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleItalic().run()}
          isActive={editor.isActive('italic')}
          aria-label="Italic"
        >
          <em>I</em>
        </ToolbarButton>

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleCode().run()}
          isActive={editor.isActive('code')}
          aria-label="Inline code"
        >
          <code className="text-xs">{'{}'}</code>
        </ToolbarButton>

        <div className="w-px h-6 bg-border mx-1 dark:bg-gray-600" aria-hidden="true" />

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
          isActive={editor.isActive('heading', { level: 2 })}
          aria-label="Heading 2"
        >
          H2
        </ToolbarButton>

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
          isActive={editor.isActive('heading', { level: 3 })}
          aria-label="Heading 3"
        >
          H3
        </ToolbarButton>

        <div className="w-px h-6 bg-border mx-1 dark:bg-gray-600" aria-hidden="true" />

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          isActive={editor.isActive('bulletList')}
          aria-label="Bullet list"
        >
          <span className="text-xs">&#8226; List</span>
        </ToolbarButton>

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          isActive={editor.isActive('orderedList')}
          aria-label="Ordered list"
        >
          <span className="text-xs">1. List</span>
        </ToolbarButton>

        <div className="w-px h-6 bg-border mx-1 dark:bg-gray-600" aria-hidden="true" />

        <ToolbarButton
          onClick={() => editor.chain().focus().toggleCodeBlock().run()}
          isActive={editor.isActive('codeBlock')}
          aria-label="Code block"
        >
          <span className="text-xs font-mono">&lt;/&gt;</span>
        </ToolbarButton>

        <ToolbarButton
          onClick={addEmbed}
          disabled={embedCount >= MAX_EMBEDS}
          aria-label="Insert embed"
        >
          <span className="text-xs">Embed</span>
        </ToolbarButton>

        <ToolbarButton
          onClick={() => editor.chain().focus().setBlockquote().run()}
          isActive={editor.isActive('blockquote')}
          aria-label="Blockquote"
        >
          <span className="text-xs">&ldquo;</span>
        </ToolbarButton>
      </div>

      {/* Editor Content */}
      <EditorContent
        editor={editor}
        className={cn(
          'prose prose-sm dark:prose-invert max-w-none p-4 min-h-[200px] focus-within:outline-none',
          '[&_.tiptap]:outline-none [&_.tiptap]:min-h-[180px]',
          '[&_.tiptap_p.is-editor-empty:first-child::before]:text-muted-foreground',
          '[&_.tiptap_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]',
          '[&_.tiptap_p.is-editor-empty:first-child::before]:float-left',
          '[&_.tiptap_p.is-editor-empty:first-child::before]:h-0',
          '[&_.tiptap_p.is-editor-empty:first-child::before]:pointer-events-none',
          '[&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:rounded-md [&_pre]:p-4 [&_pre]:overflow-x-auto',
          '[&_pre_code]:bg-transparent [&_pre_code]:text-inherit [&_pre_code]:p-0',
          '[&_.hljs-keyword]:text-purple-400',
          '[&_.hljs-string]:text-green-400',
          '[&_.hljs-number]:text-orange-400',
          '[&_.hljs-comment]:text-gray-500',
          '[&_.hljs-function]:text-blue-400',
          '[&_.hljs-built_in]:text-cyan-400',
          '[&_.hljs-title]:text-yellow-400'
        )}
      />

      {/* Status Bar */}
      <div className="flex items-center justify-between border-t border-input px-4 py-2 text-xs text-muted-foreground dark:border-gray-700">
        {/* Embed counter */}
        <span
          className={cn(
            'transition-colors',
            embedCount >= MAX_EMBEDS && 'text-destructive font-medium'
          )}
          aria-label={`${embedCount} of ${MAX_EMBEDS} embeds used`}
        >
          Embeds: {embedCount}/{MAX_EMBEDS}
        </span>

        {/* Character counter - only visible when > 45,000 chars */}
        {showCharCounter && (
          <span
            className={cn(
              'transition-colors',
              isOverLimit && 'text-destructive font-medium'
            )}
            aria-label={`${charCount} of ${MAX_CONTENT_LENGTH} characters used`}
            aria-live="polite"
          >
            {charCount.toLocaleString()}/{MAX_CONTENT_LENGTH.toLocaleString()}
          </span>
        )}
      </div>
    </div>
  );
}

export default TipTapEditor;
