import { describe, it, expect } from 'vitest';
import { detectEmbedType, extractYouTubeId, isValidUrl } from '@/Components/Editor/EmbedNode';

describe('EmbedNode utilities', () => {
  describe('isValidUrl', () => {
    it('accepts valid https URLs', () => {
      expect(isValidUrl('https://youtube.com/watch?v=abc123')).toBe(true);
      expect(isValidUrl('https://gist.github.com/user/id')).toBe(true);
    });

    it('accepts valid http URLs', () => {
      expect(isValidUrl('http://example.com')).toBe(true);
    });

    it('rejects invalid URLs', () => {
      expect(isValidUrl('')).toBe(false);
      expect(isValidUrl('not-a-url')).toBe(false);
      expect(isValidUrl('ftp://files.example.com')).toBe(false);
      expect(isValidUrl('javascript:alert(1)')).toBe(false);
    });
  });

  describe('detectEmbedType', () => {
    it('detects YouTube URLs', () => {
      expect(detectEmbedType('https://www.youtube.com/watch?v=abc123')).toBe('youtube');
      expect(detectEmbedType('https://youtube.com/watch?v=abc123')).toBe('youtube');
      expect(detectEmbedType('https://youtu.be/abc123')).toBe('youtube');
    });

    it('detects GitHub Gist URLs', () => {
      expect(detectEmbedType('https://gist.github.com/user/abc123')).toBe('github-gist');
    });

    it('detects Mermaid URLs', () => {
      expect(detectEmbedType('https://mermaid.live/edit#abc')).toBe('mermaid');
      expect(detectEmbedType('https://mermaid.ink/img/abc')).toBe('mermaid');
    });

    it('returns unknown for unsupported URLs', () => {
      expect(detectEmbedType('https://example.com')).toBe('unknown');
      expect(detectEmbedType('https://twitter.com/post/123')).toBe('unknown');
    });

    it('returns unknown for invalid URLs', () => {
      expect(detectEmbedType('not-a-url')).toBe('unknown');
      expect(detectEmbedType('')).toBe('unknown');
    });
  });

  describe('extractYouTubeId', () => {
    it('extracts ID from standard YouTube URLs', () => {
      expect(extractYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
      expect(extractYouTubeId('https://youtube.com/watch?v=abc123')).toBe('abc123');
    });

    it('extracts ID from short YouTube URLs', () => {
      expect(extractYouTubeId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    });

    it('extracts ID from embed URLs', () => {
      expect(extractYouTubeId('https://www.youtube.com/embed/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    });

    it('returns null for non-YouTube URLs', () => {
      expect(extractYouTubeId('https://example.com')).toBe(null);
    });

    it('returns null for YouTube URLs without video ID', () => {
      expect(extractYouTubeId('https://www.youtube.com/channel/abc')).toBe(null);
    });

    it('returns null for invalid URLs', () => {
      expect(extractYouTubeId('not-a-url')).toBe(null);
    });
  });
});
