import { useState, type FormEvent } from "react";
import { Eye, EyeOff, LockKeyhole, UserRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Navigate, useNavigate } from "@tanstack/react-router";
import { useAuth } from "@/contexts/AuthContext";
import concordEmblem from "@/assets/brand/concord-emblem.png";

export function LoginPage() {
  const navigate = useNavigate();
  const { status, signIn } = useAuth();

  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setIsSubmitting(true);

    try {
      await signIn(username.trim(), password);
      await navigate({ to: "/" });
    } catch (error) {
      setError(
        error instanceof Error
          ? error.message
          : "Unable to sign in. Please try again.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  if (status === "loading") {
    return (
      <main className="flex min-h-screen items-center justify-center bg-background">
        <p className="text-sm text-muted-foreground">
          Checking your session...
        </p>
      </main>
    );
  }

    if (status === "authenticated") {
    return <Navigate to="/" />;
    }

  return (
    <main className="grid min-h-screen bg-background lg:grid-cols-2">
      <section className="relative hidden min-h-screen overflow-hidden bg-[#090D18] px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 opacity-40"
          style={{
            background:
              "radial-gradient(ellipse at 15% 20%, rgba(37,99,235,0.28), transparent 48%), radial-gradient(ellipse at 85% 85%, rgba(59,130,246,0.16), transparent 42%)",
          }}
        />

        <div className="relative flex items-center gap-3">
          <img
            src={concordEmblem}
            alt=""
            className="h-11 w-11 object-contain"
          />
          <span className="text-xl font-semibold tracking-tight">Concord</span>
        </div>

        <div className="relative max-w-xl space-y-6 py-12">
          <div className="h-1 w-14 rounded-full bg-blue-500" />

          <h1 className="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
            Different systems.
            <br />
            Different people.
            <br />
            <span className="text-blue-400">One connected experience.</span>
          </h1>

          <p className="max-w-md text-base leading-7 text-slate-300">
            Your school systems, connected through one secure portal. Sign in to
            access the tools and services available to you.
          </p>
        </div>

        <p className="relative text-sm text-slate-400">
          Concord Portal · One connected school community
        </p>
      </section>

      <section className="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10 lg:px-12">
        <div className="w-full max-w-md">
          <div className="mb-10 flex items-center gap-3 lg:hidden">
            <img
              src={concordEmblem}
              alt=""
              className="h-10 w-10 object-contain"
            />
            <span className="text-xl font-semibold tracking-tight">
              Concord
            </span>
          </div>

          <div className="mb-8 space-y-2">
            <p className="text-sm font-medium text-primary">
              Welcome to Concord
            </p>
            <h2 className="text-3xl font-semibold tracking-tight">
              Welcome back
            </h2>
            <p className="text-sm leading-6 text-muted-foreground">
              Sign in with your school account to continue.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <label htmlFor="username" className="text-sm font-medium">
                Username or email
              </label>

              <div className="flex h-10 items-center gap-3 rounded-lg border border-input bg-background px-3 focus-within:ring-2 focus-within:ring-ring/40">
                <UserRound
                  aria-hidden="true"
                  className="h-4 w-4 text-muted-foreground"
                />
                <input
                  id="username"
                  name="username"
                  type="text"
                  autoComplete="username"
                  placeholder="Enter your username or email"
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  disabled={isSubmitting}
                  required
                  maxLength={100}
                  className="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground disabled:opacity-60"
                />
              </div>
            </div>

            <div className="space-y-2">
              <label htmlFor="password" className="text-sm font-medium">
                Password
              </label>

              <div className="flex h-10 items-center gap-3 rounded-lg border border-input bg-background px-3 focus-within:ring-2 focus-within:ring-ring/40">
                <LockKeyhole
                  aria-hidden="true"
                  className="h-4 w-4 text-muted-foreground"
                />
                <input
                  id="password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  placeholder="Enter your password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  disabled={isSubmitting}
                  required
                  maxLength={200}
                  className="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground disabled:opacity-60"
                />
                <button
                  type="button"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  aria-pressed={showPassword}
                  onClick={() => setShowPassword((visible) => !visible)}
                  className="rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </button>
              </div>
            </div>

            {error && (
              <div
                role="alert"
                className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm text-destructive"
              >
                {error}
              </div>
            )}

            <Button
              type="submit"
              disabled={isSubmitting || !username.trim() || !password}
              className="h-10 w-full"
            >
              {isSubmitting ? "Signing in..." : "Sign in"}
            </Button>
          </form>

          <p className="mt-8 text-center text-xs leading-5 text-muted-foreground">
            Secure authentication provided by Concord IAM.
          </p>
        </div>
      </section>
    </main>
  );
}
