import { Button } from "@/components/ui/button";
import { Toaster } from "@/components/ui/sonner";
import { toast } from "sonner";
import "./App.css";

function App() {
  return (
    <>
      <Toaster />

      <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background text-foreground">
        <h1 className="text-3xl font-bold">Concord</h1>
        <p className="text-muted-foreground">
          Different systems. Different people. One connected experience.
        </p>

        <div className="flex gap-2">
          <Button onClick={() => toast.success("Changes saved successfully!")}>
            Success Toast
          </Button>

          <Button
            variant="destructive"
            onClick={() => toast.error("Something went wrong.")}
          >
            Error Toast
          </Button>
        </div>
      </main>
    </>
  );
}

export default App;
