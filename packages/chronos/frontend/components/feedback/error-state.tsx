import { AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface ErrorStateProps {
  title?: string;
  message: string;
  retry?: () => void;
  className?: string;
}

export function ErrorState({
  title = "Something went wrong",
  message,
  retry,
  className,
}: ErrorStateProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-center",
        className
      )}
    >
      <AlertCircle className="h-10 w-10 text-destructive mb-3" />
      <h3 className="text-lg font-semibold text-destructive">{title}</h3>
      <p className="mt-1 text-sm text-muted-foreground max-w-md">{message}</p>
      {retry && (
        <Button variant="outline" size="sm" className="mt-4" onClick={retry}>
          Try Again
        </Button>
      )}
    </div>
  );
}
