import { type FormEventHandler, useState } from 'react';
import { router } from '@inertiajs/react';
import { type Profile, type User } from '@/types/index.d';
import { Button, buttonVariants } from '@/Components/UI/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import { Label } from '@/Components/UI/label';

interface AlumniProfileModalProps {
  alumni: (User & { profile: Profile }) | null;
  isOpen: boolean;
  onClose: () => void;
}

export default function AlumniProfileModal({
  alumni,
  isOpen,
  onClose,
}: AlumniProfileModalProps) {
  const [showMessageForm, setShowMessageForm] = useState(false);
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);
  const [messageSent, setMessageSent] = useState(false);
  const [messageError, setMessageError] = useState<string | null>(null);

  if (!isOpen || !alumni) return null;

  const profile = alumni.profile;

  const resetMessageState = () => {
    setShowMessageForm(false);
    setMessage('');
    setSending(false);
    setMessageSent(false);
    setMessageError(null);
  };

  const handleClose = () => {
    resetMessageState();
    onClose();
  };

  const handleSendMessage: FormEventHandler = (e) => {
    e.preventDefault();

    if (!message.trim() || message.length > 1000) return;

    setSending(true);
    setMessageError(null);

    router.post(
      '/messages',
      { recipient_id: alumni.id, body: message.trim() },
      {
        preserveScroll: true,
        onSuccess: () => {
          setMessageSent(true);
          setSending(false);
          setMessage('');
        },
        onError: (errors) => {
          setSending(false);
          setMessageError(
            errors.body || errors.recipient_id || 'Failed to send message. Please try again.'
          );
        },
      }
    );
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="profile-modal-title"
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 dark:bg-black/70"
        onClick={handleClose}
        aria-hidden="true"
      />

      {/* Modal Content */}
      <Card className="relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <CardHeader className="flex flex-row items-start justify-between">
          <div>
            <CardTitle id="profile-modal-title" className="text-xl">
              {alumni.name}
            </CardTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Batch {alumni.graduation_year} &middot; {alumni.department}
            </p>
          </div>
          <Button
            variant="ghost"
            size="icon"
            onClick={handleClose}
            aria-label="Close profile modal"
            className="shrink-0"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </Button>
        </CardHeader>

        <CardContent className="space-y-6">
          {/* Professional Info */}
          <div className="space-y-3">
            {profile.job_title && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Role
                </span>
                <p className="text-foreground">{profile.job_title}</p>
              </div>
            )}
            {profile.company && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Company
                </span>
                <p className="text-foreground">{profile.company}</p>
              </div>
            )}
            {profile.primary_tech_stack && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Tech Stack
                </span>
                <p className="text-foreground">{profile.primary_tech_stack}</p>
              </div>
            )}
            {profile.years_of_experience !== null && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Experience
                </span>
                <p className="text-foreground">
                  {profile.years_of_experience} year
                  {profile.years_of_experience !== 1 ? 's' : ''}
                </p>
              </div>
            )}
            {profile.mentorship_status && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Mentorship
                </span>
                <p className="text-foreground capitalize">
                  {profile.mentorship_status.replace('_', ' ')}
                </p>
              </div>
            )}
            {profile.hiring_status && profile.hiring_status !== 'none' && (
              <div>
                <span className="text-sm font-medium text-muted-foreground">
                  Hiring Status
                </span>
                <p className="text-foreground capitalize">
                  {profile.hiring_status.replace(/_/g, ' ')}
                </p>
              </div>
            )}
          </div>

          {/* Contact Options - Req 9.5, 9.6 */}
          <div className="border-t border-border pt-4 space-y-3">
            <h4 className="text-sm font-medium text-muted-foreground">
              Contact
            </h4>

            {/* Message Form (expanded) */}
            {showMessageForm && !messageSent && (
              <form onSubmit={handleSendMessage} className="space-y-3">
                <div className="space-y-1">
                  <Label htmlFor="portal-message" className="text-sm">
                    Message (max 1000 characters)
                  </Label>
                  <textarea
                    id="portal-message"
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    maxLength={1000}
                    rows={4}
                    className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                    placeholder={`Write a message to ${alumni.name}...`}
                    required
                    aria-describedby="message-char-count"
                  />
                  <div className="flex items-center justify-between">
                    <span
                      id="message-char-count"
                      className={`text-xs ${
                        message.length > 900
                          ? 'text-destructive'
                          : 'text-muted-foreground'
                      }`}
                    >
                      {message.length}/1000
                    </span>
                    {messageError && (
                      <span className="text-xs text-destructive" role="alert">
                        {messageError}
                      </span>
                    )}
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button
                    type="submit"
                    size="sm"
                    disabled={sending || !message.trim()}
                    aria-busy={sending}
                  >
                    {sending ? 'Sending...' : 'Send'}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setShowMessageForm(false);
                      setMessage('');
                      setMessageError(null);
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            )}

            {/* Message sent confirmation */}
            {messageSent && (
              <div
                className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
                role="alert"
              >
                Message sent successfully.
              </div>
            )}

            {/* Contact buttons */}
            {!showMessageForm && !messageSent && (
              <div className="flex flex-wrap gap-3">
                {/* Send Message - always available for logged-in members */}
                <Button
                  variant="default"
                  className="gap-2"
                  onClick={() => setShowMessageForm(true)}
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <path d="m22 2-7 20-4-9-9-4Z" />
                    <path d="M22 2 11 13" />
                  </svg>
                  Send Message
                </Button>

                {/* LinkedIn - only shown if URL provided (Req 9.6) */}
                {profile.linkedin_url && (
                  <a
                    href={profile.linkedin_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={buttonVariants({ variant: 'outline', className: 'gap-2' })}
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                      <rect width="4" height="12" x="2" y="9" />
                      <circle cx="4" cy="4" r="2" />
                    </svg>
                    LinkedIn
                  </a>
                )}

                {/* GitHub - only shown if URL provided (Req 9.6) */}
                {profile.github_url && (
                  <a
                    href={profile.github_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={buttonVariants({ variant: 'outline', className: 'gap-2' })}
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4" />
                      <path d="M9 18c-4.51 2-5-2-7-2" />
                    </svg>
                    GitHub
                  </a>
                )}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
