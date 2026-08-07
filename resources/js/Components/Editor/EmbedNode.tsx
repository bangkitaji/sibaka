import { useEffect, useState } from 'react';
import { Node, mergeAttributes } from '@tiptap/react';
import { NodeViewWrapper, NodeViewProps, ReactNodeViewRenderer } from '@tiptap/react';

type EmbedType = 'youtube' | 'github-gist' | 'mermaid' | 'unknown';

export function detectEmbedType(url: string): EmbedType {
  try {
    const parsed = new URL(url);
    const hostname = parsed.hostname.toLowerCase();

    if (
      hostname === 'youtube.com' ||
      hostname === 'www.youtube.com' ||
      hostname === 'youtu.be'
    ) {
      return 'youtube';
    }

    if (hostname === 'gist.github.com') {
      return 'github-gist';
    }

    // Mermaid embeds use a special convention: mermaid.live or contain "mermaid" in path
    if (
      hostname === 'mermaid.live' ||
      hostname === 'mermaid.ink' ||
      parsed.pathname.includes('mermaid')
    ) {
      return 'mermaid';
    }

    return 'unknown';
  } catch {
    return 'unknown';
  }
}

export function extractYouTubeId(url: string): string | null {
  try {
    const parsed = new URL(url);

    if (parsed.hostname === 'youtu.be') {
      return parsed.pathname.slice(1);
    }

    if (
      parsed.hostname === 'youtube.com' ||
      parsed.hostname === 'www.youtube.com'
    ) {
      const videoId = parsed.searchParams.get('v');
      if (videoId) return videoId;

      // Handle /embed/ID format
      const embedMatch = parsed.pathname.match(/\/embed\/([^/?]+)/);
      if (embedMatch) return embedMatch[1];
    }

    return null;
  } catch {
    return null;
  }
}

export function isValidUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

interface YouTubeEmbedProps {
  url: string;
}

function YouTubeEmbed({ url }: YouTubeEmbedProps) {
  const videoId = extractYouTubeId(url);

  if (!videoId) {
    return <EmbedError url={url} message="Invalid YouTube URL" />;
  }

  return (
    <div className="relative w-full pt-[56.25%] rounded-md overflow-hidden bg-gray-900">
      <iframe
        className="absolute inset-0 w-full h-full"
        src={`https://www.youtube-nocookie.com/embed/${videoId}`}
        title="YouTube video embed"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
        loading="lazy"
      />
    </div>
  );
}

interface GistEmbedProps {
  url: string;
}

function GistEmbed({ url }: GistEmbedProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    // Validate the gist URL format
    const gistPattern = /^https:\/\/gist\.github\.com\/[\w-]+\/[\w]+/;
    if (!gistPattern.test(url)) {
      setError(true);
      setLoading(false);
    } else {
      setLoading(false);
    }
  }, [url]);

  if (error) {
    return <EmbedError url={url} message="Invalid GitHub Gist URL" />;
  }

  if (loading) {
    return <EmbedLoading />;
  }

  return (
    <div className="border border-input rounded-md overflow-hidden bg-background dark:border-gray-700">
      <div className="p-3 bg-muted/50 dark:bg-gray-800/50 border-b border-input dark:border-gray-700">
        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          className="text-sm text-primary hover:underline break-all"
        >
          {url}
        </a>
      </div>
      <iframe
        className="w-full min-h-[200px] border-0"
        src={`${url}.pibb`}
        title="GitHub Gist embed"
        loading="lazy"
        sandbox="allow-scripts allow-same-origin"
      />
    </div>
  );
}

interface MermaidEmbedProps {
  url: string;
}

function MermaidEmbed({ url }: MermaidEmbedProps) {
  // For Mermaid.ink URLs, render as an image
  // For mermaid.live links, show as an iframe preview
  try {
    const parsed = new URL(url);
    if (parsed.hostname === 'mermaid.ink') {
      return (
        <div className="flex justify-center p-4 bg-white dark:bg-gray-900 rounded-md border border-input dark:border-gray-700">
          <img
            src={url}
            alt="Mermaid diagram"
            className="max-w-full h-auto"
            loading="lazy"
          />
        </div>
      );
    }

    return (
      <div className="border border-input rounded-md overflow-hidden dark:border-gray-700">
        <div className="p-3 bg-muted/50 dark:bg-gray-800/50 border-b border-input dark:border-gray-700">
          <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-sm text-primary hover:underline break-all"
          >
            Mermaid Diagram: {url}
          </a>
        </div>
        <iframe
          className="w-full min-h-[300px] border-0"
          src={url}
          title="Mermaid diagram embed"
          loading="lazy"
          sandbox="allow-scripts allow-same-origin"
        />
      </div>
    );
  } catch {
    return <EmbedError url={url} message="Invalid Mermaid URL" />;
  }
}

interface EmbedErrorProps {
  url: string;
  message: string;
}

function EmbedError({ url, message }: EmbedErrorProps) {
  return (
    <div className="border border-destructive/50 rounded-md p-3 bg-destructive/5 dark:bg-destructive/10">
      <p className="text-sm text-destructive font-medium">{message}</p>
      <p className="text-xs text-muted-foreground mt-1 break-all">{url}</p>
    </div>
  );
}

function EmbedLoading() {
  return (
    <div className="border border-input rounded-md p-4 bg-muted/30 animate-pulse dark:border-gray-700">
      <div className="h-4 w-1/3 bg-muted rounded" />
      <div className="h-32 w-full bg-muted rounded mt-2" />
    </div>
  );
}

// TipTap Node View Component
function EmbedNodeView({ node }: NodeViewProps) {
  const { url } = node.attrs as { url: string };

  if (!isValidUrl(url)) {
    return (
      <NodeViewWrapper className="my-4" data-type="embed-node">
        <EmbedError url={url} message="Invalid or unreachable URL" />
      </NodeViewWrapper>
    );
  }

  const embedType = detectEmbedType(url);

  return (
    <NodeViewWrapper className="my-4" data-type="embed-node">
      {embedType === 'youtube' && <YouTubeEmbed url={url} />}
      {embedType === 'github-gist' && <GistEmbed url={url} />}
      {embedType === 'mermaid' && <MermaidEmbed url={url} />}
      {embedType === 'unknown' && (
        <EmbedError url={url} message="Unsupported embed type. Supported: YouTube, GitHub Gist, Mermaid" />
      )}
    </NodeViewWrapper>
  );
}

// TipTap Extension
export const EmbedNodeExtension = Node.create({
  name: 'embedNode',

  group: 'block',

  atom: true,

  addAttributes() {
    return {
      url: {
        default: '',
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'embed-node',
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    return ['embed-node', mergeAttributes(HTMLAttributes)];
  },

  addNodeView() {
    return ReactNodeViewRenderer(EmbedNodeView);
  },
});

export { EmbedNodeView };
