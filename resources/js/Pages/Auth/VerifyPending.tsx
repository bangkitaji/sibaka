import { type FormEventHandler } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import type { SharedPageProps, VerificationStatus } from '@/types';

interface VerifyPendingPageProps extends SharedPageProps {
  verificationStatus: VerificationStatus;
  rejectionReason?: string;
}

export default function VerifyPending() {
  const { verificationStatus, rejectionReason, flash } =
    usePage<VerifyPendingPageProps>().props;

  const { data, setData, post, processing, errors } = useForm({
    invite_code: '',
  });

  const submitInviteCode: FormEventHandler = (e) => {
    e.preventDefault();
    post('/verify-invite');
  };

  const handleLogout = () => {
    router.post('/logout');
  };

  return (
    <AuthLayout title="Verification Pending">
      <div className="space-y-6">
        {/* Header */}
        <div className="text-center">
          <h2 className="text-xl font-semibold text-foreground">
            {verificationStatus === 'rejected'
              ? 'Verification Rejected'
              : 'Verification Pending'}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {verificationStatus === 'rejected'
              ? 'Your account verification was not approved'
              : 'Your account is awaiting verification'}
          </p>
        </div>

        {/* Flash messages */}
        {flash?.success && (
          <div
            role="status"
            className="rounded-md border border-green-500/50 bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-400"
          >
            {flash.success}
          </div>
        )}

        {flash?.error && (
          <div
            role="alert"
            className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
          >
            {flash.error}
          </div>
        )}

        {/* Status content */}
        {verificationStatus === 'pending' && (
          <div className="space-y-4">
            {/* Pending status message */}
            <div className="rounded-md border border-amber-500/50 bg-amber-500/10 p-4">
              <div className="flex items-start gap-3">
                <svg
                  className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  aria-hidden="true"
                >
                  <path
                    fillRule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                    clipRule="evenodd"
                  />
                </svg>
                <div>
                  <p className="text-sm font-medium text-amber-800 dark:text-amber-300">
                    Waiting for Admin Approval
                  </p>
                  <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                    Your registration is being reviewed by our moderators. This
                    usually takes 1-2 business days. You can still browse public
                    content while waiting.
                  </p>
                </div>
              </div>
            </div>

            {/* Invite code form */}
            <div className="rounded-md border border-border p-4">
              <h3 className="text-sm font-medium text-foreground">
                Have an invite code?
              </h3>
              <p className="mt-1 text-xs text-muted-foreground">
                Enter an invite code from a verified member to get instant access.
              </p>

              <form onSubmit={submitInviteCode} className="mt-3 space-y-3">
                <div className="space-y-2">
                  <Label htmlFor="invite_code" className="sr-only">
                    Invite Code
                  </Label>
                  <Input
                    id="invite_code"
                    type="text"
                    value={data.invite_code}
                    onChange={(e) => setData('invite_code', e.target.value)}
                    placeholder="Enter invite code"
                    aria-invalid={!!errors.invite_code}
                    aria-describedby={
                      errors.invite_code ? 'invite-code-error' : undefined
                    }
                  />
                  {errors.invite_code && (
                    <p
                      id="invite-code-error"
                      className="text-sm text-destructive"
                      role="alert"
                    >
                      {errors.invite_code}
                    </p>
                  )}
                </div>

                <Button
                  type="submit"
                  variant="secondary"
                  className="w-full"
                  disabled={processing || !data.invite_code.trim()}
                  aria-busy={processing}
                >
                  {processing ? 'Verifying...' : 'Verify with Code'}
                </Button>
              </form>
            </div>
          </div>
        )}

        {verificationStatus === 'rejected' && (
          <div className="space-y-4">
            {/* Rejection message */}
            <div className="rounded-md border border-destructive/50 bg-destructive/10 p-4">
              <div className="flex items-start gap-3">
                <svg
                  className="mt-0.5 h-5 w-5 flex-shrink-0 text-destructive"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  aria-hidden="true"
                >
                  <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                    clipRule="evenodd"
                  />
                </svg>
                <div>
                  <p className="text-sm font-medium text-destructive">
                    Verification Not Approved
                  </p>
                  {rejectionReason && (
                    <p className="mt-1 text-sm text-destructive/80">
                      Reason: {rejectionReason}
                    </p>
                  )}
                  <p className="mt-2 text-sm text-muted-foreground">
                    If you believe this was an error, please contact the admin
                    team at{' '}
                    <a
                      href="mailto:admin@sibaka.id"
                      className="font-medium text-primary hover:underline"
                    >
                      admin@sibaka.id
                    </a>{' '}
                    to submit an appeal.
                  </p>
                </div>
              </div>
            </div>

            {/* Invite code form for rejected users too */}
            <div className="rounded-md border border-border p-4">
              <h3 className="text-sm font-medium text-foreground">
                Have an invite code?
              </h3>
              <p className="mt-1 text-xs text-muted-foreground">
                You can still verify your account with an invite code from a
                verified member.
              </p>

              <form onSubmit={submitInviteCode} className="mt-3 space-y-3">
                <div className="space-y-2">
                  <Label htmlFor="invite_code_rejected" className="sr-only">
                    Invite Code
                  </Label>
                  <Input
                    id="invite_code_rejected"
                    type="text"
                    value={data.invite_code}
                    onChange={(e) => setData('invite_code', e.target.value)}
                    placeholder="Enter invite code"
                    aria-invalid={!!errors.invite_code}
                    aria-describedby={
                      errors.invite_code ? 'invite-code-rejected-error' : undefined
                    }
                  />
                  {errors.invite_code && (
                    <p
                      id="invite-code-rejected-error"
                      className="text-sm text-destructive"
                      role="alert"
                    >
                      {errors.invite_code}
                    </p>
                  )}
                </div>

                <Button
                  type="submit"
                  variant="secondary"
                  className="w-full"
                  disabled={processing || !data.invite_code.trim()}
                  aria-busy={processing}
                >
                  {processing ? 'Verifying...' : 'Verify with Code'}
                </Button>
              </form>
            </div>
          </div>
        )}

        {/* Logout */}
        <div className="border-t border-border pt-4">
          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handleLogout}
          >
            Logout
          </Button>
        </div>
      </div>
    </AuthLayout>
  );
}
