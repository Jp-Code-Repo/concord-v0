import concordEmblem from "@/assets/brand/concord-emblem.png";

interface SessionLoaderProps {
  message?: string;
  detail?: string;
}

export function SessionLoader({
  message = "Preparing your workspace",
  detail = "Securely checking your session",
}: SessionLoaderProps) {
  return (
    <main className="session-loader">
      <style>{`
        @keyframes concord-emblem-float {
          0%, 100% {
            transform: translateY(0) rotate(-4deg) scale(1);
          }
          50% {
            transform: translateY(-12px) rotate(4deg) scale(1.04);
          }
        }

        @keyframes concord-emblem-glow {
          0%, 100% {
            opacity: 0.3;
            transform: scale(0.85);
          }
          50% {
            opacity: 0.7;
            transform: scale(1.15);
          }
        }

        @keyframes concord-loader-fade {
          0%, 100% { opacity: 0.65; }
          50% { opacity: 1; }
        }

        .concord-emblem-motion {
          animation: concord-emblem-float 2.8s ease-in-out infinite;
          transform-origin: center;
        }

        .concord-emblem-glow {
          animation: concord-emblem-glow 2.8s ease-in-out infinite;
        }

        .concord-loader-message {
          animation: concord-loader-fade 2.4s ease-in-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
          .concord-emblem-motion,
          .concord-emblem-glow,
          .concord-loader-message {
            animation: none;
          }
        }
      `}</style>

      <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-[#090D18] px-6 text-white">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0"
          style={{
            background:
              "radial-gradient(ellipse at center, rgba(37,99,235,0.12), transparent 55%)",
          }}
        />

        <div className="relative flex flex-col items-center gap-7">
          <div className="relative flex h-32 w-32 items-center justify-center">
            <div
              aria-hidden="true"
              className="concord-emblem-glow absolute inset-3 rounded-full bg-blue-500/20 blur-2xl"
            />

            <img
              src={concordEmblem}
              alt=""
              className="concord-emblem-motion relative h-24 w-24 object-contain"
            />
          </div>

          <div className="space-y-2 text-center">
            <h1 className="concord-loader-message text-xl font-semibold tracking-tight">
              {message}
            </h1>
            <p className="text-sm text-slate-400">{detail}</p>
          </div>

          <div aria-hidden="true" className="flex items-center gap-1.5">
            {[0, 1, 2].map((dot) => (
              <span
                key={dot}
                className="h-1.5 w-1.5 rounded-full bg-blue-400"
                style={{
                  animation: `concord-loader-fade 1.2s ease-in-out ${
                    dot * 0.2
                  }s infinite`,
                }}
              />
            ))}
          </div>
        </div>
      </div>
    </main>
  );
}
